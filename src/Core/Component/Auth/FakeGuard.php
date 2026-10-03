<?php

declare(strict_types=1);

namespace LaravelFaked\Core\Component\Auth;

use LaravelFaked\Core\Component\Utility\FakeSessionStore;
use LaravelFaked\Core\Component\Utility\FakeEventDispatcher;

use LaravelFaked\Core\Component\Auth\FakeUserProvider;

/**
 * Stand-in for Illuminate\Auth\SessionGuard. Persists the logged-in id in the fake
 * session (so a guard created later still finds the user) and fires auth events
 * as string names ("Illuminate\Auth\Events\Login", ...) with one array payload:
 *   ['guard' => ..., 'user' => ..., 'remember' => ..., 'credentials' => ...]
 */
class FakeGuard
{
    protected ?object $user = null;
    protected ?object $lastAttempted = null;
    protected bool $loggedOut = false;
 
    public function __construct(
        protected string $name,
        protected FakeUserProvider $provider,
        protected ?FakeSessionStore $session = null,
        protected ?FakeEventDispatcher $events = null,
    ) {
    }
 
    public function user(): ?object
    {
        if ($this->loggedOut) {
            return null;
        }
 
        if ($this->user !== null) {
            return $this->user;
        }
 
        $id = $this->session?->get($this->getName());
 
        if ($id !== null && ($user = $this->provider->retrieveById($id)) !== null) {
            $this->user = $user;
            $this->fire('Authenticated', ['user' => $user]);
        }
 
        return $this->user;
    }
 
    public function id(): mixed
    {
        if ($this->loggedOut) {
            return null;
        }
 
        return $this->user()?->getAuthIdentifier() ?? $this->session?->get($this->getName());
    }
 
    public function check(): bool
    {
        return $this->user() !== null;
    }
 
    public function guest(): bool
    {
        return !$this->check();
    }
 
    public function hasUser(): bool
    {
        return $this->user !== null;
    }
 
    public function setUser(object $user): static
    {
        $this->user = $user;
        $this->loggedOut = false;
        $this->fire('Authenticated', ['user' => $user]);
 
        return $this;
    }
 
    public function forgetUser(): static
    {
        $this->user = null;
 
        return $this;
    }
 
    public function validate(array $credentials = []): bool
    {
        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);
 
        return $user !== null && $this->provider->validateCredentials($user, $credentials);
    }
 
    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $this->fire('Attempting', ['credentials' => $credentials, 'remember' => $remember]);
 
        if ($this->validate($credentials)) {
            $this->login($this->lastAttempted, $remember);
 
            return true;
        }
 
        $this->fire('Failed', ['user' => $this->lastAttempted, 'credentials' => $credentials]);
 
        return false;
    }
 
    public function once(array $credentials = []): bool
    {
        if ($this->validate($credentials)) {
            $this->setUser($this->lastAttempted);
 
            return true;
        }
 
        return false;
    }
 
    public function login(object $user, bool $remember = false): void
    {
        $this->session?->put($this->getName(), $user->getAuthIdentifier());
        $this->session?->migrate(true);
 
        if ($remember) {
            $this->provider->updateRememberToken($user, bin2hex(random_bytes(30)));
        }
 
        $this->fire('Login', ['user' => $user, 'remember' => $remember]);
        $this->setUser($user);
    }
 
    public function loginUsingId(mixed $id, bool $remember = false): object|false
    {
        $user = $this->provider->retrieveById($id);
 
        if ($user === null) {
            return false;
        }
 
        $this->login($user, $remember);
 
        return $user;
    }
 
    public function logout(): void
    {
        $user = $this->user();
 
        $this->session?->forget($this->getName());
        $this->fire('Logout', ['user' => $user]);
 
        $this->user = null;
        $this->loggedOut = true;
    }
 
    public function getName(): string
    {
        return 'login_' . $this->name . '_' . sha1(static::class);
    }
 
    public function getLastAttempted(): ?object
    {
        return $this->lastAttempted;
    }
 
    public function getProvider(): FakeUserProvider
    {
        return $this->provider;
    }
 
    public function setProvider(FakeUserProvider $provider): void
    {
        $this->provider = $provider;
    }
 
    public function getSession(): ?FakeSessionStore
    {
        return $this->session;
    }
 
    protected function fire(string $event, array $payload): void
    {
        $this->events?->dispatch('Illuminate\\Auth\\Events\\' . $event, [['guard' => $this->name] + $payload]);
    }
}

?>
