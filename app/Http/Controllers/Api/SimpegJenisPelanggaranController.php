<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegJenisPelanggaran;
use App\Services\ActivityLogger;

class SimpegJenisPelanggaranController extends Controller
{
    public function index(Request $request)
    {
        $jenisPelanggaran = SimpegJenisPelanggaran::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $jenisPelanggaran->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-pelanggaran/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-pelanggaran/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisPelanggaran
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisPelanggaran = SimpegJenisPelanggaran::find($id);

        if (!$jenisPelanggaran) {
            return response()->json(['success' => false, 'message' => 'Jenis pelanggaran tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisPelanggaran,
            'update_url' => url("/api/{$prefix}/jenis-pelanggaran/" . $jenisPelanggaran->id),
            'delete_url' => url("/api/{$prefix}/jenis-pelanggaran/" . $jenisPelanggaran->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:5',
            'nama_pelanggaran' => 'required|string|max:50',
        ]);

        $jenisPelanggaran = SimpegJenisPelanggaran::create([
            'kode' => $request->kode,
            'nama_pelanggaran' => $request->nama_pelanggaran,
        ]);

        ActivityLogger::log('create', $jenisPelanggaran, $jenisPelanggaran->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisPelanggaran,
            'message' => 'Jenis pelanggaran berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $jenisPelanggaran = SimpegJenisPelanggaran::find($id);

        if (!$jenisPelanggaran) {
            return response()->json(['success' => false, 'message' => 'Jenis pelanggaran tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:5',
            'nama_pelanggaran' => 'required|string|max:50',
        ]);

        $old = $jenisPelanggaran->getOriginal();

        $jenisPelanggaran->update([
            'kode' => $request->kode,
            'nama_pelanggaran' => $request->nama_pelanggaran,
        ]);

        $changes = array_diff_assoc($jenisPelanggaran->toArray(), $old);
        ActivityLogger::log('update', $jenisPelanggaran, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisPelanggaran,
            'message' => 'Jenis pelanggaran berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisPelanggaran = SimpegJenisPelanggaran::find($id);
    
        if (!$jenisPelanggaran) {
            return response()->json(['success' => false, 'message' => 'Jenis pelanggaran tidak ditemukan'], 404);
        }
    
        $jenisPelanggaranData = $jenisPelanggaran->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $jenisPelanggaran->delete(); // Soft delete
    
        ActivityLogger::log('delete', $jenisPelanggaran, $jenisPelanggaranData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Jenis pelanggaran berhasil dihapus (soft delete)'
        ]);
    }
}