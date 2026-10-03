<?php
 
declare(strict_types=1);
 
/**
 * Fake Laravel runtime for package tests — no laravel/framework app is booted.
 *
 * Load order matters: this file must run BEFORE vendor/autoload.php, because
 * Laravel's own helpers are also guarded by `function_exists()` and first one 
 * wins.
 *
 * phpunit.xml:  <phpunit bootstrap="./vendor/isocroft/laravel-faked/src/bootstrap.php" ...>
 */

require_once __DIR__ . '/Helpers/Support.php';
require_once __DIR__ . '/Core/Component/FakeConfig.php';
require_once __DIR__ . '/Core/Component/Utility/FakeSessionStore.php';
require_once __DIR__ . '/Core/Component/Utility/FakeEventDispacther.php';
require_once __DIR__ . '/Core/Component/Foundation/FakeApplication.php';
require_once __DIR__ . '/Core/Component/Foundation/FakeLaravel.php';
require_once __DIR__ . '/Core/Component/Foundation/InteractsWithFakeLaravel.php';
require_once __DIR__ . '/DataSource/FakeModel.php';
require_once __DIR__ . '/Http/Lifecycle/FakeHeaderBag.php';
require_once __DIR__ . '/Http/Lifecycle/FakeParameterBag.php';
require_once __DIR__ . '/Http/Lifecycle/FakeRequest.php';
require_once __DDIR__ . '/Http/Lifecycle/Concerns/FakeResponse.php';
require_once __DIR__ . '/Http/Lifecycle/FakeJsonRespose.php';
require_once __DIR__ . '/Http/Lifecycle/FakeRedirectResponse.php';
require_once __DIR__ . '/Http/Lifecycle/FakeHttpException.php';
require_once __DIR__ . '/Http/Routing/FakeResponseFactory.php';
require_once __DIR__ . '/Http/Routing/FakeRedirector.php';
require_once __DIR__ . '/Http/Routing/FakeRoute.php';
require_once __DIR__ . '/_functions.php';

if (!class_exists('Illuminate\\Http\\Exceptions\\HttpResponseException', false)) {
    class_alias(FakeHttpException::class, 'Illuminate\\Http\\Exceptions\\HttpResponseException');
}

if (!class_exists('Illuminate\\Routing\\ResponseFactory', false)) {
    class_alias(FakeResponseFactory::class, 'Illuminate\\Routing\\ResponseFactory');
}

if (!class_exists('Illuminate\\Routing\\Route', false)) {
    class_alias(FakeRoute::class, 'Illuminate\\Routing\\Route');
}

if (!class_exists('Illuminate\\Http\\RedirectResponse', false)) {
    class_alias(FakeRedirectResponse::class, 'Illuminate\\Http\\RedirectResponse');
}

if (!class_exists('Illuminate\\Http\JsonResponse', false)) {
    class_alias(FakeJsonResponse::class, 'Illuminate\\Http\JsonResponse');
}

if (!class_exists('Illuminate\\Http\\Response', false)) {
    class_alias(FakeResponse::class, 'Illuminate\\Http\\Response');
}

if (!class_exists('Illuminate\\Http\\Request', false)) {
    class_alias(FakeRequest::class, 'Illuminate\\Http\\Request');
}

if (!class_exists('Illuminate\\Auth\\AuthManager', false)) {
    class_alias(FakeAuthManager::class, 'Illuminate\\Auth\\AuthManager');
}

if (!class_exists('Illuminate\\Cache\\CacheManager', false)) {
    class_alias(FakeCacheManager::class, 'Illuminate\\Cache\\CacheManager');
}

if (!class_exists('Illuminate\\Events\\Dispatcher', false)) {
    class_alias(FakeEventDispatcher::class, 'Illuminate\\Events\\Dispatcher');
}

if (!class_exists('Illuminate\\Session\\Store', false)) {
    class_alias(FakeSessionStore::class, 'Illuminate\\Session\\Store');
}

if (!class_exists('Illuminate\\Database\\Eloquent\\Model', false)) {
    class_alias(FakeModel::class, 'Illuminate\\Database\\Eloquent\\Model');
}

if (!class_exists('Illuminate\\Routing\\Redirector', false)) {
    class_alias(FakeRedirector::class, 'Illuminate\\Routing\\Redirector');
}

if (!class_exists('Illuminate\\Foundation\\Application', false)) {
    class_alias(FakeApplication::class, 'Illuminate\\Foundation\\Application');
}

/* @INFO: At this point, it's safe to load Composer (package classes, PHPUnit, etc.). */

/* @HINT: Check if installed by composer as a dependency (so, up 4 levels) */
$autoloadDependent = dirname(__DIR__, 4) . '/vendor/autoload.php';
/* @HINT: Check if in local development mode (so, up 2 levels) */
$autoloadLocal = dirname(__DIR__, 2) . '/vendor/autoload.php';

if (is_file($autoloadDependent)) {
    require $autoloadDependent;
    unset($autoloadDependent);
} else if (is_file($autoloadLocal)) {
    require $autoloadLocal;
    unset($autoloadLocal);
}

?>
