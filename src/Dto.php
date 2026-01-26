<?php

namespace DTOs;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Enables dynamically accessing the properties, later called attributes of a class.
 *
 * @author Damian Ułan <damian.ulan@protonmail.com>
 * @copyright 2026 damianulan
 */
abstract class Dto implements Countable, IteratorAggregate, Traversable
{
    /**
     * The array of booted instances.
     *
     * @var array
     */
    protected static $booted = [];

    /**
     * This option prevents overriding attributes over those already assigned.
     *
     * @var bool
     */
    protected static $forbidsOverrides = false;

    /**
     * This option throws exception on accessing uninitialized attributes.
     *
     * @var bool
     */
    protected static $preventsAccessingMissingAttributes = false;

    /**
     * This option prevents reassignment of attributes after object construction.
     *
     * @var bool
     */
    protected static $isReadOnly = false;

    /**
     * This option silences any exceptions and just goes through it.
     *
     * @var bool
     */
    protected static $silentMode = false;

    /**
     * List of attributes assigned to object. You can access each of them as $dto->attribute
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Contains names of attributes that are assignable within or outside a constructor.
     * Leave empty to always allow all.
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * List of attributes assigned to the object on constructor.
     *
     * @var array
     */
    private $original = [];

    /**
     * List of attributes that were reassigned after initialization.
     *
     * @var array
     */
    private $dirty = [];

    /**
     * Determines whether object's contructor finished build.
     *
     * @var bool
     */
    private $initialized = false;

    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();

        // reassing when declared as default in static class
        foreach ($this->attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        // fill with given attributes
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

    /**
     * Fill object with given attributes.
     *
     * @param array $attributes
     * @return static
     */
    public function fill(array $attributes = []): static
    {
        foreach ($attributes as $property => $value) {
            $this->setAttribute($property, $value);
        }

        $this->initialize();

        return $this;
    }

    /**
     * Safely set attribute to the object.
     *
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function setAttribute($key, $value): void
    {
        try {
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
        } catch (\Exception $e) {
            if(!static::$silentMode){
                throw $e;
            }
        }
    }

    /**
     * Safely get attribute from the object.
     *
     * @param mixed $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        try {
            $this->validateGetAttribute($key);

            if ($this->hasAttribute($key)) {
                return $this->attributes[$key];
            }
        } catch (\Exception $e) {
            if(!static::$silentMode){
                throw $e;
            }
        }

        return null;
    }

    /**
     * Check if attribute has been assigned to the object.
     *
     * @param string $key
     * @return bool
     */
    public function hasAttribute(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get attributes or an attribute on original state.
     *
     * @param string|null $key
     * @return mixed
     */
    public function getOriginal(?string $key = null)
    {
        if (null !== $key) {
            return $this->original[$key] ?? null;
        }

        return $this->original;
    }

    /**
     * Get attributes to which changes have been made after initialization.
     *
     * @param string|null $key
     * @return mixed
     */
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

    /**
     * Get whitelist of attributes that are available to be assigned
     *
     * @return array
     */
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
     *
     * @return array
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Gets all attributes.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * Check whether if none of the attributes is assigned.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty(array_filter($this->attributes, fn($value) => $value !== null));
    }

    /**
     * Check whether any attribute is assigned.
     *
     * @return bool
     */
    public function isFilled(): bool
    {
        return ! $this->isEmpty();
    }

    // Tweaks and validations

    /**
     * Determines whether reassigned attribute object differs from an original one
     *
     * @param DtoProperty $property
     * @return bool
     */
    protected function reassureBeingDirty(DtoProperty $property): bool
    {
        return $this->getOriginal($property->name) !== $property->value;
    }

    /**
     * Checks whether attribute can be assigned.
     *
     * @param DtoProperty $property
     * @return void
     */
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

    /**
     * Checks whether attribute can be retrieved.
     *
     * @param DtoProperty $property
     * @return void
     */
    protected function validateGetAttribute($property): void
    {
        if (static::$preventsAccessingMissingAttributes) {
            if ( ! $this->hasAttribute($property)) {
                throw new Exceptions\DtoInvalidAttribute($property);
            }
        }
    }

    /**
     * Checks whether object was already initialized
     * and all constructor operations are finished.
     *
     * @return bool
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Initialize object after constructor operations are finished.
     *
     * @return void
     */
    protected function initialize(): void
    {
        if ( ! $this->isInitialized()) {
            $this->syncOriginal();
            $this->initialized = true;
        }
    }

    /**
     * Check whether object instance was booted.
     *
     * @return bool
     */
    protected function isBooted(): bool
    {
        return isset(static::$booted[static::class]);
    }

    /**
     * Boot object if needs to be booted.
     *
     * @return void
     */
    protected function bootIfNotBooted(): void
    {
        if (! $this->isBooted()) {
            static::$booted[static::class] = true;

            static::booting();
            static::boot();
            static::booted();
            $this->syncOriginal();
        }
    }

    /**
     * Perform any actions required before the object boots.
     *
     * @return void
     */
    protected static function booting(): void
    {
        //
    }

    /**
     * Perform any actions required during the object boots.
     *
     * @return void
     */
    protected static function boot(): void
    {
        static::initializeOptions();
    }

    /**
     * Perform any actions required after the object boots.
     *
     * @return void
     */
    protected static function booted(): void
    {
        //
    }

    /**
     * Initialize tweaks and options assigned to the static object on its boot.
     *
     * @return void
     */
    protected static function initializeOptions(): void
    {
        $ref = new \ReflectionClass(static::class);
        static::forbidOverrides($ref->implementsInterface(Workshop\ForbidOverrides::class));
        static::preventAccessingMissingAttributes($ref->implementsInterface(Workshop\PreventAccessingMissingAttributes::class));
        static::shouldBeReadOnly($ref->implementsInterface(Workshop\ReadOnlyAttributes::class));
    }

    /**
     * Prevent overriding attributes over those already assigned.
     *
     * @param bool $value
     * @return void
     */
    public static function forbidOverrides(bool $value): void
    {
        static::$forbidsOverrides = $value;
    }

    /**
     * Throw an exception on accessing uninitialized attributes.
     *
     * @param bool $value
     * @return void
     */
    public static function preventAccessingMissingAttributes(bool $value): void
    {
        static::$preventsAccessingMissingAttributes = $value;
    }

    /**
     * Prevent reassignment of attributes after object construction.
     *
     * @param bool $value
     * @return void
     */
    public static function shouldBeReadOnly(bool $value): void
    {
        static::$isReadOnly = $value;
    }

    /**
     * Prevents any exceptions from throwing.
     *
     * @param bool $value
     * @return void
     */
    public static function shouldBeSilent(bool $value): void
    {
        static::$silentMode = $value;
    }
}
