<?php


namespace App\Http\Controllers\BackupApi;
use App\Http\Controllers\Controller;
use App\Models\MediaPublikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MediaPublikasiController extends Controller
{
    public function index(Request $request)
    {
        try {
            $sortBy = $request->query('sort_by', 'nama');
            $sortDirection = $request->query('sort_dir', 'desc');

            $mediaPublikasi = MediaPublikasi::orderBy($sortBy, $sortDirection)
                                           ->paginate(10);

            return response()->json($mediaPublikasi);
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
                'nama' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $mediaPublikasi = MediaPublikasi::create($request->all());
            return response()->json($mediaPublikasi, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $mediaPublikasi = MediaPublikasi::findOrFail($id);
            return response()->json($mediaPublikasi);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Data tidak ditemukan.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $mediaPublikasi = MediaPublikasi::findOrFail($id);
            $mediaPublikasi->update($request->all());

            return response()->json($mediaPublikasi);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $mediaPublikasi = MediaPublikasi::findOrFail($id);
            $mediaPublikasi->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}