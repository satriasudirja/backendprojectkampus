<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataKemampuanBahasa;
use App\Models\SimpegBahasa;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// ... (namespace dan use statements)

class SimpegDataKemampuanBahasaController extends Controller
{
    // ... (method index() dan lainnya tidak perlu diubah)

    // Store new data kemampuan bahasa dengan draft/submit mode
    public function store(Request $request)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tahun' => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'bahasa_id' => 'required|exists:bahasa,id',
            'nama_lembaga' => 'nullable|string|max:100',
            // Validasi tetap menggunakan string
            'kemampuan_mendengar' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'kemampuan_bicara' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'kemampuan_menulis' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Cek duplikasi
        $existingData = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
            ->where('tahun', $request->tahun)
            ->where('bahasa_id', $request->bahasa_id)
            ->first();

        if ($existingData) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa untuk tahun dan bahasa yang sama sudah ada'
            ], 422);
        }

        // MODIFIKASI: Blok konversi manual dihapus, karena sudah ditangani oleh Model (Mutator)
        $data = $request->except(['file_pendukung', 'submit_type']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();
        
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data kemampuan bahasa berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data kemampuan bahasa berhasil disimpan sebagai draft';
        }

        if ($request->hasFile('file_pendukung')) {
            $file = $request->file('file_pendukung');
            $fileName = 'kemampuan_bahasa_'.time().'_'.$pegawai->id.'_'.$request->tahun.'_'.$request->bahasa_id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/kemampuan-bahasa', $fileName);
            $data['file_pendukung'] = $fileName;
        }

        // Saat create() dipanggil, mutator di model akan otomatis mengubah string ke integer
        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::create($data);

        ActivityLogger::log('create', $dataKemampuanBahasa, $dataKemampuanBahasa->toArray());

        // Accessor di model akan otomatis mengubah integer ke string saat data dikirim sebagai JSON
        return response()->json([
            'success' => true,
            'data' => $this->formatDataKemampuanBahasa($dataKemampuanBahasa), // formatDataKemampuanBahasa sekarang lebih sederhana
            'message' => $message
        ], 201);
    }

    // Update data kemampuan bahasa dengan validasi status
    public function update(Request $request, $id)
    {
        // ... (logika pengecekan pegawai dan data)

        // MODIFIKASI: Blok konversi manual juga dihapus dari sini
        // ... (Validasi request)
        
        $oldData = $dataKemampuanBahasa->getOriginal();
        $data = $request->except(['file_pendukung', 'submit_type']);

        // ... (logika status dan file upload)

        // Saat update() dipanggil, mutator di model akan otomatis bekerja
        $dataKemampuanBahasa->update($data);

        ActivityLogger::log('update', $dataKemampuanBahasa, $oldData);

        // Accessor di model akan otomatis bekerja saat data dikirim sebagai JSON
        return response()->json([
            'success' => true,
            'data' => $this->formatDataKemampuanBahasa($dataKemampuanBahasa->load('bahasa')),
            'message' => $message
        ]);
    }

    // ... (method lainnya)

    // MODIFIKASI: Method formatDataKemampuanBahasa sekarang lebih sederhana
    // karena konversi sudah ditangani Accessor di Model.
    // Anda bahkan bisa menghapus beberapa field karena sudah ada di $appends Model.
    protected function formatDataKemampuanBahasa($dataKemampuanBahasa, $includeActions = true)
    {
        // ...
        // Saat Anda mengakses $dataKemampuanBahasa->kemampuan_mendengar di sini,
        // nilainya sudah otomatis menjadi "Sangat Baik", "Baik", dst.
        // Tidak perlu ada perubahan kode di sini, ini hanya untuk penjelasan.
        // ...
    }
}