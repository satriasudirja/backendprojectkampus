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
        Schema::create('simpeg_jabatan_fungsional', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pangkat_id');
            $table->string('kode', 5);
            $table->string('nama_jabatan_fungsional', 30);
            $table->string('pangkat', 10);
            $table->string('angka_kredit', 6);
            $table->integer('usia_pensiun');
            $table->decimal('tunjangan', 15, 2)->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamps();
        $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_jabatan_fungsional');
    }
};