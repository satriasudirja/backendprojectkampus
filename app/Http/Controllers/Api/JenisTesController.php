<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisTes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JenisTesController extends Controller
{
    public function index(Request $request)
    {
        try {
            $sortBy = $request->query('sort_by', 'kode');
            $sortDirection = $request->query('sort_dir', 'desc');

            $jenisTes = JenisTes::orderBy($sortBy, $sortDirection)
                                ->paginate(10);

            return response()->json($jenisTes);
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
                'kode' => 'required|string|max:4|unique:jenis_tes,kode',
                'jenis_tes' => 'required|string|max:25',
                'nilai_minimal' => 'required|numeric',
                'nilai_maksimal' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisTes = JenisTes::create($request->all());
            return response()->json($jenisTes, 201);
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
            $jenisTes = JenisTes::findOrFail($kode);
            return response()->json($jenisTes);
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
                'jenis_tes' => 'required|string|max:25',
                'nilai_minimal' => 'required|numeric',
                'nilai_maksimal' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $jenisTes = JenisTes::findOrFail($kode);
            $jenisTes->update($request->all());

            return response()->json($jenisTes);
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
            $jenisTes = JenisTes::findOrFail($kode);
            $jenisTes->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}