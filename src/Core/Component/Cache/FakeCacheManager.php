<?php

declare(strict_types=1);

namespace LaravelFaked\Core\Component\Cache;

use LaravelFaked\Core\Foundation\FakeApplication;

/**
 * Stand-in for Illuminate\Cache\CacheManager. Reads config('cache.default') and
 * config('cache.stores.{name}'); a store whose driver is "null" never stores anything,
 * every other driver (array, redis, file, ...) gets an isolated in-memory array store.
 * Unknown method calls go to the default store.
 */
class FakeCacheManager
{
    /** @var array<string, object> */
    protected array $stores = [];
 
    /** @var array<string, \Closure> */
    protected array $customCreators = [];
 
    public function __construct(protected FakeApplication $app)
    {
    }
 
    public function store(?string $name = null): object
    {
        $name ??= $this->getDefaultDriver();
 
        return $this->stores[$name] ??= $this->resolve($name);
    }
 
    public function driver(?string $driver = null): object
    {
        return $this->store($driver);
    }
 
    protected function resolve(string $name): object
    {
        $config = $this->config("cache.stores.{$name}");
 
        if ($config === null && !in_array($name, ['array', 'null'], true)) {
            throw new \InvalidArgumentException("Cache store [{$name}] is not defined.");
        }
 
        $driver = (string) ($config['driver'] ?? $name);
 
        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($this->app, (array) $config);
        }
 
        return new FakeCacheRepository(
            $name,
            $driver === 'null',
            $this->app->bound('events') ? $this->app->make('events') : null,
        );
    }
 
    /** Register a custom driver: fn (FakeApplication $app, array $config) => object. */
    public function extend(string $driver, \Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;
 
        return $this;
    }
 
    public function getDefaultDriver(): string
    {
        return (string) ($this->config('cache.default') ?? 'array');
    }
 
    public function setDefaultDriver(string $name): void
    {
        if ($this->app->bound('config')) {
            $this->app->make('config')->set('cache.default', $name);
        }
    }
 
    public function forgetDriver(array|string|null $name = null): static
    {
        foreach ((array) ($name ?? $this->getDefaultDriver()) as $store) {
            unset($this->stores[$store]);
        }
 
        return $this;
    }
 
    public function purge(?string $name = null): void
    {
        unset($this->stores[$name ?? $this->getDefaultDriver()]);
    }
 
    /** Moves the shared fake cache clock forward (TTL expiry, lock expiry). */
    public function travel(int $seconds): static
    {
        FakeCacheRepository::travel($seconds);
 
        return $this;
    }
 
    protected function config(string $key): mixed
    {
        return $this->app->bound('config') ? $this->app->make('config')->get($key) : null;
    }
 
    public function __call(string $method, array $parameters): mixed
    {
        return $this->store()->{$method}(...$parameters);
    }
}
