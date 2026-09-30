<?php
 
declare(strict_types=1);
 
namespace Core\Foundation;

use Http\Lifecycle\FakeRequest;
use Http\Lifecycle\FakeResponseFactory;

use Core\Component\FakeConfig;
use Core\Component\Utility\FakeSessionStore;
use Core\Component\Utility\FakeEventDispatcher;
use Core\Component\Utility\FakeAuthManager;

use Http\Routing\FakeRedirector;

use DataSource\FakeModel;

/**
 * Builds a fresh fake "Laravel app" and makes it the global instance behind the helpers.
 * Call `FakeLaravel::boot()` in `$testCase->setUp()` for full isolation between tests.
 */
final class FakeLaravel
{
    /** Contract/class names that package code may resolve via app(...), mapped to fake services. */
    private const ALIASES = [
        'config' => ['Illuminate\\Config\\Repository', 'Illuminate\\Contracts\\Config\\Repository', FakeConfig::class],
        'request' => ['Illuminate\\Http\\Request', FakeRequest::class],
        'session' => ['session.store', 'Illuminate\\Session\\Store', 'Illuminate\\Contracts\\Session\\Session', FakeSessionStore::class],
        'auth' => ['Illuminate\\Auth\\AuthManager', 'Illuminate\\Contracts\\Auth\\Factory', FakeAuthManager::class],
        'events' => ['Illuminate\\Events\\Dispatcher', 'Illuminate\\Contracts\\Events\\Dispatcher', FakeEventDispatcher::class],
        'redirect' => ['Illuminate\\Routing\\Redirector', FakeRedirector::class],
        'response.factory' => ['Illuminate\\Routing\\ResponseFactory', 'Illuminate\\Contracts\\Routing\\ResponseFactory', FakeResponseFactory::class],
        'app' => ['Illuminate\\Foundation\\Application', 'Illuminate\\Contracts\\Container\\Container', 'Illuminate\\Contracts\\Foundation\\Application', FakeApplication::class],
    ];
 
    /**
     * @param array  $packageConfig   contents of the package config file (e.g. laravel-config.php)
     * @param string $configNamespace key it's mounted under, so config('palie.tenants.column_key') works
     */
    public static function boot(
        array $packageConfig = [],
        string $configNamespace = 'package',
        ?FakeRequest $request = null,
        array $extraConfig = [],
    ): FakeApplication {
        FakeModel::flushStore();
 
        $app = new FakeApplication('testing');
        FakeApplication::setInstance($app);
 
        $app->instance('app', $app);
        $app->instance('config', new FakeConfig(array_replace_recursive(
            ['app' => ['env' => 'testing', 'url' => 'http://localhost'], 'cache' => [], 'session' => [], 'cookie' => [], $configNamespace => $packageConfig],
            $extraConfig,
        )));
        $app->instance('events', new FakeEventDispatcher($app));
 
        $session = new FakeSessionStore();
        $session->start();
        $app->instance('session', $session);
 
        $request ??= FakeRequest::create('/');
        $request->setLaravelSession($session);
        $app->instance('request', $request);
 
        $auth = new FakeAuthManager($app);
        $app->instance('auth', $auth);
        // Same wiring as AuthServiceProvider: $request->user() resolves through the auth manager.
        $request->setUserResolver(fn (?string $guard = null) => ($auth->userResolver())($guard));
 
        $app->instance('redirect', new FakeRedirector($app));
        $app->instance('response.factory', new FakeResponseFactory($app));
 
        foreach (self::ALIASES as $abstract => $aliases) {
            foreach ($aliases as $alias) {
                $app->alias($abstract, $alias);
            }
        }
 
        return $app;
    }
 
    /** Swap the current request (e.g. per test case), keeping session + user resolver wiring. */
    public static function setRequest(FakeRequest $request): FakeRequest
    {
        $app = FakeApplication::getInstance();
        $auth = $app->make('auth');
 
        $request->setLaravelSession($app->make('session'));
        $request->setUserResolver(fn (?string $guard = null) => ($auth->userResolver())($guard));
 
        return $app->instance('request', $request);
    }
 
    public static function reset(): void
    {
        FakeModel::flushStore();
        FakeApplication::setInstance(null);
    }
}
 
/* @NOTE: Drop into a PHPUnit TestCase: call $this->bootFakeLaravel() in setUp(). */
trait InteractsWithFakeLaravel
{
    protected FakeApplication $app;
 
    protected function bootFakeLaravel(array $packageConfig = [], string $configNamespace = 'palie', ?FakeRequest $request = null): FakeApplication
    {
        return $this->app = FakeLaravel::boot($packageConfig, $configNamespace, $request);
    }

    protected function releaseFakeLaravel(): void
    {
        if (isset($this->app)) {
            unset($this->app);
        }

        FakeLaravel::reset();
    }
 
    protected function actingAs(object $user, ?string $guard = null): static
    {
        $this->app->make('auth')->actingAs($user, $guard);
 
        return $this;
    }
 
    protected function withRoute(FakeRoute $route): static
    {
        $this->app->make('request')->setRoute($route);
 
        return $this;
    }
}

?>
