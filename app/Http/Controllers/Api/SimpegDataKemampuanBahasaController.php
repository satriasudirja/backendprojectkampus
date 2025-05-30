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

class SimpegDataKemampuanBahasaController extends Controller
{
    // Get all data kemampuan bahasa for logged in pegawai
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
        $query = SimpegDataKemampuanBahasa::with('bahasa')
            ->where('pegawai_id', $pegawai->id);

        // Filter by search
        if ($search) {
            $query->search($search);
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->byStatus($statusPengajuan);
        }

        // Additional filters
        if ($request->filled('tahun')) {
            $query->where('tahun', $request->tahun);
        }
        if ($request->filled('bahasa_id')) {
            $query->where('bahasa_id', $request->bahasa_id);
        }
        if ($request->filled('nama_lembaga')) {
            $query->where('nama_lembaga', 'like', '%'.$request->nama_lembaga.'%');
        }

        // Execute query dengan pagination
        $dataKemampuanBahasa = $query->orderBy('tahun', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataKemampuanBahasa->getCollection()->transform(function ($item) {
            return $this->formatDataKemampuanBahasa($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataKemampuanBahasa,
            'empty_data' => $dataKemampuanBahasa->isEmpty(),
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
            'bahasa_options' => $this->getBahasaOptions(),
            'kemampuan_options' => $this->getKemampuanOptions(),
            'table_columns' => [
                ['field' => 'tahun', 'label' => 'Tahun', 'sortable' => true, 'sortable_field' => 'tahun'],
                ['field' => 'nama_bahasa', 'label' => 'Bahasa', 'sortable' => true, 'sortable_field' => 'bahasa_id'],
                ['field' => 'nama_lembaga', 'label' => 'Nama Lembaga', 'sortable' => true, 'sortable_field' => 'nama_lembaga'],
                ['field' => 'kemampuan_mendengar', 'label' => 'Kemampuan Mendengar', 'sortable' => true, 'sortable_field' => 'kemampuan_mendengar'],
                ['field' => 'kemampuan_bicara', 'label' => 'Kemampuan Bicara', 'sortable' => true, 'sortable_field' => 'kemampuan_bicara'],
                ['field' => 'kemampuan_menulis', 'label' => 'Kemampuan Menulis', 'sortable' => true, 'sortable_field' => 'kemampuan_menulis'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_kemampuan_bahasa_url' => url("/api/dosen/datakemampuanbahasa"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/datakemampuanbahasa/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/datakemampuanbahasa/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/datakemampuanbahasa/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataKemampuanBahasa->currentPage(),
                'per_page' => $dataKemampuanBahasa->perPage(),
                'total' => $dataKemampuanBahasa->total(),
                'last_page' => $dataKemampuanBahasa->lastPage(),
                'from' => $dataKemampuanBahasa->firstItem(),
                'to' => $dataKemampuanBahasa->lastItem()
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
        $updatedCount = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data kemampuan bahasa",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data kemampuan bahasa
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::with('bahasa')
            ->where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataKemampuanBahasa) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa tidak ditemukan'
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
            'data' => $this->formatDataKemampuanBahasa($dataKemampuanBahasa),
            'bahasa_options' => $this->getBahasaOptions(),
            'kemampuan_options' => $this->getKemampuanOptions()
        ]);
    }

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
            'kemampuan_mendengar' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'kemampuan_bicara' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'kemampuan_menulis' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if tahun and bahasa_id combination already exists
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

        $data = $request->except(['file_pendukung', 'submit_type']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Set status berdasarkan submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data kemampuan bahasa berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data kemampuan bahasa berhasil disimpan sebagai draft';
        }

        // Handle file upload
        if ($request->hasFile('file_pendukung')) {
            $file = $request->file('file_pendukung');
            $fileName = 'kemampuan_bahasa_'.time().'_'.$pegawai->id.'_'.$request->tahun.'_'.$request->bahasa_id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/kemampuan-bahasa', $fileName);
            $data['file_pendukung'] = $fileName;
        }

        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::create($data);

        ActivityLogger::log('create', $dataKemampuanBahasa, $dataKemampuanBahasa->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataKemampuanBahasa($dataKemampuanBahasa->load('bahasa')),
            'message' => $message
        ], 201);
    }

