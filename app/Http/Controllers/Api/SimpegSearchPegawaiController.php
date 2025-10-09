<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;

class SimpegSearchPegawaiController extends Controller
{
    /**
     * Search pegawai berdasarkan nama atau NIP
     * GET /api/pegawai/search?q=john
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $searchQuery = $request->input('q');

        $pegawai = SimpegPegawai::where(function ($query) use ($searchQuery) {
                $query->where('nama', 'like', "%{$searchQuery}%")
                      ->orWhere('nip', 'like', "%{$searchQuery}%");
            })
            // TAMBAHAN: Filter hanya pegawai aktif
            ->whereHas('statusAktif', function ($query) {
                $query->where('kode', 'AA'); // Hanya pegawai aktif
            })
            ->select('id', 'nama', 'nip') // Kolom yang dibutuhkan
            ->limit(10) // Batasi hasil
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pegawai
        ]);
    }
}