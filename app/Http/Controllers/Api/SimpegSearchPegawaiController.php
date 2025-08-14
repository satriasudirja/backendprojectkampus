<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;

class SimpegSearchPegawaiController extends Controller
{
    //
     public function search(Request $request)
    {
        // 1. Validasi bahwa parameter 'q' ada dan tidak kosong.
        // Jika validasi ini gagal, Laravel akan otomatis mengembalikan error 422,
        // yang memberitahu Anda bahwa inputnya salah.
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        // 2. Ambil nilai dari parameter 'q'. JANGAN gunakan 'search' atau nama lain.
        $searchQuery = $request->input('q');

        // 3. Gunakan variabel $searchQuery dalam klausa where().
        // Ini adalah bagian terpenting yang melakukan filter.
        $pegawai = SimpegPegawai::where(function ($query) use ($searchQuery) {
                // Cari berdasarkan kolom 'nama' ATAU 'nip'
                $query->where('nama', 'like', "%{$searchQuery}%")
                      ->orWhere('nip', 'like', "%{$searchQuery}%");
            })
            ->select('id', 'nama', 'nip') // Hanya ambil kolom yang dibutuhkan agar ringan
            ->limit(10) // Batasi hasil agar respons cepat
            ->get();

        // Tidak perlu cek isEmpty() di sini, karena jika kosong,
        // frontend akan menerima array kosong, yang merupakan perilaku yang benar.
        return response()->json([
            'success' => true,
            'data' => $pegawai
        ]);
    }    
}
