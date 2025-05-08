<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegUnivLuar;
use App\Services\ActivityLogger;

class SimpegUnivLuarController extends Controller
{
    public function index(Request $request)
    {
        $universitas = SimpegUnivLuar::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $universitas->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/univ-luar/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/univ-luar/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $universitas
        ]);
    }

    public function show(Request $request, $id)
    {
        $universitas = SimpegUnivLuar::find($id);

        if (!$universitas) {
            return response()->json(['success' => false, 'message' => 'Universitas tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $universitas,
            'update_url' => url("/api/{$prefix}/univ-luar/" . $universitas->id),
            'delete_url' => url("/api/{$prefix}/univ-luar/" . $universitas->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:10',
            'nama_universitas' => 'required|string|max:50',
            'alamat' => 'required|string',
            'no_telp' => 'required|string|max:20',
        ]);

        $universitas = SimpegUnivLuar::create([
            'kode' => $request->kode,
            'nama_universitas' => $request->nama_universitas,
            'alamat' => $request->alamat,
            'no_telp' => $request->no_telp,
        ]);

        ActivityLogger::log('create', $universitas, $universitas->toArray());

        return response()->json([
            'success' => true,
            'data' => $universitas,
            'message' => 'Universitas luar berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $universitas = SimpegUnivLuar::find($id);
    
        if (!$universitas) {
            return response()->json(['success' => false, 'message' => 'Universitas tidak ditemukan'], 404);
        }
    
        $request->validate([
            'kode' => 'required|string|max:10',
            'nama_universitas' => 'required|string|max:50',
            'alamat' => 'required|string',
            'no_telp' => 'required|string|max:20',
        ]);
    
        $old = $universitas->getOriginal();
    
        $universitas->update([
            'kode' => $request->kode,
            'nama_universitas' => $request->nama_universitas,
            'alamat' => $request->alamat,
            'no_telp' => $request->no_telp,
        ]);
    
        $changes = array_diff_assoc($universitas->toArray(), $old);
        ActivityLogger::log('update', $universitas, $changes);
    
        return response()->json([
            'success' => true,
            'data' => $universitas,
            'message' => 'Universitas luar berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $universitas = SimpegUnivLuar::find($id);
    
        if (!$universitas) {
            return response()->json(['success' => false, 'message' => 'Universitas tidak ditemukan'], 404);
        }
    
        $universitasData = $universitas->toArray();
    
        $universitas->delete();
    
        ActivityLogger::log('delete', $universitas, $universitasData);
    
        return response()->json([
            'success' => true,
            'message' => 'Universitas luar berhasil dihapus (soft delete)'
        ]);
    }
}