<?php

namespace App\Observers;

use App\Models\SimpegCutiRecord;
use App\Models\SimpegIzinRecord;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegJenisKehadiran;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LeavePermitObserver
{
    /**
     * Menangani event "updated" pada model Cuti dan Izin.
     */
    public function updated($model)
    {
        // Hanya jalankan jika status pengajuan berubah menjadi 'disetujui'
        if ($model->isDirty('status_pengajuan') && $model->status_pengajuan === 'disetujui') {
            $this->createOrUpdateAttendanceRecords($model);
        }
    }

    /**
     * Membuat atau memperbarui record absensi berdasarkan data Cuti atau Izin.
     */
    protected function createOrUpdateAttendanceRecords($model)
    {
        $startDate = $model->tgl_mulai;
        $endDate = $model->tgl_selesai;
        $pegawaiId = $model->pegawai_id;

        $isCuti = $model instanceof SimpegCutiRecord;
        $keterangan = $isCuti ? ($model->alasan_cuti ?? 'Cuti') : ($model->alasan ?? 'Izin');
        $foreignKey = $isCuti ? 'cuti_record_id' : 'izin_record_id';
        
        if ($isCuti) {
            $jenisKehadiranId = $this->getJenisKehadiranId('Cuti');
        } else {
            $isSakit = stripos($model->jenis_izin, 'sakit') !== false;
            $jenisKehadiranId = $isSakit ? $this->getJenisKehadiranId('Sakit') : $this->getJenisKehadiranId('Izin');
        }

        if (!$jenisKehadiranId) {
            Log::warning("Jenis Kehadiran untuk Cuti/Izin/Sakit tidak ditemukan di master 'SimpegJenisKehadiran'.");
            return;
        }

        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            // Gunakan updateOrCreate untuk membuat record baru atau menimpa yang sudah ada (misal: Alpha)
            SimpegAbsensiRecord::updateOrCreate(
                [
                    'pegawai_id' => $pegawaiId,
                    'tanggal_absensi' => $date->toDateString(),
                ],
                [
                    'jenis_kehadiran_id' => $jenisKehadiranId,
                    $foreignKey => $model->id,
                    'jam_masuk' => null, // Pastikan tidak ada jam masuk/pulang
                    'jam_keluar' => null,
                    'durasi_kerja' => 0,
                    'keterangan' => $keterangan,
                    'status_verifikasi' => 'verified',
                ]
            );
        }
    }

    /**
     * Helper untuk mendapatkan ID dari jenis kehadiran berdasarkan nama.
     */
    private function getJenisKehadiranId(string $nama)
    {
        $cacheKey = "jenis_kehadiran_id_" . strtolower(str_replace(' ', '_', $nama));
        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($nama) {
            // Mencari berdasarkan nama_jenis yang sesuai dengan seeder Anda
            return SimpegJenisKehadiran::where('nama_jenis', $nama)->value('id');
        });
    }
}
