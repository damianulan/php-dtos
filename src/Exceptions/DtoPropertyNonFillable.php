<?php

namespace DTOs\Exceptions;

use Exception;

class DtoPropertyNonFillable extends Exception
{
    public function __construct($property)
    {
        parent::__construct("Property [{$property}] is not fillable, thus unable to be set.");
    }
}
