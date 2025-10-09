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
        Schema::create('master_potongan_wajib', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode_potongan', 50)->unique();
            $table->string('nama_potongan', 200);
            $table->enum('jenis_potongan', ['persen', 'nominal'])->default('persen');
            $table->decimal('nilai_potongan', 15, 2)->default(0);
            $table->enum('dihitung_dari', ['gaji_pokok', 'penghasilan_bruto'])->default('gaji_pokok');
            $table->boolean('is_active')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_potongan_wajib');
    }
};
