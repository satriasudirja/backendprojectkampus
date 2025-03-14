<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutputPenelitian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OutputPenelitianController extends Controller
{
    public function index(Request $request)
    {
        try {
            $sortBy = $request->query('sort_by', 'kode');
            $sortDirection = $request->query('sort_dir', 'desc');

            $outputPenelitian = OutputPenelitian::orderBy($sortBy, $sortDirection)
                                               ->paginate(10);

            return response()->json($outputPenelitian);
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
                'kode' => 'required|string|max:4|unique:output_penelitian,kode',
                'output_penelitian' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $outputPenelitian = OutputPenelitian::create($request->all());
            return response()->json($outputPenelitian, 201);
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
            $outputPenelitian = OutputPenelitian::findOrFail($kode);
            return response()->json($outputPenelitian);
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
                'output_penelitian' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $outputPenelitian = OutputPenelitian::findOrFail($kode);
            $outputPenelitian->update($request->all());

            return response()->json($outputPenelitian);
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
            $outputPenelitian = OutputPenelitian::findOrFail($kode);
            $outputPenelitian->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}