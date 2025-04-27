<?php
namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public static function log($event, $model, $changes = null)
    {
        ActivityLog::create([
            'pegawai_id' => Auth::id(),
            'event' => $event,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'changes' => $changes ? json_encode($changes) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
