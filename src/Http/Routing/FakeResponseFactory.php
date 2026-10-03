<?php

declare(strict_type=1);

namespace LaravelFaked\Http\Routing;

use LaravelFaked\Core\Foundation\FakeApplication;

use LaravelFaked\Http\Lifecycle\Concerns\FakeResponse;
use LaravelFaked\Http\Lifecycle\FakeJsonResponse;
use LaravelFaked\Http\Lifecycle\FakeRedirectResponse;

/** Stand-in for Illuminate\Routing\ResponseFactory (what response() returns with no args). */
class FakeResponseFactory
{
    public function __construct(protected FakeApplication $app)
    {
    }
 
    public function make(mixed $content = '', int $status = 200, array $headers = []): FakeResponse
    {
        return new FakeResponse($content, $status, $headers);
    }
 
    public function noContent(int $status = 204, array $headers = []): FakeResponse
    {
        return $this->make('', $status, $headers);
    }
 
    public function json(mixed $data = [], int $status = 200, array $headers = [], int $options = 0): FakeJsonResponse
    {
        return new FakeJsonResponse($data, $status, $headers, $options);
    }
 
    public function redirectTo(string $path, int $status = 302, array $headers = [], ?bool $secure = null): FakeRedirectResponse
    {
        return $this->app->make('redirect')->to($path, $status, $headers, $secure);
    }
 
    public function redirectToRoute(string $route, mixed $parameters = [], int $status = 302, array $headers = []): FakeRedirectResponse
    {
        return $this->app->make('redirect')->route($route, $parameters, $status, $headers);
    }
 
    public function redirectToIntended(string $default = '/', int $status = 302, array $headers = [], ?bool $secure = null): FakeRedirectResponse
    {
        return $this->app->make('redirect')->intended($default, $status, $headers, $secure);
    }
 
    public function view(mixed ...$args): never
    {
        throw new \LogicException('Views are not available in this fake Laravel runtime; render content yourself and use make().');
    }
}

?>
