<?php

declare(strict_types=1);

namespace LaravelFaked\Http\Routing;

use LaravelFaked\Core\Foundation\FakeApplication;

use LaravelFaked\Http\Lifecycle\FakeRequest;
use LaravelFaked\Http\Lifecycle\FakeJsonResponse;
use LaravelFaked\Http\Lifecycle\Concerns\FakeResponse;

use LaravelFaked\DataSource\FakeModel;
use LaravelFaked\DataSource\FakeModelNotFoundException;

use LaravelFaked\Http\Lifecycle\FakeHttpException;


/**
 * Stand-in for Illuminate\Routing\Router: registration (verbs, groups, registrar),
 * matching + 404/405, middleware (aliases, groups, parameters), explicit and implicit
 * model binding (incl. {param:field}), controller/closure dispatch with dependency
 * injection, response preparation, HTTP exception rendering, and URL generation.
 */
class FakeRouter
{
    public const VERBS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
 
    /** @var list<FakeRoute> */
    protected array $routes = [];
 
    protected array $groupStack = [];
 
    /** @var array<string, string|\Closure> */
    protected array $middleware = [];
 
    /** @var array<string, list<string|\Closure>> */
    protected array $middlewareGroups = [];
 
    /** @var array<string, \Closure> */
    protected array $binders = [];
 
    /** @var array<string, string> global parameter patterns */
    protected array $patterns = [];
 
    protected ?FakeRoute $current = null;
    protected ?FakeRequest $currentRequest = null;
    protected bool $skipMiddleware = false;
    protected bool $handleExceptions = true;
 
    public function __construct(protected FakeApplication $app)
    {
    }
 
    /* ---- registration ---------------------------------------------------- */
 
    public function get(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }
 
    public function post(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->addRoute(['POST'], $uri, $action);
    }
 
    public function put(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }
 
    public function patch(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }
 
    public function delete(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }
 
    public function options(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }
 
    public function any(string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->addRoute(self::VERBS, $uri, $action);
    }
 
    public function match(array|string $methods, string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        return $this->addRoute((array) $methods, $uri, $action);
    }
 
    public function addRoute(array|string $methods, string $uri, \Closure|string|array|null $action = null): FakeRoute
    {
        $group = end($this->groupStack) ?: [];
 
        $prefix = trim((string) ($group['prefix'] ?? ''), '/');
        $fullUri = trim($prefix . '/' . trim($uri, '/'), '/');
 
        $uses = $action;
        if (is_string($action) && !str_contains($action, '@') && !class_exists($action) && isset($group['controller'])) {
            $uses = $group['controller'] . '@' . $action; // Route::controller(X::class)->group(fn () => Route::get('/', 'index'))
        }
 
        $route = new FakeRoute(
            $fullUri,
            [],
            isset($group['as']) ? (string) $group['as'] : null,
            (array) $methods,
            (array) ($group['middleware'] ?? []),
            [
                'uses' => $uses === null ? null : self::normalizeUses($uses),
                'prefix' => $prefix,
                'excluded_middleware' => (array) ($group['excluded_middleware'] ?? []),
            ],
        );
 
        $route->where(array_merge($this->patterns, (array) ($group['where'] ?? [])));
 
        return $this->routes[] = $route;
    }
 
    /** Closure stays a Closure; [Class, 'm'] / 'Class@m' / invokable Class become "Class@method". */
    public static function normalizeUses(\Closure|string|array $action): \Closure|string
    {
        if ($action instanceof \Closure) {
            return $action;
        }
 
        if (is_array($action)) {
            return ltrim((string) $action[0], '\\') . '@' . ($action[1] ?? '__invoke');
        }
 
        $action = ltrim($action, '\\');
 
        return str_contains($action, '@') ? $action : $action . '@__invoke';
    }
 
    public function group(array $attributes, \Closure|string|array $routes): static
    {
        $this->groupStack[] = $this->mergeWithLastGroup($attributes);
 
        foreach (is_array($routes) ? $routes : [$routes] as $callback) {
            if ($callback instanceof \Closure) {
                $callback($this);
            } else {
                $router = $this; // routes files can use $router
                require $callback;
            }
        }
 
        array_pop($this->groupStack);
 
        return $this;
    }
 
