<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str; // Import Str untuk generate UUID
use Carbon\Carbon; // Import Carbon untuk timestamps

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('captcha_puzzles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('image_path');
            $table->integer('grid_size')->default(3);
            $table->text('solution'); // JSON encoded solution
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seed the table with initial puzzle data
        $this->seedPuzzles();
    }

    /**
     * Seed the puzzles table with initial data
     */
    private function seedPuzzles()
    {
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

        // PERBAIKAN: Loop melalui setiap puzzle untuk menambahkan UUID dan timestamps
        $dataToInsert = [];
        $now = Carbon::now();

        foreach ($puzzles as $puzzle) {
            $dataToInsert[] = array_merge($puzzle, [
                'id' => Str::uuid(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('captcha_puzzles')->insert($dataToInsert);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('captcha_puzzles');
    }
};
