<?php

declare(strict_types=1);

namespace LaravelFaked\Http\Routing;

use LaravelFaked\Helpers\Support;
use LaravelFaked\Http\Lifecycle\FakeRequest;

/**
 * Stand-in for Illuminate\Routing\Route (plus what middleware / guards usually touch).
 *
 * URI placeholders: {id}, optional {id?}, and custom binding fields {tenant:slug_id}.
 * Still constructible by hand in tests: `new FakeRoute('tenants/{tenant}', ['tenant' => 'abc'], 'tenants.show')`.
 */
class FakeRoute
{
    /** Matches {name}, {name?}, {name:field}, {name:field?} */
    public const PLACEHOLDER = '/\{(\w+)(?::(\w+))?(\?)?\}/';
 
    /** @var array<string, string> parameter => regex */
    protected array $wheres = [];
 
    public function __construct(
        protected string $uri = '/',
        protected array $parameters = [],
        protected ?string $name = null,
        protected array $methods = ['GET', 'HEAD'],
        protected array $middleware = [],
        protected array $action = [],
    ) {
        $this->uri = trim($uri, '/') === '' ? '/' : trim($uri, '/');
        $this->methods = array_map('strtoupper', $methods);
 
        if (in_array('GET', $this->methods, true) && !in_array('HEAD', $this->methods, true)) {
            $this->methods[] = 'HEAD';
        }
    }
 
    public function uri(): string
    {
        return $this->uri;
    }
 
    public function getName(): ?string
    {
        return $this->name;
    }

    /** Appends, like Laravel (group 'as' prefix + ->name('show')). */
    public function name(string $name): static
    {
        $this->name = ($this->name ?? '') . $name;
 
        return $this;
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

    public function getAction(?string $key = null): mixed
    {
        return $key === null ? $this->action : ($this->action[$key] ?? null);
    }
 
    public function setAction(array $action): static
    {
        $this->action = $action;
 
        return $this;
    }
 
    public function uses(\Closure|string|array $action): static
    {
        $this->action['uses'] = FakeRouter::normalizeUses($action);
 
        return $this;
    }
 
    /** "Closure" or "Class@method". */
    public function getActionName(): string
    {
        $uses = $this->action['uses'] ?? null;
 
        return is_string($uses) ? $uses : 'Closure';
    }
 
    public function getActionMethod(): string
    {
        $name = $this->getActionName();
 
        return str_contains($name, '@') ? explode('@', $name, 2)[1] : $name;
    }
 
    public function getControllerClass(): ?string
    {
        $name = $this->getActionName();
 
        return str_contains($name, '@') ? explode('@', $name, 2)[0] : null;
    }
 
    public function parameter(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->parameters) ? $this->parameters[$name] : Support::value($default);
    }
 
    /** Getter with no args, fluent setter otherwise (same dual role as Laravel). */
    public function middleware(array|string|\Closure|null $middleware = null): static|array
    {
        if ($middleware === null) {
            return $this->middleware;
        }
 
        foreach (is_array($middleware) ? $middleware : [$middleware] as $item) {
            if (!in_array($item, $this->middleware, true)) {
                $this->middleware[] = $item; // strict check: closures can't be compared loosely
            }
        }
 
        return $this;
    }
 
    public function withoutMiddleware(array|string $middleware): static
    {
        $this->action['excluded_middleware'] = array_merge(
            (array) ($this->action['excluded_middleware'] ?? []),
            (array) $middleware,
        );
 
        return $this;
    }
 
    public function excludedMiddleware(): array
    {
        return (array) ($this->action['excluded_middleware'] ?? []);
    }
 
    public function where(array|string $name, ?string $expression = null): static
    {
        foreach (is_array($name) ? $name : [$name => $expression] as $param => $regex) {
            $this->wheres[$param] = (string) $regex;
        }
 
        return $this;
    }
 
    public function whereNumber(string ...$parameters): static
    {
        return $this->where(array_fill_keys($parameters, '[0-9]+'));
    }
 
    public function whereAlpha(string ...$parameters): static
    {
        return $this->where(array_fill_keys($parameters, '[a-zA-Z]+'));
    }
 
    public function whereUuid(string ...$parameters): static
    {
        return $this->where(array_fill_keys($parameters, '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}'));
    }
 
    public function getWheres(): array
    {
        return $this->wheres;
    }
 
    public function parameterNames(): array
    {
        preg_match_all(self::PLACEHOLDER, $this->uri, $matches);
 
        return $matches[1];
    }
 
    /** Custom binding field for a parameter, e.g. 'slug_id' for {tenant:slug_id}. */
    public function bindingFieldFor(string $parameter): ?string
    {
        preg_match_all(self::PLACEHOLDER, $this->uri, $matches, PREG_SET_ORDER);
 
        foreach ($matches as $m) {
            if ($m[1] === $parameter && ($m[2] ?? '') !== '') {
                return $m[2];
            }
        }
 
        return null;
    }
 
    public function parameters(): array
    {
        return $this->parameters;
    }
 
    public function hasParameters(): bool
    {
        return $this->parameters !== [];
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
 
    public function compileRegex(): string
    {
        $path = $this->uri === '/' ? '/' : '/' . $this->uri;
        $parts = preg_split('#(/?\{\w+(?::\w+)?\??\})#', $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $regex = '';
 
        foreach ($parts as $part) {
            if (preg_match('#^(/?)\{(\w+)(?::\w+)?(\?)?\}$#', $part, $m)) {
                $segment = preg_quote($m[1], '#') . '(?P<' . $m[2] . '>' . ($this->wheres[$m[2]] ?? '[^/]+') . ')';
                $regex .= ($m[3] ?? '') === '?' ? '(?:' . $segment . ')?' : $segment;
            } else {
                $regex .= preg_quote($part, '#');
            }
        }
 
        return '#^' . $regex . '/?$#u';
    }
 
    public function matchesPath(FakeRequest $request): bool
    {
        return (bool) preg_match($this->compileRegex(), '/' . ltrim($request->path(), '/'));
    }
 
    public function matches(FakeRequest $request): bool
    {
        return in_array($request->getMethod(), $this->methods, true) && $this->matchesPath($request);
    }
 
    /** Extract parameters from the request path (missing optional params are omitted). */
    public function bind(FakeRequest $request): static
    {
        preg_match($this->compileRegex(), '/' . ltrim($request->path(), '/'), $m);
 
        $this->parameters = [];
 
        foreach ($this->parameterNames() as $name) {
            if (isset($m[$name]) && $m[$name] !== '') {
                $this->parameters[$name] = rawurldecode($m[$name]);
            }
        }
 
        return $this;
    }
 
    public function methods(): array
    {
        return $this->methods;
    }
}

?>
