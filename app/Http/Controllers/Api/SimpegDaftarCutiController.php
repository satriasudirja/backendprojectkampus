<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegDaftarCuti;
use App\Services\ActivityLogger;

class SimpegDaftarCutiController extends Controller
{
    public function index(Request $request)
    {
        $daftarCuti = SimpegDaftarCuti::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $daftarCuti->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/daftar-cuti/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/daftar-cuti/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $daftarCuti
        ]);
    }

    public function show(Request $request, $id)
    {
        $daftarCuti = SimpegDaftarCuti::find($id);

        if (!$daftarCuti) {
            return response()->json(['success' => false, 'message' => 'Data cuti tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $daftarCuti,
            'update_url' => url("/api/{$prefix}/daftar-cuti/" . $daftarCuti->id),
            'delete_url' => url("/api/{$prefix}/daftar-cuti/" . $daftarCuti->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:5',
            'nama_jenis_cuti' => 'required|string|max:50',
            'standar_cuti' => 'required|integer',
            'format_nomor_surat' => 'required|string|max:50',
            'keterangan' => 'required|string',
        ]);

        $daftarCuti = SimpegDaftarCuti::create([
            'kode' => $request->kode,
            'nama_jenis_cuti' => $request->nama_jenis_cuti,
            'standar_cuti' => $request->standar_cuti,
            'format_nomor_surat' => $request->format_nomor_surat,
            'keterangan' => $request->keterangan,
        ]);

        ActivityLogger::log('create', $daftarCuti, $daftarCuti->toArray());

        return response()->json([
            'success' => true,
            'data' => $daftarCuti,
            'message' => 'Data cuti berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $daftarCuti = SimpegDaftarCuti::find($id);

        if (!$daftarCuti) {
            return response()->json(['success' => false, 'message' => 'Data cuti tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:5',
            'nama_jenis_cuti' => 'required|string|max:50',
            'standar_cuti' => 'required|integer',
            'format_nomor_surat' => 'required|string|max:50',
            'keterangan' => 'required|string',
        ]);

        $old = $daftarCuti->getOriginal();

        $daftarCuti->update([
            'kode' => $request->kode,
            'nama_jenis_cuti' => $request->nama_jenis_cuti,
            'standar_cuti' => $request->standar_cuti,
            'format_nomor_surat' => $request->format_nomor_surat,
            'keterangan' => $request->keterangan,
        ]);

        $changes = array_diff_assoc($daftarCuti->toArray(), $old);
        ActivityLogger::log('update', $daftarCuti, $changes);

        return response()->json([
            'success' => true,
            'data' => $daftarCuti,
            'message' => 'Data cuti berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $daftarCuti = SimpegDaftarCuti::find($id);
    
        if (!$daftarCuti) {
            return response()->json(['success' => false, 'message' => 'Data cuti tidak ditemukan'], 404);
        }
    
        $daftarCutiData = $daftarCuti->toArray();
    
        $daftarCuti->delete();
    
        ActivityLogger::log('delete', $daftarCuti, $daftarCutiData);
    
        return response()->json([
            'success' => true,
            'message' => 'Data cuti berhasil dihapus'
        ]);
    }
}