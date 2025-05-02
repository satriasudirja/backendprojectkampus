<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simpeg_jenjang_pendidikan', function (Blueprint $table) {
            $table->softDeletes(); // atau tambah kolom deleted_at jika ini yang dimaksud
        });
    }

    public function down(): void
    {
        Schema::table('simpeg_jenjang_pendidikan', function (Blueprint $table) {
            $table->dropSoftDeletes(); // atau $table->dropColumn('deleted_at');
        });
    }
};
