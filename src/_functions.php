<?php
 
declare(strict_types=1);
 
/*
 * Global fake functions shadowing that of Laravel. Same signatures/semantics as
 * src/Illuminate/Foundation/helpers.php, but resolving from the fake container.
 *
 * Laravel wraps its helpers in function_exists() too, so whichever file loads
 * FIRST wins -> `require_once './vendor/isocroft/laravel-faked/src/bootstrap.php';`
 * BEFORE `require './vendor/autoload.php';`.
 */
 
use LaravelFaked\Core\Foundation\FakeApplication;

use LaravelFaked\Http\Lifecycle\FakeHttpException;
use LaravelFaked\Http\Lifecycle\FakeResponse;

use LaravelFaked\Helpers\Support;
 
if (!function_exists('app')) {
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $app = FakeApplication::getInstance();
 
        return $abstract === null ? $app : $app->make($abstract, $parameters);
    }
}
 
if (!function_exists('config')) {
    /** config() -> repository; config(['a' => 1]) -> set; config('a.b', $default) -> get. */
    function config(array|string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return app('config');
        }
 
        if (is_array($key)) {
            app('config')->set($key);
 
            return null;
        }
 
        return app('config')->get($key, $default);
    }
}
 
if (!function_exists('request')) {
    /** request() -> request; request(['a','b']) -> only(); request('a', $default) -> input. */
    function request(array|string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return app('request');
        }
 
        if (is_array($key)) {
            return app('request')->only($key);
        }
 
        $value = app('request')->__get($key);
 
        return $value === null ? Support::value($default) : $value;
    }
}
 
if (!function_exists('response')) {
    /** response() -> factory; response($content, $status, $headers) -> FakeResponse. */
    function response(mixed $content = null, int $status = 200, array $headers = []): mixed
    {
        $factory = app('response.factory');
 
        if (func_num_args() === 0) {
            return $factory;
        }
 
        return $factory->make($content ?? '', $status, $headers);
    }
}
 
if (!function_exists('session')) {
    /** session() -> store; session(['a' => 1]) -> put; session('a', $default) -> get. */
    function session(array|string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return app('session');
        }
 
        if (is_array($key)) {
            app('session')->put($key);
 
            return null;
        }
 
        return app('session')->get($key, $default);
    }
}
 
if (!function_exists('auth')) {
    /** auth() -> manager (forwards to default guard); auth('api') -> that guard. */
    function auth(?string $guard = null): mixed
    {
        return $guard === null ? app('auth') : app('auth')->guard($guard);
    }
}
 
if (!function_exists('redirect')) {
    /** redirect() -> redirector; redirect('/path') -> FakeRedirectResponse. */
    function redirect(?string $to = null, int $status = 302, array $headers = [], ?bool $secure = null): mixed
    {
        if ($to === null) {
            return app('redirect');
        }
 
        return app('redirect')->to($to, $status, $headers, $secure);
    }
}
 
if (!function_exists('back')) {
    function back(int $status = 302, array $headers = [], string|false $fallback = false): mixed
    {
        return app('redirect')->back($status, $headers, $fallback);
    }
}
 
if (!function_exists('event')) {
    /** event(new Foo) / event('name', [$payload]) -> listener responses. */
    function event(mixed ...$args): mixed
    {
        return app('events')->dispatch(...$args);
    }
}

if (!function_exists('action')) {
    /** URL for a controller action: action([UserController::class, 'show'], ['user' => 1]), action('Ctrl@m'), action(Invokable::class). */
    function action(string|array $name, mixed $parameters = [], bool $absolute = true): string
    {
        return app('router')->toAction($name, $parameters, $absolute);
    }
}
 
if (!function_exists('route')) {
    /** URL for a named route registered on the fake router. */
    function route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        return app('router')->toRoute($name, $parameters, $absolute);
    }
}
 
if (!function_exists('cache')) {
    /** cache() -> manager; cache('k', $default) -> get; cache(['k' => 'v'], $ttl) -> put. */
    function cache(array|string|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return app('cache');
        }
 
        if (is_array($key)) {
            return app('cache')->put($key, $default);
        }
 
        return app('cache')->get($key, $default);
    }
}
 
if (!function_exists('abort')) {
    function abort(int|FakeResponse $code, string $message = '', array $headers = []): never
    {
        if ($code instanceof FakeResponse) {
            throw new FakeHttpException($code->getStatusCode(), $message, $headers, $code);
        }
 
        throw new FakeHttpException($code, $message, $headers);
    }
}
 
if (!function_exists('abort_if')) {
    function abort_if(mixed $boolean, int|FakeResponse $code, string $message = '', array $headers = []): void
    {
        if ($boolean) {
            abort($code, $message, $headers);
        }
    }
}
 
if (!function_exists('abort_unless')) {
    function abort_unless(mixed $boolean, int|FakeResponse $code, string $message = '', array $headers = []): void
    {
        if (!$boolean) {
            abort($code, $message, $headers);
        }
    }
}
 
/*
 * Guard against load-order mistakes: if any helper above was already defined
 * (e.g. by laravel/framework), warn loudly instead of silently using the real one.
 */
foreach (['app', 'config', 'request', 'response', 'session', 'auth', 'redirect', 'back', 'action', 'route', 'cache', 'event', 'abort'] as $helper) {
    if ((new ReflectionFunction($helper))->getFileName() !== __FILE__) {
        trigger_error(
            "Helper {$helper}() was defined before the fakes loaded; `require 'vendor/isocroft/laravel-faked/src/bootstrap.php';` before `require 'vendor/autoload.php';`.",
            E_USER_WARNING,
        );
    }
}

unset($helper);
?>
