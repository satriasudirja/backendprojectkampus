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
        Schema::table('simpeg_users', function (Blueprint $table) {
            $table->string('device_id')->nullable()->after('password');
            //
        });
        Schema::table('simpeg_users', function (Blueprint $table) {
            $table->index('device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_users', function (Blueprint $table) {
            //
            $table->dropIndex(['device_id']);
            $table->dropColumn(['device_id']);
        });
    }
};
