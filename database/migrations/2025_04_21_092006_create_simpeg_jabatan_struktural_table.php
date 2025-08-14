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
            
            // Gabungan hasil konflik
            $table->string('singkatan', 100); // Gunakan panjang 100 dari incoming change
            $table->string('alamat_email', 100)->nullable(); // Gunakan nullable agar lebih fleksibel
            $table->integer('beban_sks')->nullable(); // nullable diambil dari incoming change
            $table->decimal('tunjangan', 15, 2)->nullable(); // dari HEAD, tetap disimpan karena penting

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
