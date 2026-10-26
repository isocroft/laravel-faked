<?php

declare(strict_types=1);

namespace LaravelFaked\Core\Component\Cache;

use LaravelFaked\Helpers\Support;

/**
 * Stand-in for Illuminate\Cache\Repository over an ArrayStore.
 * TTLs are honoured against a fake clock you can move with `FakeCacheRepository::travel($seconds)`.
 * Events are fired as string names ("Illuminate\Cache\Events\CacheHit", ...) with one array payload.
 */
class FakeCacheRepository implements \ArrayAccess
{
    /** Shared fake clock offset (seconds) for all stores. */
    protected static int $timeOffset = 0;
 
    /** @var array<string, array{value: mixed, expiresAt: ?int}> */
    protected array $storage = [];
 
    /** @var array<string, array{owner: string, expiresAt: ?int}> */
    protected array $locks = [];
 
    public function __construct(
        protected string $name = 'array',
        protected bool $isNullStore = false,
        protected ?FakeEventDispatcher $events = null,
    ) {
    }
 
    public static function travel(int $seconds): void
    {
        static::$timeOffset += $seconds;
    }
 
    public static function resetClock(): void
    {
        static::$timeOffset = 0;
    }
 
    public static function currentTime(): int
    {
        return time() + static::$timeOffset;
    }
 
    public function has(string $key): bool
    {
        return $this->retrieve($key) !== null;
    }
 
    public function missing(string $key): bool
    {
        return !$this->has($key);
    }
 
    public function get(array|string $key, mixed $default = null): mixed
    {
        if (is_array($key)) {
            return $this->many($key);
        }
 
        $value = $this->retrieve($key);
 
        if ($value === null) {
            $this->fire('CacheMissed', ['key' => $key]);
 
            return Support::value($default);
        }
 
        $this->fire('CacheHit', ['key' => $key, 'value' => $value]);
 
        return $value;
    }
 
    /** @param array $keys list of keys, or [key => default] */
    public function many(array $keys): array
    {
        $results = [];
 
        foreach ($keys as $key => $default) {
            [$key, $default] = is_int($key) ? [$default, null] : [$key, $default];
            $results[$key] = $this->get((string) $key, $default);
        }
 
        return $results;
    }
 
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
 
        foreach ($keys as $key) {
            $results[$key] = $this->get((string) $key, $default);
        }
 
