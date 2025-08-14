<?php

namespace App\Observers;

use App\Models\SimpegPegawai;

class PegawaiObserver
{
    /**
     * Handle the SimpegPegawai "created" event.
     */
    public function created(SimpegPegawai $simpegPegawai): void
    {
        $role = $simpegPegawai -> 
        $defaultRole = Role::where('nama', 'Pegawai')->first();
    }   

    /**
     * Handle the SimpegPegawai "updated" event.
     */
    public function updated(SimpegPegawai $simpegPegawai): void
    {
        //
    }

    /**
     * Handle the SimpegPegawai "deleted" event.
     */
    public function deleted(SimpegPegawai $simpegPegawai): void
    {
        //
    }

    /**
     * Handle the SimpegPegawai "restored" event.
     */
    public function restored(SimpegPegawai $simpegPegawai): void
    {
        //
    }

    /**
     * Handle the SimpegPegawai "force deleted" event.
     */
    public function forceDeleted(SimpegPegawai $simpegPegawai): void
    {
        //
    }
}
