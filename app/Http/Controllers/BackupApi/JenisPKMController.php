<?php

namespace App\Http\Controllers\BackupApi;

use App\Http\Controllers\Controller;
use App\Models\JenisPKM;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JenisPKMController extends Controller
{
    public function index(Request $request)
    {
        try {
            $sortBy = $request->query('sort_by', 'kode');
            $sortDirection = $request->query('sort_dir', 'desc');

            $jenisPKM = JenisPKM::orderBy($sortBy, $sortDirection)
                                ->paginate(10);

            return response()->json($jenisPKM);
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
                'kode' => 'required|string|max:4|unique:jenis_pkm,kode',
                'nama_pkm' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisPKM = JenisPKM::create($request->all());
            return response()->json($jenisPKM, 201);
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
            $jenisPKM = JenisPKM::findOrFail($kode);
            return response()->json($jenisPKM);
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
                'nama_pkm' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisPKM = JenisPKM::findOrFail($kode);
            $jenisPKM->update($request->all());

            return response()->json($jenisPKM);
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
            $jenisPKM = JenisPKM::findOrFail($kode);
            $jenisPKM->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}