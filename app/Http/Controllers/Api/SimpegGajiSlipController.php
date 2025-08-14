<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegGajiSlip;
use App\Services\ActivityLogger;

class SimpegGajiSlipController extends Controller
{
    public function index(Request $request)
    {
        $gajiSlip = SimpegGajiSlip::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $gajiSlip->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-slip/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-slip/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gajiSlip
        ]);
    }

    public function show(Request $request, $id)
    {
        $gajiSlip = SimpegGajiSlip::find($id);

        if (!$gajiSlip) {
            return response()->json(['success' => false, 'message' => 'Slip gaji tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $gajiSlip,
            'update_url' => url("/api/{$prefix}/gaji-slip/" . $gajiSlip->id),
            'delete_url' => url("/api/{$prefix}/gaji-slip/" . $gajiSlip->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'required|uuid',
            'periode_id' => 'required|uuid',
            'total_pendapatan' => 'required|numeric|min:0',
            'total_potongan' => 'required|numeric|min:0',
            'gaji_bersih' => 'required|numeric|min:0',
            'status' => 'required|string|max:20|in:draft,processed,approved,paid',
            'tgl_proses' => 'nullable|date',
        ]);

        $gajiSlip = SimpegGajiSlip::create([
            'pegawai_id' => $request->pegawai_id,
            'periode_id' => $request->periode_id,
            'total_pendapatan' => $request->total_pendapatan,
            'total_potongan' => $request->total_potongan,
            'gaji_bersih' => $request->gaji_bersih,
            'status' => $request->status,
            'tgl_proses' => $request->tgl_proses,
        ]);

        ActivityLogger::log('create', $gajiSlip, $gajiSlip->toArray());

        return response()->json([
            'success' => true,
            'data' => $gajiSlip,
            'message' => 'Slip gaji berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $gajiSlip = SimpegGajiSlip::find($id);

        if (!$gajiSlip) {
            return response()->json(['success' => false, 'message' => 'Slip gaji tidak ditemukan'], 404);
        }

        $request->validate([
            'pegawai_id' => 'required|uuid',
            'periode_id' => 'required|uuid',
            'total_pendapatan' => 'required|numeric|min:0',
            'total_potongan' => 'required|numeric|min:0',
            'gaji_bersih' => 'required|numeric|min:0',
            'status' => 'required|string|max:20|in:draft,processed,approved,paid',
            'tgl_proses' => 'nullable|date',
        ]);

        $old = $gajiSlip->getOriginal();

        $gajiSlip->update([
            'pegawai_id' => $request->pegawai_id,
            'periode_id' => $request->periode_id,
            'total_pendapatan' => $request->total_pendapatan,
            'total_potongan' => $request->total_potongan,
            'gaji_bersih' => $request->gaji_bersih,
            'status' => $request->status,
            'tgl_proses' => $request->tgl_proses,
        ]);

        $changes = array_diff_assoc($gajiSlip->toArray(), $old);
        ActivityLogger::log('update', $gajiSlip, $changes);

        return response()->json([
            'success' => true,
            'data' => $gajiSlip,
            'message' => 'Slip gaji berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $gajiSlip = SimpegGajiSlip::find($id);
    
        if (!$gajiSlip) {
            return response()->json(['success' => false, 'message' => 'Slip gaji tidak ditemukan'], 404);
        }
    
        $gajiSlipData = $gajiSlip->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $gajiSlip->delete(); // Soft delete
    
        ActivityLogger::log('delete', $gajiSlip, $gajiSlipData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Slip gaji berhasil dihapus (soft delete)'
        ]);
    }
    
    // Method tambahan untuk mendapatkan slip gaji berdasarkan pegawai
    public function getByPegawai(Request $request, $pegawaiId)
    {
        $gajiSlip = SimpegGajiSlip::where('pegawai_id', $pegawaiId)
                      ->orderBy('created_at', 'desc')
                      ->paginate(10);

        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $gajiSlip->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-slip/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-slip/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gajiSlip
        ]);
    }

    // Method tambahan untuk mendapatkan slip gaji berdasarkan periode
    public function getByPeriode(Request $request, $periodeId)
    {
        $gajiSlip = SimpegGajiSlip::where('periode_id', $periodeId)
                      ->orderBy('created_at', 'desc')
                      ->paginate(10);

        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $gajiSlip->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-slip/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-slip/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gajiSlip
        ]);
    }
    
    // Method untuk memproses slip gaji (mengubah status dari draft ke processed)
    public function processSlip(Request $request, $id)
    {
        $gajiSlip = SimpegGajiSlip::find($id);
        
        if (!$gajiSlip) {
            return response()->json(['success' => false, 'message' => 'Slip gaji tidak ditemukan'], 404);
        }
        
        if ($gajiSlip->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Hanya slip gaji berstatus draft yang dapat diproses'], 400);
        }
        
        $old = $gajiSlip->getOriginal();
        
        $gajiSlip->update([
            'status' => 'processed',
            'tgl_proses' => now(),
        ]);
        
        $changes = array_diff_assoc($gajiSlip->toArray(), $old);
        ActivityLogger::log('process', $gajiSlip, $changes);
        
        return response()->json([
            'success' => true,
            'data' => $gajiSlip,
            'message' => 'Slip gaji berhasil diproses'
        ]);
    }
    
    // Method untuk menyetujui slip gaji (mengubah status dari processed ke approved)
    public function approveSlip(Request $request, $id)
    {
        $gajiSlip = SimpegGajiSlip::find($id);
        
        if (!$gajiSlip) {
            return response()->json(['success' => false, 'message' => 'Slip gaji tidak ditemukan'], 404);
        }
        
        if ($gajiSlip->status !== 'processed') {
            return response()->json(['success' => false, 'message' => 'Hanya slip gaji berstatus processed yang dapat disetujui'], 400);
        }
        
        $old = $gajiSlip->getOriginal();
        
        $gajiSlip->update([
            'status' => 'approved',
        ]);
        
        $changes = array_diff_assoc($gajiSlip->toArray(), $old);
        ActivityLogger::log('approve', $gajiSlip, $changes);
        
        return response()->json([
            'success' => true,
            'data' => $gajiSlip,
            'message' => 'Slip gaji berhasil disetujui'
        ]);
    }
    
    // Method untuk menandai slip gaji sebagai telah dibayar (mengubah status dari approved ke paid)
    public function markAsPaid(Request $request, $id)
    {
        $gajiSlip = SimpegGajiSlip::find($id);
        
        if (!$gajiSlip) {
            return response()->json(['success' => false, 'message' => 'Slip gaji tidak ditemukan'], 404);
        }
        
        if ($gajiSlip->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Hanya slip gaji berstatus approved yang dapat ditandai sebagai dibayar'], 400);
        }
        
        $old = $gajiSlip->getOriginal();
        
        $gajiSlip->update([
            'status' => 'paid',
        ]);
        
        $changes = array_diff_assoc($gajiSlip->toArray(), $old);
        ActivityLogger::log('payment', $gajiSlip, $changes);
        
        return response()->json([
            'success' => true,
            'data' => $gajiSlip,
            'message' => 'Slip gaji berhasil ditandai sebagai dibayar'
        ]);
    }
}