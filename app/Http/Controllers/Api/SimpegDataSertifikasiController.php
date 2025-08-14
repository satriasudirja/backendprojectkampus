<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegDataSertifikasi;
use App\Services\ActivityLogger;

class SimpegDataSertifikasiController extends Controller
{
    public function index(Request $request)
    {
        $sertifikasi = SimpegDataSertifikasi::with(['pegawai', 'jenisSertifikasi', 'bidangIlmu'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $sertifikasi->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/sertifikasi/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/sertifikasi/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $sertifikasi
        ]);
    }

    public function show(Request $request, $id)
    {
        $sertifikasi = SimpegDataSertifikasi::with(['pegawai', 'jenisSertifikasi', 'bidangIlmu'])->find($id);

        if (!$sertifikasi) {
            return response()->json(['success' => false, 'message' => 'Data sertifikasi tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $sertifikasi,
            'update_url' => url("/api/{$prefix}/sertifikasi/" . $sertifikasi->id),
            'delete_url' => url("/api/{$prefix}/sertifikasi/" . $sertifikasi->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'nullable|uuid',
            'jenis_sertifikasi_id' => 'nullable|uuid',
            'bidang_ilmu_id' => 'nullable|uuid',
            'no_sertifikasi' => 'required|string|max:50',
            'tgl_sertifikasi' => 'required|date',
            'no_registrasi' => 'required|string|max:20',
            'no_peserta' => 'required|string|max:50',
            'peran' => 'required|string|max:100',
            'penyelenggara' => 'required|string|max:100',
            'tempat' => 'required|string|max:100',
            'lingkup' => 'required|string|max:20',
            'tgl_input' => 'required|date',
        ]);

        $sertifikasi = SimpegDataSertifikasi::create([
            'pegawai_id' => $request->pegawai_id,
            'jenis_sertifikasi_id' => $request->jenis_sertifikasi_id,
            'bidang_ilmu_id' => $request->bidang_ilmu_id,
            'no_sertifikasi' => $request->no_sertifikasi,
            'tgl_sertifikasi' => $request->tgl_sertifikasi,
            'no_registrasi' => $request->no_registrasi,
            'no_peserta' => $request->no_peserta,
            'peran' => $request->peran,
            'penyelenggara' => $request->penyelenggara,
            'tempat' => $request->tempat,
            'lingkup' => $request->lingkup,
            'tgl_input' => $request->tgl_input,
        ]);

        ActivityLogger::log('create', $sertifikasi, $sertifikasi->toArray());

        return response()->json([
            'success' => true,
            'data' => $sertifikasi,
            'message' => 'Data sertifikasi berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $sertifikasi = SimpegDataSertifikasi::find($id);

        if (!$sertifikasi) {
            return response()->json(['success' => false, 'message' => 'Data sertifikasi tidak ditemukan'], 404);
        }

        $request->validate([
            'pegawai_id' => 'nullable|uuid',
            'jenis_sertifikasi_id' => 'nullable|uuid',
            'bidang_ilmu_id' => 'nullable|uuid',
            'no_sertifikasi' => 'required|string|max:50',
            'tgl_sertifikasi' => 'required|date',
            'no_registrasi' => 'required|string|max:20',
            'no_peserta' => 'required|string|max:50',
            'peran' => 'required|string|max:100',
            'penyelenggara' => 'required|string|max:100',
            'tempat' => 'required|string|max:100',
            'lingkup' => 'required|string|max:20',
            'tgl_input' => 'required|date',
        ]);

        $old = $sertifikasi->getOriginal();

        $sertifikasi->update([
            'pegawai_id' => $request->pegawai_id,
            'jenis_sertifikasi_id' => $request->jenis_sertifikasi_id,
            'bidang_ilmu_id' => $request->bidang_ilmu_id,
            'no_sertifikasi' => $request->no_sertifikasi,
            'tgl_sertifikasi' => $request->tgl_sertifikasi,
            'no_registrasi' => $request->no_registrasi,
            'no_peserta' => $request->no_peserta,
            'peran' => $request->peran,
            'penyelenggara' => $request->penyelenggara,
            'tempat' => $request->tempat,
            'lingkup' => $request->lingkup,
            'tgl_input' => $request->tgl_input,
        ]);

        $changes = array_diff_assoc($sertifikasi->toArray(), $old);
        ActivityLogger::log('update', $sertifikasi, $changes);

        return response()->json([
            'success' => true,
            'data' => $sertifikasi,
            'message' => 'Data sertifikasi berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $sertifikasi = SimpegDataSertifikasi::find($id);
    
        if (!$sertifikasi) {
            return response()->json(['success' => false, 'message' => 'Data sertifikasi tidak ditemukan'], 404);
        }
    
        $sertifikasiData = $sertifikasi->toArray();
    
        $sertifikasi->delete();
    
        ActivityLogger::log('delete', $sertifikasi, $sertifikasiData);
    
        return response()->json([
            'success' => true,
            'message' => 'Data sertifikasi berhasil dihapus (soft delete)'
        ]);
    }
}