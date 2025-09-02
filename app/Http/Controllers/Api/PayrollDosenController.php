<?php
// app/Http/Controllers/Api/Dosen/PayrollController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PenggajianPegawai;
use Illuminate\Support\Facades\Auth;

class PayrollDosenController extends Controller
{
    /**
     * [DOSEN] Menampilkan daftar riwayat slip gaji milik dosen yang sedang login.
     * Method: GET
     * URL: /api/dosen/payroll/slips
     */
    public function index(Request $request)
    {
        // Mengambil data pegawai yang sedang login
        $pegawai = Auth::user()->pegawai;

        // Validasi input per_page
        $allowedPerPages = [10, 25, 50, 100];
        $perPage = $request->input('per_page', 10); // Default ke 10
        if (!in_array($perPage, $allowedPerPages)) {
            $perPage = 10; // Jika tidak valid, kembalikan ke default
        }

        // Ambil semua slip gaji untuk pegawai_id yang sedang login
        $slips = PenggajianPegawai::where('pegawai_id', $pegawai->id)
                    ->with('periode:id,nama_periode') // Hanya ambil data periode yg relevan
                    ->orderBy('id', 'desc') // Urutkan berdasarkan slip terbaru
                    ->paginate($perPage);

        return response()->json($slips);
    }

    /**
     * [DOSEN] Menampilkan detail satu slip gaji, dengan validasi kepemilikan.
     * Method: GET
     * URL: /api/dosen/payroll/slips/{slip}
     */
    public function show(PenggajianPegawai $slip)
    {
        // Mengambil data pegawai yang sedang login
        $pegawai = Auth::user()->pegawai;

        // Pastikan slip gaji yang diminta adalah milik pegawai yang sedang login
        // Ini adalah langkah keamanan yang penting
        if ($slip->pegawai_id !== $pegawai->id) {
            return response()->json(['message' => 'Unauthorized. Anda tidak memiliki akses ke slip gaji ini.'], 403);
        }

        // Jika valid, load semua detailnya untuk ditampilkan
        $slip->load(['periode', 'komponenPendapatan', 'komponenPotongan']);
        
        return response()->json($slip);
    }
}
