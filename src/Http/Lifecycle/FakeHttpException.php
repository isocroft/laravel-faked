<?php

declare(strict_types=1);

namespace LaravelFaked\Http\Lifecycle;

use LaravelFaked\Http\Lifecycle\Concerns\FakeResponse;

/** Stand-in for Symfony HttpException / Laravel HttpResponseException, thrown by abort(). */
class FakeHttpException extends \RuntimeException
{
    public function __construct(
        protected int $statusCode,
        string $message = '',
        protected array $headers = [],
        protected ?FakeResponse $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
 
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
 
    public function getHeaders(): array
    {
        return $this->headers;
    }
 
    public function getResponse(): ?FakeResponse
    {
        return $this->response;
    }
}

?>
