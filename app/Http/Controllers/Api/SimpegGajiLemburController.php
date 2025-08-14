<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegGajiLembur;
use App\Services\ActivityLogger;

class SimpegGajiLemburController extends Controller
{
    public function index(Request $request)
    {
        $gajiLembur = SimpegGajiLembur::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $gajiLembur->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-lembur/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-lembur/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gajiLembur
        ]);
    }

    public function show(Request $request, $id)
    {
        $gajiLembur = SimpegGajiLembur::find($id);

        if (!$gajiLembur) {
            return response()->json(['success' => false, 'message' => 'Data lembur tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $gajiLembur,
            'update_url' => url("/api/{$prefix}/gaji-lembur/" . $gajiLembur->id),
            'delete_url' => url("/api/{$prefix}/gaji-lembur/" . $gajiLembur->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'required|uuid',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'durasi' => 'required|numeric|min:0',
            'upah_perjam' => 'required|numeric|min:0',
            'total_upah' => 'required|numeric|min:0',
            'status' => 'required|string|max:20|in:pending,approved,rejected,paid',
        ]);

        $gajiLembur = SimpegGajiLembur::create([
            'pegawai_id' => $request->pegawai_id,
            'tanggal' => $request->tanggal,
            'jam_mulai' => $request->jam_mulai,
            'jam_selesai' => $request->jam_selesai,
            'durasi' => $request->durasi,
            'upah_perjam' => $request->upah_perjam,
            'total_upah' => $request->total_upah,
            'status' => $request->status,
        ]);

        ActivityLogger::log('create', $gajiLembur, $gajiLembur->toArray());

        return response()->json([
            'success' => true,
            'data' => $gajiLembur,
            'message' => 'Data lembur berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $gajiLembur = SimpegGajiLembur::find($id);

        if (!$gajiLembur) {
            return response()->json(['success' => false, 'message' => 'Data lembur tidak ditemukan'], 404);
        }

        $request->validate([
            'pegawai_id' => 'required|uuid',
            'tanggal' => 'required|date',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            'durasi' => 'required|numeric|min:0',
            'upah_perjam' => 'required|numeric|min:0',
            'total_upah' => 'required|numeric|min:0',
            'status' => 'required|string|max:20|in:pending,approved,rejected,paid',
        ]);

        $old = $gajiLembur->getOriginal();

        $gajiLembur->update([
            'pegawai_id' => $request->pegawai_id,
            'tanggal' => $request->tanggal,
            'jam_mulai' => $request->jam_mulai,
            'jam_selesai' => $request->jam_selesai,
            'durasi' => $request->durasi,
            'upah_perjam' => $request->upah_perjam,
            'total_upah' => $request->total_upah,
            'status' => $request->status,
        ]);

        $changes = array_diff_assoc($gajiLembur->toArray(), $old);
        ActivityLogger::log('update', $gajiLembur, $changes);

        return response()->json([
            'success' => true,
            'data' => $gajiLembur,
            'message' => 'Data lembur berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $gajiLembur = SimpegGajiLembur::find($id);
    
        if (!$gajiLembur) {
            return response()->json(['success' => false, 'message' => 'Data lembur tidak ditemukan'], 404);
        }
    
        $gajiLemburData = $gajiLembur->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $gajiLembur->delete(); // Soft delete
    
        ActivityLogger::log('delete', $gajiLembur, $gajiLemburData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Data lembur berhasil dihapus (soft delete)'
        ]);
    }
    
    // Method tambahan untuk mendapatkan lembur berdasarkan pegawai
    public function getByPegawai(Request $request, $pegawaiId)
    {
        $gajiLembur = SimpegGajiLembur::where('pegawai_id', $pegawaiId)
                        ->orderBy('tanggal', 'desc')
                        ->paginate(10);

        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $gajiLembur->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-lembur/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-lembur/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gajiLembur
        ]);
    }
    
    // Method untuk mendapatkan lembur berdasarkan periode tanggal
    public function getByPeriode(Request $request)
    {
        $request->validate([
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
        ]);
        
        $gajiLembur = SimpegGajiLembur::whereBetween('tanggal', [$request->tanggal_mulai, $request->tanggal_selesai])
                        ->orderBy('tanggal', 'desc')
                        ->paginate(10);

        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $gajiLembur->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-lembur/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-lembur/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gajiLembur
        ]);
    }
    
    // Method untuk mengubah status lembur
    public function updateStatus(Request $request, $id)
    {
        $gajiLembur = SimpegGajiLembur::find($id);
        
        if (!$gajiLembur) {
            return response()->json(['success' => false, 'message' => 'Data lembur tidak ditemukan'], 404);
        }
        
        $request->validate([
            'status' => 'required|string|max:20|in:pending,approved,rejected,paid',
        ]);
        
        $old = $gajiLembur->getOriginal();
        
        $gajiLembur->update([
            'status' => $request->status,
        ]);
        
        $changes = array_diff_assoc($gajiLembur->toArray(), $old);
        ActivityLogger::log('status_update', $gajiLembur, $changes);
        
        $statusMessage = '';
        switch ($request->status) {
            case 'approved':
                $statusMessage = 'disetujui';
                break;
            case 'rejected':
                $statusMessage = 'ditolak';
                break;
            case 'paid':
                $statusMessage = 'dibayar';
                break;
            default:
                $statusMessage = 'diubah menjadi ' . $request->status;
        }
        
        return response()->json([
            'success' => true,
            'data' => $gajiLembur,
            'message' => 'Status lembur berhasil ' . $statusMessage
        ]);
    }
    
    // Method untuk menghitung total lembur per pegawai dalam periode tertentu
    public function hitungTotalByPegawai(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'required|uuid',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
        ]);
        
        $totalDurasi = SimpegGajiLembur::where('pegawai_id', $request->pegawai_id)
                          ->whereBetween('tanggal', [$request->tanggal_mulai, $request->tanggal_selesai])
                          ->where('status', 'approved')
                          ->sum('durasi');
                          
        $totalUpah = SimpegGajiLembur::where('pegawai_id', $request->pegawai_id)
                       ->whereBetween('tanggal', [$request->tanggal_mulai, $request->tanggal_selesai])
                       ->where('status', 'approved')
                       ->sum('total_upah');
        
        return response()->json([
            'success' => true,
            'data' => [
                'pegawai_id' => $request->pegawai_id,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'total_durasi' => $totalDurasi,
                'total_upah' => $totalUpah
            ]
        ]);
    }
}