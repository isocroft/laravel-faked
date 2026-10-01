<?php

declare(strict_types=1);

namespace LaravelFaked\Http\Lifecycle;

use LaravelFaked\Http\Lifecycle\FakeRequest;
use LaravelFaked\Http\Lifecycle\Concerns\FakeResponse;

use LaravelFaked\Core\Component\Utility\FakeSessionStore;

/** Stand-in for Illuminate\Http\RedirectResponse; flash helpers write to the fake session. */
class FakeRedirectResponse extends FakeResponse
{
    protected string $targetUrl = '';
    protected ?FakeSessionStore $session = null;
    protected ?FakeRequest $request = null;
 
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        parent::__construct('', $status, $headers);
        $this->setTargetUrl($url);
 
        if (!$this->isRedirect()) {
            throw new \InvalidArgumentException("The HTTP status code is not a redirect (\"{$status}\" given).");
        }
    }
 
    public function setTargetUrl(string $url): static
    {
        if ($url === '') {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }
 
        $this->targetUrl = $url;
        $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $this->setContent("<!DOCTYPE html><html><head><meta http-equiv=\"refresh\" content=\"0;url='{$escaped}'\" /><title>Redirecting to {$escaped}</title></head><body>Redirecting to <a href=\"{$escaped}\">{$escaped}</a>.</body></html>");
        $this->headers->set('Location', $url);
 
        return $this;
    }
 
    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }
 
    public function with(string|array $key, mixed $value = null): static
    {
        foreach (is_array($key) ? $key : [$key => $value] as $k => $v) {
            $this->session?->flash((string) $k, $v);
        }
 
        return $this;
    }
 
    public function withInput(?array $input = null): static
    {
        $this->session?->flashInput($input ?? ($this->request?->input() ?? []));
 
        return $this;
    }
 
    public function onlyInput(string ...$keys): static
    {
        return $this->withInput($this->request?->only($keys) ?? []);
    }
 
    /** Stores errors as a plain array under session('errors')[$bag] (no ViewErrorBag). */
    public function withErrors(array|string $errors, string $key = 'default'): static
    {
        $existing = (array) ($this->session?->get('errors', []) ?? []);
        $existing[$key] = array_merge((array) ($existing[$key] ?? []), (array) $errors);
        $this->session?->flash('errors', $existing);
 
        return $this;
    }
 
    public function setSession(FakeSessionStore $session): static
    {
        $this->session = $session;
 
        return $this;
    }
 
    public function getSession(): ?FakeSessionStore
    {
        return $this->session;
    }
 
    public function setRequest(FakeRequest $request): static
    {
        $this->request = $request;
 
        return $this;
    }
 
    public function getRequest(): ?FakeRequest
    {
        return $this->request;
    }
 
    /** Supports ->withStatus('Saved!') style dynamic flashes, like Laravel. */
    public function __call(string $method, array $parameters): static
    {
        if (str_starts_with($method, 'with') && strlen($method) > 4) {
            return $this->with(lcfirst(substr($method, 4)), $parameters[0] ?? null);
        }
 
        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
    }
}

?>
