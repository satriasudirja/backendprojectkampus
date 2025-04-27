<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegStatusPernikahan;
use App\Services\ActivityLogger;

class SimpegStatusPernikahanController extends Controller
{
    public function index(Request $request)
    {
        $statusPernikahan = SimpegStatusPernikahan::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $statusPernikahan->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/status-pernikahan/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/status-pernikahan/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $statusPernikahan
        ]);
    }

    public function show(Request $request, $id)
    {
        $statusPernikahan = SimpegStatusPernikahan::find($id);

        if (!$statusPernikahan) {
            return response()->json(['success' => false, 'message' => 'Status Pernikahan tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $statusPernikahan,
            'update_url' => url("/api/{$prefix}/status-pernikahan/" . $statusPernikahan->id),
            'delete_url' => url("/api/{$prefix}/status-pernikahan/" . $statusPernikahan->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_status' => 'required|string|max:50',
            'nama_status' => 'required|string|max:50',
        ]);

        $statusPernikahan = SimpegStatusPernikahan::create([
            'kode_status' => $request->kode_status,
            'nama_status' => $request->nama_status,
        ]);

        ActivityLogger::log('create', $statusPernikahan, $statusPernikahan->toArray());

        return response()->json([
            'success' => true,
            'data' => $statusPernikahan,
            'message' => 'Status Pernikahan berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $statusPernikahan = SimpegStatusPernikahan::find($id);

        if (!$statusPernikahan) {
            return response()->json(['success' => false, 'message' => 'Status Pernikahan tidak ditemukan'], 404);
        }

        $request->validate([
            'kode_status' => 'required|string|max:50',
            'nama_status' => 'required|string|max:50',
        ]);

        $old = $statusPernikahan->getOriginal();

        $statusPernikahan->update([
            'kode_status' => $request->kode_status,
            'nama_status' => $request->nama_status,
        ]);

        $changes = array_diff_assoc($statusPernikahan->toArray(), $old);
        ActivityLogger::log('update', $statusPernikahan, $changes);

        return response()->json([
            'success' => true,
            'data' => $statusPernikahan,
            'message' => 'Status Pernikahan berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $statusPernikahan = SimpegStatusPernikahan::find($id);
    
        if (!$statusPernikahan) {
            return response()->json(['success' => false, 'message' => 'Status Pernikahan tidak ditemukan'], 404);
        }
    
        $statusPernikahanData = $statusPernikahan->toArray(); // Simpan data untuk logging
    
        $statusPernikahan->delete(); // Hard delete (karena belum ada soft delete)
    
        ActivityLogger::log('delete', $statusPernikahan, $statusPernikahanData);
    
        return response()->json([
            'success' => true,
            'message' => 'Status Pernikahan berhasil dihapus'
        ]);
    }
}