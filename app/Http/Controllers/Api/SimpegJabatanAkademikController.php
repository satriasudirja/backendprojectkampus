<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegJabatanAkademik;
use App\Services\ActivityLogger;

class SimpegJabatanAkademikController extends Controller
{
    public function index(Request $request)
    {
        $jabatanAkademik = SimpegJabatanAkademik::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $jabatanAkademik->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jabatan-akademik/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jabatan-akademik/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jabatanAkademik
        ]);
    }

    public function show(Request $request, $id)
    {
        $jabatanAkademik = SimpegJabatanAkademik::find($id);

        if (!$jabatanAkademik) {
            return response()->json(['success' => false, 'message' => 'Jabatan Akademik tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jabatanAkademik,
            'update_url' => url("/api/{$prefix}/jabatan-akademik/" . $jabatanAkademik->id),
            'delete_url' => url("/api/{$prefix}/jabatan-akademik/" . $jabatanAkademik->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'role_id' => 'required|uuid',
            'kode' => 'required|string|max:2',
            'jabatan_akademik' => 'required|string|max:50',
        ]);

        $jabatanAkademik = SimpegJabatanAkademik::create([
            'role_id' => $request->role_id,
            'kode' => $request->kode,
            'jabatan_akademik' => $request->jabatan_akademik,
        ]);

        ActivityLogger::log('create', $jabatanAkademik, $jabatanAkademik->toArray());

        return response()->json([
            'success' => true,
            'data' => $jabatanAkademik,
            'message' => 'Jabatan Akademik berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $jabatanAkademik = SimpegJabatanAkademik::find($id);

        if (!$jabatanAkademik) {
            return response()->json(['success' => false, 'message' => 'Jabatan Akademik tidak ditemukan'], 404);
        }

        $request->validate([
            'role_id' => 'required|uuid',
            'kode' => 'required|string|max:2',
            'jabatan_akademik' => 'required|string|max:50',
        ]);

        $old = $jabatanAkademik->getOriginal();

        $jabatanAkademik->update([
            'role_id' => $request->role_id,
            'kode' => $request->kode,
            'jabatan_akademik' => $request->jabatan_akademik,
        ]);

        $changes = array_diff_assoc($jabatanAkademik->toArray(), $old);
        ActivityLogger::log('update', $jabatanAkademik, $changes);

        return response()->json([
            'success' => true,
            'data' => $jabatanAkademik,
            'message' => 'Jabatan Akademik berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jabatanAkademik = SimpegJabatanAkademik::find($id);
    
        if (!$jabatanAkademik) {
            return response()->json(['success' => false, 'message' => 'Jabatan Akademik tidak ditemukan'], 404);
        }
    
        $jabatanAkademikData = $jabatanAkademik->toArray();
    
        $jabatanAkademik->delete();
    
        ActivityLogger::log('delete', $jabatanAkademik, $jabatanAkademikData);
    
        return response()->json([
            'success' => true,
            'message' => 'Jabatan Akademik berhasil dihapus'
        ]);
    }
}