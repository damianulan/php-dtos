<?php

namespace DTOs;

use InvalidArgumentException;
use Stringable;

class DtoProperty implements Stringable
{
    public $name;

    public $type;

    public $raw_value;

    public $value;

    public static function make($name, $value, $type = null): self
    {
        if (is_numeric($value)) {
            throw new InvalidArgumentException('DtoProperty can not be created with key being a numeric value');
        }
        if ( ! is_string($name)) {
            throw new InvalidArgumentException('DtoProperty can not be created with key being a non string value');
        }

        $instance = new self();
        $instance->name = $name;
        $instance->type = $type;
        $instance->raw_value = $value;
        $instance->setValue($value);

        return $instance;
    }

    protected function setValue($value): self
    {
        $this->value = $value;

        return $this;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
