<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegIzinRecord;
use App\Services\ActivityLogger;

class SimpegIzinRecordController extends Controller
{
    public function index(Request $request)
    {
        $izinRecords = SimpegIzinRecord::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $izinRecords->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/izin-record/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/izin-record/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $izinRecords
        ]);
    }

    public function show(Request $request, $id)
    {
        $izinRecord = SimpegIzinRecord::find($id);

        if (!$izinRecord) {
            return response()->json(['success' => false, 'message' => 'Izin record tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $izinRecord,
            'update_url' => url("/api/{$prefix}/izin-record/" . $izinRecord->id),
            'delete_url' => url("/api/{$prefix}/izin-record/" . $izinRecord->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'required|uuid',
            'jenis_izin_id' => 'required|uuid',
            'alasan' => 'required|string|max:255',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'jumlah_izin' => 'required|integer',
            'file_pendukung' => 'nullable|file|max:2048',
            'status_pengajuan' => 'required|string|max:20',
        ]);

        // Handle file upload if exists
        $filePath = null;
        if ($request->hasFile('file_pendukung')) {
            $file = $request->file('file_pendukung');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('izin_files', $fileName, 'public');
        }

        $izinRecord = SimpegIzinRecord::create([
            'pegawai_id' => $request->pegawai_id,
            'jenis_izin_id' => $request->jenis_izin_id,
            'alasan' => $request->alasan,
            'tgl_mulai' => $request->tgl_mulai,
            'tgl_selesai' => $request->tgl_selesai,
            'jumlah_izin' => $request->jumlah_izin,
            'file_pendukung' => $filePath,
            'status_pengajuan' => $request->status_pengajuan,
        ]);

        ActivityLogger::log('create', $izinRecord, $izinRecord->toArray());

        return response()->json([
            'success' => true,
            'data' => $izinRecord,
            'message' => 'Izin berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $izinRecord = SimpegIzinRecord::find($id);

        if (!$izinRecord) {
            return response()->json(['success' => false, 'message' => 'Izin record tidak ditemukan'], 404);
        }

        $request->validate([
            'pegawai_id' => 'required|uuid',
            'jenis_izin_id' => 'required|uuid',
            'alasan' => 'required|string|max:255',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'jumlah_izin' => 'required|integer',
            'file_pendukung' => 'nullable|file|max:2048',
            'status_pengajuan' => 'required|string|max:20',
        ]);

        $old = $izinRecord->getOriginal();

        // Handle file upload if exists
        $filePath = $izinRecord->file_pendukung;
        if ($request->hasFile('file_pendukung')) {
            // Delete old file if exists
            if ($izinRecord->file_pendukung) {
                Storage::disk('public')->delete($izinRecord->file_pendukung);
            }
            
            $file = $request->file('file_pendukung');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('izin_files', $fileName, 'public');
        }

        $izinRecord->update([
            'pegawai_id' => $request->pegawai_id,
            'jenis_izin_id' => $request->jenis_izin_id,
            'alasan' => $request->alasan,
            'tgl_mulai' => $request->tgl_mulai,
            'tgl_selesai' => $request->tgl_selesai,
            'jumlah_izin' => $request->jumlah_izin,
            'file_pendukung' => $filePath,
            'status_pengajuan' => $request->status_pengajuan,
        ]);

        $changes = array_diff_assoc($izinRecord->toArray(), $old);
        ActivityLogger::log('update', $izinRecord, $changes);

        return response()->json([
            'success' => true,
            'data' => $izinRecord,
            'message' => 'Izin berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $izinRecord = SimpegIzinRecord::find($id);
    
        if (!$izinRecord) {
            return response()->json(['success' => false, 'message' => 'Izin record tidak ditemukan'], 404);
        }
    
        $izinRecordData = $izinRecord->toArray(); // Simpan dulu isi data sebelum dihapus
    
        // Delete file if exists
        if ($izinRecord->file_pendukung) {
            Storage::disk('public')->delete($izinRecord->file_pendukung);
        }
    
        $izinRecord->delete(); // Soft delete
    
        ActivityLogger::log('delete', $izinRecord, $izinRecordData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Izin berhasil dihapus (soft delete)'
        ]);
    }
}