    protected function mergeWithLastGroup(array $new): array
    {
        $old = end($this->groupStack) ?: [];
 
        return [
            'prefix' => trim(trim((string) ($old['prefix'] ?? ''), '/') . '/' . trim((string) ($new['prefix'] ?? ''), '/'), '/'),
            'as' => ($old['as'] ?? '') . ($new['as'] ?? ''),
            'middleware' => array_merge((array) ($old['middleware'] ?? []), (array) ($new['middleware'] ?? [])),
            'excluded_middleware' => array_merge((array) ($old['excluded_middleware'] ?? []), (array) ($new['excluded_middleware'] ?? [])),
            'controller' => $new['controller'] ?? $old['controller'] ?? null,
            'where' => array_merge((array) ($old['where'] ?? []), (array) ($new['where'] ?? [])),
        ];
    }
 
    public function hasGroupStack(): bool
    {
        return $this->groupStack !== [];
    }
 
    public function getGroupStack(): array
    {
        return $this->groupStack;
    }
 
    /** Global parameter pattern, e.g. pattern('id', '[0-9]+'). Applies to routes added afterwards. */
    public function pattern(string $key, string $pattern): void
    {
        $this->patterns[$key] = $pattern;
    }
 
    public function patterns(array $patterns): void
    {
        foreach ($patterns as $key => $pattern) {
            $this->pattern((string) $key, (string) $pattern);
        }
    }
 
    /** prefix(), name(), as(), middleware(), controller(), where(), withoutMiddleware() -> registrar */
    public function __call(string $method, array $parameters): mixed
    {
        // (withoutMiddleware() on the router itself is the test helper below, not the registrar method.)
        if (in_array($method, ['prefix', 'name', 'as', 'middleware', 'controller', 'where'], true)) {
            return (new FakeRouteRegistrar($this))->{$method}(...$parameters);
        }
 
        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
    }
 
    public function aliasMiddleware(string $name, string|\Closure $class): static
    {
        $this->middleware[$name] = $class;
 
        return $this;
    }
 
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
 
    public function middlewareGroup(string $name, array $middleware): static
    {
        $this->middlewareGroups[$name] = $middleware;
 
        return $this;
    }
 
    public function pushMiddlewareToGroup(string $group, string|\Closure $middleware): static
    {
        if (!in_array($middleware, $this->middlewareGroups[$group] ?? [], true)) {
            $this->middlewareGroups[$group][] = $middleware;
        }
 
        return $this;
    }
 
