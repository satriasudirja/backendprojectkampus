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
        Schema::create('simpeg_login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('pegawai_id')->constrained('simpeg_pegawai');
            $table->ipAddress('ip_address');
            $table->text('user_agent');
            $table->timestamp('logged_in_at')->default(now());
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_login_logs');
    }
};
