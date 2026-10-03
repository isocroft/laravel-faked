<?php
 
declare(strict_types=1);
 
namespace LaravelFaked\Http\Lifecycle;
 
/* Stand-in for parameter bags (Symfony ParameterBag / HeaderBag look-alikes) */
class FakeParameterBag implements \IteratorAggregate, \Countable
{

    /** @var array<*> */
    protected array $parameters = [];

    public function __construct(array $parameters)
    {
      $this->parameters = $parameters;
    }
 
    public function all(?string $key = null): array
    {
        return $key === null ? $this->parameters : (array) ($this->parameters[$key] ?? []);
    }
 
    public function keys(): array
    {
        return array_keys($this->parameters);
    }
 
    public function replace(array $parameters = []): void
    {
        $this->parameters = $parameters;
    }
 
    public function add(array $parameters = []): void
    {
        $this->parameters = array_replace($this->parameters, $parameters);
    }
 
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $default;
    }
 
    public function set(string $key, mixed $value): void
    {
        $this->parameters[$key] = $value;
    }
 
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->parameters);
    }
 
    public function remove(string $key): void
    {
        unset($this->parameters[$key]);
    }
 
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->parameters);
    }
 
    public function count(): int
    {
        return count($this->parameters);
    }
}

?>
