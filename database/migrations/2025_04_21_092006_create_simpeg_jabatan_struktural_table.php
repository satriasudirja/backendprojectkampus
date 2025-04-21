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
            $table->uuid('id')->primary();
            $table->uuid('unit_kerja_id');
            $table->uuid('jenis_jabatan_struktural_id');
            $table->uuid('pangkat_id');
            $table->uuid('eselon_id');
            $table->string('kode', 5);
            $table->string('singkatan', 50);
            $table->string('alamat_email', 100);
            $table->integer('beban_sks');
            $table->boolean('is_pimpinan')->default(false);
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->string('parent_jabatan', 100)->nullable();

            // // Foreign key constraints
            // $table->foreign('unit_kerja_id')
            //       ->references('id')
            //       ->on('simpeg_unit_kerja')
            //       ->onDelete('cascade');

            // $table->foreign('jenis_jabatan_struktural_id')
            //       ->references('id')
            //       ->on('simpeg_jenis_jabatan_struktural')
            //       ->onDelete('cascade');

            // $table->foreign('pangkat_id')
            //       ->references('id')
            //       ->on('simpeg_pangkat')
            //       ->onDelete('cascade');

            // $table->foreign('eselon_id')
            //       ->references('id')
            //       ->on('simpeg_eselon')
            //       ->onDelete('cascade');
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