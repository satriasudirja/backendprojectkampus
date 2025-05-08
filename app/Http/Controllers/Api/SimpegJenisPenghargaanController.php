<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegJenisPenghargaan;
use App\Services\ActivityLogger;

class SimpegJenisPenghargaanController extends Controller
{
    public function index(Request $request)
    {
        $jenisPenghargaan = SimpegJenisPenghargaan::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $jenisPenghargaan->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-penghargaan/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-penghargaan/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisPenghargaan
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisPenghargaan = SimpegJenisPenghargaan::find($id);

        if (!$jenisPenghargaan) {
            return response()->json(['success' => false, 'message' => 'Jenis penghargaan tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisPenghargaan,
            'update_url' => url("/api/{$prefix}/jenis-penghargaan/" . $jenisPenghargaan->id),
            'delete_url' => url("/api/{$prefix}/jenis-penghargaan/" . $jenisPenghargaan->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:5',
            'nama' => 'required|string|max:50',  // Diubah dari nama_penghargaan menjadi nama
        ]);

        $jenisPenghargaan = SimpegJenisPenghargaan::create([
            'kode' => $request->kode,
            'nama' => $request->nama,  // Diubah dari nama_penghargaan menjadi nama
        ]);

        ActivityLogger::log('create', $jenisPenghargaan, $jenisPenghargaan->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisPenghargaan,
            'message' => 'Jenis penghargaan berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $jenisPenghargaan = SimpegJenisPenghargaan::find($id);

        if (!$jenisPenghargaan) {
            return response()->json(['success' => false, 'message' => 'Jenis penghargaan tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:5',
            'nama' => 'required|string|max:50',  // Diubah dari nama_penghargaan menjadi nama
        ]);

        $old = $jenisPenghargaan->getOriginal();

        $jenisPenghargaan->update([
            'kode' => $request->kode,
            'nama' => $request->nama,  // Diubah dari nama_penghargaan menjadi nama
        ]);

        $changes = array_diff_assoc($jenisPenghargaan->toArray(), $old);
        ActivityLogger::log('update', $jenisPenghargaan, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisPenghargaan,
            'message' => 'Jenis penghargaan berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisPenghargaan = SimpegJenisPenghargaan::find($id);
    
        if (!$jenisPenghargaan) {
            return response()->json(['success' => false, 'message' => 'Jenis penghargaan tidak ditemukan'], 404);
        }
    
        $jenisPenghargaanData = $jenisPenghargaan->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $jenisPenghargaan->delete(); // Soft delete
    
        ActivityLogger::log('delete', $jenisPenghargaan, $jenisPenghargaanData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Jenis penghargaan berhasil dihapus (soft delete)'
        ]);
    }
}