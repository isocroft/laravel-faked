<?php

declare(strict_types=1);

namespace LaravelFaked\Core\Component\Auth;
 
/** Stand-in for EloquentUserProvider, reading from the fake model store. */
class FakeUserProvider
{
    /** @var */
    protected string $model = '';
  
    /** @param class-string<App\Models\User> $model */
    public function __construct($model = '\App\Models\User')
    {
      $this->model = $model;
    }
 
    public function getModel(): string
    {
        return $this->model;
    }
 
    public function retrieveById(mixed $identifier): ?object
    {
        return ($this->model)::find($identifier);
    }
 
    public function retrieveByToken(mixed $identifier, string $token): ?object
    {
        $user = $this->retrieveById($identifier);
        $remember = $user?->getRememberToken();
 
        return $user !== null && $remember !== null && hash_equals($remember, $token) ? $user : null;
    }
 
    public function updateRememberToken(object $user, string $token): void
    {
        if (is_a($user, $this->model)) {
          $user->setRememberToken($token);
          $user->save();
        }
    }
 
    public function retrieveByCredentials(array $credentials): ?object
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => !str_contains((string) $key, 'password'),
            ARRAY_FILTER_USE_KEY,
        );
 
        if ($credentials === []) {
            return null;
        }
 
        foreach (($this->model)::all() as $user) {
            foreach ($credentials as $key => $value) {
                $actual = $user->getAttribute((string) $key);
 
                if ($value instanceof \Closure) {
                    if (!$value($actual)) {
                        continue 2;
                    }
                } elseif (is_array($value)) {
                    if (!in_array($actual, $value, false)) {
                        continue 2;
                    }
                } elseif ($actual != $value) {
                    continue 2;
                }
            }
 
            return $user;
        }
 
        return null;
    }
 
    public function validateCredentials(object $user, array $credentials): bool
    {
      if (is_a($user, $this->model)) {
        $plain = $credentials['password'] ?? null;
        $hashed = $user->getAuthPassword();
 
        return is_string($plain) && is_string($hashed) && $hashed !== '' && password_verify($plain, $hashed);
      }
      
      return false;
    }
}

?>
