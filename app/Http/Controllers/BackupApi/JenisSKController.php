<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisSK;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JenisSKController extends Controller
{
    // Menampilkan semua data dengan pagination dan sorting
    public function index(Request $request)
    {
        try {
            // Ambil parameter sorting (default: descending berdasarkan kode)
            $sortBy = $request->query('sort_by', 'kode');
            $sortDirection = $request->query('sort_dir', 'desc');

            // Query dengan sorting dan pagination
            $jenisSK = JenisSK::orderBy($sortBy, $sortDirection)
                              ->paginate(10); // Pagination 10 data per halaman

            return response()->json($jenisSK);
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
                'kode' => 'required|string|max:3|unique:jenis_sk,kode',
                'jenis_sk' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisSK = JenisSK::create($request->all());
            return response()->json($jenisSK, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Menampilkan data berdasarkan kode
    public function show($kode)
    {
        try {
            $jenisSK = JenisSK::findOrFail($kode);
            return response()->json($jenisSK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Mengupdate data
    public function update(Request $request, $kode)
    {
        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'jenis_sk' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisSK = JenisSK::findOrFail($kode);
            $jenisSK->update($request->all());

            return response()->json($jenisSK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Menghapus data
    public function destroy($kode)
    {
        try {
            $jenisSK = JenisSK::findOrFail($kode);
            $jenisSK->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}