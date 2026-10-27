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

use LaravelFaked\Core\Component\Cache\FakeCacheRepository;
use LaravelFaked\Core\Component\Cache\FakeLockTimeoutException;

use LaravelFaked\Tests\FakeUser;
use LaravelFaked\Tests\FakeOrganization;

use LaravelFaked\Core\Foundation\InteractsWithFakeLaravel;

// Let the code that references the app's models resolve to the fakes.
if (!class_exists('App\\Models\\User', false)) {
    class_alias(FakeUser::class, 'App\\Models\\User');
}

final class TenantController
{
    public function show(FakeOrganization $tenant): array
    {
        return ['slug' => $tenant->slug_id, 'name' => $tenant->name];
    }
 
    public function members(FakeRequest $request, string $tenant, int $page = 1): string
    {
        return "tenant={$tenant};page={$page};q=" . $request->query('q', '');
    }
}
 
final class DashboardController
{
    public function __invoke(): string
    {
        return 'dashboard';
    }
}
 
final class EnsureUserIsActive
{
    public function handle(FakeRequest $request, \Closure $next, string $deniedStatus = '403'): mixed
    {
        $user = $request->user();
 
        if ($user === null || !$user->isActive()) {
            return response()->json(config('package.route_guard.json_error'), (int) $deniedStatus);
        }
 
        return $next($request);
    }
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

    public function test_action_helper_builds_urls_from_controller_actions(): void
    {
        $router = $this->app->make('router');
        $router->get('tenants/{tenant}', [TenantController::class, 'show']);
        $router->get('dashboard', DashboardController::class);
 
        $org = FakeOrganization::create(['name' => 'Acme']);
 
        $this->assertSame(
            'http://localhost/tenants/' . $org->slug_id,
            action([TenantController::class, 'show'], $org),
        );
        $this->assertSame('/tenants/abc?tab=team', action(TenantController::class . '@show', ['tenant' => 'abc', 'tab' => 'team'], false));
        $this->assertSame('http://localhost/dashboard', action(DashboardController::class));
 
        $this->expectException(\InvalidArgumentException::class);
        action([TenantController::class, 'missing']);
    }
 
    public function test_redirect_to_action_and_named_route(): void
    {
        $this->app->make('router')->get('dashboard', DashboardController::class)->name('dashboard');
 
        $first_response = redirect()->action(DashboardController::class)
        $this->assertRedirect($first_response, '/dashboard');
        $next_response = redirect()->route('dashboard');
        $this->assertRedirect($next_response, '/dashboard');
        $this->assertSame('/dashboard', route('dashboard', [], false));
    }

    public function test_model_returned_from_route_becomes_json(): void
    {
        $this->app->make('router')->post('orgs', fn (FakeRequest $request) => FakeOrganization::create($request->only('name')));
 
        $response = $this->app->make('router')->json('POST', '/orgs', ['name' => 'Acme']);
        $this->assertInstanceOf(FakeJsonResponse::class, $response);
        $this->assertStatus($response, 201);
        $this->assertJson($response, ['name' => 'Acme']);
    }
 
    public function test_cache_helper_ttl_and_time_travel(): void
    {
        cache(['tenant:abc' => 'Acme'], 60);
        $this->assertSame('Acme', cache('tenant:abc'));
 
        cache()->travel(61);
        $this->assertNull(cache('tenant:abc'));
        $this->assertSame('fallback', cache('tenant:abc', 'fallback'));
    }
 
    public function test_remember_increment_pull_and_events(): void
    {
        $calls = 0;
        $resolver = function () use (&$calls) {
            $calls++;
 
            return 'value';
        };
 
        $this->assertSame('value', cache()->remember('k', 300, $resolver));
        $this->assertSame('value', cache()->remember('k', 300, $resolver));
        $this->assertSame(1, $calls);
 
        $this->assertSame(1, cache()->increment('hits'));
        $this->assertSame(3, cache()->increment('hits', 2));
        $this->assertSame(3, cache()->pull('hits'));
        
        cache()->store();
        $this->assertMissing('hits');
 
        app('events')->assertDispatched('Illuminate\\Cache\\Events\\CacheHit', fn (array $e) => $e['key'] === 'k');
        $this->assertInstanceOf(FakeCacheRepository::class, app('cache.store'));
    }
 
    public function test_stores_are_isolated_and_null_store_never_stores(): void
    {
        config(['cache.stores.redis' => ['driver' => 'redis']]);
 
        cache()->store('redis')->put('k', 'redis-value');
        $this->assertNull(cache()->store('array')->get('k'));
        $this->assertSame('redis-value', cache()->store('redis')->get('k'));
 
        $this->assertFalse(cache()->store('null')->put('k', 'v'));
        $this->assertNull(cache()->store('null')->get('k'));
 
        $this->expectException(\InvalidArgumentException::class);
        cache()->store('memcached');
    }
 
    public function test_atomic_locks(): void
    {
        $lock = cache()->lock('provision-tenant', 10);
        $this->assertTrue($lock->get());
        $this->assertFalse(cache()->lock('provision-tenant', 10)->get());
 
        $this->assertSame('ran', cache()->lock('other')->get(fn () => 'ran'));
 
        try {
            cache()->lock('provision-tenant')->block(5);
            $this->fail('Expected lock timeout');
        } catch (FakeLockTimeoutException) {
        }
 
        cache()->travel(11); // lock expired
        $this->assertTrue(cache()->lock('provision-tenant')->get());
    }
}
