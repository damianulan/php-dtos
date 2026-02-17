<?php

namespace DTOs\Exceptions;

use Exception;

class DtoReadOnlyException extends Exception
{
    public function __construct($property)
    {
        parent::__construct("Dto object is read only. Unable to set property [{$property}].");
    }
}
