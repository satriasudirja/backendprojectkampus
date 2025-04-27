<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegStatusAktif;
use App\Services\ActivityLogger;

class SimpegStatusAktifController extends Controller
{
    public function index(Request $request)
    {
        $statusAktif = SimpegStatusAktif::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $statusAktif->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/status-aktif/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/status-aktif/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $statusAktif
        ]);
    }

    public function show(Request $request, $id)
    {
        $statusAktif = SimpegStatusAktif::find($id);

        if (!$statusAktif) {
            return response()->json(['success' => false, 'message' => 'Status Aktif tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $statusAktif,
            'update_url' => url("/api/{$prefix}/status-aktif/" . $statusAktif->id),
            'delete_url' => url("/api/{$prefix}/status-aktif/" . $statusAktif->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:2',
            'nama_status_aktif' => 'required|string|max:30',
            'status_keluar' => 'required|boolean',
        ]);

        $statusAktif = SimpegStatusAktif::create([
            'kode' => $request->kode,
            'nama_status_aktif' => $request->nama_status_aktif,
            'status_keluar' => $request->status_keluar,
        ]);

        ActivityLogger::log('create', $statusAktif, $statusAktif->toArray());

        return response()->json([
            'success' => true,
            'data' => $statusAktif,
            'message' => 'Status Aktif berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $statusAktif = SimpegStatusAktif::find($id);

        if (!$statusAktif) {
            return response()->json(['success' => false, 'message' => 'Status Aktif tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:2',
            'nama_status_aktif' => 'required|string|max:30',
            'status_keluar' => 'required|boolean',
        ]);

        $old = $statusAktif->getOriginal();

        $statusAktif->update([
            'kode' => $request->kode,
            'nama_status_aktif' => $request->nama_status_aktif,
            'status_keluar' => $request->status_keluar,
        ]);

        $changes = array_diff_assoc($statusAktif->toArray(), $old);
        ActivityLogger::log('update', $statusAktif, $changes);

        return response()->json([
            'success' => true,
            'data' => $statusAktif,
            'message' => 'Status Aktif berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $statusAktif = SimpegStatusAktif::find($id);
    
        if (!$statusAktif) {
            return response()->json(['success' => false, 'message' => 'Status Aktif tidak ditemukan'], 404);
        }
    
        $statusAktifData = $statusAktif->toArray();
    
        $statusAktif->delete();
    
        ActivityLogger::log('delete', $statusAktif, $statusAktifData);
    
        return response()->json([
            'success' => true,
            'message' => 'Status Aktif berhasil dihapus'
        ]);
    }
}