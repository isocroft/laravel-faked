<?php
 
declare(strict_types=1);
 
namespace LaravelFaked\Core\Component;

use LaravelFaked\Helpers\Support;
 
/** Stand-in for Illuminate\Config\Repository. */
class FakeConfig implements \ArrayAccess
{
    /** @var array */
    protected array $items = [];
  
    public function __construct(array $items)
    {
      $this->items = $items;
    }
 
    public function has(string $key): bool
    {
        return Support::arrayHas($this->items, $key);
    }
 
    /** @param string|array<int|string, mixed> $key array = get many (["a.b" => default, ...]) */
    public function get(string|array $key, mixed $default = null): mixed
    {
        if (is_array($key)) {
            $result = [];
 
            foreach ($key as $k => $v) {
                [$k, $v] = is_int($k) ? [$v, null] : [$k, $v];
                $result[$k] = Support::dataGet($this->items, $k, $v);
            }
 
            return $result;
        }
 
        return Support::dataGet($this->items, $key, $default);
    }
 
    public function set(string|array $key, mixed $value = null): void
    {
        $keys = is_array($key) ? $key : [$key => $value];
 
        foreach ($keys as $k => $v) {
            Support::arraySet($this->items, $k, $v);
        }
    }
 
    public function push(string $key, mixed $value): void
    {
        $array = (array) $this->get($key, []);
        $array[] = $value;
        $this->set($key, $array);
    }
 
    public function all(): array
    {
        return $this->items;
    }
 
    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }
 
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }
 
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }
 
    public function offsetUnset(mixed $offset): void
    {
        $this->set((string) $offset, null);
    }
}

?>
