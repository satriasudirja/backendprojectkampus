<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CaptchaPuzzle;

class CaptchaPuzzleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Default 3x3 puzzles
        $puzzles = [
            [
                'name' => 'puzzle1',
                'image_path' => 'puzzles/puzzle1.jpg',
                'grid_size' => 3,
                'solution' => json_encode([1, 2, 3, 4, 5, 6, 7, 8, 9]),
                'active' => true
            ],
            [
                'name' => 'puzzle2',
                'image_path' => 'puzzles/puzzle2.jpg',
                'grid_size' => 3,
                'solution' => json_encode([1, 2, 3, 4, 5, 6, 7, 8, 9]),
                'active' => true
            ],
            [
                'name' => 'puzzle3',
                'image_path' => 'puzzles/puzzle3.jpg',
                'grid_size' => 3,
                'solution' => json_encode([1, 2, 3, 4, 5, 6, 7, 8, 9]),
                'active' => true
            ],
            [
                'name' => 'puzzle4',
                'image_path' => 'puzzles/puzzle4.jpg',
                'grid_size' => 3,
                'solution' => json_encode([1, 2, 3, 4, 5, 6, 7, 8, 9]),
                'active' => true
            ],
            [
                'name' => 'puzzle5',
                'image_path' => 'puzzles/puzzle5.jpg',
                'grid_size' => 3,
                'solution' => json_encode([1, 2, 3, 4, 5, 6, 7, 8, 9]),
                'active' => true
            ],
        ];

        foreach ($puzzles as $puzzle) {
            CaptchaPuzzle::updateOrCreate(
                ['name' => $puzzle['name']], // Check if a puzzle with this name exists
                $puzzle // Update or create with this data
            );
        }
    }
}