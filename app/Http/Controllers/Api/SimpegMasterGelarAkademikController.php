<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterGelarAkademik;
use App\Services\ActivityLogger; // Asumsikan Anda punya service ini
use Illuminate\Http\Request;

class SimpegMasterGelarAkademikController extends Controller
{
    /**
     * Menampilkan daftar gelar akademik dengan paginasi.
     */
    public function index(Request $request)
    {
        $gelar = MasterGelarAkademik::orderBy('created_at', 'desc')->paginate(10);

        // Menangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Menambahkan link update dan delete untuk setiap item
        $gelar->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gelar-akademik/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gelar-akademik/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gelar
        ]);
    }

    /**
     * Menampilkan satu data gelar akademik berdasarkan ID.
     */
    public function show(Request $request, $id)
    {
        $gelar = MasterGelarAkademik::find($id);

        if (!$gelar) {
            return response()->json(['success' => false, 'message' => 'Data gelar akademik tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $gelar,
            'update_url' => url("/api/{$prefix}/gelar-akademik/" . $gelar->id),
            'delete_url' => url("/api/{$prefix}/gelar-akademik/" . $gelar->id),
        ]);
    }

    /**
     * Menyimpan data gelar akademik baru.
     */
    public function store(Request $request)
    {
        $request->validate([
            'gelar' => 'required|string|max:20',
            'nama_gelar' => 'required|string|max:100',
        ]);

        $gelar = MasterGelarAkademik::create([
            'gelar' => $request->gelar,
            'nama_gelar' => $request->nama_gelar,
        ]);

        // ActivityLogger::log('create', $gelar, $gelar->toArray());

        return response()->json([
            'success' => true,
            'data' => $gelar,
            'message' => 'Data gelar akademik berhasil ditambahkan'
        ], 201);
    }

    /**
     * Memperbarui data gelar akademik.
     */
    public function update(Request $request, $id)
    {
        $gelar = MasterGelarAkademik::find($id);

        if (!$gelar) {
            return response()->json(['success' => false, 'message' => 'Data gelar akademik tidak ditemukan'], 404);
        }

        $request->validate([
            'gelar' => 'required|string|max:20',
            'nama_gelar' => 'required|string|max:100',
        ]);

        // $old = $gelar->getOriginal();

        $gelar->update($request->only(['gelar', 'nama_gelar']));

        // $changes = array_diff_assoc($gelar->toArray(), $old);
        // ActivityLogger::log('update', $gelar, $changes);

        return response()->json([
            'success' => true,
            'data' => $gelar,
            'message' => 'Data gelar akademik berhasil diperbarui'
        ]);
    }

    /**
     * Menghapus data gelar akademik (soft delete).
     */
    public function destroy($id)
    {
        $gelar = MasterGelarAkademik::find($id);

        if (!$gelar) {
            return response()->json(['success' => false, 'message' => 'Data gelar akademik tidak ditemukan'], 404);
        }

        // $gelarData = $gelar->toArray();

        $gelar->delete();

        // ActivityLogger::log('delete', $gelar, $gelarData);

        return response()->json([
            'success' => true,
            'message' => 'Data gelar akademik berhasil dihapus'
        ]);
    }
}