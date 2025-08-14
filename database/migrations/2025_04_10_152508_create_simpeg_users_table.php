<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('simpeg_users', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Relasi ke pegawai table
            $table->foreignUuid('pegawai_id')
                  ->unique() // Memastiksan 1 pegawai hanya punya 1 akun
                  ->constrained('simpeg_pegawai')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            
                  
            $table->string('username', 50); 
            $table->string('password', 100); 
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_users');
    }
};
