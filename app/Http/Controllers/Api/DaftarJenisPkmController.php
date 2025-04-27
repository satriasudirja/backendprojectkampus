<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegDaftarJenisPkm;
use App\Services\ActivityLogger;

class DaftarJenisPkmController extends Controller
{
    public function index(Request $request)
    {
        $jenisPkm = SimpegDaftarJenisPkm::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $jenisPkm->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-pkm/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-pkm/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisPkm
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisPkm = SimpegDaftarJenisPkm::find($id);

        if (!$jenisPkm) {
            return response()->json(['success' => false, 'message' => 'Jenis PKM tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisPkm,
            'update_url' => url("/api/{$prefix}/jenis-pkm/" . $jenisPkm->id),
            'delete_url' => url("/api/{$prefix}/jenis-pkm/" . $jenisPkm->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:5|unique:daftar_jenis_pkm,kode',
            'nama_pkm' => 'required|string',
        ]);

        $jenisPkm = SimpegDaftarJenisPkm::create([
            'kode' => $request->kode,
            'nama_pkm' => $request->nama_pkm,
        ]);

        ActivityLogger::log('create', $jenisPkm, $jenisPkm->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisPkm,
            'message' => 'Jenis PKM berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $jenisPkm = SimpegDaftarJenisPkm::find($id);

        if (!$jenisPkm) {
            return response()->json(['success' => false, 'message' => 'Jenis PKM tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:5|unique:daftar_jenis_pkm,kode,'.$id,
            'nama_pkm' => 'required|string',
        ]);

        $old = $jenisPkm->getOriginal();

        $jenisPkm->update([
            'kode' => $request->kode,
            'nama_pkm' => $request->nama_pkm,
        ]);

        $changes = array_diff_assoc($jenisPkm->toArray(), $old);
        ActivityLogger::log('update', $jenisPkm, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisPkm,
            'message' => 'Jenis PKM berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisPkm = SimpegDaftarJenisPkm::find($id);
    
        if (!$jenisPkm) {
            return response()->json(['success' => false, 'message' => 'Jenis PKM tidak ditemukan'], 404);
        }
    
        $jenisPkmData = $jenisPkm->toArray();
    
        $jenisPkm->delete();
    
        ActivityLogger::log('delete', $jenisPkm, $jenisPkmData);
    
        return response()->json([
            'success' => true,
            'message' => 'Jenis PKM berhasil dihapus'
        ]);
    }
}