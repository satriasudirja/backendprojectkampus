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
        // Add additional fields to simpeg_absensi_record table if not exist
        Schema::table('simpeg_absensi_record', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('simpeg_absensi_record', 'lokasi_masuk')) {
                $table->string('lokasi_masuk', 100)->nullable()->after('file_foto');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'lokasi_keluar')) {
                $table->string('lokasi_keluar', 100)->nullable()->after('lokasi_masuk');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'latitude_masuk')) {
                $table->decimal('latitude_masuk', 10, 8)->nullable()->after('lokasi_keluar');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'longitude_masuk')) {
                $table->decimal('longitude_masuk', 11, 8)->nullable()->after('latitude_masuk');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'latitude_keluar')) {
                $table->decimal('latitude_keluar', 10, 8)->nullable()->after('longitude_masuk');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'longitude_keluar')) {
                $table->decimal('longitude_keluar', 11, 8)->nullable()->after('latitude_keluar');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'rencana_kegiatan')) {
                $table->text('rencana_kegiatan')->nullable()->after('longitude_keluar');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'realisasi_kegiatan')) {
                $table->text('realisasi_kegiatan')->nullable()->after('rencana_kegiatan');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'foto_masuk')) {
                $table->string('foto_masuk', 255)->nullable()->after('realisasi_kegiatan');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'foto_keluar')) {
                $table->string('foto_keluar', 255)->nullable()->after('foto_masuk');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'durasi_kerja')) {
                $table->integer('durasi_kerja')->nullable()->after('foto_keluar');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'durasi_terlambat')) {
                $table->integer('durasi_terlambat')->nullable()->after('durasi_kerja');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'durasi_pulang_awal')) {
                $table->integer('durasi_pulang_awal')->nullable()->after('durasi_terlambat');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('durasi_pulang_awal');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'status_verifikasi')) {
                $table->enum('status_verifikasi', ['pending', 'verified', 'rejected'])->default('pending')->after('keterangan');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'verifikasi_oleh')) {
                $table->string('verifikasi_oleh', 50)->nullable()->after('status_verifikasi');
            }
            if (!Schema::hasColumn('simpeg_absensi_record', 'verifikasi_at')) {
                $table->timestamp('verifikasi_at')->nullable()->after('verifikasi_oleh');
            }
        });

        // Create simpeg_hari_libur table for holidays management
        if (!Schema::hasTable('simpeg_hari_libur')) {
            Schema::create('simpeg_hari_libur', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->date('tanggal_libur');
                $table->string('nama_libur', 100);
                $table->text('keterangan')->nullable();
                $table->enum('jenis_libur', ['nasional', 'daerah', 'institusi'])->default('nasional');
                $table->boolean('is_active')->default(true);
                $table->integer('tahun');
                $table->timestamps();
                
                $table->index(['tanggal_libur', 'is_active']);
                $table->index(['tahun', 'jenis_libur']);
            });
        }

        // Create simpeg_jam_kerja table for working hours settings
        if (!Schema::hasTable('simpeg_jam_kerja')) {
            Schema::create('simpeg_jam_kerja', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nama_shift', 50);
                $table->time('jam_masuk');
                $table->time('jam_keluar');
                $table->time('jam_istirahat_mulai')->nullable();
                $table->time('jam_istirahat_selesai')->nullable();
                $table->integer('toleransi_terlambat')->default(15);
                $table->integer('toleransi_pulang_awal')->default(15);
                $table->integer('durasi_kerja_standar');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('hari_kerja')->nullable();
                $table->timestamps();
            });
        }

        // Add relation to simpeg_absensi_record for jam_kerja
        Schema::table('simpeg_absensi_record', function (Blueprint $table) {
            if (!Schema::hasColumn('simpeg_absensi_record', 'jam_kerja_id')) {
                $table->unsignedBigInteger('jam_kerja_id')->nullable()->after('setting_kehadiran_id');
                $table->foreign('jam_kerja_id')->references('id')->on('simpeg_jam_kerja')->onDelete('set null');
            }
        });

        // Create simpeg_absensi_correction table for attendance correction requests
        if (!Schema::hasTable('simpeg_absensi_correction')) {
            Schema::create('simpeg_absensi_correction', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('absensi_record_id');
                $table->unsignedBigInteger('pegawai_id');
                $table->date('tanggal_koreksi');
                $table->time('jam_masuk_asli')->nullable();
                $table->time('jam_keluar_asli')->nullable();
                $table->time('jam_masuk_koreksi')->nullable();
                $table->time('jam_keluar_koreksi')->nullable();
                $table->text('alasan_koreksi');
                $table->text('bukti_pendukung')->nullable();
                $table->enum('status_koreksi', ['pending', 'approved', 'rejected'])->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('catatan_approval')->nullable();
                $table->timestamps();

                $table->foreign('absensi_record_id')->references('id')->on('simpeg_absensi_record')->onDelete('cascade');
                $table->foreign('pegawai_id')->references('id')->on('simpeg_pegawai')->onDelete('cascade');
                $table->foreign('approved_by')->references('id')->on('simpeg_pegawai')->onDelete('set null');
                
                $table->index(['pegawai_id', 'tanggal_koreksi']);
                $table->index(['status_koreksi', 'created_at']);
            });
        }

        // Create simpeg_attendance_summary table for monthly/yearly summary cache
        if (!Schema::hasTable('simpeg_attendance_summary')) {
            Schema::create('simpeg_attendance_summary', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('pegawai_id');
                $table->integer('tahun');
                $table->smallInteger('bulan');
                $table->integer('total_hari_kerja');
                $table->integer('total_hadir');
                $table->integer('total_terlambat');
                $table->integer('total_pulang_awal');
                $table->integer('total_sakit');
                $table->integer('total_izin');
                $table->integer('total_alpa');
                $table->integer('total_cuti');
                $table->integer('total_hadir_libur')->default(0);
                $table->integer('total_jam_kerja_realisasi')->default(0); // Dalam menit
                $table->integer('total_jam_kerja_standar')->default(0); // Dalam menit
                $table->integer('total_durasi_terlambat')->default(0); // Dalam menit
                $table->integer('total_durasi_pulang_awal')->default(0); // Dalam menit
                $table->decimal('persentase_kehadiran', 5, 2)->default(0);
                $table->timestamp('last_calculated_at');
                $table->timestamps();

                $table->foreign('pegawai_id')->references('id')->on('simpeg_pegawai')->onDelete('cascade');
                $table->unique(['pegawai_id', 'tahun', 'bulan']);
                $table->index(['tahun', 'bulan']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys first
        Schema::table('simpeg_absensi_record', function (Blueprint $table) {
            if (Schema::hasColumn('simpeg_absensi_record', 'jam_kerja_id')) {
                $table->dropForeign(['jam_kerja_id']);
            }
        });

        // Drop tables
        Schema::dropIfExists('simpeg_attendance_summary');
        Schema::dropIfExists('simpeg_absensi_correction');
        Schema::dropIfExists('simpeg_jam_kerja');
        Schema::dropIfExists('simpeg_hari_libur');

        // Remove additional columns from simpeg_absensi_record
        Schema::table('simpeg_absensi_record', function (Blueprint $table) {
            $columnsToRemove = [
                'jam_kerja_id',
                'lokasi_masuk',
                'lokasi_keluar',
                'latitude_masuk',
                'longitude_masuk',
                'latitude_keluar',
                'longitude_keluar',
                'rencana_kegiatan',
                'realisasi_kegiatan',
                'foto_masuk',
                'foto_keluar',
                'durasi_kerja',
                'durasi_terlambat',
                'durasi_pulang_awal',
                'keterangan',
                'status_verifikasi',
                'verifikasi_oleh',
                'verifikasi_at'
            ];

            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('simpeg_absensi_record', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};