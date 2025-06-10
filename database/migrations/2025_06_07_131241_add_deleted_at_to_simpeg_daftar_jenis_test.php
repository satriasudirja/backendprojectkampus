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
        Schema::table('simpeg_daftar_jenis_test', function (Blueprint $table) {
            //
            $table->softDeletes();
             $table->index('kode');
            $table->index('jenis_tes');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_daftar_jenis_test', function (Blueprint $table) {
            //
            $table->dropIndex(['kode']);
            $table->dropIndex(['jenis_tes']);
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
