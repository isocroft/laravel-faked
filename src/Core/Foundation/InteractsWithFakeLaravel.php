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

    /* ---- test assertions: response ------------------------------------------------- */
 
    public function assertStatus(int $status): static
    {
        if ($this->app->make('response.factory')->statusCode !== $status) {
            $this->fail("Expected response status code [{$status}] but received {$this->statusCode}.");
        }
 
        $this->assertTrue(true);
    }
 
    public function assertOk(): static
    {
        return $this->assertStatus(200);
    }
 
    public function assertUnauthorized(): static
    {
        return $this->assertStatus(401);
    }
 
    public function assertForbidden(): static
    {
        return $this->assertStatus(403);
    }
 
    public function assertNotFound(): static
    {
        return $this->assertStatus(404);
    }
 
    public function assertHeader(string $name, ?string $value = null): static
    {
        if (!$this->app->make('response.factory')->headers->has($name)) {
            $this->fail("Header [{$name}] not present on response.");
        }

        $headerValue = $this->app->make('response.factory')->headers->get($name);
        
        if ($value !== null && $headerValue !== $value) {
            $this->fail("Header [{$name}] was found, but value [{$headerValue}] does not match [{$value}].");
        }
 
        $this->assertTrue(true);
    }
 
    public function assertSee(string $value): static
    {
        if (!str_contains($this->app->make('response.factory')->getContent(), $value)) {
            $this->fail("Failed asserting that the response contains [{$value}].");
        }
 
        $this->assertTrue(true);
    }
 
    /** Asserts that every key (dot-notation) in $subset exists in the JSON body with an equal value. */
    public function assertJson(array $subset): static
    {
        $decoded = json_decode($this->app->make('response.factory')->getContent(), true);
 
        if (!is_array($decoded)) {
            $this->fail("Response content is not valid JSON.");
        }
 
        $missing = new \stdClass();
        foreach ($subset as $key => $expected) {
            $actual = Support::dataGet($decoded, $key, $missing);
 
            if ($actual === $missing || $actual != $expected) {
                $this->fail("Failed asserting JSON key [{$key}] equals " . json_encode($expected) . '.');
            }
        }
 
        $this->assertTrue(true);
    }
 
    public function assertRedirect(?string $uri = null): static
    {
        if (!$this->app->make('response.factory')->isRedirect()) {
            $this->fail("Response status code [{$this->app->make('response.factory')->statusCode}] is not a redirect status code.");
        }
 
        if ($uri !== null) {
            $location = (string) $this->app->make('response.factory')->headers->get('Location', '');
            $samePath = '/' . ltrim((string) parse_url($location, PHP_URL_PATH), '/')
                === '/' . ltrim((string) parse_url($uri, PHP_URL_PATH), '/');
 
            if ($location !== $uri && !($samePath && !preg_match('#^https?://#', $uri))) {
                $this->fail("Expected redirect to [{$uri}] but got [{$location}].");
            }
        }
 
        $this->assertTrue(true);
    }
}

?>
