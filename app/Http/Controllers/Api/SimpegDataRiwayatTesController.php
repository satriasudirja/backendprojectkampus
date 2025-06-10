<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataTes;
use App\Models\SimpegDaftarJenisTest;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataRiwayatTesController extends Controller
{
    // Get all data riwayat tes for logged in pegawai
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
        $query = SimpegDataTes::where('pegawai_id', $pegawai->id)
            ->with(['jenisTes']);

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_tes', 'like', '%'.$search.'%')
                  ->orWhere('penyelenggara', 'like', '%'.$search.'%')
                  ->orWhere('skor', 'like', '%'.$search.'%')
                  ->orWhere('tgl_tes', 'like', '%'.$search.'%')
                  ->orWhereHas('jenisTes', function($jq) use ($search) {
                      $jq->where('jenis_tes', 'like', '%'.$search.'%')
                        ->orWhere('kode', 'like', '%'.$search.'%');
                  });
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('jenis_tes_id')) {
            $query->where('jenis_tes_id', $request->jenis_tes_id);
        }
        if ($request->filled('nama_tes')) {
            $query->where('nama_tes', 'like', '%'.$request->nama_tes.'%');
        }
        if ($request->filled('penyelenggara')) {
            $query->where('penyelenggara', 'like', '%'.$request->penyelenggara.'%');
        }
        if ($request->filled('tgl_tes')) {
            $query->whereDate('tgl_tes', $request->tgl_tes);
        }
        if ($request->filled('skor_min')) {
            $query->where('skor', '>=', $request->skor_min);
        }
        if ($request->filled('skor_max')) {
            $query->where('skor', '<=', $request->skor_max);
        }

        // Execute query dengan pagination
        $dataRiwayatTes = $query->orderBy('tgl_tes', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataRiwayatTes->getCollection()->transform(function ($item) {
            return $this->formatDataRiwayatTes($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataRiwayatTes,
            'empty_data' => $dataRiwayatTes->isEmpty(),
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ],
                'jenis_tes' => SimpegDaftarJenisTest::select('id', 'jenis_tes as nama', 'kode')
                    ->orderBy('jenis_tes')
                    ->get()
                    ->toArray()
            ],
            'table_columns' => [
                ['field' => 'jenis_tes', 'label' => 'Jenis Tes', 'sortable' => true, 'sortable_field' => 'jenis_tes_id'],
                ['field' => 'nama_tes', 'label' => 'Nama Tes', 'sortable' => true, 'sortable_field' => 'nama_tes'],
                ['field' => 'penyelenggara', 'label' => 'Penyelenggara', 'sortable' => true, 'sortable_field' => 'penyelenggara'],
                ['field' => 'tgl_tes', 'label' => 'Tanggal Tes', 'sortable' => true, 'sortable_field' => 'tgl_tes'],
                ['field' => 'skor', 'label' => 'Skor', 'sortable' => true, 'sortable_field' => 'skor'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_url' => url("/api/dosen/datariwayattes"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/datariwayattes/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/datariwayattes/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/datariwayattes/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataRiwayatTes->currentPage(),
                'per_page' => $dataRiwayatTes->perPage(),
                'total' => $dataRiwayatTes->total(),
                'last_page' => $dataRiwayatTes->lastPage(),
                'from' => $dataRiwayatTes->firstItem(),
                'to' => $dataRiwayatTes->lastItem()
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
        $updatedCount = SimpegDataTes::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data riwayat tes",
            'updated_count' => $updatedCount
        ]);
    }

    // Bulk fix all existing data (admin only atau bisa untuk semua user)
    public function bulkFixExistingData()
    {
        $updatedCount = SimpegDataTes::whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data riwayat tes dari semua pegawai",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data riwayat tes
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataRiwayatTes = SimpegDataTes::where('pegawai_id', $pegawai->id)
            ->with(['jenisTes'])
            ->find($id);

        if (!$dataRiwayatTes) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat tes tidak ditemukan'
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
            'data' => $this->formatDataRiwayatTes($dataRiwayatTes)
        ]);
    }

    // Store new data riwayat tes dengan draft/submit mode
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
            'jenis_tes_id' => 'required|integer|exists:simpeg_daftar_jenis_test,id',
            'nama_tes' => 'required|string|max:100',
            'penyelenggara' => 'required|string|max:100',
            'tgl_tes' => 'required|date|before_or_equal:today',
            'skor' => 'required|numeric|min:0|max:999.99',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'sometimes|in:draft,submit', // Optional, default to draft
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
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
            $message = 'Data riwayat tes berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data riwayat tes berhasil disimpan sebagai draft';
        }

        // Handle file upload
        if ($request->hasFile('file_pendukung')) {
            $file = $request->file('file_pendukung');
            $fileName = 'tes_'.time().'_'.$pegawai->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/tes/dokumen', $fileName);
            $data['file_pendukung'] = $fileName;
        }

        $dataRiwayatTes = SimpegDataTes::create($data);

        ActivityLogger::log('create', $dataRiwayatTes, $dataRiwayatTes->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataRiwayatTes($dataRiwayatTes->load('jenisTes')),
            'message' => $message
        ], 201);
    }

    // Update data riwayat tes dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataRiwayatTes = SimpegDataTes::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataRiwayatTes) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat tes tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataRiwayatTes->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'jenis_tes_id' => 'sometimes|integer|exists:simpeg_daftar_jenis_test,id',
            'nama_tes' => 'sometimes|string|max:100',
            'penyelenggara' => 'sometimes|string|max:100',
            'tgl_tes' => 'sometimes|date|before_or_equal:today',
            'skor' => 'sometimes|numeric|min:0|max:999.99',
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

        $oldData = $dataRiwayatTes->getOriginal();
        $data = $request->except(['file_pendukung', 'submit_type']);

        // Reset status jika dari ditolak
        if ($dataRiwayatTes->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null;
            $data['keterangan'] = $request->keterangan ?? null;
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data riwayat tes berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Data riwayat tes berhasil diperbarui';
        }

        // Handle file upload
        if ($request->hasFile('file_pendukung')) {
            if ($dataRiwayatTes->file_pendukung) {
                Storage::delete('public/pegawai/tes/dokumen/'.$dataRiwayatTes->file_pendukung);
            }

            $file = $request->file('file_pendukung');
            $fileName = 'tes_'.time().'_'.$pegawai->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/tes/dokumen', $fileName);
            $data['file_pendukung'] = $fileName;
        }

        $dataRiwayatTes->update($data);

        ActivityLogger::log('update', $dataRiwayatTes, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataRiwayatTes($dataRiwayatTes->load('jenisTes')),
            'message' => $message
        ]);
    }

    // Delete data riwayat tes
    public function destroy($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataRiwayatTes = SimpegDataTes::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataRiwayatTes) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat tes tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($dataRiwayatTes->file_pendukung) {
            Storage::delete('public/pegawai/tes/dokumen/'.$dataRiwayatTes->file_pendukung);
        }

        $oldData = $dataRiwayatTes->toArray();
        $dataRiwayatTes->delete();

        ActivityLogger::log('delete', $dataRiwayatTes, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat tes berhasil dihapus'
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

        $dataRiwayatTes = SimpegDataTes::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataRiwayatTes) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat tes draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataRiwayatTes->getOriginal();
        
        $dataRiwayatTes->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataRiwayatTes, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat tes berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete data riwayat tes
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_tes,id'
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

        $dataRiwayatTesList = SimpegDataTes::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataRiwayatTesList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat tes tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataRiwayatTesList as $dataRiwayatTes) {
            try {
                // Delete file if exists
                if ($dataRiwayatTes->file_pendukung) {
                    Storage::delete('public/pegawai/tes/dokumen/'.$dataRiwayatTes->file_pendukung);
                }

                $oldData = $dataRiwayatTes->toArray();
                $dataRiwayatTes->delete();
                
                ActivityLogger::log('delete', $dataRiwayatTes, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataRiwayatTes->id,
                    'nama_tes' => $dataRiwayatTes->nama_tes,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data riwayat tes",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data riwayat tes",
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

        $updatedCount = SimpegDataTes::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data riwayat tes untuk persetujuan",
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

        $updatedCount = SimpegDataTes::where('pegawai_id', $pegawai->id)
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

        $statistics = SimpegDataTes::where('pegawai_id', $pegawai->id)
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

        $jenisTesList = SimpegDaftarJenisTest::select('id', 'jenis_tes as nama', 'kode')
            ->orderBy('jenis_tes')
            ->get()
            ->toArray();;

        $penyelenggaraList = SimpegDataTes::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('penyelenggara')
            ->filter()
            ->values();

        $namaTesList = SimpegDataTes::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('nama_tes')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'jenis_tes' => $jenisTesList,
                'penyelenggara' => $penyelenggaraList,
                'nama_tes' => $namaTesList,
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus data riwayat tes ini?',
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua data riwayat tes yang dipilih?'
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

    // Get list jenis tes untuk dropdown
    public function getJenisTes()
    {
        $jenisTesList = SimpegDaftarJenisTest::select('id', 'kode', 'jenis_tes as nama', 'nilai_minimal', 'nilai_maksimal')
            ->orderBy('jenis_tes')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jenisTesList
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

    // Helper: Format data riwayat tes response
    protected function formatDataRiwayatTes($dataRiwayatTes, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataRiwayatTes->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        $data = [
            'id' => $dataRiwayatTes->id,
            'jenis_tes_id' => $dataRiwayatTes->jenis_tes_id,
            'jenis_tes' => $dataRiwayatTes->jenisTes ? $dataRiwayatTes->jenisTes->jenis_tes : '-',
            'nama_tes' => $dataRiwayatTes->nama_tes,
            'penyelenggara' => $dataRiwayatTes->penyelenggara,
            'tgl_tes' => $dataRiwayatTes->tgl_tes,
            'skor' => $dataRiwayatTes->skor,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataRiwayatTes->keterangan,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataRiwayatTes->tgl_input,
                'tgl_diajukan' => $dataRiwayatTes->tgl_diajukan ?? null,
                'tgl_disetujui' => $dataRiwayatTes->tgl_disetujui ?? null,
                'tgl_ditolak' => $dataRiwayatTes->tgl_ditolak ?? null
            ],
            'dokumen' => $dataRiwayatTes->file_pendukung ? [
                'nama_file' => $dataRiwayatTes->file_pendukung,
                'url' => url('storage/pegawai/tes/dokumen/'.$dataRiwayatTes->file_pendukung)
            ] : null,
            'created_at' => $dataRiwayatTes->created_at,
            'updated_at' => $dataRiwayatTes->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/dosen/datariwayattes/{$dataRiwayatTes->id}"),
                'update_url' => url("/api/dosen/datariwayattes/{$dataRiwayatTes->id}"),
                'delete_url' => url("/api/dosen/datariwayattes/{$dataRiwayatTes->id}"),
                'submit_url' => url("/api/dosen/datariwayattes/{$dataRiwayatTes->id}/submit"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data riwayat tes "' . $dataRiwayatTes->nama_tes . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data riwayat tes "' . $dataRiwayatTes->nama_tes . '" untuk persetujuan?'
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