    // Update data kemampuan bahasa dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataKemampuanBahasa) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataKemampuanBahasa->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'tahun' => 'sometimes|integer|min:1900|max:' . (date('Y') + 5),
            'bahasa_id' => 'sometimes|exists:bahasa,id',
            'nama_lembaga' => 'nullable|string|max:100',
            'kemampuan_mendengar' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'kemampuan_bicara' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'kemampuan_menulis' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check tahun and bahasa_id uniqueness
        if ($request->has('tahun') || $request->has('bahasa_id')) {
            $existingData = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
                ->where('tahun', $request->input('tahun', $dataKemampuanBahasa->tahun))
                ->where('bahasa_id', $request->input('bahasa_id', $dataKemampuanBahasa->bahasa_id))
                ->where('id', '!=', $id)
                ->first();

            if ($existingData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data kemampuan bahasa untuk tahun dan bahasa yang sama sudah ada'
                ], 422);
            }
        }

        $oldData = $dataKemampuanBahasa->getOriginal();
        $data = $request->except(['file_pendukung', 'submit_type']);

        // Reset status jika dari ditolak
        if ($dataKemampuanBahasa->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null;
            $data['keterangan'] = $request->keterangan ?? null;
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data kemampuan bahasa berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Data kemampuan bahasa berhasil diperbarui';
        }

        // Handle file upload
        if ($request->hasFile('file_pendukung')) {
            if ($dataKemampuanBahasa->file_pendukung) {
                Storage::delete('public/pegawai/kemampuan-bahasa/'.$dataKemampuanBahasa->file_pendukung);
            }

            $file = $request->file('file_pendukung');
            $fileName = 'kemampuan_bahasa_'.time().'_'.$pegawai->id.'_'.($request->tahun ?? $dataKemampuanBahasa->tahun).'_'.($request->bahasa_id ?? $dataKemampuanBahasa->bahasa_id).'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/kemampuan-bahasa', $fileName);
            $data['file_pendukung'] = $fileName;
        }

        $dataKemampuanBahasa->update($data);

        ActivityLogger::log('update', $dataKemampuanBahasa, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataKemampuanBahasa($dataKemampuanBahasa->load('bahasa')),
            'message' => $message
        ]);
    }

    // Delete data kemampuan bahasa
    public function destroy($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataKemampuanBahasa) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($dataKemampuanBahasa->file_pendukung) {
            Storage::delete('public/pegawai/kemampuan-bahasa/'.$dataKemampuanBahasa->file_pendukung);
        }

        $oldData = $dataKemampuanBahasa->toArray();
        $dataKemampuanBahasa->delete();

        ActivityLogger::log('delete', $dataKemampuanBahasa, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data kemampuan bahasa berhasil dihapus'
        ]);
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

        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataKemampuanBahasa) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataKemampuanBahasa->getOriginal();
        
        $dataKemampuanBahasa->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataKemampuanBahasa, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data kemampuan bahasa berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete data kemampuan bahasa
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_kemampuan_bahasa,id'
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

        $dataKemampuanBahasaList = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataKemampuanBahasaList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataKemampuanBahasaList as $dataKemampuanBahasa) {
            try {
                // Delete file if exists
                if ($dataKemampuanBahasa->file_pendukung) {
                    Storage::delete('public/pegawai/kemampuan-bahasa/'.$dataKemampuanBahasa->file_pendukung);
                }

                $oldData = $dataKemampuanBahasa->toArray();
                $dataKemampuanBahasa->delete();
                
                ActivityLogger::log('delete', $dataKemampuanBahasa, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataKemampuanBahasa->id,
                    'tahun' => $dataKemampuanBahasa->tahun,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data kemampuan bahasa",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data kemampuan bahasa",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
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

        $updatedCount = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data kemampuan bahasa untuk persetujuan",
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

        $updatedCount = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
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

        $statistics = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
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

        $tahunList = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('tahun')
            ->filter()
            ->sort()
            ->values();

        $namaLembaga = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('nama_lembaga')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'tahun' => $tahunList,
                'nama_lembaga' => $namaLembaga,
                'bahasa_options' => $this->getBahasaOptions(),
                'kemampuan_options' => $this->getKemampuanOptions(),
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

    // Helper: Get bahasa options
    private function getBahasaOptions()
    {
        return SimpegBahasa::select('id', 'nama_bahasa')
            ->orderBy('nama_bahasa')
            ->get()
            ->map(function($bahasa) {
                return [
                    'value' => $bahasa->id,
                    'label' => $bahasa->nama_bahasa
                ];
            });
    }

    // Helper: Get kemampuan options
    private function getKemampuanOptions()
    {
        return [
            ['value' => 'Sangat Baik', 'label' => 'Sangat Baik'],
            ['value' => 'Baik', 'label' => 'Baik'],
            ['value' => 'Cukup', 'label' => 'Cukup'],
            ['value' => 'Kurang', 'label' => 'Kurang']
        ];
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
            $jabatanStruktural = $pegawai->dataJabatanStruktural->first();
            
            if ($jabatanStruktural->jabatanStruktural && $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural;
            }
            elseif (isset($jabatanStruktural->jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->nama_jabatan;
            }
            elseif (isset($jabatanStruktural->jabatanStruktural->singkatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->singkatan;
            }
            elseif (isset($jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->nama_jabatan;
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

    // Helper: Format data kemampuan bahasa response
    protected function formatDataKemampuanBahasa($dataKemampuanBahasa, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataKemampuanBahasa->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        $data = [
            'id' => $dataKemampuanBahasa->id,
            'tahun' => $dataKemampuanBahasa->tahun,
            'bahasa_id' => $dataKemampuanBahasa->bahasa_id,
            'nama_bahasa' => $dataKemampuanBahasa->nama_bahasa,
            'nama_lembaga' => $dataKemampuanBahasa->nama_lembaga,
            'kemampuan_mendengar' => $dataKemampuanBahasa->kemampuan_mendengar,
            'kemampuan_bicara' => $dataKemampuanBahasa->kemampuan_bicara,
            'kemampuan_menulis' => $dataKemampuanBahasa->kemampuan_menulis,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataKemampuanBahasa->keterangan,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataKemampuanBahasa->tgl_input,
                'tgl_diajukan' => $dataKemampuanBahasa->tgl_diajukan,
                'tgl_disetujui' => $dataKemampuanBahasa->tgl_disetujui,
                'tgl_ditolak' => $dataKemampuanBahasa->tgl_ditolak
            ],
            'dokumen' => $dataKemampuanBahasa->file_pendukung ? [
                'nama_file' => $dataKemampuanBahasa->file_pendukung,
                'url' => url('storage/pegawai/kemampuan-bahasa/'.$dataKemampuanBahasa->file_pendukung)
            ] : null,
            'created_at' => $dataKemampuanBahasa->created_at,
            'updated_at' => $dataKemampuanBahasa->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/dosen/datakemampuanbahasa/{$dataKemampuanBahasa->id}"),
                'update_url' => url("/api/dosen/datakemampuanbahasa/{$dataKemampuanBahasa->id}"),
                'delete_url' => url("/api/dosen/datakemampuanbahasa/{$dataKemampuanBahasa->id}"),
                'submit_url' => url("/api/dosen/datakemampuanbahasa/{$dataKemampuanBahasa->id}/submit"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data kemampuan bahasa tahun "' . $dataKemampuanBahasa->tahun . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data kemampuan bahasa tahun "' . $dataKemampuanBahasa->tahun . '" untuk persetujuan?'
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