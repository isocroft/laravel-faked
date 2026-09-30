<?php

declare(strict_types=1);

namespace Core\Foundation;
 
/**
 * Tiny container standing in for Illuminate\Foundation\Application.
 * Supports bind / singleton / instance / alias / make with basic constructor autowiring.
 */
class FakeApplication implements \ArrayAccess
{
    protected static ?self $instance = null;
 
    /** @var array<string, array{concrete: \Closure|string, shared: bool}> */
    protected array $bindings = [];
 
    /** @var array<string, mixed> */
    protected array $instances = [];
 
    /** @var array<string, string> alias => abstract */
    protected array $aliases = [];

    /** @var string */
    protected string $environment = '';
 
    public function __construct()
    {
      $this->environment = 'testing';
    }
 
    public static function getInstance(): static
    {
        return static::$instance ??= new static();
    }
 
    public static function setInstance(?self $app = null): ?self
    {
        return static::$instance = $app;
    }
 
    public function bind(string $abstract, \Closure|string|null $concrete = null, bool $shared = false): void
    {
        $abstract = $this->getAlias($abstract);
        unset($this->instances[$abstract]);
 
        $this->bindings[$abstract] = ['concrete' => $concrete ?? $abstract, 'shared' => $shared];
    }
 
    public function singleton(string $abstract, \Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }
 
    public function instance(string $abstract, mixed $instance): mixed
    {
        $this->instances[$this->getAlias($abstract)] = $instance;
 
        return $instance;
    }
 
    public function alias(string $abstract, string $alias): void
    {
        if ($alias === $abstract) {
            throw new \LogicException("[{$abstract}] is aliased to itself.");
        }
 
        $this->aliases[$alias] = $abstract;
    }
 
    public function getAlias(string $abstract): string
    {
        while (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }
 
        return $abstract;
    }
 
    public function bound(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);
 
        return isset($this->bindings[$abstract]) || array_key_exists($abstract, $this->instances);
    }
 
    public function has(string $id): bool
    {
        return $this->bound($id);
    }
 
    public function make(string $abstract, array $parameters = []): mixed
    {
        $abstract = $this->getAlias($abstract);
 
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }
 
        $binding = $this->bindings[$abstract] ?? null;
        $concrete = $binding['concrete'] ?? $abstract;
 
        $object = $concrete instanceof \Closure
            ? $concrete($this, $parameters)
            : $this->build($concrete, $parameters);
 
        if ($binding['shared'] ?? false) {
            $this->instances[$abstract] = $object;
        }
 
        return $object;
    }
 
    public function get(string $id): mixed
    {
        return $this->make($id);
    }
 
    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$this->getAlias($abstract)]);
    }
 
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
    }
 
    public function environment(string|array ...$environments): string|bool
    {
        if ($environments === []) {
            return $this->environment;
        }
 
        $patterns = is_array($environments[0]) ? $environments[0] : $environments;
 
        foreach ($patterns as $pattern) {
            if (Support::strIs($pattern, $this->environment)) {
                return true;
            }
        }
 
        return false;
    }
 
    public function runningUnitTests(): bool
    {
        return $this->environment === 'testing';
    }
 
    public function runningInConsole(): bool
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }
 
    protected function build(string $class, array $parameters): object
    {
        if (!class_exists($class)) {
            throw new \RuntimeException("Target [{$class}] is not bound in the fake container and is not a class.");
        }
 
        $reflector = new \ReflectionClass($class);
 
        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("Target [{$class}] is not instantiable.");
        }
 
        $constructor = $reflector->getConstructor();
 
        if ($constructor === null) {
            return new $class();
        }
 
        $arguments = [];
 
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
 
            if (array_key_exists($name, $parameters)) {
                $arguments[] = $parameters[$name];
                continue;
            }
 
            $type = $parameter->getType();
 
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                try {
                    $arguments[] = $this->make($type->getName());
                    continue;
                } catch (\RuntimeException $e) {
                    if (!$parameter->isDefaultValueAvailable()) {
                        throw $e;
                    }
                }
            }
 
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }
 
            throw new \RuntimeException("Unresolvable dependency [\${$name}] in class {$class}.");
        }
 
        return $reflector->newInstanceArgs($arguments);
    }
 
    public function offsetExists(mixed $offset): bool
    {
        return $this->bound((string) $offset);
    }
 
    public function offsetGet(mixed $offset): mixed
    {
        return $this->make((string) $offset);
    }
 
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->bind((string) $offset, $value instanceof \Closure ? $value : fn () => $value);
    }
 
    public function offsetUnset(mixed $offset): void
    {
        $abstract = $this->getAlias((string) $offset);
        unset($this->bindings[$abstract], $this->instances[$abstract]);
    }
 
    public function __get(string $key): mixed
    {
        return $this->make($key);
    }
}

?>
