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
             $table->bigIncrements('id');
            $table->foreignId('role_id')
            ->constrained('simpeg_users_roles')
            ->onDelete('restrict');
            $table->string('username', 50); 
            $table->string('password', 100); 
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
