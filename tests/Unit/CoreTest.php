<?php
 
declare(strict_types=1);
 
namespace LaravelFaked\Tests\Unit;
 
use PHPUnit\Framework\TestCase;

use LaravelFaked\Http\Routing\FakeResponseFactory;
use LaravelFaked\Http\Lifecycle\FakeJsonResponse;
use LaravelFaked\Core\Foundation\FakeLaravel;

use LaravelFaked\Http\Lifecycle\FakeHttpException;
use LaravelFaked\Http\Lifecycle\FakeRedirectResponse;
use LaravelFaked\Http\Lifecycle\FakeRequest;
use LaravelFaked\Http\Routing\FakeRoute;

use LaravelFaked\Tests\FakeUser;

use LaravelFaked\Core\Foundation\InteractsWithFakeLaravel;

// Let the code that references the app's models resolve to the fakes.
if (!class_exists('App\\Models\\User', false)) {
    class_alias(FakeUser::class, 'App\\Models\\User');
}
 
final class CoreTest extends TestCase
{
    use InteractsWithFakeLaravel;
 
    protected function setUp(): void
    {
        $this->bootFakeLaravel(
            packageConfig: require __DIR__ . '/../fixtures/laravel-fake-config.php',
            request: require __DIR__ . '/../fixtures/laravel-fake-request.php',
        );
    }
 
    public function test_config_helper_reads_and_writes_package_config(): void
    {
        $this->assertSame('type_id', config('package.type.column_key'));
        $this->assertSame('slug_id', config('package.user.id_name'));
        $this->assertNull(config('package.nope'));
        $this->assertSame('fallback', config('package.nope', 'fallback'));
    }
 
    public function test_user_fake_behaves_like_app_models_user(): void
    {
        $attributes = require __DIR__ . '/../fixtures/laravel-fake-user_attributes.php';
        $user = \App\Models\User::create($attributes + ['password' => 'secret']);
 
        $this->assertInstanceOf(\App\Models\User::class, $user);
        $this->assertSame(1, $user->getKey());
        $this->assertTrue($user->exists);
        $this->assertTrue($user->incrementing);
        $this->assertSame('tbl_users', $user->getTableName());
        $this->assertSame('id', $user->getRouteKeyName());
        $this->assertSame('is_active', $user->getUserActiveColumnName());
        $this->assertSame('bool', $user->getUserActiveColumnType());
        $this->assertTrue($user->isActive());
        $this->assertTrue(password_verify('secret', $user->getAuthPassword()));
        $this->assertArrayNotHasKey('password', $user->toArray());
 
        $user->name = 'Onyi Deborah';
        $this->assertTrue($user->isDirty('name'));
        $user->save();
        $this->assertSame('Onyi Deborah', FakeUser::find(1)?->name);
    }
 
    public function test_auth_and_request_user_resolver(): void
    {
        $user = \App\Models\User::create(['name' => 'Amin Taminu', 'email' => 'amin.tam@xgraph.test', 'password' => 'secret']);
 
        $this->assertTrue(auth()->guest());
        $this->assertNull(request()->user());
 
        $this->assertFalse(auth()->attempt(['email' => 'amin.tam@xgraph.test', 'password' => 'wrong']));
        $this->assertTrue(auth()->attempt(['email' => 'amin.tam@xgraph.test', 'password' => 'secret']));
 
        $this->assertTrue(auth()->check());
        $this->assertSame(1, auth()->id());
        $this->assertTrue(request()->user()->is($user));
        $this->assertDispatched('Illuminate\\Auth\\Events\\Login');
 
        auth()->logout();
        $this->assertNull(request()->user());
    }
 
    public function test_acting_as_on_named_guard(): void
    {
        $user = \App\Models\User::make(['name' => 'Api']);
        $this->actingAs($user, 'api');
 
        $this->assertSame($user, auth('api')->user());
        $this->assertSame($user, request()->user());
    }
 
    public function test_request_input_and_route_parameters(): void
    {
        FakeLaravel::setRequest(FakeRequest::createJson('/listings/abc/users?page=2', 'POST', ['name' => 'Bob']));
        $this->withRoute(new FakeRoute('listings/{type}/users', ['type' => 'abc'], 'type.user.listings'));
 
        $this->assertSame('Bob', request('name'));
        $this->assertSame('2', request()->query('page'));
        $this->assertSame('abc', request()->route('type'));
        $this->assertSame('abc', request('type'));
        $this->assertTrue(request()->expectsJson());
        $this->assertTrue(request()->routeIs('type.*'));
        $this->assertTrue(request()->is('/listings/*'));
    }
 
    public function test_response_helpers(): void
    {
        $body = config('package.route_guard.json_error');
 
        $json = response()->json($body, 403);
        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $json);
        $this->assertForbidden($json);
        $this->assertJson($json, ['message' => 'Access Denied']);
 
        $text = response(config('package.route_guard.text_error'), 403);
        $this->assertForbidden($text);
        $this->assertSee($text, 'Access Denied');
    }
 
    public function test_session_and_redirects(): void
    {
        session(['type' => 'abc']);
        $this->assertSame('abc', session('type'));
 
        $redirect = redirect('/login')->with('status', 'Please sign in');
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $redirect);
        $this->assertRedirect($redirect, '/login');
        $this->assertInSession('status', 'Please sign in');
 
        session()->save(); // end of request 1: flash survives
        $this->assertSame('Please sign in', session('status'));
        session()->save(); // end of request 2: flash gone
        $this->assertNull(session('status'));
 
        $routeRedirect = redirect()->defineRoute('user.show', 'user/{id}');
        $user = \App\Models\User::create(['name' => 'Gideon Agboola']);
        $routeRedirect->route('user.show', $user);
        $this->assertRedirect($routeRedirect, '/user/' . $user->getRouteKey());
    }
 
    public function test_events_spy_and_fake(): void
    {
        $seen = [];
        app('events')->listen('type.switched', function (string $id) use (&$seen) {
            $seen[] = $id;
        });
 
        event('type.switched', ['abc']);
        $this->assertSame(['abc'], $seen);
 
        app('events')->fake();
        event('tenant.switched', ['def']);
        $this->assertSame(['abc'], $seen); // listener not run while faking
        $this->assertDispatchedTimes('type.switched', 2);
        $this->assertDispatched('type.switched', fn (string $id) => $id === 'def');
    }
 
    public function test_abort_throws_http_exception(): void
    {
        try {
            abort_unless(auth()->check(), 403, 'Access Denied');
            $this->fail('`abort_unless(...)` did not throw');
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame('Access Denied', $e->getMessage());
        }
    }
}
