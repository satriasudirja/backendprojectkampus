<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegJenisIzin;
use App\Services\ActivityLogger;

class SimpegJenisIzinController extends Controller
{
    public function index(Request $request)
    {
        $jenisIzin = SimpegJenisIzin::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $jenisIzin->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-izin/" . $item->kode);
            $item->delete_url = url("/api/{$prefix}/jenis-izin/" . $item->kode);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisIzin
        ]);
    }

    public function show(Request $request, $kode)
    {
        $jenisIzin = SimpegJenisIzin::find($kode);

        if (!$jenisIzin) {
            return response()->json(['success' => false, 'message' => 'Jenis izin tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisIzin,
            'update_url' => url("/api/{$prefix}/jenis-izin/" . $jenisIzin->kode),
            'delete_url' => url("/api/{$prefix}/jenis-izin/" . $jenisIzin->kode),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:3|unique:jenis_izin,kode',
            'jenis_izin' => 'required|string|max:50',
            'status_presensi' => 'required|string|max:30',
            'maksimal' => 'required|integer|min:0|max:99',
            'potong_cuti' => 'required|boolean',
        ]);

        $jenisIzin = SimpegJenisIzin::create([
            'kode' => $request->kode,
            'jenis_izin' => $request->jenis_izin,
            'status_presensi' => $request->status_presensi,
            'maksimal' => $request->maksimal,
            'potong_cuti' => $request->potong_cuti,
        ]);

        ActivityLogger::log('create', $jenisIzin, $jenisIzin->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisIzin,
            'message' => 'Jenis izin berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $kode)
    {
        $jenisIzin = SimpegJenisIzin::find($kode);

        if (!$jenisIzin) {
            return response()->json(['success' => false, 'message' => 'Jenis izin tidak ditemukan'], 404);
        }

        $request->validate([
            'jenis_izin' => 'required|string|max:50',
            'status_presensi' => 'required|string|max:30',
            'maksimal' => 'required|integer|min:0|max:99',
            'potong_cuti' => 'required|boolean',
        ]);

        $old = $jenisIzin->getOriginal();

        $jenisIzin->update([
            'jenis_izin' => $request->jenis_izin,
            'status_presensi' => $request->status_presensi,
            'maksimal' => $request->maksimal,
            'potong_cuti' => $request->potong_cuti,
        ]);

        $changes = array_diff_assoc($jenisIzin->toArray(), $old);
        ActivityLogger::log('update', $jenisIzin, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisIzin,
            'message' => 'Jenis izin berhasil diperbarui'
        ]);
    }

    public function destroy($kode)
    {
        $jenisIzin = SimpegJenisIzin::find($kode);
    
        if (!$jenisIzin) {
            return response()->json(['success' => false, 'message' => 'Jenis izin tidak ditemukan'], 404);
        }
    
        $jenisIzinData = $jenisIzin->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $jenisIzin->delete(); // Soft delete
    
        ActivityLogger::log('delete', $jenisIzin, $jenisIzinData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Jenis izin berhasil dihapus (soft delete)'
        ]);
    }
    
    // Method tambahan untuk mendapatkan semua jenis izin tanpa pagination (untuk dropdown)
    public function all(Request $request)
    {
        $jenisIzin = SimpegJenisIzin::orderBy('jenis_izin', 'asc')->get();
        
        $prefix = $request->segment(2);
        
        // Tambahkan link update dan delete ke setiap item
        $jenisIzin->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-izin/" . $item->kode);
            $item->delete_url = url("/api/{$prefix}/jenis-izin/" . $item->kode);
            return $item;
        });
        
        return response()->json([
            'success' => true,
            'data' => $jenisIzin
        ]);
    }
    
    // Method untuk mendapatkan jenis izin berdasarkan status potong cuti
    public function getByPotongCuti(Request $request, $potongCuti)
    {
        // Validasi potong_cuti (0 atau 1)
        if (!in_array($potongCuti, ['0', '1'])) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter potong_cuti harus 0 atau 1'
            ], 400);
        }
        
        $jenisIzin = SimpegJenisIzin::where('potong_cuti', $potongCuti)
                        ->orderBy('jenis_izin', 'asc')
                        ->paginate(10);
        
        $prefix = $request->segment(2);
        
        // Tambahkan link update dan delete ke setiap item
        $jenisIzin->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-izin/" . $item->kode);
            $item->delete_url = url("/api/{$prefix}/jenis-izin/" . $item->kode);
            return $item;
        });
        
        return response()->json([
            'success' => true,
            'data' => $jenisIzin
        ]);
    }
    
    // Method untuk mencari jenis izin
    public function search(Request $request)
    {
        $query = SimpegJenisIzin::query();
        
        // Filter berdasarkan kode
        if ($request->has('kode')) {
            $query->where('kode', 'like', '%' . $request->kode . '%');
        }
        
        // Filter berdasarkan jenis izin
        if ($request->has('jenis_izin')) {
            $query->where('jenis_izin', 'like', '%' . $request->jenis_izin . '%');
        }
        
        // Filter berdasarkan status presensi
        if ($request->has('status_presensi')) {
            $query->where('status_presensi', 'like', '%' . $request->status_presensi . '%');
        }
        
        // Filter berdasarkan maksimal
        if ($request->has('maksimal')) {
            $query->where('maksimal', $request->maksimal);
        }
        
        // Filter berdasarkan potong cuti
        if ($request->has('potong_cuti')) {
            $query->where('potong_cuti', $request->potong_cuti);
        }
        
        $jenisIzin = $query->orderBy('jenis_izin', 'asc')->paginate(10);
        
        $prefix = $request->segment(2);
        
        // Tambahkan link update dan delete ke setiap item
        $jenisIzin->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-izin/" . $item->kode);
            $item->delete_url = url("/api/{$prefix}/jenis-izin/" . $item->kode);
            return $item;
        });
        
        return response()->json([
            'success' => true,
            'data' => $jenisIzin
        ]);
    }
}