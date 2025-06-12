<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegGolonganDarah;
use App\Services\ActivityLogger;

class SimpegGolonganDarahController extends Controller
{
    public function index(Request $request)
    {
        $golonganDarah = SimpegGolonganDarah::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $golonganDarah->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/golongan-darah/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/golongan-darah/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $golonganDarah
        ]);
    }

    public function show(Request $request, $id)
    {
        $golonganDarah = SimpegGolonganDarah::find($id);

        if (!$golonganDarah) {
            return response()->json(['success' => false, 'message' => 'Golongan darah tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $golonganDarah,
            'update_url' => url("/api/{$prefix}/golongan-darah/" . $golonganDarah->id),
            'delete_url' => url("/api/{$prefix}/golongan-darah/" . $golonganDarah->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'golongan_darah' => 'required|string|max:10|unique:simpeg_golongan_darah,golongan_darah',
        ]);

        $golonganDarah = SimpegGolonganDarah::create([
            'golongan_darah' => $request->golongan_darah,
        ]);

        ActivityLogger::log('create', $golonganDarah, $golonganDarah->toArray());

        return response()->json([
            'success' => true,
            'data' => $golonganDarah,
            'message' => 'Golongan darah berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $golonganDarah = SimpegGolonganDarah::find($id);

        if (!$golonganDarah) {
            return response()->json(['success' => false, 'message' => 'Golongan darah tidak ditemukan'], 404);
        }

        $request->validate([
            'golongan_darah' => 'required|string|max:10|unique:simpeg_golongan_darah,golongan_darah,' . $id,
        ]);

        $old = $golonganDarah->getOriginal();

        $golonganDarah->update([
            'golongan_darah' => $request->golongan_darah,
        ]);

        $changes = array_diff_assoc($golonganDarah->toArray(), $old);
        ActivityLogger::log('update', $golonganDarah, $changes);

        return response()->json([
            'success' => true,
            'data' => $golonganDarah,
            'message' => 'Golongan darah berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $golonganDarah = SimpegGolonganDarah::find($id);
    
        if (!$golonganDarah) {
            return response()->json(['success' => false, 'message' => 'Golongan darah tidak ditemukan'], 404);
        }
    
        $golonganDarahData = $golonganDarah->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $golonganDarah->delete(); // Soft delete
    
        ActivityLogger::log('delete', $golonganDarah, $golonganDarahData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Golongan darah berhasil dihapus (soft delete)'
        ]);
    }
}