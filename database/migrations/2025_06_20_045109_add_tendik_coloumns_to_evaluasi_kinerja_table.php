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
        Schema::table('simpeg_evaluasi_kinerja', function (Blueprint $table) {
            // Kolom ini untuk menyimpan nilai-nilai khusus Tenaga Kependidikan
            // Dibuat nullable karena tidak akan digunakan untuk Dosen
            $table->float('nilai_komitmen_disiplin')->nullable()->after('nilai_pengabdian');
            $table->float('nilai_kepemimpinan_kerjasama')->nullable()->after('nilai_komitmen_disiplin');
            $table->float('nilai_inisiatif_integritas')->nullable()->after('nilai_kepemimpinan_kerjasama');

            // Mengubah beberapa kolom agar nullable untuk mengakomodasi perbedaan form
            $table->float('nilai_pendidikan')->nullable()->change();
            $table->float('nilai_penelitian')->nullable()->change();
            $table->float('nilai_pengabdian')->nullable()->change();
            $table->float('nilai_penunjang1')->nullable()->change();
            $table->float('nilai_penunjang2')->nullable()->change();
            $table->float('nilai_penunjang3')->nullable()->change();
            $table->float('nilai_penunjang4')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpeg_evaluasi_kinerja', function (Blueprint $table) {
            $table->dropColumn([
                'nilai_komitmen_disiplin',
                'nilai_kepemimpinan_kerjasama',
                'nilai_inisiatif_integritas'
            ]);

            // Kembalikan ke kondisi semula jika diperlukan (non-nullable)
            // Sesuaikan jika definisi awal Anda berbeda
            $table->float('nilai_pendidikan')->nullable(false)->change();
            $table->float('nilai_penelitian')->nullable(false)->change();
            $table->float('nilai_pengabdian')->nullable(false)->change();
            $table->float('nilai_penunjang1')->nullable(false)->change();
            $table->float('nilai_penunjang2')->nullable(false)->change();
            $table->float('nilai_penunjang3')->nullable(false)->change();
            $table->float('nilai_penunjang4')->nullable(false)->change();
        });
    }
};
