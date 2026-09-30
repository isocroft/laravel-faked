<?php

declare(strict_types=1);
 
namespace LaravelFaked\Core\Component\Utility;

use LaravelFaked\Core\Foundation\FakeApplication;
use LaravelFaked\Helpers\Support;
 
/**
 * Stand-in for Illuminate\Events\Dispatcher that also behaves like `Event::fake()`.
 *
 * By default it's a spy: listeners run AND every dispatch is recorded.
 * Call `->fake()` (all events) or `->fake([Foo::class])` to record without running listeners.
 */
class FakeEventDispatcher
{
    /** @var array<string, list<callable|string|array>> */
    protected array $listeners = [];
 
    /** @var array<string, list<callable|string|array>> */
    protected array $wildcards = [];
 
    /** @var array<string, list<array>> event name => list of payloads */
    protected array $dispatched = [];
 
    protected bool|array $faking = false;
 
    public function __construct(protected ?FakeApplication $app = null)
    {
    }
 
    public function listen(string|array $events, callable|string|array $listener): void
    {
        foreach ((array) $events as $event) {
            if (str_contains($event, '*')) {
                $this->wildcards[$event][] = $listener;
            } else {
                $this->listeners[$event][] = $listener;
            }
        }
    }
 
    public function hasListeners(string $eventName): bool
    {
        return $this->getListeners($eventName) !== [];
    }
 
    /** @return list<array{0: callable|string|array, 1: bool}> [listener, isWildcard] */
    public function getListeners(string $eventName): array
    {
        $listeners = array_map(fn ($l) => [$l, false], $this->listeners[$eventName] ?? []);
 
        foreach ($this->wildcards as $pattern => $wildcardListeners) {
            if (Support::strIs($pattern, $eventName)) {
                foreach ($wildcardListeners as $listener) {
                    $listeners[] = [$listener, true];
                }
            }
        }
 
        return $listeners;
    }
 
    public function dispatch(string|object $event, mixed $payload = [], bool $halt = false): mixed
    {
        [$name, $payload] = is_object($event)
            ? [get_class($event), [$event]]
            : [$event, is_array($payload) ? $payload : [$payload]];
 
        $this->dispatched[$name][] = $payload;
 
        if ($this->shouldFake($name)) {
            return $halt ? null : [];
        }
 
        $responses = [];
 
        foreach ($this->getListeners($name) as [$listener, $isWildcard]) {
            $callable = $this->resolveListener($listener);
            $response = $isWildcard ? $callable($name, $payload) : $callable(...$payload);
 
            if ($halt && $response !== null) {
                return $response;
            }
 
            if ($response === false) {
                break;
            }
 
            $responses[] = $response;
        }
 
        return $halt ? null : $responses;
    }
 
    public function until(string|object $event, mixed $payload = []): mixed
    {
        return $this->dispatch($event, $payload, true);
    }
 
    public function forget(string $event): void
    {
        if (str_contains($event, '*')) {
            unset($this->wildcards[$event]);
        } else {
            unset($this->listeners[$event]);
        }
    }
 
    /** Event::fake() equivalent. Pass a list to fake only those events. */
    public function fake(array|string|null $eventsToFake = null): static
    {
        $this->faking = $eventsToFake === null ? true : (array) $eventsToFake;
 
        return $this;
    }
 
    public function stopFaking(): static
    {
        $this->faking = false;
 
        return $this;
    }
 
    public function clearDispatched(): static
    {
        $this->dispatched = [];
 
        return $this;
    }
 
    protected function shouldFake(string $name): bool
    {
        if ($this->faking === true) {
            return true;
        }
 
        foreach ((array) $this->faking as $pattern) {
            if (Support::strIs((string) $pattern, $name)) {
                return true;
            }
        }
 
        return false;
    }
 
    protected function resolveListener(callable|string|array $listener): callable
    {
        $app = $this->app ?? FakeApplication::getInstance();
 
        if (is_string($listener) && str_contains($listener, '@')) {
            [$class, $method] = explode('@', $listener, 2);
 
            return [$app->make($class), $method];
        }
 
        if (is_string($listener) && class_exists($listener)) {
            $instance = $app->make($listener);
 
            return method_exists($instance, 'handle') ? [$instance, 'handle'] : $instance;
        }
 
        if (is_array($listener) && is_string($listener[0] ?? null) && class_exists($listener[0])) {
            return [$app->make($listener[0]), $listener[1] ?? 'handle'];
        }
 
        if (!is_callable($listener)) {
            throw new \InvalidArgumentException('Event listener is not callable.');
        }
 
        return $listener;
    }
 
    /* ---- inspection & assertions ----------------------------------------- */
 
    /** @return list<array> payloads (each an argument list) matching the optional callback */
    public function dispatched(string $event, ?callable $callback = null): array
    {
        $payloads = $this->dispatched[$event] ?? [];
 
        return $callback === null
            ? $payloads
            : array_values(array_filter($payloads, fn (array $payload) => (bool) $callback(...$payload)));
    }
 
    public function hasDispatched(string $event): bool
    {
        return ($this->dispatched[$event] ?? []) !== [];
    }
}

?>
