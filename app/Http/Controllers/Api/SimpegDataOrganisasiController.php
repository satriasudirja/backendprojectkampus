<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataOrganisasi;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataOrganisasiController extends Controller
{
    // Get all data organisasi for logged in pegawai
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
        $query = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id);

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_organisasi', 'like', '%'.$search.'%')
                  ->orWhere('jabatan_dalam_organisasi', 'like', '%'.$search.'%')
                  ->orWhere('tempat_organisasi', 'like', '%'.$search.'%')
                  ->orWhere('jenis_organisasi', 'like', '%'.$search.'%');
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('nama_organisasi')) {
            $query->where('nama_organisasi', 'like', '%'.$request->nama_organisasi.'%');
        }
        if ($request->filled('jenis_organisasi')) {
            $query->where('jenis_organisasi', $request->jenis_organisasi);
        }
        if ($request->filled('periode_mulai')) {
            $query->whereDate('periode_mulai', '>=', $request->periode_mulai);
        }
        if ($request->filled('periode_selesai')) {
            $query->whereDate('periode_selesai', '<=', $request->periode_selesai);
        }
        if ($request->filled('jabatan_dalam_organisasi')) {
            $query->where('jabatan_dalam_organisasi', 'like', '%'.$request->jabatan_dalam_organisasi.'%');
        }

        // Execute query dengan pagination
        $dataOrganisasi = $query->orderBy('periode_mulai', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataOrganisasi->getCollection()->transform(function ($item) {
            return $this->formatDataOrganisasi($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataOrganisasi,
            'empty_data' => $dataOrganisasi->isEmpty(),
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ],
                'jenis_organisasi' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'lokal', 'nama' => 'Lokal'],
                    ['id' => 'nasional', 'nama' => 'Nasional'],
                    ['id' => 'internasional', 'nama' => 'Internasional'],
                    ['id' => 'lainnya', 'nama' => 'Lainnya']
                ]
            ],
            'table_columns' => [
                ['field' => 'nama_organisasi', 'label' => 'Nama Organisasi', 'sortable' => true, 'sortable_field' => 'nama_organisasi'],
                ['field' => 'jabatan_dalam_organisasi', 'label' => 'Jabatan', 'sortable' => true, 'sortable_field' => 'jabatan_dalam_organisasi'],
                ['field' => 'jenis_organisasi', 'label' => 'Jenis Organisasi', 'sortable' => true, 'sortable_field' => 'jenis_organisasi'],
                ['field' => 'periode', 'label' => 'Periode', 'sortable' => true, 'sortable_field' => 'periode_mulai'],
                ['field' => 'tempat_organisasi', 'label' => 'Tempat', 'sortable' => true, 'sortable_field' => 'tempat_organisasi'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_organisasi_url' => url("/api/dosen/dataorganisasi"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/dataorganisasi/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/dataorganisasi/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/dataorganisasi/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataOrganisasi->currentPage(),
                'per_page' => $dataOrganisasi->perPage(),
                'total' => $dataOrganisasi->total(),
                'last_page' => $dataOrganisasi->lastPage(),
                'from' => $dataOrganisasi->firstItem(),
                'to' => $dataOrganisasi->lastItem()
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
        $updatedCount = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data organisasi",
            'updated_count' => $updatedCount
        ]);
    }

    // Bulk fix all existing data (admin only atau bisa untuk semua user)
    public function bulkFixExistingData()
    {
        $updatedCount = SimpegDataOrganisasi::whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data organisasi dari semua pegawai",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data organisasi
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataOrganisasi = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataOrganisasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data organisasi tidak ditemukan'
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
            'data' => $this->formatDataOrganisasi($dataOrganisasi)
        ]);
    }

    // Store new data organisasi dengan draft/submit mode
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
            'nama_organisasi' => 'required|string|max:100',
            'jabatan_dalam_organisasi' => 'nullable|string|max:100',
            'jenis_organisasi' => 'nullable|in:lokal,nasional,internasional,lainnya',
            'tempat_organisasi' => 'nullable|string|max:200',
            'periode_mulai' => 'required|date',
            'periode_selesai' => 'nullable|date|after_or_equal:periode_mulai',
            'website' => 'nullable|url|max:200',
            'keterangan' => 'nullable|string|max:1000',
            'file_dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
            'submit_type' => 'sometimes|in:draft,submit', // Optional, default to draft
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['file_dokumen', 'submit_type']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Set jenis_organisasi default jika tidak ada
        if (empty($data['jenis_organisasi'])) {
            $data['jenis_organisasi'] = 'lainnya';
        }

        // Set status berdasarkan submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data organisasi berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data organisasi berhasil disimpan sebagai draft';
        }

        // Handle file upload
        if ($request->hasFile('file_dokumen')) {
            $file = $request->file('file_dokumen');
            $fileName = 'dok_organisasi_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/organisasi', $fileName);
            $data['file_dokumen'] = $fileName;
        }

        $dataOrganisasi = SimpegDataOrganisasi::create($data);

        ActivityLogger::log('create', $dataOrganisasi, $dataOrganisasi->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataOrganisasi($dataOrganisasi),
            'message' => $message
        ], 201);
    }

    // Update data organisasi dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataOrganisasi = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataOrganisasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data organisasi tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataOrganisasi->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'sometimes|string|max:100',
            'jabatan_dalam_organisasi' => 'nullable|string|max:100',
            'jenis_organisasi' => 'nullable|in:lokal,nasional,internasional,lainnya',
            'tempat_organisasi' => 'nullable|string|max:200',
            'periode_mulai' => 'sometimes|date',
            'periode_selesai' => 'nullable|date|after_or_equal:periode_mulai',
            'website' => 'nullable|url|max:200',
            'keterangan' => 'nullable|string|max:1000',
            'file_dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $dataOrganisasi->getOriginal();
        $data = $request->except(['file_dokumen', 'submit_type']);

        // Reset status jika dari ditolak
        if ($dataOrganisasi->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null;
            $data['keterangan_penolakan'] = null;
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data organisasi berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Data organisasi berhasil diperbarui';
        }

        // Handle file upload
        if ($request->hasFile('file_dokumen')) {
            if ($dataOrganisasi->file_dokumen) {
                Storage::delete('public/pegawai/organisasi/'.$dataOrganisasi->file_dokumen);
            }

            $file = $request->file('file_dokumen');
            $fileName = 'dok_organisasi_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/organisasi', $fileName);
            $data['file_dokumen'] = $fileName;
        }

        $dataOrganisasi->update($data);

        ActivityLogger::log('update', $dataOrganisasi, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataOrganisasi($dataOrganisasi),
            'message' => $message
        ]);
    }

    // Delete data organisasi
    public function destroy($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataOrganisasi = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataOrganisasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data organisasi tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($dataOrganisasi->file_dokumen) {
            Storage::delete('public/pegawai/organisasi/'.$dataOrganisasi->file_dokumen);
        }

        $oldData = $dataOrganisasi->toArray();
        $dataOrganisasi->delete();

        ActivityLogger::log('delete', $dataOrganisasi, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data organisasi berhasil dihapus'
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

        $dataOrganisasi = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataOrganisasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data organisasi draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataOrganisasi->getOriginal();
        
        $dataOrganisasi->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataOrganisasi, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data organisasi berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete data organisasi
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_organisasi,id'
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

        $dataOrganisasiList = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataOrganisasiList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data organisasi tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataOrganisasiList as $dataOrganisasi) {
            try {
                // Delete file if exists
                if ($dataOrganisasi->file_dokumen) {
                    Storage::delete('public/pegawai/organisasi/'.$dataOrganisasi->file_dokumen);
                }

                $oldData = $dataOrganisasi->toArray();
                $dataOrganisasi->delete();
                
                ActivityLogger::log('delete', $dataOrganisasi, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataOrganisasi->id,
                    'nama_organisasi' => $dataOrganisasi->nama_organisasi,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data organisasi",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data organisasi",
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

        $updatedCount = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data organisasi untuk persetujuan",
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

        $updatedCount = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
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

        $statistics = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
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

        $namaOrganisasi = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('nama_organisasi')
            ->filter()
            ->values();

        $jenisOrganisasi = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('jenis_organisasi')
            ->filter()
            ->values();

        $jabatanDalamOrganisasi = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('jabatan_dalam_organisasi')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'nama_organisasi' => $namaOrganisasi,
                'jenis_organisasi' => $jenisOrganisasi,
                'jabatan_dalam_organisasi' => $jabatanDalamOrganisasi,
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus data organisasi ini?',
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua data organisasi yang dipilih?'
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

    // Helper: Format data organisasi response
    protected function formatDataOrganisasi($dataOrganisasi, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataOrganisasi->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        // Format periode display
        $periode = '';
        if ($dataOrganisasi->periode_mulai) {
            $periode = date('d/m/Y', strtotime($dataOrganisasi->periode_mulai));
            if ($dataOrganisasi->periode_selesai) {
                $periode .= ' - ' . date('d/m/Y', strtotime($dataOrganisasi->periode_selesai));
            } else {
                $periode .= ' - Sekarang';
            }
        }
        
        $data = [
            'id' => $dataOrganisasi->id,
            'nama_organisasi' => $dataOrganisasi->nama_organisasi,
            'jabatan_dalam_organisasi' => $dataOrganisasi->jabatan_dalam_organisasi,
            'jenis_organisasi' => $dataOrganisasi->jenis_organisasi,
            'tempat_organisasi' => $dataOrganisasi->tempat_organisasi,
            'periode_mulai' => $dataOrganisasi->periode_mulai,
            'periode_selesai' => $dataOrganisasi->periode_selesai,
            'periode' => $periode,
            'website' => $dataOrganisasi->website,
            'keterangan' => $dataOrganisasi->keterangan,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataOrganisasi->tgl_input,
                'tgl_diajukan' => $dataOrganisasi->tgl_diajukan,
                'tgl_disetujui' => $dataOrganisasi->tgl_disetujui,
                'tgl_ditolak' => $dataOrganisasi->tgl_ditolak
            ],
            'dokumen' => $dataOrganisasi->file_dokumen ? [
                'nama_file' => $dataOrganisasi->file_dokumen,
                'url' => url('storage/pegawai/organisasi/'.$dataOrganisasi->file_dokumen)
            ] : null,
            'created_at' => $dataOrganisasi->created_at,
            'updated_at' => $dataOrganisasi->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            // Get URL prefix from request
            $request = request();
            $prefix = $request->segment(2) ?? 'dosen'; // fallback to 'dosen'
            
            $data['aksi'] = [
                'detail_url' => url("/api/{$prefix}/dataorganisasi/{$dataOrganisasi->id}"),
                'update_url' => url("/api/{$prefix}/dataorganisasi/{$dataOrganisasi->id}"),
                'delete_url' => url("/api/{$prefix}/dataorganisasi/{$dataOrganisasi->id}"),
                'submit_url' => url("/api/{$prefix}/dataorganisasi/{$dataOrganisasi->id}/submit"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data organisasi "' . $dataOrganisasi->nama_organisasi . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data organisasi "' . $dataOrganisasi->nama_organisasi . '" untuk persetujuan?'
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