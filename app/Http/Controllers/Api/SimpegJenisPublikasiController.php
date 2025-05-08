<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegJenisPublikasi;
use App\Services\ActivityLogger;

class SimpegJenisPublikasiController extends Controller
{
    public function index(Request $request)
    {
        $jenisPublikasi = SimpegJenisPublikasi::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $jenisPublikasi->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-publikasi/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-publikasi/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisPublikasi
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisPublikasi = SimpegJenisPublikasi::find($id);

        if (!$jenisPublikasi) {
            return response()->json(['success' => false, 'message' => 'Jenis publikasi tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisPublikasi,
            'update_url' => url("/api/{$prefix}/jenis-publikasi/" . $jenisPublikasi->id),
            'delete_url' => url("/api/{$prefix}/jenis-publikasi/" . $jenisPublikasi->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:5',
            'jenis_publikasi' => 'required|string|max:50',
        ]);

        $jenisPublikasi = SimpegJenisPublikasi::create([
            'kode' => $request->kode,
            'jenis_publikasi' => $request->jenis_publikasi,
        ]);

        ActivityLogger::log('create', $jenisPublikasi, $jenisPublikasi->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisPublikasi,
            'message' => 'Jenis publikasi berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $jenisPublikasi = SimpegJenisPublikasi::find($id);

        if (!$jenisPublikasi) {
            return response()->json(['success' => false, 'message' => 'Jenis publikasi tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:5',
            'jenis_publikasi' => 'required|string|max:50',
        ]);

        $old = $jenisPublikasi->getOriginal();

        $jenisPublikasi->update([
            'kode' => $request->kode,
            'jenis_publikasi' => $request->jenis_publikasi,
        ]);

        $changes = array_diff_assoc($jenisPublikasi->toArray(), $old);
        ActivityLogger::log('update', $jenisPublikasi, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisPublikasi,
            'message' => 'Jenis publikasi berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisPublikasi = SimpegJenisPublikasi::find($id);
    
        if (!$jenisPublikasi) {
            return response()->json(['success' => false, 'message' => 'Jenis publikasi tidak ditemukan'], 404);
        }
    
        $jenisPublikasiData = $jenisPublikasi->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $jenisPublikasi->delete(); // Soft delete
    
        ActivityLogger::log('delete', $jenisPublikasi, $jenisPublikasiData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Jenis publikasi berhasil dihapus (soft delete)'
        ]);
    }
}