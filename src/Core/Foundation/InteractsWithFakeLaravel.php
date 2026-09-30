<?php

declare(strict_types=1);

namespace LaravelFaked\Core\Foundation;

use LaravelFaked\Helpers\Support;

/* @NOTE: Drop into a PHPUnit TestCase: call `$this->bootFakeLaravel()` in `$testCase->setUp()` and call `$this->releaseFakeLaravel()` in `$testCase->tearDown()`. */
trait InteractsWithFakeLaravel
{
    protected FakeApplication $app;
 
    protected function bootFakeLaravel(array $packageConfig = [], string $configNamespace = 'package', ?FakeRequest $request = null): FakeApplication
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

    /* ---- test assertions: session ------------------------------------------------- */
 
    public function assertInSession(string $key, mixed $value = null): static
    {
        if ($this->app->make('session')->missing($key)) {
            $this->fail("Session is missing expected key [{$key}].");
        }
 
        if (func_num_args() > 1 && $this->app->make('session')->get($key) != $value) {
            $this->fail("Session key [{$key}] does not match the expected value.");
        }
 
        $this->assertTrue(true);
    }
 
    public function assertNotInSession(string $key): static
    {
        if ($this->app->make('session')->exists($key)) {
            $this->fail("Session has unexpected key [{$key}].");
        }
 
        $this->assertTrue(true);
    }

    /* ---- test assertions: eevents ------------------------------------------------- */

    public function assertDispatched(string $event, callable|int|null $callback = null): void
    {
        if (is_int($callback)) {
            $this->assertDispatchedTimes($event, $callback);
 
            return;
        }
 
        if ($this->app->make('events')->dispatched($event, $callback) === []) {
            $this->fail("The expected [{$event}] event was not dispatched.");
        }
 
        $this->assertTrue(true);
    }
 
    public function assertDispatchedTimes(string $event, int $times = 1): void
    {
        $count = count($this->app->make('events')->dispatched($event));
 
        if ($count !== $times) {
            $this->fail("The expected [{$event}] event was dispatched {$count} times instead of {$times} times.");
        }
 
        $this->assertTrue(true);
    }
 
    public function assertNotDispatched(string $event, ?callable $callback = null): void
    {
        if ($this->app->make('events')->dispatched($event, $callback) !== []) {
            $this->fail("The unexpected [{$event}] event was dispatched.");
        }
 
        $this->assertTrue(true);
    }
 
    public function assertNothingDispatched(): void
    {
        $names = array_keys(array_filter($this->app->make('events')->dispatched));
 
        if ($names !== []) {
            $this->fail("Events were dispatched unexpectedly: " . implode(', ', $names));
        }
 
        $this->assertTrue(true);
    }
}

?>
