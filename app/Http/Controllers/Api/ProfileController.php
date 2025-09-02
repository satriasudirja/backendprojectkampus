<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user()->pegawai;
        $role = $user->jabatanAkademik->role;
        
        $profileData = [
            'nip' => $user->nip,
            'nama' => $user->nama,
            'email' => $user->email_pribadi,
            'no_handphone' => $user->no_handphone,
            'jabatan' => $user->jabatanAkademik->jabatan_akademik,
            'role' => $role->nama,
            'unit_kerja' => $user->unitKerja->nama_unit_kerja ?? '-',
        ];
        
        return response()->json([
            'success' => true,
            'data' => $profileData
        ]);
    }
}