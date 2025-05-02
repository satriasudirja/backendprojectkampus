<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegJabatanStruktural;
use App\Services\ActivityLogger;

class SimpegJabatanStrukturalController extends Controller
{
    public function index(Request $request)
    {
        $jabatan = SimpegJabatanStruktural::with(['unitKerja', 'jenisJabatanStruktural', 'pangkat', 'eselon'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Tangkap prefix dari URL
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $jabatan->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jabatan-struktural/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jabatan-struktural/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jabatan
        ]);
    }

    public function show(Request $request, $id)
    {
        $jabatan = SimpegJabatanStruktural::with(['unitKerja', 'jenisJabatanStruktural', 'pangkat', 'eselon'])
            ->find($id);

        if (!$jabatan) {
            return response()->json(['success' => false, 'message' => 'Jabatan Struktural tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jabatan,
            'update_url' => url("/api/{$prefix}/jabatan-struktural/" . $jabatan->id),
            'delete_url' => url("/api/{$prefix}/jabatan-struktural/" . $jabatan->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'unit_kerja_id' => 'required|integer',
            'jenis_jabatan_struktural_id' => 'required|integer',
            'pangkat_id' => 'required|integer',
            'eselon_id' => 'required|integer',
            'kode' => 'required|string|max:5',
            'singkatan' => 'required|string|max:50',
            'alamat_email' => 'required|email|max:100',
            'beban_sks' => 'required|integer',
            'is_pimpinan' => 'boolean',
            'aktif' => 'boolean',
            'keterangan' => 'nullable|string',
            'parent_jabatan' => 'nullable|string|max:100'
        ]);

        $jabatan = SimpegJabatanStruktural::create([
            'unit_kerja_id' => $request->unit_kerja_id,
            'jenis_jabatan_struktural_id' => $request->jenis_jabatan_struktural_id,
            'pangkat_id' => $request->pangkat_id,
            'eselon_id' => $request->eselon_id,
            'kode' => $request->kode,
            'singkatan' => $request->singkatan,
            'alamat_email' => $request->alamat_email,
            'beban_sks' => $request->beban_sks,
            'is_pimpinan' => $request->is_pimpinan ?? false,
            'aktif' => $request->aktif ?? true,
            'keterangan' => $request->keterangan,
            'parent_jabatan' => $request->parent_jabatan
        ]);

        ActivityLogger::log('create', $jabatan, $jabatan->toArray());

        return response()->json([
            'success' => true,
            'data' => $jabatan,
            'message' => 'Jabatan Struktural berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $jabatan = SimpegJabatanStruktural::find($id);

        if (!$jabatan) {
            return response()->json(['success' => false, 'message' => 'Jabatan Struktural tidak ditemukan'], 404);
        }

        $request->validate([
            'unit_kerja_id' => 'required|integer',
            'jenis_jabatan_struktural_id' => 'required|integer',
            'pangkat_id' => 'required|integer',
            'eselon_id' => 'required|integer',
            'kode' => 'required|string|max:5',
            'singkatan' => 'required|string|max:50',
            'alamat_email' => 'required|email|max:100',
            'beban_sks' => 'required|integer',
            'is_pimpinan' => 'boolean',
            'aktif' => 'boolean',
            'keterangan' => 'nullable|string',
            'parent_jabatan' => 'nullable|string|max:100'
        ]);

        $old = $jabatan->getOriginal();

        $jabatan->update([
            'unit_kerja_id' => $request->unit_kerja_id,
            'jenis_jabatan_struktural_id' => $request->jenis_jabatan_struktural_id,
            'pangkat_id' => $request->pangkat_id,
            'eselon_id' => $request->eselon_id,
            'kode' => $request->kode,
            'singkatan' => $request->singkatan,
            'alamat_email' => $request->alamat_email,
            'beban_sks' => $request->beban_sks,
            'is_pimpinan' => $request->is_pimpinan ?? $jabatan->is_pimpinan,
            'aktif' => $request->aktif ?? $jabatan->aktif,
            'keterangan' => $request->keterangan,
            'parent_jabatan' => $request->parent_jabatan
        ]);

        $changes = array_diff_assoc($jabatan->toArray(), $old);
        ActivityLogger::log('update', $jabatan, $changes);

        return response()->json([
            'success' => true,
            'data' => $jabatan,
            'message' => 'Jabatan Struktural berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jabatan = SimpegJabatanStruktural::find($id);
    
        if (!$jabatan) {
            return response()->json(['success' => false, 'message' => 'Jabatan Struktural tidak ditemukan'], 404);
        }
    
        $jabatanData = $jabatan->toArray();
    
        $jabatan->delete();
    
        ActivityLogger::log('delete', $jabatan, $jabatanData);
    
        return response()->json([
            'success' => true,
            'message' => 'Jabatan Struktural berhasil dihapus (soft delete)'
        ]);
    }
}