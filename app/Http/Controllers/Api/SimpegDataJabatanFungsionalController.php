<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataJabatanFungsional;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegJabatanFungsional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataJabatanFungsionalController extends Controller
{
    // ... (method index, fixExistingData, bulkFixExistingData, show tidak ada perubahan signifikan, hanya di bagian format) ...

    // Store new data jabatan fungsional dengan draft/submit mode
    public function store(Request $request)
    {
        // ... (validasi dan kode sebelum create) ...

        // Set status berdasarkan submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now(); // Ditambahkan
            $message = 'Data jabatan fungsional berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data jabatan fungsional berhasil disimpan sebagai draft';
        }

        $dataJabatanFungsional = SimpegDataJabatanFungsional::create($data);

        ActivityLogger::log('create', $dataJabatanFungsional, $dataJabatanFungsional->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataJabatanFungsional($dataJabatanFungsional->load(['jabatanFungsional']), false),
            'message' => $message
        ], 201);
    }

    // Update data jabatan fungsional dengan validasi status
    public function update(Request $request, $id)
    {
        // ... (kode otentikasi dan validasi) ...

        $oldData = $dataJabatanFungsional->getOriginal();
        $data = $request->except(['submit_type', 'file_sk_jabatan']);
        
        // ... (handle file upload) ...

        // Reset status jika dari ditolak
        if ($dataJabatanFungsional->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null; // Diubah: reset tanggal ditolak
            $data['tgl_diajukan'] = null; // Diubah: reset tanggal diajukan sebelumnya
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now(); // Ditambahkan
            $message = 'Data jabatan fungsional berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Data jabatan fungsional berhasil diperbarui';
        }

        $dataJabatanFungsional->update($data);

        ActivityLogger::log('update', $dataJabatanFungsional, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataJabatanFungsional($dataJabatanFungsional->load(['jabatanFungsional']), false),
            'message' => $message
        ]);
    }

    // ... (method destroy tidak ada perubahan) ...

    // Submit draft ke diajukan
    public function submitDraft($id)
    {
        // ... (kode otentikasi dan pencarian data) ...

        $oldData = $dataJabatanFungsional->getOriginal();
        
        $dataJabatanFungsional->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now() // Ditambahkan
        ]);

        ActivityLogger::log('update', $dataJabatanFungsional, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data jabatan fungsional berhasil diajukan untuk persetujuan'
        ]);
    }

    // ... (method batchDelete tidak ada perubahan) ...

    // Batch submit drafts
    public function batchSubmitDrafts(Request $request)
    {
        // ... (kode validasi dan otentikasi) ...

        $updatedCount = SimpegDataJabatanFungsional::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now() // Ditambahkan
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data jabatan fungsional untuk persetujuan",
            'updated_count' => $updatedCount
        ]);
    }

    // Batch update status
    public function batchUpdateStatus(Request $request)
    {
        // ... (kode validasi dan otentikasi) ...

        $updateData = [
            'status_pengajuan' => $request->status_pengajuan
        ];

        // Diubah: Set timestamp berdasarkan status
        switch ($request->status_pengajuan) {
            case 'diajukan':
                $updateData['tgl_diajukan'] = now();
                $updateData['tgl_disetujui'] = null;
                $updateData['tgl_ditolak'] = null;
                break;
            case 'disetujui':
                $updateData['tgl_disetujui'] = now();
                $updateData['tgl_ditolak'] = null;
                break;
            case 'ditolak':
                $updateData['tgl_ditolak'] = now();
                $updateData['tgl_disetujui'] = null;
                break;
            case 'draft':
                $updateData['tgl_diajukan'] = null;
                $updateData['tgl_disetujui'] = null;
                $updateData['tgl_ditolak'] = null;
                break;
        }

        $updatedCount = SimpegDataJabatanFungsional::whereIn('id', $request->ids) // Dihapus pegawai_id agar admin bisa update
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui',
            'updated_count' => $updatedCount
        ]);
    }
    
    public function show($id)
    {
        $dataJabatanFungsional = SimpegDataJabatanFungsional::with([
            'jabatanFungsional'
        ])->findOrFail($id);

        // Ambil pengguna yang terotentikasi
        $authenticatedUser = Auth::user();

        // PERBAIKAN: Periksa apakah pengguna ada, lalu ambil relasi pegawai
        if (!$authenticatedUser) {
             return response()->json(['success' => false, 'message' => 'Tidak ada pengguna yang terotentikasi.'], 401);
        }

        $pegawai = $authenticatedUser->pegawai;

        // PERBAIKAN: Periksa apakah relasi pegawai ada
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai untuk pengguna yang login tidak ditemukan.'], 403);
        }

        // Kode otorisasi sekarang aman karena kita sudah yakin $pegawai tidak null
        if (!$pegawai->hasRole('Admin') && $pegawai->id !== $dataJabatanFungsional->pegawai_id) {
            return response()->json(['success' => false, 'message' => 'Anda tidak diizinkan untuk melihat data ini.'], 403);
        }

        $formattedData = $this->formatDataJabatanFungsional($dataJabatanFungsional, true);

        return response()->json([
            'success' => true,
            'data' => $formattedData
        ]);
    }

        public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai untuk pengguna yang login tidak ditemukan.'], 403);
        }

        $pegawai = $user->pegawai;

        // Ambil query builder
        $query = SimpegDataJabatanFungsional::with(['jabatanFungsional'])
            ->where('pegawai_id', $pegawai->id);

        // Lakukan sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Lakukan paginasi
        $perPage = $request->input('per_page', 10);
        $dataJabatanFungsional = $query->paginate($perPage);

        // Format setiap item dalam koleksi
        $dataJabatanFungsional->getCollection()->transform(function ($item) {
            return $this->formatDataJabatanFungsional($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataJabatanFungsional,
            'message' => 'Data jabatan fungsional berhasil diambil.'
        ]);
    }
 public function downloadFile($id)
    {
        // 1. Cari data di database, jika tidak ada akan otomatis 404 Not Found.
        $dataJabatanFungsional = SimpegDataJabatanFungsional::findOrFail($id);

        // 2. Lakukan otorisasi untuk memastikan pengguna berhak mengakses file ini.
        $user = Auth::user();
        if (!$user || !$user->pegawai) {
            return response()->json(['success' => false, 'message' => 'Tidak dapat mengidentifikasi pengguna.'], 403);
        }

        $pegawai = $user->pegawai;
        if (!$pegawai->hasRole('Admin') && $pegawai->id !== $dataJabatanFungsional->pegawai_id) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki izin untuk mengunduh file ini.'], 403);
        }

        // 3. Ambil path file dari record database yang terpercaya.
        $filePath = $dataJabatanFungsional->file_sk_jabatan;

        // 4. Periksa apakah path file ada dan file-nya benar-benar ada di storage.
        if (!$filePath || !Storage::exists($filePath)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan.'], 404);
        }

        // 5. Jika semua pemeriksaan lolos, kirim file untuk diunduh.
        // Laravel akan secara otomatis mengatur header yang diperlukan.
        return Storage::download($filePath);
    }

     public function getSystemConfig()
    {
        // Mendapatkan batas maksimum ukuran file upload dari konfigurasi PHP
        $uploadMaxFilesize = ini_get('upload_max_filesize');
        
        // Contoh mengambil tipe file yang diizinkan dari file .env (praktik yang baik)
        // Anda bisa menambahkan `ALLOWED_MIME_TYPES=application/pdf,image/jpeg,image/png` di file .env
        $allowedMimeTypes = env('ALLOWED_MIME_TYPES', 'application/pdf');

        return response()->json([
            'success' => true,
            'data' => [
                'upload_max_filesize' => $uploadMaxFilesize,
                'allowed_mime_types' => explode(',', $allowedMimeTypes) // Mengubah string menjadi array
            ],
            'message' => 'Konfigurasi sistem berhasil diambil.'
        ]);
    }

     public function getStatusStatistics()
    {
        $user = Auth::user();
        if (!$user || !$user->pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai untuk pengguna yang login tidak ditemukan.'], 403);
        }

        $pegawai = $user->pegawai;

        // Membangun query dasar
        $query = DB::table('simpeg_data_jabatan_fungsional')
            ->select('status_pengajuan', DB::raw('count(*) as total'));

        // Admin bisa melihat semua statistik, pengguna lain hanya bisa melihat miliknya
        if (!$pegawai->hasRole('Admin')) {
            $query->where('pegawai_id', $pegawai->id);
        }

        // Melakukan grouping dan mengambil hasil
        $statistics = $query->groupBy('status_pengajuan')->get();

        // Mengubah format hasil menjadi lebih mudah digunakan di frontend
        // Contoh: { "draft": 5, "diajukan": 2, "disetujui": 10, "ditolak": 1 }
        $formattedStatistics = $statistics->pluck('total', 'status_pengajuan')->all();
        
        // Memastikan semua status ada di dalam hasil, meskipun nilainya 0
        $allStatuses = ['draft', 'diajukan', 'disetujui', 'ditolak'];
        foreach ($allStatuses as $status) {
            if (!isset($formattedStatistics[$status])) {
                $formattedStatistics[$status] = 0;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $formattedStatistics,
            'message' => 'Statistik status berhasil diambil.'
        ]);
    }

    // ... (method getFormOptions, getStatusStatistics, getSystemConfig, downloadFile, formatPegawaiInfo tidak ada perubahan) ...

    // Helper: Format data jabatan fungsional response
    protected function formatDataJabatanFungsional($dataJabatanFungsional, $includeActions = true)
    {
        // ... (kode sebelum return $data) ...
        
        $data = [
            'id' => $dataJabatanFungsional->id,
            'jabatan_fungsional_id' => $dataJabatanFungsional->jabatan_fungsional_id,
            'nama_jabatan_fungsional' => $dataJabatanFungsional->jabatanFungsional ? $dataJabatanFungsional->jabatanFungsional->nama_jabatan_fungsional : '-',
            'tmt_jabatan' => $dataJabatanFungsional->tmt_jabatan,
            'tmt_jabatan_formatted' => $dataJabatanFungsional->tmt_jabatan ? $dataJabatanFungsional->tmt_jabatan->format('d-m-Y') : '-',
            'pejabat_penetap' => $dataJabatanFungsional->pejabat_penetap,
            'no_sk' => $dataJabatanFungsional->no_sk,
            'tanggal_sk' => $dataJabatanFungsional->tanggal_sk,
            'tanggal_sk_formatted' => $dataJabatanFungsional->tanggal_sk ? $dataJabatanFungsional->tanggal_sk->format('d-m-Y') : '-',
            'file_sk_jabatan' => $dataJabatanFungsional->file_sk_jabatan,
            'file_url' => $dataJabatanFungsional->file_sk_jabatan ? Storage::url($dataJabatanFungsional->file_sk_jabatan) : null,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            // Diubah: Menambahkan tanggal ke dalam response
            'timestamps' => [
                'tgl_input' => $dataJabatanFungsional->tgl_input ? \Carbon\Carbon::parse($dataJabatanFungsional->tgl_input)->format('d-m-Y') : '-',
                'tgl_diajukan' => $dataJabatanFungsional->tgl_diajukan ? \Carbon\Carbon::parse($dataJabatanFungsional->tgl_diajukan)->format('d-m-Y H:i') : null,
                'tgl_disetujui' => $dataJabatanFungsional->tgl_disetujui ? \Carbon\Carbon::parse($dataJabatanFungsional->tgl_disetujui)->format('d-m-Y H:i') : null,
                'tgl_ditolak' => $dataJabatanFungsional->tgl_ditolak ? \Carbon\Carbon::parse($dataJabatanFungsional->tgl_ditolak)->format('d-m-Y H:i') : null,
            ],
            'created_at' => $dataJabatanFungsional->created_at,
            'updated_at' => $dataJabatanFungsional->updated_at
        ];

        // ... (sisa method tidak berubah) ...
        return $data;
    }

    // ... (sisa controller tidak berubah) ...
}