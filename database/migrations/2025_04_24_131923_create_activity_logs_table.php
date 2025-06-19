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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('simpeg_pegawai')->nullable();
            $table->string('event'); // create, update, delete
            $table->string('model_type'); // Nama model yang diubah
            $table->unsignedBigInteger('model_id'); // ID data model-nya
            $table->json('changes')->nullable(); // Perubahan
            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
