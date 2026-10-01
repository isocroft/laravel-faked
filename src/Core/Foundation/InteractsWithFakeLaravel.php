<?php

declare(strict_types=1);

namespace LaravelFaked\Core\Foundation;

use LaravelFaked\Http\Lifecycle\Concerns\FakeResponse;

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
 
    public function assertStatus(FakeResponse $response, int $status): static
    {
        $statusCode = $response->getStatusCode();
        
        if ($statusCode !== $status) {
            $this->fail("Expected response status code [{$status}] but received {$statusCode}.");
        }
 
        $this->assertTrue(true);
    }
 
    public function assertOk(FakeResponse $response): static
    {
        return $this->assertStatus($response, 200);
    }
 
    public function assertUnauthorized(FakeResponse $response): static
    {
        return $this->assertStatus($response, 401);
    }
 
    public function assertForbidden(FakeResponse $response): static
    {
        return $this->assertStatus($response, 403);
    }
 
    public function assertNotFound(FakeResponse $response): static
    {
        return $this->assertStatus($response, 404);
    }
 
    public function assertHeader(FakeResponse $response, string $name, ?string $value = null): static
    {
        if (!$response->headers->has($name)) {
            $this->fail("Header [{$name}] not present on response.");
        }

        $headerValue = $response->headers->get($name);
        
        if ($value !== null && $headerValue !== $value) {
            $this->fail("Header [{$name}] was found, but value [{$headerValue}] does not match [{$value}].");
        }
 
        $this->assertTrue(true);
    }
 
    public function assertSee(FakeResponse $response, string $value): static
    {
        if (!str_contains($response->getContent(), $value)) {
            $this->fail("Failed asserting that the response contains [{$value}].");
        }
 
        $this->assertTrue(true);
    }
 
    /** Asserts that every key (dot-notation) in $subset exists in the JSON body with an equal value. */
    public function assertJson(FakeResponse $response, array $subset): static
    {
        $decoded = json_decode($response->getContent(), true);
 
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
 
    public function assertRedirect(FakeResponse $response, ?string $uri = null): static
    {
        if (!$response->isRedirect()) {
            $this->fail("Response status code [{$response->getStatusCode()}] is not a redirect status code.");
        }
 
        if ($uri !== null) {
            $location = (string) $response->headers->get('Location', '');
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
