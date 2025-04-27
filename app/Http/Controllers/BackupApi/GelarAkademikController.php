<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GelarAkademik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GelarAkademikController extends Controller
{
    // Menampilkan semua data dengan pagination dan sorting
    public function index(Request $request)
    {
        try {
            // Ambil parameter sorting (default: descending berdasarkan gelar)
            $sortBy = $request->query('sort_by', 'gelar');
            $sortDirection = $request->query('sort_dir', 'desc');

            // Query dengan sorting dan pagination
            $gelarAkademik = GelarAkademik::orderBy($sortBy, $sortDirection)
                                          ->paginate(10); // Pagination 10 data per halaman

            return response()->json($gelarAkademik);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Menyimpan data baru
    public function store(Request $request)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'gelar' => 'required|string|max:10|unique:gelar_akademik,gelar',
                'nama_gelar' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $gelarAkademik = GelarAkademik::create($request->all());
            return response()->json($gelarAkademik, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Menampilkan data berdasarkan gelar
    public function show($gelar)
    {
        try {
            $gelarAkademik = GelarAkademik::findOrFail($gelar);
            return response()->json($gelarAkademik);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Mengupdate data
    public function update(Request $request, $gelar)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'nama_gelar' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $gelarAkademik = GelarAkademik::findOrFail($gelar);
            $gelarAkademik->update($request->all());

            return response()->json($gelarAkademik);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Menghapus data
    public function destroy($gelar)
    {
        try {
            $gelarAkademik = GelarAkademik::findOrFail($gelar);
            $gelarAkademik->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}