        return $results;
    }
 
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
 
        return $value;
    }
 
    public function put(array|string $key, mixed $value = null, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        if (is_array($key)) {
            return $this->putMany($key, $value);
        }
 
        $seconds = $this->getSeconds($ttl);
 
        if ($seconds !== null && $seconds <= 0) {
            return $this->forget($key);
        }
 
        if ($this->isNullStore) {
            return false;
        }
 
        $this->storage[$key] = [
            'value' => $value,
            'expiresAt' => $seconds === null ? null : static::currentTime() + $seconds,
        ];
 
        $this->fire('KeyWritten', ['key' => $key, 'value' => $value, 'seconds' => $seconds]);
 
        return true;
    }
 
    public function set(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        return $this->put($key, $value, $ttl);
    }
 
    public function putMany(array $values, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        $ok = true;
 
        foreach ($values as $key => $value) {
            $ok = $this->put((string) $key, $value, $ttl) && $ok;
        }
 
        return $ok;
    }
 
    public function setMultiple(iterable $values, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        return $this->putMany(is_array($values) ? $values : iterator_to_array($values), $ttl);
    }
 
    public function add(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): bool
    {
        if ($this->retrieve($key) !== null) {
            return false;
        }
 
        return $this->put($key, $value, $ttl);
    }
 
    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value);
    }
 
    public function increment(string $key, int $value = 1): int|bool
    {
        if ($this->isNullStore) {
            return false;
        }
 
        $existing = $this->retrieve($key);
 
        if ($existing === null) {
            $this->forever($key, $value);
 
            return $value;
        }
 
        $incremented = (int) $existing + $value;
        $this->storage[$key]['value'] = $incremented; // keeps the original expiry, like ArrayStore
 
        return $incremented;
    }
 
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }
 
    public function remember(string $key, \DateTimeInterface|\DateInterval|int|null $ttl, \Closure $callback): mixed
    {
        $value = $this->get($key);
 
        if ($value !== null) {
            return $value;
        }
 
        $this->put($key, $value = $callback(), $ttl);
 
        return $value;
    }
 
    public function rememberForever(string $key, \Closure $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }
 
    public function sear(string $key, \Closure $callback): mixed
    {
        return $this->rememberForever($key, $callback);
    }
 
    public function forget(string $key): bool
    {
        $existed = array_key_exists($key, $this->storage);
        unset($this->storage[$key]);
 
        $this->fire('KeyForgotten', ['key' => $key]);
 
        return $existed;
    }
 
    public function delete(string $key): bool
    {
        return $this->forget($key);
    }
 
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->forget((string) $key);
        }
 
        return true;
    }
 
    public function flush(): bool
    {
        $this->storage = [];
 
        return true;
    }
 
    public function clear(): bool
    {
        return $this->flush();
    }
 
    public function lock(string $name, int $seconds = 0, ?string $owner = null): FakeLock
    {
        return new FakeLock($this, $name, $seconds, $owner);
    }
 
    public function restoreLock(string $name, string $owner): FakeLock
    {
        return $this->lock($name, 0, $owner);
    }
 
    /** @internal used by FakeLock */
    public function acquireLock(string $name, string $owner, int $seconds): bool
    {
        $lock = $this->locks[$name] ?? null;
        $expired = $lock !== null && $lock['expiresAt'] !== null && $lock['expiresAt'] <= static::currentTime();
 
        if ($lock !== null && !$expired) {
            return false;
        }
 
        $this->locks[$name] = ['owner' => $owner, 'expiresAt' => $seconds > 0 ? static::currentTime() + $seconds : null];
 
        return true;
    }
 
    /** @internal used by FakeLock */
    public function releaseLock(string $name, string $owner): bool
    {
        if (($this->locks[$name]['owner'] ?? null) !== $owner) {
            return false;
        }
 
        unset($this->locks[$name]);
 
        return true;
    }
 
    /** @internal used by FakeLock */
    public function forceReleaseLock(string $name): void
    {
        unset($this->locks[$name]);
    }
 
    /** @internal used by FakeLock */
    public function lockOwner(string $name): ?string
    {
        return $this->locks[$name]['owner'] ?? null;
    }
 
    public function getName(): string
    {
        return $this->name;
    }
 
    /** The repository is its own store here. */
    public function getStore(): static
    {
        return $this;
    }
 
    /** Raw snapshot of unexpired entries (key => value), handy for debugging tests. */
    public function all(): array
    {
        $values = [];
 
        foreach (array_keys($this->storage) as $key) {
            if (($value = $this->retrieve((string) $key)) !== null) {
                $values[$key] = $value;
            }
        }
 
        return $values;
    }
 
    public function tags(mixed ...$names): never
    {
        throw new \BadMethodCallException('Cache tags are not implemented in the fake cache.');
    }
 
    protected function retrieve(string $key): mixed
    {
        if ($this->isNullStore || !isset($this->storage[$key])) {
            return null;
        }
 
        $item = $this->storage[$key];
 
        if ($item['expiresAt'] !== null && $item['expiresAt'] <= static::currentTime()) {
            unset($this->storage[$key]);
 
            return null;
        }
 
        return $item['value'];
    }
 
    protected function getSeconds(\DateTimeInterface|\DateInterval|int|null $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }
 
        $now = static::currentTime();
 
        if ($ttl instanceof \DateInterval) {
            return (new \DateTimeImmutable('@' . $now))->add($ttl)->getTimestamp() - $now;
        }
 
        if ($ttl instanceof \DateTimeInterface) {
            return $ttl->getTimestamp() - $now;
        }
 
        return $ttl;
    }
 
    protected function fire(string $event, array $payload): void
    {
        $this->events?->dispatch('Illuminate\\Cache\\Events\\' . $event, [['storeName' => $this->name] + $payload]);
    }
 
    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }
 
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }
 
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->put((string) $offset, $value);
    }
 
    public function offsetUnset(mixed $offset): void
    {
        $this->forget((string) $offset);
    }
}

?>
