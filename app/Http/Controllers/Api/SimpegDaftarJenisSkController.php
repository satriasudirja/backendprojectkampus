<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegDaftarJenisSk;
use App\Services\ActivityLogger;

class SimpegDaftarJenisSkController extends Controller
{
    public function index(Request $request)
    {
        $jenisSk = SimpegDaftarJenisSk::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $jenisSk->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-sk/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-sk/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisSk
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisSk = SimpegDaftarJenisSk::find($id);

        if (!$jenisSk) {
            return response()->json(['success' => false, 'message' => 'Jenis SK tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisSk,
            'update_url' => url("/api/{$prefix}/jenis-sk/" . $jenisSk->id),
            'delete_url' => url("/api/{$prefix}/jenis-sk/" . $jenisSk->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:5|unique:simpeg_daftar_jenis_sk,kode',
            'jenis_sk' => 'required|string|max:20',
        ]);

        $jenisSk = SimpegDaftarJenisSk::create([
            'kode' => $request->kode,
            'jenis_sk' => $request->jenis_sk,
        ]);

        ActivityLogger::log('create', $jenisSk, $jenisSk->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisSk,
            'message' => 'Jenis SK berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $jenisSk = SimpegDaftarJenisSk::find($id);

        if (!$jenisSk) {
            return response()->json(['success' => false, 'message' => 'Jenis SK tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:5|unique:simpeg_daftar_jenis_sk,kode,'.$id,
            'jenis_sk' => 'required|string|max:20',
        ]);

        $old = $jenisSk->getOriginal();

        $jenisSk->update([
            'kode' => $request->kode,
            'jenis_sk' => $request->jenis_sk,
        ]);

        $changes = array_diff_assoc($jenisSk->toArray(), $old);
        ActivityLogger::log('update', $jenisSk, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisSk,
            'message' => 'Jenis SK berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisSk = SimpegDaftarJenisSk::find($id);
    
        if (!$jenisSk) {
            return response()->json(['success' => false, 'message' => 'Jenis SK tidak ditemukan'], 404);
        }
    
        $jenisSkData = $jenisSk->toArray();
    
        $jenisSk->delete();
    
        ActivityLogger::log('delete', $jenisSk, $jenisSkData);
    
        return response()->json([
            'success' => true,
            'message' => 'Jenis SK berhasil dihapus'
        ]);
    }
}