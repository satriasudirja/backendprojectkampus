<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegBahasa;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegBahasaController extends Controller
{
    public function index(Request $request)
    {
        $bahasa = SimpegBahasa::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $bahasa->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/bahasa/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/bahasa/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $bahasa
        ]);
    }

    public function show(Request $request, $id)
    {
        $bahasa = SimpegBahasa::find($id);

        if (!$bahasa) {
            return response()->json(['success' => false, 'message' => 'Data bahasa tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $bahasa,
            'update_url' => url("/api/{$prefix}/bahasa/" . $bahasa->id),
            'delete_url' => url("/api/{$prefix}/bahasa/" . $bahasa->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:5',
            'nama_bahasa' => 'required|string|max:20',
        ]);

        $bahasa = SimpegBahasa::create([
            'kode' => $request->kode,
            'nama_bahasa' => $request->nama_bahasa,
        ]);

        ActivityLogger::log('create', $bahasa, $bahasa->toArray());

        return response()->json([
            'success' => true,
            'data' => $bahasa,
            'message' => 'Data bahasa berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $bahasa = SimpegBahasa::find($id);

        if (!$bahasa) {
            return response()->json(['success' => false, 'message' => 'Data bahasa tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:5',
            'nama_bahasa' => 'required|string|max:20',
        ]);

        $old = $bahasa->getOriginal();

        $bahasa->update([
            'kode' => $request->kode,
            'nama_bahasa' => $request->nama_bahasa,
        ]);

        $changes = array_diff_assoc($bahasa->toArray(), $old);
        ActivityLogger::log('update', $bahasa, $changes);

        return response()->json([
            'success' => true,
            'data' => $bahasa,
            'message' => 'Data bahasa berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $bahasa = SimpegBahasa::find($id);
    
        if (!$bahasa) {
            return response()->json(['success' => false, 'message' => 'Data bahasa tidak ditemukan'], 404);
        }
    
        $bahasaData = $bahasa->toArray();
    
        $bahasa->delete();
    
        ActivityLogger::log('delete', $bahasa, $bahasaData);
    
        return response()->json([
            'success' => true,
            'message' => 'Data bahasa berhasil dihapus'
        ]);
    }
}