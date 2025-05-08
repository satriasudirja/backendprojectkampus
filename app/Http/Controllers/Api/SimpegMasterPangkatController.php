<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegMasterPangkat;
use App\Services\ActivityLogger;

class SimpegMasterPangkatController extends Controller
{
    public function index(Request $request)
    {
        $pangkat = SimpegMasterPangkat::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $pangkat->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/pangkat/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/pangkat/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $pangkat
        ]);
    }

    public function show(Request $request, $id)
    {
        $pangkat = SimpegMasterPangkat::find($id);

        if (!$pangkat) {
            return response()->json(['success' => false, 'message' => 'Pangkat tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $pangkat,
            'update_url' => url("/api/{$prefix}/pangkat/" . $pangkat->id),
            'delete_url' => url("/api/{$prefix}/pangkat/" . $pangkat->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pangkat' => 'required|string|max:6',
            'nama_golongan' => 'required|string|max:30',
        ]);

        $pangkat = SimpegMasterPangkat::create([
            'pangkat' => $request->pangkat,
            'nama_golongan' => $request->nama_golongan,
        ]);

        ActivityLogger::log('create', $pangkat, $pangkat->toArray());

        return response()->json([
            'success' => true,
            'data' => $pangkat,
            'message' => 'Pangkat berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $pangkat = SimpegMasterPangkat::find($id);

        if (!$pangkat) {
            return response()->json(['success' => false, 'message' => 'Pangkat tidak ditemukan'], 404);
        }

        $request->validate([
            'pangkat' => 'required|string|max:6',
            'nama_golongan' => 'required|string|max:30',
        ]);

        $old = $pangkat->getOriginal();

        $pangkat->update([
            'pangkat' => $request->pangkat,
            'nama_golongan' => $request->nama_golongan,
        ]);

        $changes = array_diff_assoc($pangkat->toArray(), $old);
        ActivityLogger::log('update', $pangkat, $changes);

        return response()->json([
            'success' => true,
            'data' => $pangkat,
            'message' => 'Pangkat berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $pangkat = SimpegMasterPangkat::find($id);
    
        if (!$pangkat) {
            return response()->json(['success' => false, 'message' => 'Pangkat tidak ditemukan'], 404);
        }
    
        $pangkatData = $pangkat->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $pangkat->delete(); // Soft delete
    
        ActivityLogger::log('delete', $pangkat, $pangkatData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Pangkat berhasil dihapus (soft delete)'
        ]);
    }
}