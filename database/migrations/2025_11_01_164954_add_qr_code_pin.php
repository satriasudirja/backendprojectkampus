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
            // Add PIN code fields
            $table->string('qr_pin_code', 8)->nullable()->after('qr_code_token')
                ->comment('6-8 digit numeric code as QR alternative');
            $table->timestamp('qr_pin_expires_at')->nullable()->after('qr_pin_code')
                ->comment('PIN expiration timestamp (optional)');
            $table->boolean('qr_pin_enabled')->default(true)->after('qr_pin_expires_at')
                ->comment('Enable/disable PIN code access');
            
            // Add index for faster PIN lookup
            $table->index('qr_pin_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_setting_kehadiran', function (Blueprint $table) {
            $table->dropIndex(['qr_pin_code']);
            $table->dropColumn(['qr_pin_code', 'qr_pin_expires_at', 'qr_pin_enabled']);
        });
    }
};
