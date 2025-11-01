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
        Schema::table('simpeg_setting_kehadiran', function (Blueprint $table) {
            //
            $table->string('qr_code_token', 100)->unique()->nullable()->after('wajib_presensi_dilokasi');
            $table->string('qr_code_path')->nullable()->after('qr_code_token');
            $table->timestamp('qr_code_generated_at')->nullable()->after('qr_code_path');
            $table->boolean('qr_code_enabled')->default(false)->after('qr_code_generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_setting_kehadiran', function (Blueprint $table) {
            //
            $table->dropColumn(['qr_code_token', 'qr_code_path', 'qr_code_generated_at', 'qr_code_enabled']);
        });
    }
};
