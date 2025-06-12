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
        Schema::create('simpeg_jabatan_struktural', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('unit_kerja_id');
            $table->integer('jenis_jabatan_struktural_id');
            $table->integer('pangkat_id');
            $table->integer('eselon_id');
            $table->string('kode', 5);
            $table->string('singkatan', 50);
            $table->string('alamat_email', 100);
            $table->integer('beban_sks');
            $table->decimal('tunjangan', 15, 2)->nullable();
            $table->boolean('is_pimpinan');
            $table->boolean('aktif');
            $table->text('keterangan')->nullable();
            $table->string('parent_jabatan', 100)->nullable();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_jabatan_struktural');
    }
};