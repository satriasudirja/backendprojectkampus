<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegGajiTunjanganKhusus;
use App\Services\ActivityLogger;

class SimpegGajiTunjanganKhususController extends Controller
{
    public function index(Request $request)
    {
        $tunjangan = SimpegGajiTunjanganKhusus::with(['pegawai', 'komponen'])
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $tunjangan->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-tunjangan-khusus/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-tunjangan-khusus/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $tunjangan
        ]);
    }

    public function show(Request $request, $id)
    {
        $tunjangan = SimpegGajiTunjanganKhusus::with(['pegawai', 'komponen'])->find($id);

        if (!$tunjangan) {
            return response()->json(['success' => false, 'message' => 'Tunjangan khusus tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $tunjangan,
            'update_url' => url("/api/{$prefix}/gaji-tunjangan-khusus/" . $tunjangan->id),
            'delete_url' => url("/api/{$prefix}/gaji-tunjangan-khusus/" . $tunjangan->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id',
            'komponen_id' => 'required|integer|exists:simpeg_komponen_gaji,id',
            'jumlah' => 'required|numeric',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'nullable|date|after_or_equal:tgl_mulai',
            'keterangan' => 'nullable|string',
        ]);

        $tunjangan = SimpegGajiTunjanganKhusus::create([
            'pegawai_id' => $request->pegawai_id,
            'komponen_id' => $request->komponen_id,
            'jumlah' => $request->jumlah,
            'tgl_mulai' => $request->tgl_mulai,
            'tgl_selesai' => $request->tgl_selesai,
            'keterangan' => $request->keterangan,
        ]);

        ActivityLogger::log('create', $tunjangan, $tunjangan->toArray());

        return response()->json([
            'success' => true,
            'data' => $tunjangan,
            'message' => 'Tunjangan khusus berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $tunjangan = SimpegGajiTunjanganKhusus::find($id);

        if (!$tunjangan) {
            return response()->json(['success' => false, 'message' => 'Tunjangan khusus tidak ditemukan'], 404);
        }

        $request->validate([
            'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id',
            'komponen_id' => 'required|integer|exists:simpeg_komponen_gaji,id',
            'jumlah' => 'required|numeric',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'nullable|date|after_or_equal:tgl_mulai',
            'keterangan' => 'nullable|string',
        ]);

        $old = $tunjangan->getOriginal();

        $tunjangan->update([
            'pegawai_id' => $request->pegawai_id,
            'komponen_id' => $request->komponen_id,
            'jumlah' => $request->jumlah,
            'tgl_mulai' => $request->tgl_mulai,
            'tgl_selesai' => $request->tgl_selesai,
            'keterangan' => $request->keterangan,
        ]);

        $changes = array_diff_assoc($tunjangan->toArray(), $old);
        ActivityLogger::log('update', $tunjangan, $changes);

        return response()->json([
            'success' => true,
            'data' => $tunjangan,
            'message' => 'Tunjangan khusus berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $tunjangan = SimpegGajiTunjanganKhusus::find($id);
    
        if (!$tunjangan) {
            return response()->json(['success' => false, 'message' => 'Tunjangan khusus tidak ditemukan'], 404);
        }
    
        $tunjanganData = $tunjangan->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $tunjangan->delete(); // Soft delete
    
        ActivityLogger::log('delete', $tunjangan, $tunjanganData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Tunjangan khusus berhasil dihapus (soft delete)'
        ]);
    }
}