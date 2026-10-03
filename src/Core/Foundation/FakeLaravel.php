<?php
 
declare(strict_types=1);
 
namespace LaravelFaked\Core\Foundation;

use LaravelFaked\Http\Lifecycle\FakeRequest;
use LaravelFaked\Http\Lifecycle\FakeResponseFactory;

use LaravelFaked\Core\Component\FakeConfig;
use LaravelFaked\Core\Component\Utility\FakeSessionStore;
use LaravelFaked\Core\Component\Utility\FakeEventDispatcher;
use LaravelFaked\Core\Component\Auth\FakeAuthManager;
use LaravelFaked\Core\Component\Cache\FakeCacheRepository;
use LaravelFaked\Core\Component\Cache\FakeCacheManager;

use LaravelFaked\Http\Routing\FakeRedirector;
use LaravelFaked\Http\Routing\FakeRouter;

use LaravelFaked\DataSource\FakeModel;

use LaravelFaked\Helpers\Support;

/**
 * Builds a fresh fake "Laravel app" and makes it the global instance behind the helpers.
 * Call `FakeLaravel::boot()` in `$testCase->setUp()` for full isolation between tests.
 */
final class FakeLaravel
{
    /** Contract/class names that package code may resolve via app(...), mapped to fake services. */
    private const ALIASES = [
        'config' => ['Illuminate\\Config\\Repository', 'Illuminate\\Contracts\\Config\\Repository', FakeConfig::class],
        'cache' => [],
        'request' => ['Illuminate\\Http\\Request', FakeRequest::class],
        'session' => ['session.store', 'Illuminate\\Session\\Store', 'Illuminate\\Contracts\\Session\\Session', FakeSessionStore::class],
        'auth' => ['Illuminate\\Auth\\AuthManager', 'Illuminate\\Contracts\\Auth\\Factory', FakeAuthManager::class],
        'events' => ['Illuminate\\Events\\Dispatcher', 'Illuminate\\Contracts\\Events\\Dispatcher', FakeEventDispatcher::class],
        'redirect' => ['Illuminate\\Routing\\Redirector', FakeRedirector::class],
        'response.factory' => ['Illuminate\\Routing\\ResponseFactory', 'Illuminate\\Contracts\\Routing\\ResponseFactory', FakeResponseFactory::class],
        'router' => ['Illuminate\\Routing\\Router', 'Illuminate\\Contracts\\Routing\\Registrar', 'Illuminate\\Contracts\\Routing\\BindingRegistrar', FakeRouter::class],
        'cache' => ['Illuminate\\Cache\\CacheManager', 'Illuminate\\Contracts\\Cache\\Factory', FakeCacheManager::class],
        'cache.store' => ['Illuminate\\Cache\\Repository', 'Illuminate\\Contracts\\Cache\\Repository', FakeCacheRepository::class],
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
        FakeCacheRepository::resetClock();
 
        $app = new FakeApplication('testing');
        FakeApplication::setInstance($app);
 
        $app->instance('app', $app);
        $app->instance('config', new FakeConfig(array_replace_recursive(
          [
            'app' => ['env' => 'testing', 'url' => 'http://localhost'],
            'cache' => [
                'default' => 'array',
                'stores' => ['array' => ['driver' => 'array'],
                'null' => ['driver' => 'null']
            ],
            'session' => [],
            'cookie' => [],
            $configNamespace => $packageConfig
          ],
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
        $app->instance('router', new FakeRouter($app));
        $app->instance('cache', new FakeCacheManager($app));
        $app->bind('cache.store', fn (FakeApplication $app) => $app->make('cache')->store());
 
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
        FakeCacheRepository::resetClock();
        FakeApplication::setInstance(null);
    }
}

?>
