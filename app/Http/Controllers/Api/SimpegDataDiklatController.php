<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataDiklat;
use App\Models\SimpegDataPendukung;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataDiklatController extends Controller
{
    // Get all data diklat for logged in pegawai
    public function index(Request $request) 
    {
        // Pastikan user sudah login
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        // Eager load semua relasi yang diperlukan untuk menghindari N+1 query problem
        $pegawai = Auth::user()->load([
            'unitKerja',
            'statusAktif', 
            'jabatanAkademik',
            'dataJabatanFungsional' => function($query) {
                $query->with('jabatanFungsional')
                      ->orderBy('tmt_jabatan', 'desc')
                      ->limit(1);
            },
            'dataJabatanStruktural' => function($query) {
                $query->with('jabatanStruktural.jenisJabatanStruktural')
                      ->orderBy('tgl_mulai', 'desc')
                      ->limit(1);
            },
            'dataPendidikanFormal' => function($query) {
                $query->with('jenjangPendidikan')
                      ->orderBy('jenjang_pendidikan_id', 'desc')
                      ->limit(1);
            }
        ]);

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan atau belum login'
            ], 404);
        }

        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $statusPengajuan = $request->status_pengajuan;

        // Query HANYA untuk pegawai yang sedang login
        $query = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->with(['dataPendukung']);

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_diklat', 'like', '%'.$search.'%')
                  ->orWhere('penyelenggara', 'like', '%'.$search.'%')
                  ->orWhere('jenis_diklat', 'like', '%'.$search.'%')
                  ->orWhere('kategori_diklat', 'like', '%'.$search.'%')
                  ->orWhere('tingkat_diklat', 'like', '%'.$search.'%')
                  ->orWhere('no_sertifikat', 'like', '%'.$search.'%');
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('jenis_diklat')) {
            $query->where('jenis_diklat', 'like', '%'.$request->jenis_diklat.'%');
        }
        if ($request->filled('kategori_diklat')) {
            $query->where('kategori_diklat', 'like', '%'.$request->kategori_diklat.'%');
        }
        if ($request->filled('tingkat_diklat')) {
            $query->where('tingkat_diklat', 'like', '%'.$request->tingkat_diklat.'%');
        }
        if ($request->filled('tahun_penyelenggaraan')) {
            $query->where('tahun_penyelenggaraan', $request->tahun_penyelenggaraan);
        }
        if ($request->filled('tgl_mulai')) {
            $query->whereDate('tgl_mulai', $request->tgl_mulai);
        }
        if ($request->filled('tgl_selesai')) {
            $query->whereDate('tgl_selesai', $request->tgl_selesai);
        }

        // Execute query dengan pagination
        $dataDiklat = $query->orderBy('tgl_mulai', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataDiklat->getCollection()->transform(function ($item) {
            return $this->formatDataDiklat($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataDiklat,
            'empty_data' => $dataDiklat->isEmpty(),
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ]
            ],
            'table_columns' => [
                ['field' => 'nama_diklat', 'label' => 'Nama Diklat', 'sortable' => true, 'sortable_field' => 'nama_diklat'],
                ['field' => 'jenis_diklat', 'label' => 'Jenis Diklat', 'sortable' => true, 'sortable_field' => 'jenis_diklat'],
                ['field' => 'kategori_diklat', 'label' => 'Kategori Kegiatan', 'sortable' => true, 'sortable_field' => 'kategori_diklat'],
                ['field' => 'tingkat_diklat', 'label' => 'Tingkatan Diklat', 'sortable' => true, 'sortable_field' => 'tingkat_diklat'],
                ['field' => 'penyelenggara', 'label' => 'Penyelenggara', 'sortable' => true, 'sortable_field' => 'penyelenggara'],
                ['field' => 'tahun_penyelenggaraan', 'label' => 'Tahun', 'sortable' => true, 'sortable_field' => 'tahun_penyelenggaraan'],
                ['field' => 'jumlah_jam', 'label' => 'Jumlah Jam', 'sortable' => true, 'sortable_field' => 'jumlah_jam'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_diklat_url' => url("/api/dosen/data-diklat"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/data-diklat/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/data-diklat/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/data-diklat/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataDiklat->currentPage(),
                'per_page' => $dataDiklat->perPage(),
                'total' => $dataDiklat->total(),
                'last_page' => $dataDiklat->lastPage(),
                'from' => $dataDiklat->firstItem(),
                'to' => $dataDiklat->lastItem()
            ]
        ]);
    }

    // Fix existing data dengan status_pengajuan null
    public function fixExistingData()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        // Update data yang status_pengajuan-nya null menjadi draft
        $updatedCount = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data diklat",
            'updated_count' => $updatedCount
        ]);
    }

    // Bulk fix all existing data (admin only atau bisa untuk semua user)
    public function bulkFixExistingData()
    {
        $updatedCount = SimpegDataDiklat::whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data diklat dari semua pegawai",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data diklat
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataDiklat = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->with(['dataPendukung'])
            ->find($id);

        if (!$dataDiklat) {
            return response()->json([
                'success' => false,
                'message' => 'Data diklat tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($pegawai->load([
                'unitKerja', 'statusAktif', 'jabatanAkademik',
                'dataJabatanFungsional.jabatanFungsional',
                'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
                'dataPendidikanFormal.jenjangPendidikan'
            ])),
            'data' => $this->formatDataDiklat($dataDiklat)
        ]);
    }

    // Store new data diklat dengan draft/submit mode
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
        'jenis_diklat' => 'required|string|max:100',
        'kategori_diklat' => 'required|string|max:100',
        'tingkat_diklat' => 'required|string|max:100',
        'nama_diklat' => 'required|string|max:255',
        'penyelenggara' => 'required|string|max:255',
        'peran' => 'nullable|string|max:100',
        'jumlah_jam' => 'nullable|integer|min:1',
        'no_sertifikat' => 'nullable|string|max:100',
        'tgl_sertifikat' => 'nullable|date',  // Make sure this matches frontend
        'tahun_penyelenggaraan' => 'required|integer|min:1900|max:' . (date('Y') + 1),
        'tgl_mulai' => 'required|date',  // Changed from tgl_mulai to tgl_mulai
        'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai', // Changed from tgl_selesai
        'tempat' => 'nullable|string|max:255',
        'sk_penugasan' => 'nullable|string|max:255', // This should NOT be a date field
        'submit_type' => 'sometimes|in:draft,submit',
        'keterangan' => 'nullable|string',
        // Files untuk data pendukung
        'files' => 'nullable|array',
        'files.*.file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
        'files.*.tipe_dokumen' => 'required|string|max:100',
        'files.*.nama_dokumen' => 'required|string|max:255',
        'files.*.jenis_dokumen_id' => 'nullable|integer',
        'files.*.keterangan' => 'nullable|string'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $data = $request->except(['files', 'submit_type']);
    $data['pegawai_id'] = $pegawai->id;
    $data['tgl_input'] = now()->toDateString();

    // Set status berdasarkan submit_type (default: draft)
    $submitType = $request->input('submit_type', 'draft');
    if ($submitType === 'submit') {
        $data['status_pengajuan'] = 'diajukan';
        $data['tgl_diajukan'] = now();
        $message = 'Data diklat berhasil diajukan untuk persetujuan';
    } else {
        $data['status_pengajuan'] = 'draft';
        $message = 'Data diklat berhasil disimpan sebagai draft';
    }

    DB::beginTransaction();
    try {
        $dataDiklat = SimpegDataDiklat::create($data);

        // Handle multiple file uploads untuk data pendukung
        if ($request->has('files') && is_array($request->files)) {
            foreach ($request->files as $index => $fileData) {
                if (isset($fileData['file']) && $fileData['file']->isValid()) {
                    $file = $fileData['file'];
                    $fileName = 'diklat_'.time().'_'.$pegawai->id.'_'.$index.'.'.$file->getClientOriginalExtension();
                    $filePath = $file->storeAs('public/pegawai/diklat/dokumen', $fileName);

                    SimpegDataPendukung::create([
                        'tipe_dokumen' => $request->input("files.{$index}.tipe_dokumen"),
                        'file_path' => $fileName,
                        'nama_dokumen' => $request->input("files.{$index}.nama_dokumen"),
                        'jenis_dokumen_id' => $request->input("files.{$index}.jenis_dokumen_id"),
                        'keterangan' => $request->input("files.{$index}.keterangan"),
                        'pendukungable_type' => SimpegDataDiklat::class,
                        'pendukungable_id' => $dataDiklat->id
                    ]);
                }
            }
        }

        DB::commit();
        ActivityLogger::log('create', $dataDiklat, $dataDiklat->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataDiklat($dataDiklat->load('dataPendukung')),
            'message' => $message
        ], 201);

    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'success' => false,
            'message' => 'Gagal menyimpan data diklat: ' . $e->getMessage()
        ], 500);
    }
}

    // Update data diklat dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        
        $dataDiklat = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->with(['dataPendukung'])
            ->find($id);

        if (!$dataDiklat) {
            return response()->json([
                'success' => false,
                'message' => 'Data diklat tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataDiklat->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'jenis_diklat' => 'sometimes|string|max:100',
            'kategori_diklat' => 'sometimes|string|max:100',
            'tingkat_diklat' => 'sometimes|string|max:100',
            'nama_diklat' => 'sometimes|string|max:255',
            'penyelenggara' => 'sometimes|string|max:255',
            'peran' => 'nullable|string|max:100',
            'jumlah_jam' => 'nullable|integer|min:1',
            'no_sertifikat' => 'nullable|string|max:100',
            'tgl_sertifikat' => 'nullable|date',
            'tahun_penyelenggaraan' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'tempat' => 'nullable|string|max:255',
            'sk_penugasan' => 'nullable|string|max:255',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string',
            // Files untuk data pendukung
            'files' => 'nullable|array',
            'files.*.file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'files.*.tipe_dokumen' => 'required|string|max:100',
            'files.*.nama_dokumen' => 'required|string|max:255',
            'files.*.jenis_dokumen_id' => 'nullable|integer',
            'files.*.keterangan' => 'nullable|string',
            // Untuk menghapus file yang sudah ada
            'remove_files' => 'nullable|array',
            'remove_files.*' => 'nullable|integer|exists:simpeg_data_pendukung,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldData = $dataDiklat->getOriginal();
            $data = $request->except(['files', 'submit_type', 'remove_files']);

            // Reset status jika dari ditolak
            if ($dataDiklat->status_pengajuan === 'ditolak') {
                $data['status_pengajuan'] = 'draft';
                $data['tgl_ditolak'] = null;
                $data['keterangan'] = $request->keterangan ?? null;
            }

            // Handle submit_type
            if ($request->submit_type === 'submit') {
                $data['status_pengajuan'] = 'diajukan';
                $data['tgl_diajukan'] = now();
                $message = 'Data diklat berhasil diperbarui dan diajukan untuk persetujuan';
            } else {
                $message = 'Data diklat berhasil diperbarui';
            }

            $dataDiklat->update($data);

            // Handle file removal
            if ($request->has('remove_files') && is_array($request->remove_files)) {
                foreach ($request->remove_files as $fileId) {
                    $pendukung = $dataDiklat->dataPendukung()->find($fileId);
                    if ($pendukung) {
                        Storage::delete('public/pegawai/diklat/dokumen/'.$pendukung->file_path);
                        $pendukung->delete();
                    }
                }
            }

            // Handle new file uploads
            if ($request->has('files') && is_array($request->files)) {
                foreach ($request->files as $index => $fileData) {
                    if (isset($fileData['file']) && $fileData['file']->isValid()) {
                        $file = $fileData['file'];
                        $fileName = 'diklat_'.time().'_'.$pegawai->id.'_'.$index.'.'.$file->getClientOriginalExtension();
                        $filePath = $file->storeAs('public/pegawai/diklat/dokumen', $fileName);

                        SimpegDataPendukung::create([
                            'tipe_dokumen' => $request->input("files.{$index}.tipe_dokumen"),
                            'file_path' => $fileName,
                            'nama_dokumen' => $request->input("files.{$index}.nama_dokumen"),
                            'jenis_dokumen_id' => $request->input("files.{$index}.jenis_dokumen_id"),
                            'keterangan' => $request->input("files.{$index}.keterangan"),
                            'pendukungable_type' => SimpegDataDiklat::class,
                            'pendukungable_id' => $dataDiklat->id
                        ]);
                    }
                }
            }

            DB::commit();
            ActivityLogger::log('update', $dataDiklat, $oldData);

            return response()->json([
                'success' => true,
                'data' => $this->formatDataDiklat($dataDiklat->load('dataPendukung')),
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data diklat: ' . $e->getMessage()
            ], 500);
        }
    }
    


    // Delete data diklat
  public function destroy($id)
{
    $pegawai = Auth::user();

    if (!$pegawai) {
        return response()->json([
            'success' => false,
            'message' => 'Data pegawai tidak ditemukan'
        ], 404);
    }

    $dataDiklat = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
        ->with(['dataPendukung'])
        ->find($id);

    if (!$dataDiklat) {
        return response()->json([
            'success' => false,
            'message' => 'Data diklat tidak ditemukan'
        ], 404);
    }

    DB::beginTransaction();
    try {
        // Delete files dari data pendukung
        foreach ($dataDiklat->dataPendukung as $pendukung) {
            Storage::delete('public/pegawai/diklat/dokumen/'.$pendukung->file_path);
            $pendukung->delete();
        }

        $oldData = $dataDiklat->toArray();
        $dataDiklat->delete();

        DB::commit();
        ActivityLogger::log('delete', $dataDiklat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data diklat berhasil dihapus'
        ]);

    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'success' => false,
            'message' => 'Gagal menghapus data diklat: ' . $e->getMessage()
        ], 500);
    }
}

    // Submit draft ke diajukan
    public function submitDraft($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }
        
        $dataDiklat = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataDiklat) {
            return response()->json([
                'success' => false,
                'message' => 'Data diklat draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataDiklat->getOriginal();
        
        $dataDiklat->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataDiklat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data diklat berhasil diajukan untuk persetujuan'
        ]);
    }

    public function submitDraftBatch(Request $request) // Menerima Request, tidak ada parameter $id di sini
{
    $request->validate([
        'ids' => 'required|array',
        'ids.*' => 'integer|exists:simpeg_data_diklat,id', // Validasi setiap ID adalah integer dan ada di tabel
    ]);

    $pegawai = Auth::user();

    if (!$pegawai) {
        return response()->json([
            'success' => false,
            'message' => 'Data pegawai tidak ditemukan'
        ], 404);
    }

    $diklatIds = $request->input('ids');

    // Ambil data diklat yang akan diupdate untuk logging
    $dataDiklatToUpdate = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
                                          ->whereIn('id', $diklatIds) // Gunakan whereIn untuk array ID
                                          ->where('status_pengajuan', 'draft') // Hanya yang statusnya 'draft'
                                          ->get();

    if ($dataDiklatToUpdate->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'Tidak ada data diklat draft yang ditemukan untuk ID yang diberikan atau sudah diajukan.'
        ], 404);
    }

    $updatedCount = 0;
    foreach ($dataDiklatToUpdate as $diklat) {
        $oldData = $diklat->getOriginal(); // Simpan data lama sebelum update
        $diklat->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now(), // Memperbarui 'tgl_diajukan'
        ]);
        ActivityLogger::log('update', $diklat, $oldData); // Log setiap update
        $updatedCount++;
    }


    return response()->json([
        'success' => true,
        'message' => "Berhasil mengajukan {$updatedCount} data diklat untuk persetujuan."
    ]);
}

    // Batch delete data diklat
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_diklat,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataDiklatList = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->with(['dataPendukung'])
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataDiklatList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data diklat tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataDiklatList as $dataDiklat) {
                try {
                    // Delete files dari data pendukung
                    foreach ($dataDiklat->dataPendukung as $pendukung) {
                        Storage::delete('public/pegawai/diklat/dokumen/'.$pendukung->file_path);
                        $pendukung->delete();
                    }

                    $oldData = $dataDiklat->toArray();
                    $dataDiklat->delete();
                    
                    ActivityLogger::log('delete', $dataDiklat, $oldData);
                    $deletedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $dataDiklat->id,
                        'nama_diklat' => $dataDiklat->nama_diklat,
                        'error' => 'Gagal menghapus: ' . $e->getMessage()
                    ];
                }
            }

            DB::commit();

            if ($deletedCount == count($request->ids)) {
                return response()->json([
                    'success' => true,
                    'message' => "Berhasil menghapus {$deletedCount} data diklat",
                    'deleted_count' => $deletedCount
                ]);
            } else {
                return response()->json([
                    'success' => $deletedCount > 0,
                    'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data diklat",
                    'deleted_count' => $deletedCount,
                    'errors' => $errors
                ], $deletedCount > 0 ? 207 : 422);
            }

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan batch delete: ' . $e->getMessage()
            ], 500);
        }
    }

    // Batch submit drafts
    public function batchSubmitDrafts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $updatedCount = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data diklat untuk persetujuan",
            'updated_count' => $updatedCount
        ]);
    }

    // Batch update status
    public function batchUpdateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $updateData = ['status_pengajuan' => $request->status_pengajuan];

        // Set timestamp based on status
        switch ($request->status_pengajuan) {
            case 'diajukan':
                $updateData['tgl_diajukan'] = now();
                break;
            case 'disetujui':
                $updateData['tgl_disetujui'] = now();
                break;
            case 'ditolak':
                $updateData['tgl_ditolak'] = now();
                break;
        }

        $updatedCount = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui',
            'updated_count' => $updatedCount
        ]);
    }

    // Get status statistics untuk dashboard
    public function getStatusStatistics()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $statistics = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->selectRaw('status_pengajuan, COUNT(*) as total')
            ->groupBy('status_pengajuan')
            ->get()
            ->pluck('total', 'status_pengajuan')
            ->toArray();

        $defaultStats = [
            'draft' => 0,
            'diajukan' => 0,
            'disetujui' => 0,
            'ditolak' => 0
        ];

        $statistics = array_merge($defaultStats, $statistics);
        $statistics['total'] = array_sum($statistics);

        return response()->json([
            'success' => true,
            'statistics' => $statistics
        ]);
    }

    // Get system configuration
    public function getSystemConfig()
    {
        $config = [
            'submission_mode' => env('SUBMISSION_MODE', 'draft'),
            'allow_edit_after_submit' => env('ALLOW_EDIT_AFTER_SUBMIT', false),
            'require_document_upload' => env('REQUIRE_DOCUMENT_UPLOAD', false),
            'max_draft_days' => env('MAX_DRAFT_DAYS', 30),
            'auto_submit_reminder_days' => env('AUTO_SUBMIT_REMINDER_DAYS', 7)
        ];

        return response()->json([
            'success' => true,
            'config' => $config,
            'status_flow' => [
                [
                    'status' => 'draft',
                    'label' => 'Draft',
                    'description' => 'Data tersimpan tapi belum diajukan',
                    'color' => 'secondary',
                    'icon' => 'edit',
                    'actions' => ['edit', 'delete', 'submit']
                ],
                [
                    'status' => 'diajukan',
                    'label' => 'Diajukan',
                    'description' => 'Menunggu persetujuan atasan',
                    'color' => 'info',
                    'icon' => 'clock',
                    'actions' => ['view']
                ],
                [
                    'status' => 'disetujui',
                    'label' => 'Disetujui',
                    'description' => 'Telah disetujui oleh atasan',
                    'color' => 'success',
                    'icon' => 'check-circle',
                    'actions' => ['view']
                ],
                [
                    'status' => 'ditolak',
                    'label' => 'Ditolak',
                    'description' => 'Ditolak oleh atasan',
                    'color' => 'danger',
                    'icon' => 'x-circle',
                    'actions' => ['view', 'edit', 'resubmit']
                ]
            ]
        ]);
    }

    // Get filter options
    public function getFilterOptions()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $jenisDiklat = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('jenis_diklat')
            ->filter()
            ->values();

        $kategoriKegiatan = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('kategori_diklat')
            ->filter()
            ->values();

        $tingkatanDiklat = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('tingkat_diklat')
            ->filter()
            ->values();

        $tahunPenyelenggaraan = SimpegDataDiklat::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('tahun_penyelenggaraan')
            ->filter()
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'jenis_diklat' => $jenisDiklat,
                'kategori_diklat' => $kategoriKegiatan,
                'tingkat_diklat' => $tingkatanDiklat,
                'tahun_penyelenggaraan' => $tahunPenyelenggaraan,
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ]
            ]
        ]);
    }

    // Get available actions
    public function getAvailableActions()
    {
        return response()->json([
            'success' => true,
            'actions' => [
                'single' => [
                    [
                        'key' => 'view',
                        'label' => 'Lihat Detail',
                        'icon' => 'eye',
                        'color' => 'info'
                    ],
                    [
                        'key' => 'edit',
                        'label' => 'Edit',
                        'icon' => 'edit',
                        'color' => 'warning',
                        'condition' => 'can_edit'
                    ],
                    [
                        'key' => 'delete',
                        'label' => 'Hapus',
                        'icon' => 'trash',
                        'color' => 'danger',
                        'confirm' => true,
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus data diklat ini?',
                        'condition' => 'can_delete'
                    ],
                    [
                        'key' => 'submit',
                        'label' => 'Ajukan',
                        'icon' => 'paper-plane',
                        'color' => 'primary',
                        'condition' => 'can_submit'
                    ]
                ],
                'batch' => [
                    [
                        'key' => 'batch_delete',
                        'label' => 'Hapus Terpilih',
                        'icon' => 'trash',
                        'color' => 'danger',
                        'confirm' => true,
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua data diklat yang dipilih?'
                    ],
                    [
                        'key' => 'batch_submit',
                        'label' => 'Ajukan Terpilih',
                        'icon' => 'paper-plane',
                        'color' => 'primary'
                    ],
                    [
                        'key' => 'batch_update_status',
                        'label' => 'Update Status Terpilih',
                        'icon' => 'check-circle',
                        'color' => 'info'
                    ]
                ]
            ],
            'status_options' => [
                ['value' => 'draft', 'label' => 'Draft', 'color' => 'secondary'],
                ['value' => 'diajukan', 'label' => 'Diajukan', 'color' => 'info'],
                ['value' => 'disetujui', 'label' => 'Disetujui', 'color' => 'success'],
                ['value' => 'ditolak', 'label' => 'Ditolak', 'color' => 'danger']
            ]
        ]);
    }

    // Helper: Format pegawai info
    private function formatPegawaiInfo($pegawai)
    {
        $jabatanAkademikNama = '-';
        if ($pegawai->jabatanAkademik) {
            $jabatanAkademikNama = $pegawai->jabatanAkademik->jabatan_akademik ?? '-';
        }

        $jabatanFungsionalNama = '-';
        if ($pegawai->dataJabatanFungsional && $pegawai->dataJabatanFungsional->isNotEmpty()) {
            $jabatanFungsional = $pegawai->dataJabatanFungsional->first()->jabatanFungsional;
            if ($jabatanFungsional) {
                if (isset($jabatanFungsional->nama_jabatan_fungsional)) {
                    $jabatanFungsionalNama = $jabatanFungsional->nama_jabatan_fungsional;
                } elseif (isset($jabatanFungsional->nama)) {
                    $jabatanFungsionalNama = $jabatanFungsional->nama;
                }
            }
        }

        $jabatanStrukturalNama = '-';
        if ($pegawai->dataJabatanStruktural && $pegawai->dataJabatanStruktural->isNotEmpty()) {
            $jabatan = $pegawai->dataJabatanStruktural->first();
            
            if ($jabatan->jabatanStruktural && $jabatan->jabatanStruktural->jenisJabatanStruktural) {
                $jabatanStrukturalNama = $jabatan->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural;
            }
            elseif (isset($jabatan->jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatan->jabatanStruktural->nama_jabatan;
            }
            elseif (isset($jabatan->jabatanStruktural->singkatan)) {
                $jabatanStrukturalNama = $jabatan->jabatanStruktural->singkatan;
            }
            elseif (isset($jabatan->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatan->nama_jabatan;
            }
        }

        $jenjangPendidikanNama = '-';
        if ($pegawai->dataPendidikanFormal && $pegawai->dataPendidikanFormal->isNotEmpty()) {
            $highestEducation = $pegawai->dataPendidikanFormal->first();
            if ($highestEducation && $highestEducation->jenjangPendidikan) {
                $jenjangPendidikanNama = $highestEducation->jenjangPendidikan->jenjang_pendidikan ?? '-';
            }
        }

        $unitKerjaNama = 'Tidak Ada';
        if ($pegawai->unitKerja) {
            $unitKerjaNama = $pegawai->unitKerja->nama_unit;
        } elseif ($pegawai->unit_kerja_id) {
            $unitKerja = SimpegUnitKerja::find($pegawai->unit_kerja_id);
            $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $pegawai->unit_kerja_id;
        }

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama' => $pegawai->nama ?? '-',
            'unit_kerja' => $unitKerjaNama,
            'status' => $pegawai->statusAktif ? $pegawai->statusAktif->nama_status_aktif : '-',
            'jab_akademik' => $jabatanAkademikNama,
            'jab_fungsional' => $jabatanFungsionalNama,
            'jab_struktural' => $jabatanStrukturalNama,
            'pendidikan' => $jenjangPendidikanNama
        ];
    }

    // Helper: Format data diklat response
    protected function formatDataDiklat($dataDiklat, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataDiklat->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        $data = [
            'id' => $dataDiklat->id,
            'jenis_diklat' => $dataDiklat->jenis_diklat,
            'kategori_diklat' => $dataDiklat->kategori_diklat,
            'tingkat_diklat' => $dataDiklat->tingkat_diklat,
            'nama_diklat' => $dataDiklat->nama_diklat,
            'penyelenggara' => $dataDiklat->penyelenggara,
            'peran' => $dataDiklat->peran,
            'jumlah_jam' => $dataDiklat->jumlah_jam,
            'no_sertifikat' => $dataDiklat->no_sertifikat,
            'tgl_sertifikat' => $dataDiklat->tgl_sertifikat,
            'tahun_penyelenggaraan' => $dataDiklat->tahun_penyelenggaraan,
            'tgl_mulai' => $dataDiklat->tgl_mulai,
            'tgl_selesai' => $dataDiklat->tgl_selesai,
            'tempat' => $dataDiklat->tempat,
            'sk_penugasan' => $dataDiklat->sk_penugasan,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataDiklat->keterangan,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataDiklat->tgl_input,
                'tgl_diajukan' => $dataDiklat->tgl_diajukan,
                'tgl_disetujui' => $dataDiklat->tgl_disetujui,
                'tgl_ditolak' => $dataDiklat->tgl_ditolak
            ],
            'created_at' => $dataDiklat->created_at,
            'updated_at' => $dataDiklat->updated_at
        ];

        // Format data pendukung (files)
        if ($dataDiklat->dataPendukung) {
            $data['dokumen_pendukung'] = $dataDiklat->dataPendukung->map(function($pendukung) {
                return [
                    'id' => $pendukung->id,
                    'tipe_dokumen' => $pendukung->tipe_dokumen,
                    'nama_dokumen' => $pendukung->nama_dokumen,
                    'jenis_dokumen_id' => $pendukung->jenis_dokumen_id,
                    'keterangan' => $pendukung->keterangan,
                    'file_path' => $pendukung->file_path,
                    'url' => url('storage/pegawai/diklat/dokumen/'.$pendukung->file_path),
                    'created_at' => $pendukung->created_at
                ];
            });
        } else {
            $data['dokumen_pendukung'] = [];
        }

        // Add action URLs if requested
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/dosen/data-diklat/{$dataDiklat->id}"),
                'update_url' => url("/api/dosen/data-diklat/{$dataDiklat->id}"),
                'delete_url' => url("/api/dosen/data-diklat/{$dataDiklat->id}"),
                'submit_url' => url("/api/dosen/data-diklat/{$dataDiklat->id}/submit"),
            ];

            // Conditional action URLs based on permissions
            $data['actions'] = [];
            
            if ($canEdit) {
                $data['actions']['edit'] = [
                    'url' => $data['aksi']['update_url'],
                    'method' => 'PUT',
                    'label' => 'Edit',
                    'icon' => 'edit',
                    'color' => 'warning'
                ];
            }
            
            if ($canDelete) {
                $data['actions']['delete'] = [
                    'url' => $data['aksi']['delete_url'],
                    'method' => 'DELETE',
                    'label' => 'Hapus',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data diklat "' . $dataDiklat->nama_diklat . '"?'
                ];
            }
            
            if ($canSubmit) {
                $data['actions']['submit'] = [
                    'url' => $data['aksi']['submit_url'],
                    'method' => 'PATCH',
                    'label' => 'Ajukan',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data diklat "' . $dataDiklat->nama_diklat . '" untuk persetujuan?'
                ];
            }
            
            // Always allow view/detail
            $data['actions']['view'] = [
                'url' => $data['aksi']['detail_url'],
                'method' => 'GET',
                'label' => 'Lihat Detail',
                'icon' => 'eye',
                'color' => 'info'
            ];
        }

        return $data;
    }

    // Helper: Get status info
    private function getStatusInfo($status)
    {
        $statusMap = [
            'draft' => [
                'label' => 'Draft',
                'color' => 'secondary',
                'icon' => 'edit',
                'description' => 'Belum diajukan'
            ],
            'diajukan' => [
                'label' => 'Diajukan',
                'color' => 'info',
                'icon' => 'clock',
                'description' => 'Menunggu persetujuan'
            ],
            'disetujui' => [
                'label' => 'Disetujui',
                'color' => 'success',
                'icon' => 'check-circle',
                'description' => 'Telah disetujui'
            ],
            'ditolak' => [
                'label' => 'Ditolak',
                'color' => 'danger',
                'icon' => 'x-circle',
                'description' => 'Ditolak, dapat diedit ulang'
            ]
        ];

        return $statusMap[$status] ?? [
            'label' => ucfirst($status),
            'color' => 'secondary',
            'icon' => 'circle',
            'description' => ''
        ];
    }
}