<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JenisHari;
use App\Services\ActivityLogger;

class SimpegJenisHariController extends Controller
{
    public function index(Request $request)
    {
        $jenisHari = JenisHari::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $jenisHari->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-hari/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-hari/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisHari
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisHari = JenisHari::find($id);

        if (!$jenisHari) {
            return response()->json(['success' => false, 'message' => 'Data jenis hari tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisHari,
            "update_url" => url("/api/{$prefix}/jenis-hari/" . $jenisHari->id),
            "delete_url" => url("/api/{$prefix}/jenis-hari/" . $jenisHari->id)
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:2',
            'nama_hari' => 'required|string|max:10',
            'jenis_hari' => 'required|boolean',
        ]);

        $jenisHari = JenisHari::create([
            'kode' => $request->kode,
            'nama_hari' => $request->nama_hari,
            'jenis_hari' => $request->jenis_hari,
        ]);

        ActivityLogger::log('create', $jenisHari, $jenisHari->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisHari,
            'message' => 'Data jenis hari berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $jenisHari = JenisHari::find($id);

        if (!$jenisHari) {
            return response()->json(['success' => false, 'message' => 'Data jenis hari tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:2',
            'nama_hari' => 'required|string|max:10',
            'jenis_hari' => 'required|boolean',
        ]);

        $old = $jenisHari->getOriginal();

        $jenisHari->update([
            'kode' => $request->kode,
            'nama_hari' => $request->nama_hari,
            'jenis_hari' => $request->jenis_hari,
        ]);

        $changes = array_diff_assoc($jenisHari->toArray(), $old);
        ActivityLogger::log('update', $jenisHari, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisHari,
            'message' => 'Data jenis hari berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisHari = JenisHari::find($id);
    
        if (!$jenisHari) {
            return response()->json(['success' => false, 'message' => 'Data jenis hari tidak ditemukan'], 404);
        }
    
        $jenisHariData = $jenisHari->toArray();
    
        $jenisHari->delete();
    
        ActivityLogger::log('delete', $jenisHari, $jenisHariData);
    
        return response()->json([
            'success' => true,
            'message' => 'Data jenis hari berhasil dihapus'
        ]);
    }
}