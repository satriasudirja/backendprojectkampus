<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('simpeg_jam_kerja', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true); // Add this too if missing
        });
    }

    public function down()
    {
        Schema::table('simpeg_jam_kerja', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'is_active']);
        });
    }
};