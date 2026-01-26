# PHP DTOs

[![Static Badge](https://img.shields.io/badge/made_with-Laravel-red?style=for-the-badge)](https://laravel.com/docs/11.x/releases) &nbsp; [![Licence](https://img.shields.io/github/license/Ileriayo/markdown-badges?style=for-the-badge)](./LICENSE) &nbsp; [![Static Badge](https://img.shields.io/badge/maintainer-damianulan-blue?style=for-the-badge)](https://damianulan.me)

## Description

Provides a template for creating robust and efficient data-transfer-objects (DTOs) in your PHP projects.
Initialization steps look very similar to the ones in Laravel's Eloquent models, regarding objects are based on attributes, which are accessible as propertiesm and DTO objects are bootable.

## Installation

You can install the package via composer in your laravel project:
```
composer require damianulan/php-dtos
```

## Usage
Standard DTO object looks like this:
```php
use DTOs\Dto;

class UserDto extends Dto
{
    // here you can define default values to your attributes
    protected $attributes = [
        'name' => 'Damian',
        'email' => 'damian.ulan@protonmail.com',

    ];

    // here you can define attributes that are assignable outside of constructor
    // then assigning anything outside of this list will throw an exception
    protected $fillable = ['name', 'email'];


    /**
     * Here contain all the logic you need.
    **/
}
```

Creating and basics on DTO instance:
```php
$dto = new UserDto();

// or with attributes assigned.
// these will be assigned to the object before its initialization
$dto = new UserDto([
    'name' => 'Damian',
    'email' => 'damian.ulan@protonmail.com',
]);

// you can retrieve attributes changes as:
$dto->name = 'Alexander';
$dto->getDirty('name'); // returns 'Alexander'
$dto->getOriginal('name'); // returns 'Damian'

// those will return all attributes as an assoc array
$dto->all();
$dto->toArray();

// check if attributes are empty/filled
$dto->isEmpty();
$dto->isFilled();

// or check if a specific attribute is assigned
$dto->hasAttribute('name');
$dto->hasAttribute('email');
```
### Additional Options
In your DTO class you can implement additional options, that will add custom behavior to your object.

```php
use DTOs\Workshop\ReadOnlyAttributes;

/**
 * This interface will prevent overriding attributes over those previously assigned.
 */
class UserDto extends Dto implements ReadOnlyAttributes
{
    //
}
```
Available options:
- `ReadOnlyAttributes` - prevents reassignment of attributes after object construction.
- `ForbidOverrides` - prevents overriding attributes over those already assigned.
- `PreventAccessingMissingAttributes` - throws exception on accessing uninitialized attributes.

## Contact & Contributing

Any question You can submit to **damian.ulan@protonmail.com**.
