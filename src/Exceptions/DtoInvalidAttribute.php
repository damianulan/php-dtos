<?php

namespace DTOs\Exceptions;

use Exception;

class DtoInvalidAttribute extends Exception
{
    public function __construct($property)
    {
        parent::__construct("Property [{$property}] was not found in this object.");
    }
}
