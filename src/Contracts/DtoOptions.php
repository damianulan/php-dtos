<?php

namespace DTOs\Contracts;

use DTOs\Workshop\ForbidsOverrides;
use DTOs\Workshop\IgnoresUnknownAttributes;
use DTOs\Workshop\ReadOnlyAttributes;
use ReflectionClass;

trait DtoOptions
{
    private $options = array(
        'forbids_overrides' => false,
        'ignores_unknown' => false,
        'read_only' => false,
    );

    public function setOptions(array $options): static
    {
        foreach ($options as $key => $value) {
            if (isset($this->options[$key])) {
                $this->options[$key] = (bool) $value;
            }
        }

        return $this;
    }

    protected function initializeDtoOptions(): void
    {
        $ref = new ReflectionClass(static::class);

        $this->options = array(
            'forbids_overrides' => $ref->implementsInterface(ForbidsOverrides::class),
            'ignores_unknown' => $ref->implementsInterface(IgnoresUnknownAttributes::class),
            'read_only' => $ref->implementsInterface(ReadOnlyAttributes::class),
        );
    }

    protected function option(string $key)
    {
        return $this->options[$key] ?? false;
    }
}
