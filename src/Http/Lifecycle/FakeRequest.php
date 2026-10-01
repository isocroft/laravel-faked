<?php

declare(strict_types=1);

namespace LaravelFaked\Http\Lifecycle;

use LaravelFaked\Core\Component\Utility\FakeSessionStore;

use LaravelFaked\Http\Routing\FakeRoute;

use LaravelFaked\Helpers\Support;

/** Stand-in for Illuminate\Http\Request. */
class FakeRequest implements \ArrayAccess
{
    public FakeParameterBag $query;
    public FakeParameterBag $request;
    public FakeParameterBag $attributes;
    public FakeParameterBag $cookies;
    public FakeParameterBag $files;
    public FakeParameterBag $server;
    public FakeHeaderBag $headers;
 
    protected string $method;
    protected string $pathInfo;
    protected ?FakeParameterBag $json = null;
    protected ?\Closure $userResolver = null;
    protected ?\Closure $routeResolver = null;
    protected ?FakeSessionStore $session = null;
 
    public function __construct(
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        protected ?string $content = null,
    ) {
        $this->query = new FakeParameterBag($query);
        $this->request = new FakeParameterBag($request);
        $this->attributes = new FakeParameterBag($attributes);
        $this->cookies = new FakeParameterBag($cookies);
        $this->files = new FakeParameterBag($files);
        $this->server = new FakeParameterBag($server);
 
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $headers[substr((string) $key, 5)] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[$key] = $value;
            }
        }
        $this->headers = new FakeHeaderBag($headers);
 
        $this->method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $path = parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $this->pathInfo = is_string($path) && $path !== '' ? $path : '/';
    }
 
    public static function create(
        string $uri,
        string $method = 'GET',
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null,
    ): static {
        $components = parse_url($uri) ?: [];
 
        $server = array_replace([
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'HTTP_HOST' => 'localhost',
            'HTTP_USER_AGENT' => 'FakeLaravel/1.0',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),
        ], $server);
 
        if (isset($components['host'])) {
            $server['SERVER_NAME'] = $components['host'];
            $server['HTTP_HOST'] = $components['host'];
        }
 
        if (($components['scheme'] ?? null) === 'https') {
            $server['HTTPS'] = 'on';
            $server['SERVER_PORT'] = 443;
        }
 
        if (isset($components['port'])) {
            $server['SERVER_PORT'] = $components['port'];
            $server['HTTP_HOST'] .= ':' . $components['port'];
        }
 
        $path = ($components['path'] ?? '') === '' ? '/' : $components['path'];
        $query = [];
        if (isset($components['query'])) {
            parse_str($components['query'], $query);
        }
 
        $method = strtoupper($method);
        $server['REQUEST_METHOD'] = $method;
 
        if (in_array($method, ['GET', 'HEAD'], true)) {
            $query = array_replace($query, $parameters);
            $request = [];
        } else {
            $request = $parameters;
            $server['CONTENT_TYPE'] ??= 'application/x-www-form-urlencoded';
        }
 
        $queryString = http_build_query($query, '', '&');
        $server['QUERY_STRING'] = $queryString;
        $server['REQUEST_URI'] = $path . ($queryString !== '' ? '?' . $queryString : '');
 
        return new static($query, $request, [], $cookies, $files, $server, $content);
    }
 
    /** Convenience: a JSON API request (Content-Type + Accept: application/json). */
    public static function createJson(string $uri, string $method = 'POST', array $data = [], array $server = []): static
    {
        $server = array_replace([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], $server);
 
        return static::create($uri, $method, [], [], [], $server, json_encode($data, JSON_THROW_ON_ERROR));
    }
 
    /* ---- method / url ---------------------------------------------------- */
 
    public function method(): string
    {
        return $this->getMethod();
    }
 
    public function getMethod(): string
    {
        return $this->method;
    }
 
    public function setMethod(string $method): static
    {
        $this->method = strtoupper($method);
        $this->server->set('REQUEST_METHOD', $this->method);
 
        return $this;
    }
 
    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }
 
    public function getPathInfo(): string
    {
        return $this->pathInfo;
    }
 
    public function path(): string
    {
        $pattern = trim($this->pathInfo, '/');
 
        return $pattern === '' ? '/' : $pattern;
    }
 
    public function decodedPath(): string
    {
        return rawurldecode($this->path());
    }
 
    public function segments(): array
    {
        return array_values(array_filter(explode('/', $this->decodedPath()), fn ($s) => $s !== ''));
    }
 
    public function segment(int $index, ?string $default = null): ?string
    {
        return $this->segments()[$index - 1] ?? $default;
    }
 
    public function isSecure(): bool
    {
        $https = $this->server->get('HTTPS');
 
        return !empty($https) && strtolower((string) $https) !== 'off';
    }
 
    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }
 
    public function getHost(): string
    {
        return (string) preg_replace('/:\d+$/', '', (string) $this->headers->get('host', $this->server->get('SERVER_NAME', 'localhost')));
    }
 
    public function getHttpHost(): string
    {
        return (string) $this->headers->get('host', $this->getHost());
    }
 
    public function root(): string
    {
        return $this->getScheme() . '://' . $this->getHttpHost();
    }
 
    public function url(): string
    {
        return rtrim($this->root() . ($this->pathInfo === '/' ? '' : $this->pathInfo), '/');
    }
 
    public function fullUrl(): string
    {
        $query = (string) $this->server->get('QUERY_STRING', '');
 
        return $query !== '' ? $this->url() . '?' . $query : $this->url();
    }
 
    public function is(string ...$patterns): bool
    {
        $path = $this->decodedPath();
 
        foreach ($patterns as $pattern) {
            if (Support::strIs($pattern, $path)) {
                return true;
            }
        }
 
        return false;
    }
 
    public function routeIs(string ...$patterns): bool
    {
        $route = $this->route();
 
        return $route instanceof FakeRoute && $route->named(...$patterns);
    }
 
    /* ---- negotiation ----------------------------------------------------- */
 
    public function ajax(): bool
    {
        return $this->headers->get('X-Requested-With') === 'XMLHttpRequest';
    }
 
    public function pjax(): bool
    {
        return $this->headers->get('X-PJAX') == true;
    }
 
    public function isJson(): bool
    {
        $type = (string) $this->headers->get('Content-Type', '');
 
        return str_contains($type, '/json') || str_contains($type, '+json');
    }
 
    public function wantsJson(): bool
    {
        $accept = (string) $this->headers->get('Accept', '');
 
        return str_contains($accept, '/json') || str_contains($accept, '+json');
    }
 
    public function expectsJson(): bool
    {
        return ($this->ajax() && !$this->pjax()) || $this->wantsJson();
    }
 
    /* ---- headers / server / cookies -------------------------------------- */
 
    public function header(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->headers->all() : $this->headers->get($key, $default);
    }
 
    public function hasHeader(string $key): bool
    {
        return $this->headers->has($key);
    }
 
    public function bearerToken(): ?string
    {
        $header = (string) $this->header('Authorization', '');
 
        return preg_match('/^Bearer\s+(.+)$/i', $header, $m) ? trim($m[1]) : null;
    }
 
    public function ip(): ?string
    {
        return $this->server->get('REMOTE_ADDR');
    }
 
    public function userAgent(): ?string
    {
        return $this->headers->get('User-Agent');
    }
 
    public function server(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->server->all() : $this->server->get($key, $default);
    }
 
    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->cookies->all() : $this->cookies->get($key, $default);
    }
 
    /* ---- input ----------------------------------------------------------- */
 
    public function getContent(): string
    {
        return $this->content ?? '';
    }
 
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->json === null) {
            $decoded = json_decode($this->getContent(), true);
            $this->json = new FakeParameterBag(is_array($decoded) ? $decoded : []);
        }
 
        return $key === null ? $this->json : Support::dataGet($this->json->all(), $key, $default);
    }
 
    protected function getInputSource(): FakeParameterBag
    {
        if ($this->isJson()) {
            return $this->json();
        }
 
        return in_array($this->method, ['GET', 'HEAD'], true) ? $this->query : $this->request;
    }
 
    public function input(?string $key = null, mixed $default = null): mixed
    {
        return Support::dataGet($this->getInputSource()->all() + $this->query->all(), $key, $default);
    }
 
    public function all(array|string|null $keys = null): array
    {
        $input = array_replace_recursive($this->input(), $this->files->all());
 
        if ($keys === null) {
            return $input;
        }
 
        $results = [];
        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            Support::arraySet($results, $key, Support::dataGet($input, $key));
        }
 
        return $results;
    }
 
    public function only(array|string ...$keys): array
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;
        $input = $this->all();
        $results = [];
 
        foreach ($keys as $key) {
            if (Support::arrayHas($input, $key)) {
                Support::arraySet($results, $key, Support::dataGet($input, $key));
            }
        }
 
        return $results;
    }
 
    public function except(array|string ...$keys): array
    {
        $keys = is_array($keys[0] ?? null) ? $keys[0] : $keys;
        $results = $this->all();
 
        foreach ($keys as $key) {
            Support::arrayForget($results, $key);
        }
 
        return $results;
    }
 
    public function query(?string $key = null, mixed $default = null): mixed
    {
        return Support::dataGet($this->query->all(), $key, $default);
    }
 
    public function post(?string $key = null, mixed $default = null): mixed
    {
        return Support::dataGet($this->request->all(), $key, $default);
    }
 
    public function has(string|array $key): bool
    {
        $input = $this->all();
 
        foreach ((array) $key as $k) {
            if (!Support::arrayHas($input, $k)) {
                return false;
            }
        }
 
        return true;
    }
 
    public function hasAny(string|array $keys): bool
    {
        $input = $this->all();
 
        foreach ((array) $keys as $k) {
            if (Support::arrayHas($input, $k)) {
                return true;
            }
        }
 
        return false;
    }
 
    public function missing(string|array $key): bool
    {
        return !$this->has($key);
    }
 
    public function filled(string|array $key): bool
    {
        foreach ((array) $key as $k) {
            $value = $this->input($k);
 
            if ($value === null || (is_string($value) && trim($value) === '') || $value === []) {
                return false;
            }
        }
 
        return true;
    }
 
    public function boolean(?string $key = null, bool $default = false): bool
    {
        return filter_var($this->input($key, $default), FILTER_VALIDATE_BOOLEAN);
    }
 
    public function integer(string $key, int $default = 0): int
    {
        return (int) $this->input($key, $default);
    }
 
    public function string(string $key, string $default = ''): string
    {
        return (string) $this->input($key, $default);
    }
 
    public function merge(array $input): static
    {
        $this->getInputSource()->add($input);
 
        return $this;
    }
 
    public function replace(array $input): static
    {
        $this->getInputSource()->replace($input);
 
        return $this;
    }
 
    /* ---- user / route / session resolvers -------------------------------- */
 
    public function user(?string $guard = null): mixed
    {
        return ($this->getUserResolver())($guard);
    }
 
    public function setUserResolver(\Closure $callback): static
    {
        $this->userResolver = $callback;
 
        return $this;
    }
 
    public function getUserResolver(): \Closure
    {
        return $this->userResolver ?? fn () => null;
    }
 
    public function route(?string $param = null, mixed $default = null): mixed
    {
        $route = ($this->getRouteResolver())();
 
        if ($param === null) {
            return $route;
        }
 
        return $route instanceof FakeRoute ? $route->parameter($param, $default) : Support::value($default);
    }
 
    public function setRouteResolver(\Closure $callback): static
    {
        $this->routeResolver = $callback;
 
        return $this;
    }
 
    public function getRouteResolver(): \Closure
    {
        return $this->routeResolver ?? fn () => null;
    }
 
    /** Test convenience: bind a matched route (with parameters) to this request. */
    public function setRoute(FakeRoute $route): static
    {
        return $this->setRouteResolver(fn () => $route);
    }
 
    public function session(): FakeSessionStore
    {
        if ($this->session === null) {
            throw new \RuntimeException('Session store not set on request.');
        }
 
        return $this->session;
    }
 
    public function hasSession(): bool
    {
        return $this->session !== null;
    }
 
    public function setLaravelSession(FakeSessionStore $session): void
    {
        $this->session = $session;
    }
 
    /* ---- magic ----------------------------------------------------------- */
 
    public function offsetExists(mixed $offset): bool
    {
        return Support::arrayHas($this->all() + ($this->route()?->parameters() ?? []), (string) $offset);
    }
 
    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get((string) $offset);
    }
 
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->getInputSource()->set((string) $offset, $value);
    }
 
    public function offsetUnset(mixed $offset): void
    {
        $this->getInputSource()->remove((string) $offset);
    }
 
    public function __get(string $key): mixed
    {
        return Support::dataGet($this->all(), $key, fn () => $this->route($key));
    }
 
    public function __isset(string $key): bool
    {
        return $this->__get($key) !== null;
    }
}

?>
