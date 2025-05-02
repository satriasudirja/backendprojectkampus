<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegEselon;
use App\Services\ActivityLogger;

class SimpegEselonController extends Controller
{
    public function index(Request $request)
    {
        $eselon = SimpegEselon::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $eselon->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/eselon/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/eselon/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $eselon
        ]);
    }

    public function show(Request $request, $id)
    {
        $eselon = SimpegEselon::find($id);

        if (!$eselon) {
            return response()->json(['success' => false, 'message' => 'Eselon tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $eselon,
            'update_url' => url("/api/{$prefix}/eselon/" . $eselon->id),
            'delete_url' => url("/api/{$prefix}/eselon/" . $eselon->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:2',
            'nama_eselon' => 'required|string|max:5',
            'status' => 'required|boolean',
        ]);

        $eselon = SimpegEselon::create([
            'kode' => $request->kode,
            'nama_eselon' => $request->nama_eselon,
            'status' => $request->status,
        ]);

        ActivityLogger::log('create', $eselon, $eselon->toArray());

        return response()->json([
            'success' => true,
            'data' => $eselon,
            'message' => 'Eselon berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $eselon = SimpegEselon::find($id);

        if (!$eselon) {
            return response()->json(['success' => false, 'message' => 'Eselon tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:2',
            'nama_eselon' => 'required|string|max:5',
            'status' => 'required|boolean',
        ]);

        $old = $eselon->getOriginal();

        $eselon->update([
            'kode' => $request->kode,
            'nama_eselon' => $request->nama_eselon,
            'status' => $request->status,
        ]);

        $changes = array_diff_assoc($eselon->toArray(), $old);
        ActivityLogger::log('update', $eselon, $changes);

        return response()->json([
            'success' => true,
            'data' => $eselon,
            'message' => 'Eselon berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $eselon = SimpegEselon::find($id);
    
        if (!$eselon) {
            return response()->json(['success' => false, 'message' => 'Eselon tidak ditemukan'], 404);
        }
    
        $eselonData = $eselon->toArray();
    
        $eselon->delete();
    
        ActivityLogger::log('delete', $eselon, $eselonData);
    
        return response()->json([
            'success' => true,
            'message' => 'Eselon berhasil dihapus (soft delete)'
        ]);
    }
}