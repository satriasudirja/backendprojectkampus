<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {
        Schema::table('simpeg_jabatan_akademik', function (Blueprint $table) {
            // Foreign key untuk role_id
            $table->foreign('role_id')
                ->references('id')->on('simpeg_users_roles')
                ->onDelete('restrict');
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('simpeg_jabatan_akademik', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });
    }
};