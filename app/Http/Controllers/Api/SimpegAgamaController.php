<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegAgama;
use App\Services\ActivityLogger;

class SimpegAgamaController extends Controller
{
    public function index(Request $request)
    {
        $agama = SimpegAgama::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $agama->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/agama/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/agama/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $agama
        ]);
    }

    public function show(Request $request, $id)
    {
        $agama = SimpegAgama::find($id);

        if (!$agama) {
            return response()->json(['success' => false, 'message' => 'Agama tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $agama,
            'update_url' => url("/api/{$prefix}/agama/" . $agama->id),
            'delete_url' => url("/api/{$prefix}/agama/" . $agama->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|integer|unique:simpeg_agama,kode',
            'nama_agama' => 'required|string|max:100',
        ]);

        $agama = SimpegAgama::create([
            'kode' => $request->kode,
            'nama_agama' => $request->nama_agama,
        ]);

        ActivityLogger::log('create', $agama, $agama->toArray());

        return response()->json([
            'success' => true,
            'data' => $agama,
            'message' => 'Agama berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $agama = SimpegAgama::find($id);

        if (!$agama) {
            return response()->json(['success' => false, 'message' => 'Agama tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|integer|unique:simpeg_agama,kode,' . $id,
            'nama_agama' => 'required|string|max:100',
        ]);

        $old = $agama->getOriginal();

        $agama->update([
            'kode' => $request->kode,
            'nama_agama' => $request->nama_agama,
        ]);

        $changes = array_diff_assoc($agama->toArray(), $old);
        ActivityLogger::log('update', $agama, $changes);

        return response()->json([
            'success' => true,
            'data' => $agama,
            'message' => 'Agama berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $agama = SimpegAgama::find($id);
    
        if (!$agama) {
            return response()->json(['success' => false, 'message' => 'Agama tidak ditemukan'], 404);
        }
    
        $agamaData = $agama->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $agama->delete(); // Soft delete
    
        ActivityLogger::log('delete', $agama, $agamaData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Agama berhasil dihapus (soft delete)'
        ]);
    }
}