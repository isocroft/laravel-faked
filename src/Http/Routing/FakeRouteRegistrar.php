<?php

declare(strict_types=1);

namespace LaravelFaked\Http\Routing;

/** Stand-in for Illuminate\Routing\RouteRegistrar. */
class FakeRouteRegistrar
{
    protected array $attributes = [];
 
    public function __construct(protected FakeRouter $router)
    {
    }
 
    public function attribute(string $key, mixed $value): static
    {
        if ($key === 'middleware') {
            $value = array_merge((array) ($this->attributes['middleware'] ?? []), is_array($value) ? $value : [$value]);
        }
 
        $this->attributes[$key] = $value;
 
        return $this;
    }
 
    public function prefix(string $prefix): static
    {
        return $this->attribute('prefix', $prefix);
    }
 
    public function name(string $name): static
    {
        return $this->attribute('as', $name);
    }
 
    public function as(string $name): static
    {
        return $this->name($name);
    }
 
    public function middleware(array|string|\Closure $middleware): static
    {
        return $this->attribute('middleware', $middleware);
    }
 
    public function withoutMiddleware(array|string $middleware): static
    {
        return $this->attribute('excluded_middleware', array_merge(
            (array) ($this->attributes['excluded_middleware'] ?? []),
            (array) $middleware,
        ));
    }
 
    public function controller(string $controller): static
    {
        return $this->attribute('controller', $controller);
    }
 
    public function where(array $wheres): static
    {
        return $this->attribute('where', array_merge((array) ($this->attributes['where'] ?? []), $wheres));
    }
 
    public function group(\Closure|string|array $callback): FakeRouter
    {
        $this->router->group($this->attributes, $callback);
 
        return $this->router;
    }
 
    public function get(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->register(['GET', 'HEAD'], $uri, $action);
    }
 
    public function post(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->register(['POST'], $uri, $action);
    }
 
    public function put(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->register(['PUT'], $uri, $action);
    }
 
    public function patch(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->register(['PATCH'], $uri, $action);
    }
 
    public function delete(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->register(['DELETE'], $uri, $action);
    }
 
    public function options(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->register(['OPTIONS'], $uri, $action);
    }
 
    public function any(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->register(FakeRouter::VERBS, $uri, $action);
    }
 
    public function match(array|string $methods, string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->register((array) $methods, $uri, $action);
    }
 
    protected function register(array $methods, string $uri, \Closure|string|array|null $action): FakeRoute
    {
        $route = null;
        $this->router->group($this->attributes, function (FakeRouter $router) use ($methods, $uri, $action, &$route) {
            $route = $router->addRoute($methods, $uri, $action);
        });
 
        return $route;
    }
}

?>
