<?php

declare(strict_types=1);
 
namespace LaravelFaked\Core\Component\Cache;
 
class FakeLockTimeoutException extends \RuntimeException
{
}

/** Stand-in for Illuminate\Cache\ArrayLock. block() never waits: time only moves via travel(). */
class FakeLock
{
    protected string $owner;
 
    public function __construct(
        protected FakeCacheRepository $store,
        protected string $name,
        protected int $seconds = 0,
        ?string $owner = null,
    ) {
        $this->owner = $owner ?? bin2hex(random_bytes(8));
    }
 
    public function acquire(): bool
    {
        return $this->store->acquireLock($this->name, $this->owner, $this->seconds);
    }
 
    public function get(?callable $callback = null): mixed
    {
        $acquired = $this->acquire();
 
        if ($acquired && $callback !== null) {
            try {
                return $callback();
            } finally {
                $this->release();
            }
        }
 
        return $acquired;
    }
 
    public function block(int $seconds, ?callable $callback = null): mixed
    {
        if (!$this->acquire()) {
            throw new FakeLockTimeoutException("Unable to acquire lock [{$this->name}] (fake locks never wait).");
        }
 
        if ($callback === null) {
            return true;
        }
 
        try {
            return $callback();
        } finally {
            $this->release();
        }
    }
 
    public function release(): bool
    {
        return $this->store->releaseLock($this->name, $this->owner);
    }
 
    public function forceRelease(): void
    {
        $this->store->forceReleaseLock($this->name);
    }
 
    public function owner(): string
    {
        return $this->owner;
    }
 
    public function isOwnedByCurrentProcess(): bool
    {
        return $this->store->lockOwner($this->name) === $this->owner;
    }
}

?>
