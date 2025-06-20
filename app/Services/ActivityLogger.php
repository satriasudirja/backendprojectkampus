<?php
namespace App\Services;

use App\Models\ActivityLog; // Pastikan model ActivityLog sudah ada dan terimport
use Illuminate\Support\Facades\Auth; // Pastikan ini terimport

class ActivityLogger
{
    public static function log($event, $model, $changes = null)
    {
        // Mendapatkan ID pengguna yang saat ini login.
        // Jika tidak ada user yang login (misalnya, via console command atau API tanpa auth),
        // Auth::id() akan mengembalikan null.
        $userId = Auth::id();

        ActivityLog::create([
            // Pastikan kolom 'pegawai_id' di tabel 'activity_logs' bisa menampung null
            // ATAU berikan nilai default jika user tidak login.
            // Contoh: Jika kolom 'pegawai_id' tidak nullable, Anda bisa set ke ID user default (misal user guest/system)
            // atau tambahkan validasi/penanganan.
            'pegawai_id' => $userId, // Pastikan tabel activity_logs.pegawai_id nullable
            'event' => $event,
            'model_type' => get_class($model), // Ini sudah tidak error lagi karena $model valid
            'model_id' => $model->id,           // Ini sudah tidak error lagi karena $model valid
            'changes' => $changes ? json_encode($changes) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}