<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Intervention\Image\Image make(mixed $data)
 * @method static \Intervention\Image\Image canvas(int $width, int $height, mixed $background = null)
 * @method static \Intervention\Image\Image cache(\Closure $callback, int $lifetime = null, bool $returnObj = false)
 * 
 * @see \Intervention\Image\ImageManager
 */
class Image extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'image';
    }
}