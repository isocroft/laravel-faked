<?php

namespace LaravelFaked\Http\Lifecycle;

/** Case-insensitive header bag; "Content_Type" === "content-type". Stores one value per header. */
class FakeHeaderBag extends FakeParameterBag
{
    public function __construct(array $headers = [])
    {
        parent::__construct([]);
 
        foreach ($headers as $key => $value) {
            $this->set((string) $key, $value);
        }
    }
 
    public function get(string $key, mixed $default = null): mixed
    {
        $value = parent::get($this->normalize($key), $default);
 
        return is_array($value) ? ($value[0] ?? $default) : $value;
    }
 
    public function set(string $key, mixed $value): void
    {
        parent::set($this->normalize($key), $value);
    }
 
    public function has(string $key): bool
    {
        return parent::has($this->normalize($key));
    }
 
    public function remove(string $key): void
    {
        parent::remove($this->normalize($key));
    }
 
    protected function normalize(string $key): string
    {
        return strtr(strtolower($key), '_', '-');
    }
}

?>
