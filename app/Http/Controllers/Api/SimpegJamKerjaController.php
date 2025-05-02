<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegJamKerja;
use App\Services\ActivityLogger;

class SimpegJamKerjaController extends Controller
{
    public function index(Request $request)
    {
        $jamKerja = SimpegJamKerja::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $jamKerja->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jam-kerja/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jam-kerja/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jamKerja
        ]);
    }

    public function show(Request $request, $id)
    {
        $jamKerja = SimpegJamKerja::find($id);

        if (!$jamKerja) {
            return response()->json(['success' => false, 'message' => 'Jam kerja tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jamKerja,
            'update_url' => url("/api/{$prefix}/jam-kerja/" . $jamKerja->id),
            'delete_url' => url("/api/{$prefix}/jam-kerja/" . $jamKerja->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'jenis_jam_kerja' => 'required|string|max:50',
            'jam_normal' => 'required|boolean',
            'jam_datang' => 'required|string|max:20',
            'jam_pulang' => 'required|string|max:20',
        ]);

        $jamKerja = SimpegJamKerja::create([
            'jenis_jam_kerja' => $request->jenis_jam_kerja,
            'jam_normal' => $request->jam_normal,
            'jam_datang' => $request->jam_datang,
            'jam_pulang' => $request->jam_pulang,
        ]);

        ActivityLogger::log('create', $jamKerja, $jamKerja->toArray());

        return response()->json([
            'success' => true,
            'data' => $jamKerja,
            'message' => 'Jam kerja berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $jamKerja = SimpegJamKerja::find($id);

        if (!$jamKerja) {
            return response()->json(['success' => false, 'message' => 'Jam kerja tidak ditemukan'], 404);
        }

        $request->validate([
            'jenis_jam_kerja' => 'required|string|max:50',
            'jam_normal' => 'required|boolean',
            'jam_datang' => 'required|string|max:20',
            'jam_pulang' => 'required|string|max:20',
        ]);

        $old = $jamKerja->getOriginal();

        $jamKerja->update([
            'jenis_jam_kerja' => $request->jenis_jam_kerja,
            'jam_normal' => $request->jam_normal,
            'jam_datang' => $request->jam_datang,
            'jam_pulang' => $request->jam_pulang,
        ]);

        $changes = array_diff_assoc($jamKerja->toArray(), $old);
        ActivityLogger::log('update', $jamKerja, $changes);

        return response()->json([
            'success' => true,
            'data' => $jamKerja,
            'message' => 'Jam kerja berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jamKerja = SimpegJamKerja::find($id);
    
        if (!$jamKerja) {
            return response()->json(['success' => false, 'message' => 'Jam kerja tidak ditemukan'], 404);
        }
    
        $jamKerjaData = $jamKerja->toArray();
    
        $jamKerja->delete();
    
        ActivityLogger::log('delete', $jamKerja, $jamKerjaData);
    
        return response()->json([
            'success' => true,
            'message' => 'Jam kerja berhasil dihapus (soft delete)'
        ]);
    }
}