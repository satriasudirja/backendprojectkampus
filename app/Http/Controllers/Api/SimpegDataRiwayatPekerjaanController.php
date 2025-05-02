<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegDataRiwayatPekerjaan;
use App\Services\ActivityLogger;

class SimpegDataRiwayatPekerjaanController extends Controller
{
    public function index(Request $request)
    {
        $riwayatPekerjaan = SimpegDataRiwayatPekerjaan::with(['pegawai'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $riwayatPekerjaan->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/riwayat-pekerjaan/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/riwayat-pekerjaan/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $riwayatPekerjaan
        ]);
    }

    public function show(Request $request, $id)
    {
        $riwayatPekerjaan = SimpegDataRiwayatPekerjaan::with(['pegawai'])->find($id);

        if (!$riwayatPekerjaan) {
            return response()->json(['success' => false, 'message' => 'Riwayat pekerjaan tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $riwayatPekerjaan,
            'update_url' => url("/api/{$prefix}/riwayat-pekerjaan/" . $riwayatPekerjaan->id),
            'delete_url' => url("/api/{$prefix}/riwayat-pekerjaan/" . $riwayatPekerjaan->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'required|integer',
            'bidang_usaha' => 'required|string|max:200',
            'jenis_pekerjaan' => 'required|string|max:50',
            'jabatan' => 'required|string|max:50',
            'instansi' => 'required|string|max:100',
            'divisi' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string',
            'mulai_bekerja' => 'required|date',
            'selesai_bekerja' => 'nullable|date',
            'area_pekerjaan' => 'boolean',
            'tgl_input' => 'required|date',
        ]);

        $riwayatPekerjaan = SimpegDataRiwayatPekerjaan::create([
            'pegawai_id' => $request->pegawai_id,
            'bidang_usaha' => $request->bidang_usaha,
            'jenis_pekerjaan' => $request->jenis_pekerjaan,
            'jabatan' => $request->jabatan,
            'instansi' => $request->instansi,
            'divisi' => $request->divisi,
            'deskripsi' => $request->deskripsi,
            'mulai_bekerja' => $request->mulai_bekerja,
            'selesai_bekerja' => $request->selesai_bekerja,
            'area_pekerjaan' => $request->area_pekerjaan ?? false,
            'tgl_input' => $request->tgl_input,
        ]);

        ActivityLogger::log('create', $riwayatPekerjaan, $riwayatPekerjaan->toArray());

        return response()->json([
            'success' => true,
            'data' => $riwayatPekerjaan,
            'message' => 'Riwayat pekerjaan berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $riwayatPekerjaan = SimpegDataRiwayatPekerjaan::find($id);

        if (!$riwayatPekerjaan) {
            return response()->json(['success' => false, 'message' => 'Riwayat pekerjaan tidak ditemukan'], 404);
        }

        $request->validate([
            'pegawai_id' => 'required|integer',
            'bidang_usaha' => 'required|string|max:200',
            'jenis_pekerjaan' => 'required|string|max:50',
            'jabatan' => 'required|string|max:50',
            'instansi' => 'required|string|max:100',
            'divisi' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string',
            'mulai_bekerja' => 'required|date',
            'selesai_bekerja' => 'nullable|date',
            'area_pekerjaan' => 'boolean',
            'tgl_input' => 'required|date',
        ]);

        $old = $riwayatPekerjaan->getOriginal();

        $riwayatPekerjaan->update([
            'pegawai_id' => $request->pegawai_id,
            'bidang_usaha' => $request->bidang_usaha,
            'jenis_pekerjaan' => $request->jenis_pekerjaan,
            'jabatan' => $request->jabatan,
            'instansi' => $request->instansi,
            'divisi' => $request->divisi,
            'deskripsi' => $request->deskripsi,
            'mulai_bekerja' => $request->mulai_bekerja,
            'selesai_bekerja' => $request->selesai_bekerja,
            'area_pekerjaan' => $request->area_pekerjaan ?? $riwayatPekerjaan->area_pekerjaan,
            'tgl_input' => $request->tgl_input,
        ]);

        $changes = array_diff_assoc($riwayatPekerjaan->toArray(), $old);
        ActivityLogger::log('update', $riwayatPekerjaan, $changes);

        return response()->json([
            'success' => true,
            'data' => $riwayatPekerjaan,
            'message' => 'Riwayat pekerjaan berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $riwayatPekerjaan = SimpegDataRiwayatPekerjaan::find($id);
    
        if (!$riwayatPekerjaan) {
            return response()->json(['success' => false, 'message' => 'Riwayat pekerjaan tidak ditemukan'], 404);
        }
    
        $riwayatPekerjaanData = $riwayatPekerjaan->toArray();
    
        $riwayatPekerjaan->delete();
    
        ActivityLogger::log('delete', $riwayatPekerjaan, $riwayatPekerjaanData);
    
        return response()->json([
            'success' => true,
            'message' => 'Riwayat pekerjaan berhasil dihapus (soft delete)'
        ]);
    }
}