<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegJenisKenaikanPangkat;
use App\Services\ActivityLogger;

class SimpegJenisKenaikanPangkatController extends Controller
{
    public function index(Request $request)
    {
        $jenisKenaikanPangkat = SimpegJenisKenaikanPangkat::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $jenisKenaikanPangkat->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-kenaikan-pangkat/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-kenaikan-pangkat/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisKenaikanPangkat
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisKenaikanPangkat = SimpegJenisKenaikanPangkat::find($id);

        if (!$jenisKenaikanPangkat) {
            return response()->json(['success' => false, 'message' => 'Jenis kenaikan pangkat tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisKenaikanPangkat,
            'update_url' => url("/api/{$prefix}/jenis-kenaikan-pangkat/" . $jenisKenaikanPangkat->id),
            'delete_url' => url("/api/{$prefix}/jenis-kenaikan-pangkat/" . $jenisKenaikanPangkat->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:2',
            'jenis_pangkat' => 'required|string|max:20',
        ]);

        $jenisKenaikanPangkat = SimpegJenisKenaikanPangkat::create([
            'kode' => $request->kode,
            'jenis_pangkat' => $request->jenis_pangkat,
        ]);

        ActivityLogger::log('create', $jenisKenaikanPangkat, $jenisKenaikanPangkat->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisKenaikanPangkat,
            'message' => 'Jenis kenaikan pangkat berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $jenisKenaikanPangkat = SimpegJenisKenaikanPangkat::find($id);

        if (!$jenisKenaikanPangkat) {
            return response()->json(['success' => false, 'message' => 'Jenis kenaikan pangkat tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:2',
            'jenis_pangkat' => 'required|string|max:20',
        ]);

        $old = $jenisKenaikanPangkat->getOriginal();

        $jenisKenaikanPangkat->update([
            'kode' => $request->kode,
            'jenis_pangkat' => $request->jenis_pangkat,
        ]);

        $changes = array_diff_assoc($jenisKenaikanPangkat->toArray(), $old);
        ActivityLogger::log('update', $jenisKenaikanPangkat, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisKenaikanPangkat,
            'message' => 'Jenis kenaikan pangkat berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisKenaikanPangkat = SimpegJenisKenaikanPangkat::find($id);
    
        if (!$jenisKenaikanPangkat) {
            return response()->json(['success' => false, 'message' => 'Jenis kenaikan pangkat tidak ditemukan'], 404);
        }
    
        $jenisKenaikanPangkatData = $jenisKenaikanPangkat->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $jenisKenaikanPangkat->delete(); // Soft delete
    
        ActivityLogger::log('delete', $jenisKenaikanPangkat, $jenisKenaikanPangkatData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Jenis kenaikan pangkat berhasil dihapus (soft delete)'
        ]);
    }
}