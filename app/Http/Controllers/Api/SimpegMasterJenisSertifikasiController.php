<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegMasterJenisSertifikasi;
use App\Services\ActivityLogger;

class SimpegMasterJenisSertifikasiController extends Controller
{
    public function index(Request $request)
    {
        $jenisSertifikasi = SimpegMasterJenisSertifikasi::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $jenisSertifikasi->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-sertifikasi/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-sertifikasi/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisSertifikasi
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisSertifikasi = SimpegMasterJenisSertifikasi::find($id);

        if (!$jenisSertifikasi) {
            return response()->json(['success' => false, 'message' => 'Jenis sertifikasi tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisSertifikasi,
            'update_url' => url("/api/{$prefix}/jenis-sertifikasi/" . $jenisSertifikasi->id),
            'delete_url' => url("/api/{$prefix}/jenis-sertifikasi/" . $jenisSertifikasi->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:5',
            'nama_sertifikasi' => 'required|string|max:50',
            'jenis_sertifikasi' => 'required|string|max:50',
        ]);

        $jenisSertifikasi = SimpegMasterJenisSertifikasi::create([
            'kode' => $request->kode,
            'nama_sertifikasi' => $request->nama_sertifikasi,
            'jenis_sertifikasi' => $request->jenis_sertifikasi,
        ]);

        ActivityLogger::log('create', $jenisSertifikasi, $jenisSertifikasi->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisSertifikasi,
            'message' => 'Jenis sertifikasi berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $jenisSertifikasi = SimpegMasterJenisSertifikasi::find($id);

        if (!$jenisSertifikasi) {
            return response()->json(['success' => false, 'message' => 'Jenis sertifikasi tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:5',
            'nama_sertifikasi' => 'required|string|max:50',
            'jenis_sertifikasi' => 'required|string|max:50',
        ]);

        $old = $jenisSertifikasi->getOriginal();

        $jenisSertifikasi->update([
            'kode' => $request->kode,
            'nama_sertifikasi' => $request->nama_sertifikasi,
            'jenis_sertifikasi' => $request->jenis_sertifikasi,
        ]);

        $changes = array_diff_assoc($jenisSertifikasi->toArray(), $old);
        ActivityLogger::log('update', $jenisSertifikasi, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisSertifikasi,
            'message' => 'Jenis sertifikasi berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisSertifikasi = SimpegMasterJenisSertifikasi::find($id);
    
        if (!$jenisSertifikasi) {
            return response()->json(['success' => false, 'message' => 'Jenis sertifikasi tidak ditemukan'], 404);
        }
    
        $jenisSertifikasiData = $jenisSertifikasi->toArray();
    
        $jenisSertifikasi->delete();
    
        ActivityLogger::log('delete', $jenisSertifikasi, $jenisSertifikasiData);
    
        return response()->json([
            'success' => true,
            'message' => 'Jenis sertifikasi berhasil dihapus (soft delete)'
        ]);
    }
}