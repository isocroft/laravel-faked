<?php
 
declare(strict_types=1);
 
namespace Core\Component\Utility;

use Helpers\Support;
 
/** In-memory stand-in for Illuminate\Session\Store (array driver semantics, incl. flash aging). */
class FakeSessionStore
{
    protected array $attributes = [];
    protected bool $started = false;
    protected string $id;
 
    public function __construct(protected string $name = 'laravel_session', ?string $id = null)
    {
        $this->id = $id ?? $this->generateSessionId();
    }
 
    public function start(): bool
    {
        if (!$this->has('_token')) {
            $this->regenerateToken();
        }
 
        return $this->started = true;
    }
 
    /** Mimics end-of-request: ages flash data like Store::save(). */
    public function save(): void
    {
        $this->ageFlashData();
    }
 
    public function isStarted(): bool
    {
        return $this->started;
    }
 
    public function getName(): string
    {
        return $this->name;
    }
 
    public function getId(): string
    {
        return $this->id;
    }
 
    public function setId(?string $id): void
    {
        $this->id = $id !== null && ctype_alnum($id) && strlen($id) === 40 ? $id : $this->generateSessionId();
    }
 
    public function all(): array
    {
        return $this->attributes;
    }
 
    public function only(array $keys): array
    {
        $results = [];
 
        foreach ($keys as $key) {
            if (Support::arrayHas($this->attributes, $key)) {
                Support::arraySet($results, $key, Support::dataGet($this->attributes, $key));
            }
        }
 
        return $results;
    }
 
    public function except(array $keys): array
    {
        $results = $this->attributes;
 
        foreach ($keys as $key) {
            Support::arrayForget($results, $key);
        }
 
        return $results;
    }
 
    public function exists(string|array $key): bool
    {
        foreach ((array) $key as $k) {
            if (!Support::arrayHas($this->attributes, $k)) {
                return false;
            }
        }
 
        return true;
    }
 
    public function missing(string|array $key): bool
    {
        return !$this->exists($key);
    }
 
    public function has(string|array $key): bool
    {
        foreach ((array) $key as $k) {
            if ($this->get($k) === null) {
                return false;
            }
        }
 
        return true;
    }
 
    public function get(string $key, mixed $default = null): mixed
    {
        return Support::dataGet($this->attributes, $key, $default);
    }
 
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
 
        return $value;
    }
 
    public function put(string|array $key, mixed $value = null): void
    {
        foreach (is_array($key) ? $key : [$key => $value] as $k => $v) {
            Support::arraySet($this->attributes, $k, $v);
        }
    }
 
    public function replace(array $attributes): void
    {
        $this->put($attributes);
    }
 
    public function remember(string $key, \Closure $callback): mixed
    {
        if (($value = $this->get($key)) !== null) {
            return $value;
        }
 
        $this->put($key, $value = $callback());
 
        return $value;
    }
 
    public function push(string $key, mixed $value): void
    {
        $array = (array) $this->get($key, []);
        $array[] = $value;
        $this->put($key, $array);
    }
 
    public function increment(string $key, int $amount = 1): int
    {
        $this->put($key, $value = (int) $this->get($key, 0) + $amount);
 
        return $value;
    }
 
    public function decrement(string $key, int $amount = 1): int
    {
        return $this->increment($key, -$amount);
    }
 
    public function remove(string $key): mixed
    {
        return $this->pull($key);
    }
 
    public function forget(string|array $keys): void
    {
        foreach ((array) $keys as $key) {
            Support::arrayForget($this->attributes, $key);
        }
    }
 
    public function flush(): void
    {
        $this->attributes = [];
    }
 
    /* ---- flash ----------------------------------------------------------- */
 
    public function flash(string $key, mixed $value = true): void
    {
        $this->put($key, $value);
        $this->push('_flash.new', $key);
        $this->removeFromOldFlashData([$key]);
    }
 
    public function now(string $key, mixed $value): void
    {
        $this->put($key, $value);
        $this->push('_flash.old', $key);
    }
 
    public function reflash(): void
    {
        $this->mergeNewFlashes((array) $this->get('_flash.old', []));
        $this->put('_flash.old', []);
    }
 
    public function keep(array|string|null $keys = null): void
    {
        $keys = $keys === null ? (array) $this->get('_flash.old', []) : (array) $keys;
        $this->mergeNewFlashes($keys);
        $this->removeFromOldFlashData($keys);
    }
 
    public function flashInput(array $value): void
    {
        $this->flash('_old_input', $value);
    }
 
    public function hasOldInput(?string $key = null): bool
    {
        $old = $this->getOldInput($key);
 
        return $key === null ? $old !== [] : $old !== null;
    }
 
    public function getOldInput(?string $key = null, mixed $default = null): mixed
    {
        return Support::dataGet((array) $this->get('_old_input', []), $key, $default);
    }
 
    public function ageFlashData(): void
    {
        $this->forget((array) $this->get('_flash.old', []));
        $this->put('_flash.old', (array) $this->get('_flash.new', []));
        $this->put('_flash.new', []);
    }
 
    protected function mergeNewFlashes(array $keys): void
    {
        $this->put('_flash.new', array_values(array_unique(array_merge((array) $this->get('_flash.new', []), $keys))));
    }
 
    protected function removeFromOldFlashData(array $keys): void
    {
        $this->put('_flash.old', array_values(array_diff((array) $this->get('_flash.old', []), $keys)));
    }
 
    /* ---- lifecycle / tokens ---------------------------------------------- */
 
    public function invalidate(): bool
    {
        $this->flush();
 
        return $this->migrate(true);
    }
 
    public function regenerate(bool $destroy = false): bool
    {
        $this->migrate($destroy);
        $this->regenerateToken();
 
        return true;
    }
 
    public function migrate(bool $destroy = false): bool
    {
        $this->setId($this->generateSessionId());
 
        return true;
    }
 
    public function token(): ?string
    {
        return $this->get('_token');
    }
 
    public function regenerateToken(): void
    {
        $this->put('_token', bin2hex(random_bytes(20)));
    }
 
    public function previousUrl(): ?string
    {
        return $this->get('_previous.url');
    }
 
    public function setPreviousUrl(string $url): void
    {
        $this->put('_previous.url', $url);
    }
 
    protected function generateSessionId(): string
    {
        return bin2hex(random_bytes(20));
    }
}

?>
