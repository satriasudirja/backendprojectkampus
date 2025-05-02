<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JenisJabatanStruktural;
use App\Services\ActivityLogger;

class SimpegJenisJabatanStrukturalController extends Controller
{
    public function index(Request $request)
    {
        $jenisJabatan = JenisJabatanStruktural::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $jenisJabatan->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-jabatan-struktural/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-jabatan-struktural/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisJabatan
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisJabatan = JenisJabatanStruktural::find($id);

        if (!$jenisJabatan) {
            return response()->json(['success' => false, 'message' => 'Jenis Jabatan Struktural tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisJabatan,
            'update_url' => url("/api/{$prefix}/jenis-jabatan-struktural/" . $jenisJabatan->id),
            'delete_url' => url("/api/{$prefix}/jenis-jabatan-struktural/" . $jenisJabatan->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:6',
            'jenis_jabatan_struktural' => 'required|string|max:30',
        ]);

        $jenisJabatan = JenisJabatanStruktural::create([
            'kode' => $request->kode,
            'jenis_jabatan_struktural' => $request->jenis_jabatan_struktural,
        ]);

        ActivityLogger::log('create', $jenisJabatan, $jenisJabatan->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisJabatan,
            'message' => 'Jenis Jabatan Struktural berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $jenisJabatan = JenisJabatanStruktural::find($id);

        if (!$jenisJabatan) {
            return response()->json(['success' => false, 'message' => 'Jenis Jabatan Struktural tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:6',
            'jenis_jabatan_struktural' => 'required|string|max:30',
        ]);

        $old = $jenisJabatan->getOriginal();

        $jenisJabatan->update([
            'kode' => $request->kode,
            'jenis_jabatan_struktural' => $request->jenis_jabatan_struktural,
        ]);

        $changes = array_diff_assoc($jenisJabatan->toArray(), $old);
        ActivityLogger::log('update', $jenisJabatan, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisJabatan,
            'message' => 'Jenis Jabatan Struktural berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisJabatan = JenisJabatanStruktural::find($id);
    
        if (!$jenisJabatan) {
            return response()->json(['success' => false, 'message' => 'Jenis Jabatan Struktural tidak ditemukan'], 404);
        }
    
        $jenisJabatanData = $jenisJabatan->toArray();
    
        $jenisJabatan->delete();
    
        ActivityLogger::log('delete', $jenisJabatan, $jenisJabatanData);
    
        return response()->json([
            'success' => true,
            'message' => 'Jenis Jabatan Struktural berhasil dihapus (soft delete)'
        ]);
    }
}