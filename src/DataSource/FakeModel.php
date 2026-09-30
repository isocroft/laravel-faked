<?php
 
declare(strict_types=1);
 
namespace Tests\Fakes;

use LaravelFaked\Core\Foundation\FakeApplication;

use LaravelFaked\Helpers\Support;
 
class FakeModelNotFoundException extends \RuntimeException
{
    //
}
 
/**
 * Eloquent look-alike backed by a static in-memory "table" per model class.
 * Covers attributes/casts/fillable/hidden, dirty tracking, save/delete/find,
 * model events (via the fake dispatcher), serialization, and ArrayAccess.
 */
abstract class FakeModel implements \ArrayAccess, \JsonSerializable, \Stringable
{
    /** @var array<class-string, array<int|string, array>> */
    protected static array $store = [];
 
    /** @var array<class-string, int> */
    protected static array $autoIncrement = [];
 
    protected ?string $table = null;
    protected string $primaryKey = 'id';
    protected string $keyType = 'int';
    public bool $incrementing = true;
    public bool $timestamps = true;
    public bool $exists = false;
    public bool $wasRecentlyCreated = false;
 
    protected array $fillable = [];
    protected array $guarded = ['*'];
    protected array $hidden = [];
    protected array $casts = [];
    protected array $attributes = [];
    protected array $original = [];
    protected array $changes = [];
 
    /** @var array<string, callable> ad-hoc methods added in tests via ->stub() */
    protected array $methodStubs = [];
 