    public function prependMiddlewareToGroup(string $group, string|\Closure $middleware): static
    {
        $this->middlewareGroups[$group] ??= [];
 
        if (!in_array($middleware, $this->middlewareGroups[$group], true)) {
            array_unshift($this->middlewareGroups[$group], $middleware);
        }
 
        return $this;
    }
 
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }
 
    /** Like TestCase::withoutMiddleware(). */
    public function withoutMiddleware(bool $skip = true): static
    {
        $this->skipMiddleware = $skip;
 
        return $this;
    }
 
    /** Like TestCase::withoutExceptionHandling(): HTTP / not-found exceptions propagate. */
    public function withoutExceptionHandling(bool $rethrow = true): static
    {
        $this->handleExceptions = !$rethrow;
 
        return $this;
    }
 
    /** Expands groups and removes excluded middleware, preserving order. */
    public function gatherRouteMiddleware(FakeRoute $route): array
    {
        $expanded = $this->expandMiddleware($route->middleware());
        $excluded = $this->expandMiddleware($route->excludedMiddleware());
 
        $result = [];
        foreach ($expanded as $middleware) {
            $name = is_string($middleware) ? explode(':', $middleware, 2)[0] : null;
            $isExcluded = $name !== null && (
                in_array($middleware, $excluded, true)
                || in_array($name, $excluded, true)
                || in_array($this->middleware[$name] ?? null, $excluded, true)
            );
 
            if (!$isExcluded && !in_array($middleware, $result, true)) {
                $result[] = $middleware;
            }
        }
 
        return $result;
    }
 
    protected function expandMiddleware(array $middleware, int $depth = 0): array
    {
        if ($depth > 10) {
            throw new \LogicException('Middleware groups are nested too deeply (circular reference?).');
        }
 
        $result = [];
 
        foreach ($middleware as $item) {
            if (is_string($item) && isset($this->middlewareGroups[$item])) {
                array_push($result, ...$this->expandMiddleware($this->middlewareGroups[$item], $depth + 1));
            } else {
                $result[] = $item;
            }
        }
 
        return $result;
    }
 
    /** Explicit binding: bind('tenant', fn (string $value, FakeRoute $route) => ...). */
    public function bind(string $key, \Closure|string $binder): void
    {
        $this->binders[str_replace('-', '_', $key)] = $binder instanceof \Closure
            ? $binder
            : function (mixed $value) use ($binder) {
                [$class, $method] = str_contains($binder, '@') ? explode('@', $binder, 2) : [$binder, 'bind'];
 
                return $this->app->make($class)->{$method}($value);
            };
    }
 
    /** Explicit model binding: model('tenant', FakeOrganization::class). Looks up by the model's route key. */
    public function model(string $key, string $class, ?\Closure $callback = null): void
    {
        $this->bind($key, function (mixed $value, FakeRoute $route) use ($class, $callback, $key) {
            if ($value === null) {
                return null;
            }
 
            $instance = new $class();
            $field = $route->bindingFieldFor($key) ?? $instance->getRouteKeyName();
            $model = $class::firstWhere($field, $value);
 
            if ($model !== null) {
                return $model;
            }
 
            if ($callback !== null) {
                return $callback($value);
            }
 
            throw new FakeModelNotFoundException("No query results for model [{$class}] {$value}");
        });
    }
 
    public function getBindingCallback(string $key): ?\Closure
    {
        return $this->binders[str_replace('-', '_', $key)] ?? null;
    }
 
    protected function substituteExplicitBindings(FakeRoute $route): void
    {
        foreach ($route->parameters() as $key => $value) {
            if (($binder = $this->getBindingCallback((string) $key)) !== null) {
                $route->setParameter((string) $key, $binder($value, $route));
            }
        }
    }
 
    protected function resolveImplicitBinding(string $class, mixed $value, FakeRoute $route, string $parameter): FakeModel
    {
        /** @var FakeModel $instance */
        $instance = new $class();
        $field = $route->bindingFieldFor($parameter) ?? $instance->getRouteKeyName();
        $model = $class::firstWhere($field, $value);
 
        if ($model === null) {
            throw new FakeModelNotFoundException("No query results for model [{$class}] {$value}");
        }
 
        $route->setParameter($parameter, $model);
 
        return $model;
    }
 
    /* ---- dispatching ----------------------------------------------------- */
 
    public function dispatch(FakeRequest $request): FakeResponse
    {
        $this->currentRequest = $request;
        $this->bindRequestToApp($request);
 
        try {
            $route = $this->findRoute($request);
 
            $request->setRouteResolver(fn () => $route);
            $this->current = $route;
 
            if ($this->app->bound('events')) {
                $this->app->make('events')->dispatch('Illuminate\\Routing\\Events\\RouteMatched', [['route' => $route, 'request' => $request]]);
            }
 
            $this->substituteExplicitBindings($route);
 
            $middleware = $this->skipMiddleware ? [] : $this->gatherRouteMiddleware($route);
 
            $result = $this->runThroughMiddleware(
                $request,
                $middleware,
                fn (FakeRequest $request) => $this->toResponse($request, $this->runRoute($request, $route)),
            );
 
            return $this->toResponse($request, $result);
        } catch (FakeHttpException|FakeModelNotFoundException $e) {
            if (!$this->handleExceptions) {
                throw $e;
            }
 
            return $this->renderException($request, $e);
        }
    }
 
    /** Test convenience: $router->call('GET', '/tenants/abc'). */
    public function call(string $method, string $uri, array $parameters = [], array $server = [], ?string $content = null): FakeResponse
    {
        return $this->dispatch(FakeRequest::create($uri, $method, $parameters, [], [], $server, $content));
    }
 
    /** Test convenience: $router->json('POST', '/api/users', ['name' => 'x']). */
    public function json(string $method, string $uri, array $data = [], array $server = []): FakeResponse
    {
        return $this->dispatch(FakeRequest::createJson($uri, $method, $data, $server));
    }
 
    public function findRoute(FakeRequest $request): FakeRoute
    {
        $allowed = [];
 
        foreach ($this->routes as $route) {
            if (!$route->matchesPath($request)) {
                continue;
            }
 
            if (in_array($request->getMethod(), $route->methods(), true)) {
                return $route->bind($request);
            }
 
            array_push($allowed, ...$route->methods());
        }
 
        if ($allowed !== []) {
            $allowed = array_values(array_unique($allowed));
 
            throw new FakeHttpException(
                405,
                sprintf('The %s method is not supported for route %s. Supported methods: %s.', $request->getMethod(), $request->path(), implode(', ', $allowed)),
                ['Allow' => implode(', ', $allowed)],
            );
        }
 
        throw new FakeHttpException(404, "The route {$request->path()} could not be found.");
    }
 
    protected function runThroughMiddleware(FakeRequest $request, array $middleware, \Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($middleware),
            fn (\Closure $next, mixed $item) => function (FakeRequest $request) use ($next, $item) {
                [$callable, $parameters] = $this->resolveMiddleware($item);
 
                return $callable($request, $next, ...$parameters);
            },
            $destination,
        );
 
        return $pipeline($request);
    }
 
    /** @return array{0: callable, 1: list<string>} */
    protected function resolveMiddleware(mixed $middleware): array
    {
        if ($middleware instanceof \Closure) {
            return [$middleware, []];
        }
 
        if (is_object($middleware) && method_exists($middleware, 'handle')) {
            return [[$middleware, 'handle'], []];
        }
 
        [$name, $parameters] = array_pad(explode(':', (string) $middleware, 2), 2, null);
        $parameters = $parameters === null || $parameters === '' ? [] : explode(',', $parameters);
        $target = $this->middleware[$name] ?? $name;
 
        if ($target instanceof \Closure) {
            return [$target, $parameters];
        }
 
        if (!class_exists($target)) {
            throw new \RuntimeException("Middleware [{$name}] is neither an alias nor a class.");
        }
 
        return [[$this->app->make($target), 'handle'], $parameters];
    }
 
    protected function runRoute(FakeRequest $request, FakeRoute $route): mixed
    {
        $uses = $route->getAction('uses');
 
        if ($uses instanceof \Closure) {
            return $uses(...$this->resolveMethodDependencies(new \ReflectionFunction($uses), $route, $request));
        }
 
        if (is_string($uses) && str_contains($uses, '@')) {
            [$class, $method] = explode('@', $uses, 2);
            $controller = $this->app->make($class);
 
            if (!method_exists($controller, $method)) {
                throw new \BadMethodCallException("Method {$class}::{$method} does not exist.");
            }
 
            return $controller->{$method}(...$this->resolveMethodDependencies(new \ReflectionMethod($controller, $method), $route, $request));
        }
 
        throw new \LogicException("Route [{$route->uri()}] has no action.");
    }
 
    /**
     * Fills a closure/controller signature: the request by type, route parameters by name
     * (models implicitly bound), other classes from the container, then leftover route
     * parameters positionally, then defaults.
     */
    protected function resolveMethodDependencies(\ReflectionFunctionAbstract $function, FakeRoute $route, FakeRequest $request): array
    {
        $routeParameters = $route->parameters();
        $unused = $routeParameters;
        $arguments = [];
 
        foreach ($function->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                array_push($arguments, ...array_values($unused));
                break;
            }
 
            $name = $parameter->getName();
            $type = $parameter->getType();
            $class = $type instanceof \ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
 
            if ($class !== null && is_a($class, FakeRequest::class, true)) {
                $arguments[] = $request;
                continue;
            }
 
            if (array_key_exists($name, $routeParameters)) {
                $value = $routeParameters[$name];
                unset($unused[$name]);
 
                if ($class !== null && is_subclass_of($class, FakeModel::class) && !$value instanceof FakeModel) {
                    $value = $this->resolveImplicitBinding($class, $value, $route, $name);
                }
 
                $arguments[] = $this->coerceScalar($parameter, $value);
                continue;
            }
 
            if ($class !== null) {
                try {
                    $arguments[] = $this->app->make($class);
                    continue;
                } catch (\RuntimeException $e) {
                    if (!$parameter->isDefaultValueAvailable() && !$parameter->allowsNull()) {
                        throw $e;
                    }
                }
            } elseif ($unused !== []) {
                $arguments[] = $this->coerceScalar($parameter, array_shift($unused));
                continue;
            }
 
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $arguments[] = null;
            } else {
                throw new \RuntimeException("Unable to resolve parameter [\${$name}] for route [{$route->uri()}].");
            }
        }
 
        return $arguments;
    }
 
    /**
     * Route parameters are strings; Laravel's (non-strict) files let PHP coerce them into
     * int/float/bool parameters. This file is strict, so coerce explicitly to match.
     */
    protected function coerceScalar(\ReflectionParameter $parameter, mixed $value): mixed
    {
        $type = $parameter->getType();
 
        if (!is_string($value) || !$type instanceof \ReflectionNamedType || !$type->isBuiltin()) {
            return $value;
        }
 
        return match ($type->getName()) {
            'int' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
            default => $value,
        };
    }
 
    /** Router::toResponse(): responses pass through, arrays/models -> JSON, scalars -> text. */
    public function toResponse(FakeRequest $request, mixed $result): FakeResponse
    {
        if ($result instanceof FakeResponse) {
            $response = $result;
        } elseif ($result instanceof FakeModel && $result->wasRecentlyCreated) {
            $response = new FakeJsonResponse($result, 201);
        } elseif (is_array($result) || $result instanceof \JsonSerializable || $result instanceof \ArrayObject
            || (is_object($result) && method_exists($result, 'toArray'))) {
            $response = new FakeJsonResponse($result);
        } else {
            $response = new FakeResponse($result ?? '', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }
 
        if ($response->getStatusCode() === 304 || $request->isMethod('HEAD')) {
            $response->setContent('');
        }
 
        return $response;
    }
 
    /** Mimics the default exception handler for HTTP exceptions. */
    protected function renderException(FakeRequest $request, \Throwable $e): FakeResponse
    {
        if ($e instanceof FakeHttpException && $e->getResponse() !== null) {
            return $e->getResponse();
        }
 
        $status = $e instanceof FakeHttpException ? $e->getStatusCode() : 404;
        $headers = $e instanceof FakeHttpException ? $e->getHeaders() : [];
        $message = $e->getMessage() !== '' ? $e->getMessage() : self::statusText($status);
 
        return $request->expectsJson()
            ? (new FakeJsonResponse(['message' => $message], $status, $headers))->withException($e)
            : (new FakeResponse($message, $status, $headers))->withException($e);
    }
 
    protected static function statusText(int $status): string
    {
        return [
            400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden',
            404 => 'Not Found', 405 => 'Method Not Allowed', 409 => 'Conflict', 419 => 'Page Expired',
            422 => 'Unprocessable Content', 429 => 'Too Many Requests', 500 => 'Server Error', 503 => 'Service Unavailable',
        ][$status] ?? 'Error occurred';
    }
 
    protected function bindRequestToApp(FakeRequest $request): void
    {
        $this->app->instance('request', $request);
 
        if ($this->app->bound('session') && !$request->hasSession()) {
            $request->setLaravelSession($this->app->make('session'));
        }
 
        if ($this->app->bound('auth')) {
            $auth = $this->app->make('auth');
            $request->setUserResolver(fn (?string $guard = null) => ($auth->userResolver())($guard));
        }
    }
 
    /** @return list<FakeRoute> */
    public function getRoutes(): array
    {
        return $this->routes;
    }
 
    public function getByName(string $name): ?FakeRoute
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }
 
        return null;
    }
 
    /** @param string $action "Class@method" (already normalized) */
    public function getByAction(string $action): ?FakeRoute
    {
        foreach ($this->routes as $route) {
            if ($route->getActionName() === ltrim($action, '\\')) {
                return $route;
            }
        }
 
        return null;
    }
 
    public function has(string|array $name): bool
    {
        foreach ((array) $name as $n) {
            if ($this->getByName($n) === null) {
                return false;
            }
        }
 
        return true;
    }
 
    public function current(): ?FakeRoute
    {
        return $this->current;
    }
 
    public function getCurrentRoute(): ?FakeRoute
    {
        return $this->current;
    }
 
    public function getCurrentRequest(): ?FakeRequest
    {
        return $this->currentRequest;
    }
 
    public function currentRouteName(): ?string
    {
        return $this->current?->getName();
    }
 
    public function currentRouteAction(): ?string
    {
        return $this->current?->getActionName();
    }
 
    public function currentRouteNamed(string ...$patterns): bool
    {
        return $this->current !== null && $this->current->named(...$patterns);
    }
 
    public function is(string ...$patterns): bool
    {
        return $this->currentRouteNamed(...$patterns);
    }
 
    public function toRoute(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        $route = $this->getByName($name) ?? throw new \InvalidArgumentException("Route [{$name}] not defined.");
 
        return $this->routeUrl($route, $parameters, $absolute);
    }
 
    public function toAction(string|array $action, mixed $parameters = [], bool $absolute = true): string
    {
        $key = self::formatAction($action);
        $route = $this->getByAction($key) ?? throw new \InvalidArgumentException("Action {$key} not defined.");
 
        return $this->routeUrl($route, $parameters, $absolute);
    }
 
    public static function formatAction(string|array $action): string
    {
        $uses = self::normalizeUses(is_array($action) && count($action) === 1 ? (string) $action[0] : $action);
 
        return is_string($uses) ? $uses : 'Closure';
    }
 
    public function routeUrl(FakeRoute $route, mixed $parameters = [], bool $absolute = true): string
    {
        $parameters = is_array($parameters) ? $parameters : [$parameters];
 
        $path = (string) preg_replace_callback(FakeRoute::PLACEHOLDER, function (array $m) use (&$parameters, $route): string {
            [$name, $field, $optional] = [$m[1], $m[2] ?? '', ($m[3] ?? '') === '?'];
 
            if (array_key_exists($name, $parameters)) {
                $value = $parameters[$name];
                unset($parameters[$name]);
            } else {
                $value = null;
                foreach ($parameters as $key => $candidate) {
                    if (is_int($key)) {
                        $value = $candidate;
                        unset($parameters[$key]);
                        break;
                    }
                }
            }
 
            if ($value === null) {
                if ($optional) {
                    return '';
                }
 
                throw new \InvalidArgumentException(sprintf(
                    'Missing required parameter for [Route: %s] [URI: %s] [Missing parameter: %s].',
                    $route->getName() ?? 'unnamed',
                    $route->uri(),
                    $name,
                ));
            }
 
            if (is_object($value)) {
                $value = $field !== '' && method_exists($value, 'getAttribute')
                    ? $value->getAttribute($field)
                    : (method_exists($value, 'getRouteKey') ? $value->getRouteKey() : (string) $value);
            }
 
            return rawurlencode((string) $value);
        }, $route->uri());
 
        $path = '/' . trim((string) preg_replace('#/+#', '/', $path), '/');
        $query = array_filter($parameters, fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);
        $query = $query !== [] ? '?' . http_build_query($query) : '';
 
        if (!$absolute) {
            return $path . $query;
        }
 
        $root = $this->app->bound('request') ? $this->app->make('request')->root() : 'http://localhost';
 
        return ($path === '/' ? $root : $root . $path) . $query;
    }
}

?>
