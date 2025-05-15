<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegKategoriSertifikasi;
use App\Services\ActivityLogger;

class SimpegKategoriSertifikasiController extends Controller
{
    public function index(Request $request)
    {
        $kategoriSertifikasi = SimpegKategoriSertifikasi::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $kategoriSertifikasi->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/kategori-sertifikasi/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/kategori-sertifikasi/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $kategoriSertifikasi
        ]);
    }

    public function show(Request $request, $id)
    {
        $kategoriSertifikasi = SimpegKategoriSertifikasi::find($id);

        if (!$kategoriSertifikasi) {
            return response()->json(['success' => false, 'message' => 'Kategori Sertifikasi tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $kategoriSertifikasi,
            'update_url' => url("/api/{$prefix}/kategori-sertifikasi/" . $kategoriSertifikasi->id),
            'delete_url' => url("/api/{$prefix}/kategori-sertifikasi/" . $kategoriSertifikasi->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kategori_sertifikasi' => 'required|string|max:50',
        ]);

        $kategoriSertifikasi = SimpegKategoriSertifikasi::create([
            'kategori_sertifikasi' => $request->kategori_sertifikasi,
        ]);

        ActivityLogger::log('create', $kategoriSertifikasi, $kategoriSertifikasi->toArray());

        return response()->json([
            'success' => true,
            'data' => $kategoriSertifikasi,
            'message' => 'Kategori Sertifikasi berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $kategoriSertifikasi = SimpegKategoriSertifikasi::find($id);

        if (!$kategoriSertifikasi) {
            return response()->json(['success' => false, 'message' => 'Kategori Sertifikasi tidak ditemukan'], 404);
        }

        $request->validate([
            'kategori_sertifikasi' => 'required|string|max:50',
        ]);

        $old = $kategoriSertifikasi->getOriginal();

        $kategoriSertifikasi->update([
            'kategori_sertifikasi' => $request->kategori_sertifikasi,
        ]);

        $changes = array_diff_assoc($kategoriSertifikasi->toArray(), $old);
        ActivityLogger::log('update', $kategoriSertifikasi, $changes);

        return response()->json([
            'success' => true,
            'data' => $kategoriSertifikasi,
            'message' => 'Kategori Sertifikasi berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $kategoriSertifikasi = SimpegKategoriSertifikasi::find($id);
    
        if (!$kategoriSertifikasi) {
            return response()->json(['success' => false, 'message' => 'Kategori Sertifikasi tidak ditemukan'], 404);
        }
    
        $kategoriSertifikasiData = $kategoriSertifikasi->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $kategoriSertifikasi->delete(); // Soft delete
    
        ActivityLogger::log('delete', $kategoriSertifikasi, $kategoriSertifikasiData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Kategori Sertifikasi berhasil dihapus (soft delete)'
        ]);
    }
}