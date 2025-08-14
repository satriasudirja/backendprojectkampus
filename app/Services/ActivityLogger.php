<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Mencatat aktivitas yang dilakukan oleh pengguna.
     *
     * @param string $event Nama event (e.g., 'create', 'update', 'delete').
     * @param \Illuminate\Database\Eloquent\Model $model Model yang mengalami perubahan.
     * @param array|null $changes Data perubahan (biasanya untuk event 'update').
     * @return void
     */
    public static function log($event, $model, $changes = null)
    {
        // 1. Dapatkan objek user yang sedang login
        $user = Auth::user();

        // Jika tidak ada user yang login (misalnya dari proses console), hentikan proses.
        if (!$user) {
            return;
        }

        // 2. PERBAIKAN: Ambil ID pegawai dari relasi user->pegawai
        // Ini akan mengambil ID dari tabel 'simpeg_pegawai', bukan 'simpeg_users'.
        // Menggunakan null-safe operator (?->) untuk mencegah error jika relasi 'pegawai' tidak ada.
        $pegawaiId = $user?->pegawai?->id;

        // 3. Hanya catat log jika ID pegawai ditemukan.
        // Ini mencegah error jika ada user yang tidak tertaut ke data pegawai.
        if (!$pegawaiId) {
            // Anda bisa menambahkan log error di sini jika perlu,
            // misalnya: \Log::warning("Activity log skipped: User {$user->id} has no associated pegawai data.");
            return;
        }

        ActivityLog::create([
            'pegawai_id' => $pegawaiId, // <-- Menggunakan ID yang sudah benar
            'event'      => $event,
            'model_type' => get_class($model),
            'model_id'   => $model->id,
            'changes'    => $changes ? json_encode($changes) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
