<?php
 
declare(strict_types=1);
 
namespace LaravelFaked\Helpers;
 
/**
 * Minimal stand-ins for Illuminate\Support\Arr / Str / data_get so the fakes
 * have zero dependency on 'laravel/framework'.
 */
final class Support
{
    public static function value(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
 
    /* @HINT: dot-notation read over arrays, ArrayAccess and plain objects (like data_get()). */
    public static function dataGet(mixed $target, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $target;
        }
 
        if (is_array($target) && array_key_exists($key, $target)) {
            return $target[$key];
        }
 
        foreach (explode('.', (string) $key) as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif ($target instanceof \ArrayAccess && $target->offsetExists($segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return self::value($default);
            }
        }
 
        return $target;
    }
 
    /* @HINT: dot-notation write (like Arr::set()). */
    public static function arraySet(array &$array, string|int $key, mixed $value): void
    {
        $segments = explode('.', (string) $key);
        $current = &$array;
 
        while (count($segments) > 1) {
            $segment = array_shift($segments);
 
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
 
            $current = &$current[$segment];
        }
 
        $current[array_shift($segments)] = $value;
    }
 
    /* @HINT: dot-notation existence check (like Arr::has() for a single key). */
    public static function arrayHas(array $array, string|int $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }
 
        $missing = new \stdClass();
 
        return self::dataGet($array, $key, $missing) !== $missing;
    }
 
    /* @HINT: dot-notation removal (like Arr::forget() for a single key). */
    public static function arrayForget(array &$array, string|int $key): void
    {
        if (array_key_exists($key, $array)) {
            unset($array[$key]);
 
            return;
        }
 
        $segments = explode('.', (string) $key);
        $last = array_pop($segments);
        $current = &$array;
 
        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }
 
            $current = &$current[$segment];
        }
 
        unset($current[$last]);
    }
 
    /* @HINT: wildcard match (like Str::is()). */
    public static function strIs(string $pattern, string $value): bool
    {
        if ($pattern === $value) {
            return true;
        }
 
        $regex = str_replace('\*', '.*', preg_quote($pattern, '#'));
 
        return (bool) preg_match('#^' . $regex . '\z#u', $value);
    }
 
    public static function classBasename(string|object $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
 
        return basename(str_replace('\\', '/', $class));
    }
 
    public static function snake(string $value): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
 
    /* @HINT: Naive pluralisation, good enough for default table names. */
    public static function pluralSnake(string $value): string
    {
        $snake = self::snake($value);
 
        return match (true) {
            str_ends_with($snake, 'y') && !preg_match('/[aeiou]y$/', $snake) => substr($snake, 0, -1) . 'ies',
            (bool) preg_match('/(s|x|z|ch|sh)$/', $snake) => $snake . 'es',
            default => $snake . 's',
        };
    }

    /* @HINT: UUID-shaped id. Not as random yet will suffice just fine for tests. */
    public static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
 
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
 
    /* @HINT: CUID2-shaped id (lowercase letter + base36). Not collision-hardened; fine for tests. */
    public static function cuid2(int $length = 24): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $id = $alphabet[random_int(0, 25)];
 
        for ($i = 1; $i < $length; $i++) {
            $id .= $alphabet[random_int(0, 35)];
        }
 
        return $id;
    }
 
    /* @INFO: reports with a normal assertion failure upon invariant condition check. */
    public static function invariant(bool $condition, string $message): never
    {
        if (!$condition) {
            throw new \AssertionError($message);
        }
    }
 
    /* @INFO: Counts a passed assertion in PHPUnit (so tests using only fake assertions aren't "risky"). */
    public static function pass(): void
    {
        if (class_exists(\PHPUnit\Framework\Assert::class)) {
            \PHPUnit\Framework\Assert::assertTrue(true);
        }
    }
}

?>
