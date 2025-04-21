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
        Schema::create('simpeg_gaji_slip', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('pegawai_id');
            $table->integer('periode_id');
            $table->decimal('total_pendapatan', 12, 2)->default(0);
            $table->decimal('total_potongan', 12, 2)->default(0);
            $table->decimal('gaji_bersih', 12, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, processed, approved, paid
            $table->date('tgl_proses')->nullable();
            $table->timestamps();

            // // Foreign keys
            // $table->foreign('pegawai_id')
            //     ->references('id')
            //     ->on('simpeg_pegawai')
            //     ->onDelete('restrict');

            // $table->foreign('periode_id')
            //     ->references('id')
            //     ->on('simpeg_gaji_periode')
            //     ->onDelete('restrict');

            // Indexes
            $table->index(['pegawai_id', 'periode_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simpeg_gaji_slip');
    }
};