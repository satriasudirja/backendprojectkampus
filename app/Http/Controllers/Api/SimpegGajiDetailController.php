<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegGajiDetail;
use App\Services\ActivityLogger;

class SimpegGajiDetailController extends Controller
{
    public function index(Request $request)
    {
        $gajiDetail = SimpegGajiDetail::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $gajiDetail->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-detail/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-detail/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gajiDetail
        ]);
    }

    public function show(Request $request, $id)
    {
        $gajiDetail = SimpegGajiDetail::find($id);

        if (!$gajiDetail) {
            return response()->json(['success' => false, 'message' => 'Detail gaji tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $gajiDetail,
            'update_url' => url("/api/{$prefix}/gaji-detail/" . $gajiDetail->id),
            'delete_url' => url("/api/{$prefix}/gaji-detail/" . $gajiDetail->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'gaji_slip_id' => 'required|integer',
            'komponen_id' => 'required|integer',
            'jumlah' => 'required|numeric|min:0',
            'keterangan' => 'nullable|string',
        ]);

        $gajiDetail = SimpegGajiDetail::create([
            'gaji_slip_id' => $request->gaji_slip_id,
            'komponen_id' => $request->komponen_id,
            'jumlah' => $request->jumlah,
            'keterangan' => $request->keterangan,
        ]);

        ActivityLogger::log('create', $gajiDetail, $gajiDetail->toArray());

        return response()->json([
            'success' => true,
            'data' => $gajiDetail,
            'message' => 'Detail gaji berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $gajiDetail = SimpegGajiDetail::find($id);

        if (!$gajiDetail) {
            return response()->json(['success' => false, 'message' => 'Detail gaji tidak ditemukan'], 404);
        }

        $request->validate([
            'gaji_slip_id' => 'required|integer',
            'komponen_id' => 'required|integer',
            'jumlah' => 'required|numeric|min:0',
            'keterangan' => 'nullable|string',
        ]);

        $old = $gajiDetail->getOriginal();

        $gajiDetail->update([
            'gaji_slip_id' => $request->gaji_slip_id,
            'komponen_id' => $request->komponen_id,
            'jumlah' => $request->jumlah,
            'keterangan' => $request->keterangan,
        ]);

        $changes = array_diff_assoc($gajiDetail->toArray(), $old);
        ActivityLogger::log('update', $gajiDetail, $changes);

        return response()->json([
            'success' => true,
            'data' => $gajiDetail,
            'message' => 'Detail gaji berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $gajiDetail = SimpegGajiDetail::find($id);
    
        if (!$gajiDetail) {
            return response()->json(['success' => false, 'message' => 'Detail gaji tidak ditemukan'], 404);
        }
    
        $gajiDetailData = $gajiDetail->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $gajiDetail->delete(); // Soft delete
    
        ActivityLogger::log('delete', $gajiDetail, $gajiDetailData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Detail gaji berhasil dihapus (soft delete)'
        ]);
    }
    
    // Method tambahan untuk mendapatkan semua detail berdasarkan gaji_slip_id
    public function getByGajiSlip(Request $request, $gajiSlipId)
    {
        $gajiDetail = SimpegGajiDetail::where('gaji_slip_id', $gajiSlipId)
                        ->orderBy('created_at', 'desc')
                        ->paginate(10);

        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $gajiDetail->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-detail/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-detail/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gajiDetail
        ]);
    }
    
    // Method untuk menambahkan multiple detail gaji sekaligus
    public function storeBatch(Request $request)
    {
        $request->validate([
            'details' => 'required|array',
            'details.*.gaji_slip_id' => 'required|integer',
            'details.*.komponen_id' => 'required|integer',
            'details.*.jumlah' => 'required|numeric|min:0',
            'details.*.keterangan' => 'nullable|string',
        ]);
        
        $createdDetails = [];
        
        foreach ($request->details as $detail) {
            $gajiDetail = SimpegGajiDetail::create([
                'gaji_slip_id' => $detail['gaji_slip_id'],
                'komponen_id' => $detail['komponen_id'],
                'jumlah' => $detail['jumlah'],
                'keterangan' => $detail['keterangan'] ?? null,
            ]);
            
            ActivityLogger::log('create', $gajiDetail, $gajiDetail->toArray());
            
            $createdDetails[] = $gajiDetail;
        }
        
        return response()->json([
            'success' => true,
            'data' => $createdDetails,
            'message' => count($createdDetails) . ' detail gaji berhasil ditambahkan'
        ]);
    }
    
    // Method untuk menghitung total komponen berdasarkan jenis (pendapatan/potongan)
    public function calculateComponentTotal(Request $request, $gajiSlipId, $jenis)
    {
        // Validasi jenis harus 'pendapatan' atau 'potongan'
        if (!in_array($jenis, ['pendapatan', 'potongan'])) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis harus "pendapatan" atau "potongan"'
            ], 400);
        }
        
        // Asumsi: SimpegKomponenGaji memiliki kolom 'jenis' dengan nilai 'pendapatan' atau 'potongan'
        $total = SimpegGajiDetail::join('simpeg_komponen_gaji', 'simpeg_gaji_detail.komponen_id', '=', 'simpeg_komponen_gaji.id')
                    ->where('simpeg_gaji_detail.gaji_slip_id', $gajiSlipId)
                    ->where('simpeg_komponen_gaji.jenis', $jenis)
                    ->sum('simpeg_gaji_detail.jumlah');
        
        return response()->json([
            'success' => true,
            'data' => [
                'gaji_slip_id' => $gajiSlipId,
                'jenis' => $jenis,
                'total' => $total
            ]
        ]);
    }
}