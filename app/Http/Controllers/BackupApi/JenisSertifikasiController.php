<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisSertifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JenisSertifikasiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $sortBy = $request->query('sort_by', 'kode');
            $sortDirection = $request->query('sort_dir', 'desc');

            $jenisSertifikasi = JenisSertifikasi::orderBy($sortBy, $sortDirection)
                                               ->paginate(10);

            return response()->json($jenisSertifikasi);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kode' => 'required|string|max:5|unique:jenis_sertifikasi,kode',
                'jenis_sertifikasi' => 'required|string|max:30',
                'kategorisertifikasi_id' => 'required|exists:kategorisertifikasi,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisSertifikasi = JenisSertifikasi::create($request->all());
            return response()->json($jenisSertifikasi, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($kode)
    {
        try {
            $jenisSertifikasi = JenisSertifikasi::findOrFail($kode);
            return response()->json($jenisSertifikasi);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $kode)
    {
        try {
            $validator = Validator::make($request->all(), [
                'jenis_sertifikasi' => 'required|string|max:30',
                'kategorisertifikasi_id' => 'required|exists:kategorisertifikasi,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisSertifikasi = JenisSertifikasi::findOrFail($kode);
            $jenisSertifikasi->update($request->all());

            return response()->json($jenisSertifikasi);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($kode)
    {
        try {
            $jenisSertifikasi = JenisSertifikasi::findOrFail($kode);
            $jenisSertifikasi->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}