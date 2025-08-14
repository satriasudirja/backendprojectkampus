<?php

namespace App\Http\Controllers;

use App\Models\SimpegUser;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Membuat akun user untuk pegawai yang sudah ada.
     */
    public function store(Request $request)
    {
        // PERUBAHAN: Validasi disederhanakan. Tidak ada lagi 'role_id'.
        $request->validate([
            'pegawai_id' => 'required|exists:simpeg_pegawai,id|unique:simpeg_users,pegawai_id',
            'password' => 'required|min:6|confirmed',
        ]);

        // 1. Cari data pegawai berdasarkan pegawai_id
        $pegawai = SimpegPegawai::findOrFail($request->pegawai_id);

        // 2. Buat user baru dengan username diambil dari NIP pegawai
        $user = SimpegUser::create([
            'pegawai_id' => $pegawai->id,
            'username'   => $pegawai->nip, // Logika ini sudah benar
            'password'   => Hash::make($request->password),
            'is_active'  => true,
        ]);

        return redirect()->route('users.index')->with('success', 'Akun user untuk ' . $pegawai->nama . ' berhasil dibuat.');
    }
}