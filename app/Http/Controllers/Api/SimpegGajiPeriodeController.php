<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegGajiPeriode;
use App\Services\ActivityLogger;

class SimpegGajiPeriodeController extends Controller
{
    public function index(Request $request)
    {
        $gajiPeriode = SimpegGajiPeriode::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $gajiPeriode->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-periode/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-periode/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gajiPeriode
        ]);
    }

    public function show(Request $request, $id)
    {
        $gajiPeriode = SimpegGajiPeriode::find($id);

        if (!$gajiPeriode) {
            return response()->json(['success' => false, 'message' => 'Periode gaji tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $gajiPeriode,
            'update_url' => url("/api/{$prefix}/gaji-periode/" . $gajiPeriode->id),
            'delete_url' => url("/api/{$prefix}/gaji-periode/" . $gajiPeriode->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_periode' => 'required|string|max:50',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'status' => 'required|string|max:20|in:draft,proses,selesai',
        ]);

        $gajiPeriode = SimpegGajiPeriode::create([
            'nama_periode' => $request->nama_periode,
            'tgl_mulai' => $request->tgl_mulai,
            'tgl_selesai' => $request->tgl_selesai,
            'status' => $request->status,
        ]);

        ActivityLogger::log('create', $gajiPeriode, $gajiPeriode->toArray());

        return response()->json([
            'success' => true,
            'data' => $gajiPeriode,
            'message' => 'Periode gaji berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $gajiPeriode = SimpegGajiPeriode::find($id);

        if (!$gajiPeriode) {
            return response()->json(['success' => false, 'message' => 'Periode gaji tidak ditemukan'], 404);
        }

        $request->validate([
            'nama_periode' => 'required|string|max:50',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'status' => 'required|string|max:20|in:draft,proses,selesai',
        ]);

        $old = $gajiPeriode->getOriginal();

        $gajiPeriode->update([
            'nama_periode' => $request->nama_periode,
            'tgl_mulai' => $request->tgl_mulai,
            'tgl_selesai' => $request->tgl_selesai,
            'status' => $request->status,
        ]);

        $changes = array_diff_assoc($gajiPeriode->toArray(), $old);
        ActivityLogger::log('update', $gajiPeriode, $changes);

        return response()->json([
            'success' => true,
            'data' => $gajiPeriode,
            'message' => 'Periode gaji berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $gajiPeriode = SimpegGajiPeriode::find($id);
    
        if (!$gajiPeriode) {
            return response()->json(['success' => false, 'message' => 'Periode gaji tidak ditemukan'], 404);
        }
    
        $gajiPeriodeData = $gajiPeriode->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $gajiPeriode->delete(); // Soft delete
    
        ActivityLogger::log('delete', $gajiPeriode, $gajiPeriodeData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Periode gaji berhasil dihapus (soft delete)'
        ]);
    }
}