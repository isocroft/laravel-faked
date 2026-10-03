<?php

declare(strict_types=1);

namespace LaravelFaked\Core\Component\Auth;

use LaravelFaked\Core\Foundation\FakeApplication;

/** Stand-in for Illuminate\Auth\AuthManager; unknown method calls go to the default guard. */
class FakeAuthManager
{
    /** @var array<string, object> */
    protected array $guards = [];
 
    /** @var array<string, \Closure> */
    protected array $customCreators = [];
 
    protected string $defaultGuard = 'web';
    protected \Closure $userResolver;
 
    /** @param class-string<FakeModel> $userModel */
    public function __construct(protected FakeApplication $app, protected string $userModel = '\App\Models\User')
    {
        $this->userResolver = fn (?string $guard = null) => $this->guard($guard)->user();
    }
 
    public function guard(?string $name = null): object
    {
        $name ??= $this->defaultGuard;
 
        return $this->guards[$name] ??= isset($this->customCreators[$name])
            ? ($this->customCreators[$name])($this->app, $name)
            : new FakeGuard(
                $name,
                new FakeUserProvider($this->userModel),
                $this->app->bound('session') ? $this->app->make('session') : null,
                $this->app->bound('events') ? $this->app->make('events') : null,
            );
    }
 
    /** Register a custom guard factory: fn (FakeApplication $app, string $name) => object. */
    public function extend(string $driver, \Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;
 
        return $this;
    }
 
    public function shouldUse(?string $name): void
    {
        $this->setDefaultDriver($name ?? $this->defaultGuard);
        $this->userResolver = fn (?string $guard = null) => $this->guard($guard)->user();
    }
 
    public function getDefaultDriver(): string
    {
        return $this->defaultGuard;
    }
 
    public function setDefaultDriver(string $name): void
    {
        $this->defaultGuard = $name;
    }
 
    public function hasResolvedGuards(): bool
    {
        return $this->guards !== [];
    }
 
    public function forgetGuards(): static
    {
        $this->guards = [];
 
        return $this;
    }
 
    public function userResolver(): \Closure
    {
        return $this->userResolver;
    }
 
    public function resolveUsersUsing(\Closure $userResolver): static
    {
        $this->userResolver = $userResolver;
 
        return $this;
    }

 
    public function __call(string $method, array $parameters): mixed
    {
        return $this->guard()->{$method}(...$parameters);
    }
}

?>
