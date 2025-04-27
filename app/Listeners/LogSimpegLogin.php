<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogSimpegLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event)
{
    \DB::table('simpeg_login_logs')->insert([
        'pegawai_id' => $event->user->id,
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'logged_in_at' => now(),
    ]);
}

}
