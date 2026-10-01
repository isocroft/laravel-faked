<?php

declare(strict_types=1);

namespace LaravelFaked\Http\Lifecycle\FakeJsonResponse;

use LaravelFaked\Http\Lifecycle\Concerns\FakeResponse;

/** Stand-in for Illuminate\Http\JsonResponse. */
class FakeJsonResponse extends FakeResponse
{
    protected string $data = '{}';
 
    public function __construct(mixed $data = null, int $status = 200, array $headers = [], protected int $encodingOptions = 0)
    {
        parent::__construct('', $status, $headers);
        $this->setData($data ?? new \ArrayObject());
    }
 
    public function setData(mixed $data = []): static
    {
        $this->original = $data;
        $flags = $this->encodingOptions | JSON_THROW_ON_ERROR;
 
        $this->data = match (true) {
            is_object($data) && method_exists($data, 'toJson') => $data->toJson($this->encodingOptions),
            $data instanceof \JsonSerializable => json_encode($data->jsonSerialize(), $flags),
            is_object($data) && method_exists($data, 'toArray') => json_encode($data->toArray(), $flags),
            default => json_encode($data, $flags),
        };
 
        $this->headers->set('Content-Type', 'application/json');
        $this->content = $this->data;
 
        return $this;
    }
 
    public function getData(bool $assoc = false, int $depth = 512): mixed
    {
        return json_decode($this->data, $assoc, $depth);
    }
}

?>
