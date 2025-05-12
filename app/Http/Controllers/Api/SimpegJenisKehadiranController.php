<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegJenisKehadiran;
use App\Services\ActivityLogger;

class SimpegJenisKehadiranController extends Controller
{
    public function index(Request $request)
    {
        $jenisKehadiran = SimpegJenisKehadiran::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $jenisKehadiran->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-kehadiran/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-kehadiran/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisKehadiran
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisKehadiran = SimpegJenisKehadiran::find($id);

        if (!$jenisKehadiran) {
            return response()->json(['success' => false, 'message' => 'Jenis kehadiran tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisKehadiran,
            'update_url' => url("/api/{$prefix}/jenis-kehadiran/" . $jenisKehadiran->id),
            'delete_url' => url("/api/{$prefix}/jenis-kehadiran/" . $jenisKehadiran->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_jenis' => 'required|string|max:2',
            'nama_jenis' => 'required|string|max:50',
        ]);

        $jenisKehadiran = SimpegJenisKehadiran::create([
            'kode_jenis' => $request->kode_jenis,
            'nama_jenis' => $request->nama_jenis,
        ]);

        ActivityLogger::log('create', $jenisKehadiran, $jenisKehadiran->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisKehadiran,
            'message' => 'Jenis kehadiran berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $jenisKehadiran = SimpegJenisKehadiran::find($id);

        if (!$jenisKehadiran) {
            return response()->json(['success' => false, 'message' => 'Jenis kehadiran tidak ditemukan'], 404);
        }

        $request->validate([
            'kode_jenis' => 'required|string|max:2',
            'nama_jenis' => 'required|string|max:50',
        ]);

        $old = $jenisKehadiran->getOriginal();

        $jenisKehadiran->update([
            'kode_jenis' => $request->kode_jenis,
            'nama_jenis' => $request->nama_jenis,
        ]);

        $changes = array_diff_assoc($jenisKehadiran->toArray(), $old);
        ActivityLogger::log('update', $jenisKehadiran, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisKehadiran,
            'message' => 'Jenis kehadiran berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisKehadiran = SimpegJenisKehadiran::find($id);
    
        if (!$jenisKehadiran) {
            return response()->json(['success' => false, 'message' => 'Jenis kehadiran tidak ditemukan'], 404);
        }
    
        $jenisKehadiranData = $jenisKehadiran->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $jenisKehadiran->delete(); // Soft delete
    
        ActivityLogger::log('delete', $jenisKehadiran, $jenisKehadiranData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Jenis kehadiran berhasil dihapus (soft delete)'
        ]);
    }
}