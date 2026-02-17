<?php

namespace DTOs;

use ArrayIterator;
use BadMethodCallException;
use Countable;
use Illuminate\Support\Str;
use IteratorAggregate;
use ReflectionClass;
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

        // reassign when declared as default in static class
        foreach ($this->attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        // fill with given attributes
        $this->fill($attributes);
    }

    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
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

    public function __call($name, $arguments)
    {
        if(Str::startsWith($name, 'get')){
            $attribute = Str::after($name, 'get');
            return $this->getAttribute($attribute);
        } elseif (Str::startsWith($name, 'set')){
            $attribute = Str::after($name, 'set');
            return $this->setAttribute($attribute, $arguments[0]);
        }

        throw new BadMethodCallException('Method does not exist');
    }

    /**
     * Prevent overriding attributes over those already assigned.
     */
    public static function forbidOverrides(bool $value): void
    {
        static::$forbidsOverrides = $value;
    }

    /**
     * Throw an exception on accessing uninitialized attributes.
     */
    public static function preventAccessingMissingAttributes(bool $value): void
    {
        static::$preventsAccessingMissingAttributes = $value;
    }

    /**
     * Prevent reassignment of attributes after object construction.
     */
    public static function shouldBeReadOnly(bool $value): void
    {
        static::$isReadOnly = $value;
    }

    /**
     * Fill object with given attributes.
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
     * @param  mixed  $key
     * @param  mixed  $value
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
     * @param  mixed  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        try {
            $key = Str::snake($key);
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
     */
    public function hasAttribute(string $key): bool
    {
        $key = Str::snake($key);
        return isset($this->attributes[$key]);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get attributes or an attribute on original state.
     *
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
        return $this->all();
    }

    /**
     * Check whether if none of the attributes is assigned.
     */
    public function isEmpty(): bool
    {
        return empty(array_filter($this->attributes, fn ($value) => null !== $value));
    }

    /**
     * Check whether any attribute is assigned.
     */
    public function isFilled(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Checks whether object was already initialized
     * and all constructor operations are finished.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Perform any actions required before the object boots.
     */
    protected static function booting(): void {}

    /**
     * Perform any actions required during the object boots.
     */
    protected static function boot(): void
    {
        static::initializeOptions();
    }

    /**
     * Perform any actions required after the object boots.
     */
    protected static function booted(): void {}

    /**
     * Initialize tweaks and options assigned to the static object on its boot.
     */
    protected static function initializeOptions(): void
    {
        $ref = new ReflectionClass(static::class);
        static::forbidOverrides($ref->implementsInterface(Workshop\ForbidOverrides::class));
        static::preventAccessingMissingAttributes($ref->implementsInterface(Workshop\PreventAccessingMissingAttributes::class));
        static::shouldBeReadOnly($ref->implementsInterface(Workshop\ReadOnlyAttributes::class));
    }

    // Tweaks and validations

    /**
     * Determines whether reassigned attribute object differs from an original one
     */
    protected function reassureBeingDirty(DtoProperty $property): bool
    {
        return $this->getOriginal($property->name) !== $property->value;
    }

    /**
     * Checks whether attribute can be assigned.
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
     * @param  DtoProperty  $property
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
     * Initialize object after constructor operations are finished.
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
     */
    protected function isBooted(): bool
    {
        return isset(static::$booted[static::class]);
    }

    /**
     * Boot object if needs to be booted.
     */
    protected function bootIfNotBooted(): void
    {
        if ( ! $this->isBooted()) {
            static::$booted[static::class] = true;

            static::booting();
            static::boot();
            static::booted();
            $this->syncOriginal();
        }
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
