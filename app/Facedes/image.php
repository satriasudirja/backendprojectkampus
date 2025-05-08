<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Intervention\Image\ImageManager imageManager()
 * @method static \Intervention\Image\Interfaces\ImageInterface make(mixed $source)
 * @method static \Intervention\Image\Interfaces\ImageInterface read(mixed $source)
 * @method static \Intervention\Image\Interfaces\ImageInterface create(int $width, int $height)
 * @method static \Intervention\Image\Interfaces\ImageInterface canvas(int $width, int $height)
 * 
 * @see \Intervention\Image\ImageManager
 */
class Image extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'image';
    }
}