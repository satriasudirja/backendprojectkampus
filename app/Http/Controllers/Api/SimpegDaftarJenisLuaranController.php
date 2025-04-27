<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegDaftarJenisLuaran;
use App\Services\ActivityLogger;

class SimpegDaftarJenisLuaranController extends Controller
{
    public function index(Request $request)
    {
        $jenisLuaran = SimpegDaftarJenisLuaran::withTrashed()->orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $jenisLuaran->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-luaran/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-luaran/" . $item->id);
            $item->restore_url = $item->trashed() ? url("/api/{$prefix}/jenis-luaran/" . $item->id . "/restore") : null;
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisLuaran
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisLuaran = SimpegDaftarJenisLuaran::withTrashed()->find($id);

        if (!$jenisLuaran) {
            return response()->json(['success' => false, 'message' => 'Jenis Luaran tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisLuaran,
            'update_url' => url("/api/{$prefix}/jenis-luaran/" . $jenisLuaran->id),
            'delete_url' => url("/api/{$prefix}/jenis-luaran/" . $jenisLuaran->id),
            'restore_url' => $jenisLuaran->trashed() ? url("/api/{$prefix}/jenis-luaran/" . $jenisLuaran->id . "/restore") : null,
            'luaran_records' => $jenisLuaran->luaranRecords,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:50|unique:simpeg_daftar_jenis_luaran,kode',
            'jenis_luaran' => 'required|string|max:255',
        ]);

        $jenisLuaran = SimpegDaftarJenisLuaran::create([
            'kode' => $request->kode,
            'jenis_luaran' => $request->jenis_luaran,
        ]);

        ActivityLogger::log('create', $jenisLuaran, $jenisLuaran->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisLuaran,
            'message' => 'Jenis Luaran berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $jenisLuaran = SimpegDaftarJenisLuaran::find($id);

        if (!$jenisLuaran) {
            return response()->json(['success' => false, 'message' => 'Jenis Luaran tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:50|unique:simpeg_daftar_jenis_luaran,kode,'.$id,
            'jenis_luaran' => 'required|string|max:255',
        ]);

        $old = $jenisLuaran->getOriginal();

        $jenisLuaran->update([
            'kode' => $request->kode,
            'jenis_luaran' => $request->jenis_luaran,
        ]);

        $changes = array_diff_assoc($jenisLuaran->toArray(), $old);
        ActivityLogger::log('update', $jenisLuaran, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisLuaran,
            'message' => 'Jenis Luaran berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisLuaran = SimpegDaftarJenisLuaran::find($id);
    
        if (!$jenisLuaran) {
            return response()->json(['success' => false, 'message' => 'Jenis Luaran tidak ditemukan'], 404);
        }
    
        $jenisLuaranData = $jenisLuaran->toArray();
    
        $jenisLuaran->delete(); // Soft delete
    
        ActivityLogger::log('delete', $jenisLuaran, $jenisLuaranData);
    
        return response()->json([
            'success' => true,
            'message' => 'Jenis Luaran berhasil dihapus (soft delete)'
        ]);
    }

    public function restore($id)
    {
        $jenisLuaran = SimpegDaftarJenisLuaran::withTrashed()->find($id);
    
        if (!$jenisLuaran) {
            return response()->json(['success' => false, 'message' => 'Jenis Luaran tidak ditemukan'], 404);
        }
    
        if (!$jenisLuaran->trashed()) {
            return response()->json(['success' => false, 'message' => 'Jenis Luaran tidak dalam status terhapus'], 400);
        }
    
        $jenisLuaran->restore();
    
        ActivityLogger::log('restore', $jenisLuaran, $jenisLuaran->toArray());
    
        return response()->json([
            'success' => true,
            'data' => $jenisLuaran,
            'message' => 'Jenis Luaran berhasil dipulihkan'
        ]);
    }
}