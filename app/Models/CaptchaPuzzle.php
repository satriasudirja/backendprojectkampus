<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaptchaPuzzle extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'image_path',
        'grid_size',
        'solution',
        'active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'grid_size' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * Get the solution as an array
     *
     * @return array
     */
    public function getSolutionArray()
    {
        return json_decode($this->solution, true);
    }
    
    /**
     * Get a random active puzzle
     * 
     * @return CaptchaPuzzle|null
     */
    public static function getRandomPuzzle()
    {
        return self::where('active', true)
            ->inRandomOrder()
            ->first();
    }
}