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
        Schema::create('simpeg_data_pendukung', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('tipe_dokumen', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png', 'jpeg'])->nullable();
            $table->string('file_path')->nullable();
            $table->string('nama_dokumen')->nullable();
            $table->integer('jenis_dokumen_id');
            $table->text('keterangan')->nullable();
            $table->string('pendukungable_type')->nullable();
            $table->unsignedBigInteger('pendukungable_id')->nullable();
            
            $table->timestamps();
            $table->index(['pendukungable_type', 'pendukungable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_pendukung');
    }
};