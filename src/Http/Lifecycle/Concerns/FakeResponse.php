<?php

declare(strict_types=1);

namespace LaravelFaked\Http\Lifecycle\Concerns;

use LaravelFaked\Http\Lifecycle\FakeHeaderBag;
 
/** Stand-in for Illuminate\Http\Response with a few TestResponse-style assertions. */
class FakeResponse implements \Stringable
{
    public FakeHeaderBag $headers;
    protected string $content = '';
    protected mixed $original = null;
    protected int $statusCode = 200;
    protected ?\Throwable $exception = null;
 
    public function __construct(mixed $content = '', int $status = 200, array $headers = [])
    {
        $this->headers = new FakeHeaderBag($headers);
        $this->setContent($content);
        $this->setStatusCode($status);
    }
 
    public function setContent(mixed $content): static
    {
        $this->original = $content;
 
        if ($this->shouldBeJson($content)) {
            $this->headers->set('Content-Type', 'application/json');
            $content = $this->morphToJson($content);
        }
 
        $this->content = (string) ($content ?? '');
 
        return $this;
    }
 
    protected function shouldBeJson(mixed $content): bool
    {
        return is_array($content)
            || $content instanceof \JsonSerializable
            || $content instanceof \ArrayObject
            || (is_object($content) && (method_exists($content, 'toJson') || method_exists($content, 'toArray')));
    }
 
    protected function morphToJson(mixed $content): string
    {
        if (is_object($content) && method_exists($content, 'toJson')) {
            return $content->toJson();
        }
 
        if (is_object($content) && !$content instanceof \JsonSerializable && method_exists($content, 'toArray')) {
            $content = $content->toArray();
        }
 
        return json_encode($content, JSON_THROW_ON_ERROR);
    }
 
    public function getContent(): string
    {
        return $this->content;
    }
 
    public function content(): string
    {
        return $this->content;
    }
 
    public function getOriginalContent(): mixed
    {
        return $this->original;
    }
 
    public function status(): int
    {
        return $this->statusCode;
    }
 
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
 
    public function setStatusCode(int $code): static
    {
        if ($code < 100 || $code >= 600) {
            throw new \InvalidArgumentException("The HTTP status code \"{$code}\" is not valid.");
        }
 
        $this->statusCode = $code;
 
        return $this;
    }
 
    public function header(string $key, string $values, bool $replace = true): static
    {
        if ($replace || !$this->headers->has($key)) {
            $this->headers->set($key, $values);
        }
 
        return $this;
    }
 
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $key => $value) {
            $this->headers->set((string) $key, $value);
        }
 
        return $this;
    }
 
    public function withException(\Throwable $e): static
    {
        $this->exception = $e;
 
        return $this;
    }
 
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
 
    public function isOk(): bool
    {
        return $this->statusCode === 200;
    }
 
    public function isRedirection(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }
 
    public function isRedirect(?string $location = null): bool
    {
        return in_array($this->statusCode, [201, 301, 302, 303, 307, 308], true)
            && ($location === null || $location === $this->headers->get('Location'));
    }
 
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }
 
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }
 
    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }
 
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }
 
    public function isEmpty(): bool
    {
        return in_array($this->statusCode, [204, 304], true);
    }
 
    public function send(): static
    {
        echo $this->content;
 
        return $this;
    }
 
    public function __toString(): string
    {
        return $this->content;
    }
}

?>
