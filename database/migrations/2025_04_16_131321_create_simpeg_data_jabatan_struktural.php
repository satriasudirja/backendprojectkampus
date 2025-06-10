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
        Schema::create('simpeg_data_jabatan_struktural', function (Blueprint $table) {

               $table->bigIncrements('id')->primary();
            
            // Relasi ke tabel referensi
            $table->integer('jabatan_struktural_id');
            $table->integer('pegawai_id');
            

            
            // Date ranges
            $table->date('tgl_mulai');
            $table->date('tgl_selesai')->nullable(); // Made nullable for current positions
            
            // SK data
            $table->string('no_sk', 50);
            $table->date('tgl_sk');
            $table->string('pejabat_penetap', 100);
            
            // Supporting documents
            $table->string('file_jabatan', 255)->nullable(); // Changed to string for file path
            
            // Metadata
            $table->date('tgl_input')->nullable();
            $table->string('status_pengajuan', 20)->default('draft'); // draft, submitted, approved, rejected
            
            $table->timestamp('tgl_diajukan')->nullable();
            $table->timestamp('tgl_disetujui')->nullable();
            $table->timestamp('tgl_ditolak')->nullable();
            $table->timestamps();
            $table->softDeletes();

   
            // Indexes
            $table->index('pegawai_id');
            $table->index('jabatan_struktural_id');
            $table->index('no_sk');
            $table->index('tgl_mulai');


         
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_data_jabatan_struktural');
    }
};