    public function __construct(array $attributes = [])
    {
        $this->syncOriginal();
        $this->fill($attributes);
    }
 
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable((string) $key)) {
                $this->setAttribute((string) $key, $value);
            }
        }
 
        return $this;
    }
 
    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute((string) $key, $value);
        }
 
        return $this;
    }
 
    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->fillable, true)) {
            return true;
        }
 
        if ($this->guarded === ['*'] || in_array($key, $this->guarded, true)) {
            return false;
        }
 
        return $this->fillable === [];
    }
 
    public function getFillable(): array
    {
        return $this->fillable;
    }
 
    public function getHidden(): array
    {
        return $this->hidden;
    }
 
    public function setHidden(array $hidden): static
    {
        $this->hidden = $hidden;
 
        return $this;
    }
 
    public function makeVisible(array|string $attributes): static
    {
        $this->hidden = array_values(array_diff($this->hidden, (array) $attributes));
 
        return $this;
    }
 
    public function makeHidden(array|string $attributes): static
    {
        $this->hidden = array_values(array_unique(array_merge($this->hidden, (array) $attributes)));
 
        return $this;
    }
 
    public function getCasts(): array
    {
        return $this->casts;
    }
 
    public function hasCast(string $key): bool
    {
        return array_key_exists($key, $this->casts);
    }
 
    public function getAttribute(string $key): mixed
    {
        if ($key === '' || !array_key_exists($key, $this->attributes)) {
            return null;
        }
 
        return $this->castAttribute($key, $this->attributes[$key]);
    }
 
    public function setAttribute(string $key, mixed $value): static
    {
        if ($value !== null) {
            $value = match ($this->casts[$key] ?? null) {
                'hashed' => $this->hashValue((string) $value),
                'array', 'json', 'object' => is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR),
                'datetime', 'immutable_datetime', 'date' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value,
                default => $value,
            };
        }
 
        $this->attributes[$key] = $value;
 
        return $this;
    }
 
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
 
        return match ($this->casts[$key] ?? null) {
            'int', 'integer' => (int) $value,
            'float', 'double', 'real' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => json_decode((string) $value, true),
            'object' => json_decode((string) $value),
            'datetime', 'immutable_datetime' => new \DateTimeImmutable((string) $value),
            'date' => (new \DateTimeImmutable((string) $value))->setTime(0, 0),
            default => $value,
        };
    }
 
    protected function hashValue(string $value): string
    {
        // Already hashed? keep it (same behaviour as the 'hashed' cast).
        if ((password_get_info($value)['algoName'] ?? 'unknown') !== 'unknown') {
            return $value;
        }
 
        return password_hash($value, PASSWORD_BCRYPT, ['cost' => 4]); // low cost = fast tests
    }
 
    public function getAttributes(): array
    {
        return $this->attributes;
    }
 
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;
 
        if ($sync) {
            $this->syncOriginal();
        }
 
        return $this;
    }
 
    public function getOriginal(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->original;
        }
 
        return array_key_exists($key, $this->original) ? $this->castAttribute($key, $this->original[$key]) : $default;
    }
 
    public function syncOriginal(): static
    {
        $this->original = $this->attributes;
 
        return $this;
    }
 
    public function getDirty(): array
    {
        $dirty = [];
 
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
 
        return $dirty;
    }
 
    public function isDirty(array|string|null $attributes = null): bool
    {
        $dirty = $this->getDirty();
 
        if ($attributes === null) {
            return $dirty !== [];
        }
 
        foreach ((array) $attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }
 
        return false;
    }
 
    public function isClean(array|string|null $attributes = null): bool
    {
        return !$this->isDirty($attributes);
    }
 
    public function getChanges(): array
    {
        return $this->changes;
    }
 
    public function wasChanged(array|string|null $attributes = null): bool
    {
        if ($attributes === null) {
            return $this->changes !== [];
        }
 
        foreach ((array) $attributes as $attribute) {
            if (array_key_exists($attribute, $this->changes)) {
                return true;
            }
        }
 
        return false;
    }
 
    /* ---- keys / table ---------------------------------------------------- */
 
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }
 
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }
 
    public function setKeyName(string $key): static
    {
        $this->primaryKey = $key;
 
        return $this;
    }
 
    public function getKeyType(): string
    {
        return $this->keyType;
    }
 
    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }
 
    public function setIncrementing(bool $value): static
    {
        $this->incrementing = $value;
 
        return $this;
    }
 
    public function getTable(): string
    {
        return $this->table ?? Support::pluralSnake(Support::classBasename($this));
    }
 
    public function setTable(string $table): static
    {
        $this->table = $table;
 
        return $this;
    }
 
    public function getQualifiedKeyName(): string
    {
        return $this->getTable() . '.' . $this->getKeyName();
    }
 
    public function getRouteKey(): mixed
    {
        return $this->getAttribute($this->getRouteKeyName());
    }
 
    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }
 
    public function getForeignKey(): string
    {
        return Support::snake(Support::classBasename($this)) . '_' . $this->getKeyName();
    }
 
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }
 
    /* ---- persistence (in-memory) ----------------------------------------- */
 
    public function save(array $options = []): bool
    {
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }
 
        if ($this->exists) {
            if (!$this->isDirty()) {
                $this->fireModelEvent('saved', false);
 
                return true;
            }
 
            if ($this->fireModelEvent('updating') === false) {
                return false;
            }
 
            if ($this->timestamps) {
                $this->attributes['updated_at'] = $this->freshTimestamp();
            }
 
            $this->persist();
            $this->fireModelEvent('updated', false);
        } else {
            if ($this->fireModelEvent('creating') === false) {
                return false;
            }
 
            $this->creating();
 
            if ($this->timestamps) {
                $now = $this->freshTimestamp();
                $this->attributes['created_at'] ??= $now;
                $this->attributes['updated_at'] = $now;
            }
 
            if (($this->attributes[$this->getKeyName()] ?? null) === null) {
                $this->attributes[$this->getKeyName()] = $this->newKey();
            }
 
            $this->persist();
            $this->exists = true;
            $this->wasRecentlyCreated = true;
            $this->fireModelEvent('created', false);
        }
 
        $this->changes = $this->getDirty();
        $this->syncOriginal();
        $this->fireModelEvent('saved', false);
 
        return true;
    }
 
    public function update(array $attributes = []): bool
    {
        return $this->exists && $this->fill($attributes)->save();
    }
 
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }
 
        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }
 
        unset(static::$store[static::class][$this->attributes[$this->getKeyName()]]);
        $this->exists = false;
        $this->fireModelEvent('deleted', false);
 
        return true;
    }
 
    public function refresh(): static
    {
        $row = static::$store[static::class][$this->getKey()] ?? null;
 
        if ($row !== null) {
            $this->setRawAttributes($row, true);
        }
 
        return $this;
    }
 
    public function fresh(): ?static
    {
        return $this->exists ? static::find($this->getKey()) : null;
    }
 
    /** Hook for subclasses; runs right before an insert. */
    protected function creating(): void
    {
        //
    }
 
    protected function persist(): void
    {
        static::$store[static::class][$this->attributes[$this->getKeyName()]] = $this->attributes;
    }
 
    protected function newKey(): int|string
    {
        if ($this->incrementing) {
            return static::$autoIncrement[static::class] = (static::$autoIncrement[static::class] ?? 0) + 1;
        }
 
        return Support::uuid4();
    }
 
    protected function freshTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }
 
    /* @HINT: Fires "eloquent.{event}: {Class}" through the fake dispatcher when one is bound. */
    protected function fireModelEvent(string $event, bool $halt = true): mixed
    {
        $app = FakeApplication::getInstance();
 
        if (!$app->bound('events')) {
            return true;
        }
 
        $name = "eloquent.{$event}: " . static::class;
 
        return $halt ? $app->make('events')->until($name, [$this]) : $app->make('events')->dispatch($name, [$this]);
    }
 
    /* @INFO: query helpers */
 
    public static function make(array $attributes = []): static
    {
        return new static($attributes);
    }
 
    public static function create(array $attributes = []): static
    {
        $model = new static($attributes);
        $model->save();
 
        return $model;
    }
 
    public static function forceCreate(array $attributes = []): static
    {
        $model = (new static())->forceFill($attributes);
        $model->save();
 
        return $model;
    }
 
    public static function find(mixed $id): ?static
    {
        if (!is_int($id) && !is_string($id)) {
            return null;
        }
 
        $row = static::$store[static::class][$id] ?? null;
 
        return $row === null ? null : static::newFromStore($row);
    }
 
    public static function findOrFail(mixed $id): static
    {
        return static::find($id)
            ?? throw new FakeModelNotFoundException('No query results for model [' . static::class . '] ' . (is_scalar($id) ? $id : ''));
    }
 
    /** @return list<static> */
    public static function all(): array
    {
        return array_values(array_map(fn (array $row) => static::newFromStore($row), static::$store[static::class] ?? []));
    }
 
    /** @return list<static> */
    public static function where(string $column, mixed $value): array
    {
        return array_values(array_filter(static::all(), fn (FakeModel $m) => $m->getAttribute($column) == $value));
    }
 
    public static function firstWhere(string $column, mixed $value): ?static
    {
        return static::where($column, $value)[0] ?? null;
    }
 
    public static function count(): int
    {
        return count(static::$store[static::class] ?? []);
    }
 
    public static function newFromStore(array $attributes): static
    {
        $model = new static();
        $model->setRawAttributes($attributes, true);
        $model->exists = true;
 
        return $model;
    }
 
    /** Clear the in-memory tables (all models, or one class). Call in setUp()/tearDown(). */
    public static function flushStore(?string $class = null): void
    {
        if ($class === null) {
            static::$store = [];
            static::$autoIncrement = [];
 
            return;
        }
 
        unset(static::$store[$class], static::$autoIncrement[$class]);
    }
 
    /* ---- comparison ------------------------------------------------------ */
 
    public function is(?FakeModel $model): bool
    {
        return $model !== null
            && $this->getKey() === $model->getKey()
            && $this->getTable() === $model->getTable();
    }
 
    public function isNot(?FakeModel $model): bool
    {
        return !$this->is($model);
    }
 
    /* ---- serialization --------------------------------------------------- */
 
    public function toArray(): array
    {
        $array = [];
 
        foreach ($this->attributes as $key => $raw) {
            if (in_array($key, $this->hidden, true)) {
                continue;
            }
 
            $value = $this->castAttribute((string) $key, $raw);
 
            if ($value instanceof \DateTimeInterface) {
                $value = \DateTimeImmutable::createFromInterface($value)
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format('Y-m-d\TH:i:s.u\Z');
            }
 
            $array[$key] = $value;
        }
 
        return $array;
    }
 
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
    }
 
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
 
    public function __toString(): string
    {
        return $this->toJson();
    }
 
    /* ---- magic ----------------------------------------------------------- */
 
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }
 
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }
 
    public function __isset(string $key): bool
    {
        return $this->getAttribute($key) !== null;
    }
 
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }
 
    public function offsetExists(mixed $offset): bool
    {
        return $this->getAttribute((string) $offset) !== null;
    }
 
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute((string) $offset);
    }
 
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute((string) $offset, $value);
    }
 
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[(string) $offset]);
    }
 
    /**
     * Add an ad-hoc method for a test, e.g. $user->stub('hasRole', fn ($role) => $role === 'admin').
     * Closures are bound to the model, so $this works inside them.
     * (To override an existing method, use an anonymous subclass instead.)
     */
    public function stub(string $method, callable $implementation): static
    {
        $this->methodStubs[$method] = $implementation;
 
        return $this;
    }
 
    public function __call(string $method, array $parameters): mixed
    {
        if (isset($this->methodStubs[$method])) {
            $stub = $this->methodStubs[$method];
 
            if ($stub instanceof \Closure) {
                $stub = \Closure::bind($stub, $this, static::class) ?? $stub;
            }
 
            return $stub(...$parameters);
        }
 
        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
    }
}

?>
