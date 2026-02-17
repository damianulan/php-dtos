<?php

namespace DTOs\Factories;

use DTOs\Dto;

class DtoFactory
{
    /**
     * Make DTO Object from iterable source.
     *
     * @param iterable    $attributes
     * @param string|null $dtoClass
     * @return \DTOs\Dto
     */
    public static function make(iterable $attributes, ?string $dtoClass = null): Dto
    {
        static::validateDtoClass($dtoClass);

        $datas = [];

        if(!is_array($attributes)){
            if(is_iterable($attributes)){
                $datas = static::iterateAttributes($attributes);
            } else {
                if(is_object($attributes)){
                    $attrClass = get_class($attributes);
                    if(class_exists($attrClass)){
                        $attributes = get_object_vars($attributes);
                        $datas = static::iterateAttributes($attributes);
                    }
                }
            }
        } else {
            $datas = $attributes;
        }

        if(empty($datas)){
            throw new InvalidArgumentException("Non-empty attributes must be provided");
        }

        $dtoClass::shouldBeSilent(true);
        $dto = new $dtoClass($datas);
        $dto->shouldBeSilent(false);

        return $dto;
    }

    protected static function iterateAttributes(iterable $attributes): array
    {
        $datas = [];
        foreach($attributes as $key => $value){
            if(is_string($key) && !is_numeric($key)){
                $datas[$key] = $value;
            }
        }
        return $datas;
    }

    /**
     * Check if given class is suitable for a factory
     *
     * @param string $dtoClass
     * @return void
     */
    protected static function validateDtoClass(string $dtoClass): void
    {
        if(empty($dtoClass)){
            throw new InvalidArgumentException("Dto class must be provided");
        }

        if ( ! class_exists($dtoClass)) {
            throw new InvalidArgumentException("Dto class $dtoClass does not exist");
        }

        $reflection = new \ReflectionClass($dtoClass);
        if ( ! $reflection->isSubclassOf(Dto::class)) {
            throw new InvalidArgumentException("Dto class $dtoClass must extend DTOs\Dto");
        }

        if( ! $reflection->isInstantiable()) {
            throw new InvalidArgumentException("Dto class $dtoClass must be instantiable");
        }
    }

}
