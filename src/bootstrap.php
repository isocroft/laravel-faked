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
require_once __DIR__ . '/Http/Routing/FakeRedirector.php';
require_once __DIR__ . '/Http/Routing/FakeRoute.php';
require_once __DIR__ . '/_functions.php';
 
/* @INFO: Now it's safe to load Composer (package classes, PHPUnit, etc.). */
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';

if (is_file($autoload)) {
    require $autoload;
}

unset($autoload);
?>
