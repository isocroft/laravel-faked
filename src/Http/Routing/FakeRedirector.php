<?php

namespace Http\Routing;

use Core\Foundation\FakeApplication;

/** Stand-in for Illuminate\Routing\Redirector (plus a tiny named-route table). */
class FakeRedirector
{
    /* @HINT: routes table */
    /** @var array<string, string> name => uri */
    protected array $routes = [];

    /* @HINT: app container */
    protected FakeApplication $app;
 
    public function __construct(FakeApplication $app)
    {
      $this->app = $app;
    }
 
    /** Register a named route using `route()` or `redirect()->route()` can build URLs, e.g. ('tenants.show', 'tenants/{tenant}'). */
    public function defineRoute(string $name, string $uri): static
    {
        $this->routes[$name] = $uri;
 
        return $this;
    }
 
    public function to(string $path, int $status = 302, array $headers = [], ?bool $secure = null): FakeRedirectResponse
    {
        return $this->createRedirect($this->toUrl($path, $secure), $status, $headers);
    }
 
    public function away(string $path, int $status = 302, array $headers = []): FakeRedirectResponse
    {
        return $this->createRedirect($path, $status, $headers);
    }
 
    public function back(int $status = 302, array $headers = [], string|false $fallback = false): FakeRedirectResponse
    {
        $previous = $this->request()?->header('referer');
 
        if (!$previous && $this->app->bound('session')) {
            $previous = $this->app->make('session')->previousUrl();
        }
 
        return $this->createRedirect($previous ?: $this->toUrl($fallback ?: '/'), $status, $headers);
    }
 
    public function refresh(int $status = 302, array $headers = []): FakeRedirectResponse
    {
        return $this->to($this->request()?->fullUrl() ?? '/', $status, $headers);
    }
 
    public function route(string $name, mixed $parameters = [], int $status = 302, array $headers = []): FakeRedirectResponse
    {
        return $this->to($this->routeUrl($name, $parameters), $status, $headers);
    }
 
    public function guest(string $path, int $status = 302, array $headers = [], ?bool $secure = null): FakeRedirectResponse
    {
        $request = $this->request();
 
        if ($request !== null && $request->isMethod('GET') && !$request->expectsJson()) {
            $this->setIntendedUrl($request->fullUrl());
        }
 
        return $this->to($path, $status, $headers, $secure);
    }
 
    public function intended(string $default = '/', int $status = 302, array $headers = [], ?bool $secure = null): FakeRedirectResponse
    {
        $path = $this->app->bound('session') ? $this->app->make('session')->pull('url.intended', $default) : $default;
 
        return $this->to((string) $path, $status, $headers, $secure);
    }
 
    public function setIntendedUrl(string $url): static
    {
        if ($this->app->bound('session')) {
            $this->app->make('session')->put('url.intended', $url);
        }
 
        return $this;
    }
 
    public function getIntendedUrl(): ?string
    {
        return $this->app->bound('session') ? $this->app->make('session')->get('url.intended') : null;
    }
 
    public function toUrl(string $path, ?bool $secure = null): string
    {
        if (preg_match('#^(https?:)?//#i', $path) || preg_match('#^(mailto|tel|sms):#i', $path)) {
            return $path;
        }
 
        $root = $this->request()?->root() ?? 'http://localhost';
 
        if ($secure === true) {
            $root = (string) preg_replace('#^http://#', 'https://', $root);
        }
 
        $tail = trim($path, '/');
 
        return $tail === '' ? $root : $root . '/' . $tail;
    }
 
    public function routeUrl(string $name, mixed $parameters = []): string
    {
        if (!isset($this->routes[$name])) {
            throw new \InvalidArgumentException("Route [{$name}] not defined.");
        }
 
        $parameters = is_array($parameters) ? $parameters : [$parameters];
        $parameters = array_map(
            fn ($p) => is_object($p) && method_exists($p, 'getRouteKey') ? $p->getRouteKey() : $p,
            $parameters,
        );
 
        $uri = (string) preg_replace_callback('/\{(\w+)(\?)?\}/', function (array $m) use (&$parameters, $name): string {
            if (array_key_exists($m[1], $parameters)) {
                $value = $parameters[$m[1]];
                unset($parameters[$m[1]]);
 
                return rawurlencode((string) $value);
            }
 
            foreach ($parameters as $key => $value) {
                if (is_int($key)) {
                    unset($parameters[$key]);
 
                    return rawurlencode((string) $value);
                }
            }
 
            if (($m[2] ?? '') === '?') {
                return '';
            }
 
            throw new \InvalidArgumentException("Missing required parameter [{$m[1]}] for route [{$name}].");
        }, $this->routes[$name]);
 
        $uri = (string) preg_replace('#/+#', '/', $uri);
        $query = array_filter($parameters, fn ($k) => is_string($k), ARRAY_FILTER_USE_KEY);
 
        return $this->toUrl($uri) . ($query !== [] ? '?' . http_build_query($query) : '');
    }
 
    protected function createRedirect(string $url, int $status, array $headers): FakeRedirectResponse
    {
        $redirect = new FakeRedirectResponse($url, $status, $headers);
 
        if ($this->app->bound('session')) {
            $redirect->setSession($this->app->make('session'));
        }
 
        if (($request = $this->request()) !== null) {
            $redirect->setRequest($request);
        }
 
        return $redirect;
    }
 
    protected function request(): ?FakeRequest
    {
        return $this->app->bound('request') ? $this->app->make('request') : null;
    }
}

?>
