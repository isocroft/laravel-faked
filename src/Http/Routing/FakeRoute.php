<?php

declare(strict_types=1);

namespace LaravelFaked\Http\Routing;

use LaravelFaked\Helpers\Support;

/** Stand-in for Illuminate\Routing\Route (only what middleware / guards usually touch). */
class FakeRoute
{
    public function __construct(
        protected string $uri = '/',
        protected array $parameters = [],
        protected ?string $name = null,
        protected array $methods = ['GET', 'HEAD'],
        protected array $middleware = [],
    ) {
    }
 
    public function uri(): string
    {
        return $this->uri;
    }
 
    public function getName(): ?string
    {
        return $this->name;
    }
 
    public function named(string|array ...$patterns): bool
    {
        if ($this->name === null) {
            return false;
        }
 
        $patterns = is_array($patterns[0] ?? null) ? $patterns[0] : $patterns;
 
        foreach ($patterns as $pattern) {
            if (Support::strIs($pattern, $this->name)) {
                return true;
            }
        }
 
        return false;
    }
 
    public function parameter(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->parameters) ? $this->parameters[$name] : Support::value($default);
    }
 
    public function parameters(): array
    {
        return $this->parameters;
    }
 
    public function hasParameter(string $name): bool
    {
        return array_key_exists($name, $this->parameters);
    }
 
    public function setParameter(string $name, mixed $value): void
    {
        $this->parameters[$name] = $value;
    }
 
    public function forgetParameter(string $name): void
    {
        unset($this->parameters[$name]);
    }
 
    public function methods(): array
    {
        return $this->methods;
    }
 
    public function middleware(): array
    {
        return $this->middleware;
    }
}

?>
