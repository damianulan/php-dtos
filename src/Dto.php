<?php

namespace DTOs;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;


abstract class Dto implements Countable, IteratorAggregate, Traversable
{
    /**
     * The array of booted instances.
     *
     * @var array
     */
    protected static $booted = [];

    protected static $forbidsOverrides = false;

    protected static $preventsAccessingMissingAttributes = false;

    protected static $isReadOnly = false;

    protected $attributes = [];

    protected $fillable = [];

    private $original = [];

    private $dirty = [];

    private $initialized = false;

    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->fill($attributes);
    }

    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function &__get(string $key)
    {
        return $this->getAttribute($key);
    }

    public function __isset(string $key)
    {
        return $this->hasAttribute($key);
    }

    public function __unset(string $key): void
    {
        if ($this->hasAttribute($key)) {
            unset($this->attributes[$key]);
        }
    }

    public function fill(array $attributes = []): static
    {
        foreach ($attributes as $property => $value) {
            $this->setAttribute($property, $value);
        }

        $this->initialize();

        return $this;
    }

    public function setAttribute($key, $value): void
    {
        $property = DtoProperty::make($key, $value);
        $this->validateSetAttribute($property);

        if ($this->hasAttribute($key)) {
            if ($this->reassureBeingDirty($property)) {
                $this->dirty[$key] = $property->value;
            }
        } else {
            $this->original[$key] = $property->value;
        }
        $this->attributes[$key] = $property->value;
    }

    public function getAttribute($key)
    {
        $this->validateGetAttribute($key);

        if ($this->hasAttribute($key)) {
            return $this->attributes[$key];
        }

        return null;
    }

    public function hasAttribute(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getOriginal(?string $key = null)
    {
        if (null !== $key) {
            return $this->original[$key] ?? null;
        }

        return $this->original;
    }

    public function getDirty(?string $key = null)
    {
        if (null !== $key) {
            return $this->dirty[$key] ?? null;
        }

        return $this->dirty;
    }

    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function syncOriginal()
    {
        $this->original = $this->getAttributes();

        return $this;
    }

    public function getFillable(): array
    {
        return $this->fillable;
    }

    public function toJson($options = 0)
    {
        return json_encode($this->attributes, $options);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->attributes);
    }

    public function count(): int
    {
        return count($this->attributes);
    }

    /**
     * Gets all attributes.
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Gets all attributes.
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function isEmpty(): bool
    {
        return empty($this->attributes);
    }

    public function isFilled(): bool
    {
        return ! empty($this->attributes);
    }

    protected function reassureBeingDirty(DtoProperty $property): bool
    {
        return $this->getOriginal($property->name) !== $property->value;
    }

    // Tweaks and validations

    protected function validateSetAttribute(DtoProperty $property): void
    {
        if (static::$isReadOnly && $this->isInitialized()) {
            throw new Exceptions\DtoReadOnlyException($property->name);
        }
        if ( ! empty($this->fillable) && ! in_array($property->name, $this->fillable)) {
            throw new Exceptions\DtoPropertyNonFillable($property->name);
        }
        if (static::$forbidsOverrides) {
            if ($this->hasAttribute($property->name)) {
                throw new Exceptions\DtoPropertyOverride($property->name);
            }
        }
    }

    protected function validateGetAttribute($property): void
    {
        if (static::$preventsAccessingMissingAttributes) {
            if ( ! $this->hasAttribute($property)) {
                throw new Exceptions\DtoInvalidAttribute($property);
            }
        }
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    protected function initialize(): void
    {
        if ( ! $this->isInitialized()) {
            $this->syncOriginal();
            $this->initialized = true;
        }
    }

    protected function isBooted(): bool
    {
        return isset(static::$booted[static::class]);
    }

    protected function bootIfNotBooted()
    {
        if (! $this->isBooted()) {
            static::$booted[static::class] = true;

            static::booting();
            static::boot();
            static::booted();
            $this->syncOriginal();
        }
    }

    protected static function booting()
    {
        static::initializeOptions();
    }

    protected static function boot()
    {
        //
    }

    protected static function booted()
    {
        //
    }

    protected static function initializeOptions(): void
    {
        $ref = new \ReflectionClass(static::class);
        static::forbidOverrides($ref->implementsInterface(Workshop\ForbidOverrides::class));
        static::preventAccessingMissingAttributes($ref->implementsInterface(Workshop\PreventAccessingMissingAttributes::class));
        static::shouldBeReadOnly($ref->implementsInterface(Workshop\ReadOnlyAttributes::class));
    }

    public static function forbidOverrides(bool $value): void
    {
        static::$forbidsOverrides = $value;
    }

    public static function preventAccessingMissingAttributes(bool $value): void
    {
        static::$preventsAccessingMissingAttributes = $value;
    }

    public static function shouldBeReadOnly(bool $value): void
    {
        static::$isReadOnly = $value;
    }

}
