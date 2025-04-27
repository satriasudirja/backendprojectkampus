<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegSuku;
use App\Services\ActivityLogger;

class SimpegSukuController extends Controller
{
    public function index(Request $request)
    {
        $suku = SimpegSuku::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $suku->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/suku/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/suku/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $suku
        ]);
    }

    public function show(Request $request, $id)
    {
        $suku = SimpegSuku::find($id);

        if (!$suku) {
            return response()->json(['success' => false, 'message' => 'Suku tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $suku,
            'update_url' => url("/api/{$prefix}/suku/" . $suku->id),
            'delete_url' => url("/api/{$prefix}/suku/" . $suku->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_suku' => 'required|string|max:255',
        ]);

        $suku = SimpegSuku::create([
            'nama_suku' => $request->nama_suku,
        ]);

        ActivityLogger::log('create', $suku, $suku->toArray());

        return response()->json([
            'success' => true,
            'data' => $suku,
            'message' => 'Suku berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $suku = SimpegSuku::find($id);

        if (!$suku) {
            return response()->json(['success' => false, 'message' => 'Suku tidak ditemukan'], 404);
        }

        $request->validate([
            'nama_suku' => 'required|string|max:255',
        ]);

        $old = $suku->getOriginal();

        $suku->update([
            'nama_suku' => $request->nama_suku,
        ]);

        $changes = array_diff_assoc($suku->toArray(), $old);
        ActivityLogger::log('update', $suku, $changes);

        return response()->json([
            'success' => true,
            'data' => $suku,
            'message' => 'Suku berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $suku = SimpegSuku::find($id);
    
        if (!$suku) {
            return response()->json(['success' => false, 'message' => 'Suku tidak ditemukan'], 404);
        }
    
        $sukuData = $suku->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $suku->delete(); // Soft delete
    
        ActivityLogger::log('delete', $suku, $sukuData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Suku berhasil dihapus (soft delete)'
        ]);
    }
    

}
