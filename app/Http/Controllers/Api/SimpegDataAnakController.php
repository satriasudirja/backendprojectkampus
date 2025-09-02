<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataKeluargaPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataAnakController extends Controller
{
    // Get all data anak for logged in pegawai
    public function index(Request $request) 
    {
        // Pastikan user sudah login
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        $pegawai = Auth::user()->pegawai;
        // Eager load semua relasi yang diperlukan untuk menghindari N+1 query problem
        $pegawai->load([
            'unitKerja',
            'statusAktif', 
            'jabatanFungsional',
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
        $query = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke');

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', '%'.$search.'%')
                  ->orWhere('umur', 'like', '%'.$search.'%')
                  ->orWhere('anak_ke', 'like', '%'.$search.'%')
                  ->orWhere('tgl_lahir', 'like', '%'.$search.'%');
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('nama_anak')) {
            $query->where('nama', 'like', '%'.$request->nama_anak.'%');
        }
        if ($request->filled('tgl_lahir')) {
            $query->whereDate('tgl_lahir', $request->tgl_lahir);
        }
        if ($request->filled('umur')) {
            $query->where('umur', $request->umur);
        }
        if ($request->filled('anak_ke')) {
            $query->where('anak_ke', $request->anak_ke);
        }

        // Execute query dengan pagination
        $dataAnak = $query->orderBy('anak_ke', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataAnak->getCollection()->transform(function ($item) {
            return $this->formatDataAnak($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataAnak,
            'empty_data' => $dataAnak->isEmpty(),
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
                ['field' => 'nama', 'label' => 'Nama Anak', 'sortable' => true, 'sortable_field' => 'nama'],
                ['field' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'sortable' => true, 'sortable_field' => 'jenis_kelamin'],
                ['field' => 'anak_ke', 'label' => 'Anak Ke-', 'sortable' => true, 'sortable_field' => 'anak_ke'],
                ['field' => 'umur', 'label' => 'Umur', 'sortable' => true, 'sortable_field' => 'umur'],
                ['field' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'sortable' => true, 'sortable_field' => 'tempat_lahir'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_anak_url' => url("/api/pegawai/data-anak"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/pegawai/data-anak/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/pegawai/data-anak/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/pegawai/data-anak/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataAnak->currentPage(),
                'per_page' => $dataAnak->perPage(),
                'total' => $dataAnak->total(),
                'last_page' => $dataAnak->lastPage(),
                'from' => $dataAnak->firstItem(),
                'to' => $dataAnak->lastItem()
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
        $updatedCount = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data anak",
            'updated_count' => $updatedCount
        ]);
    }

    // Bulk fix all existing data (admin only atau bisa untuk semua user)
    public function bulkFixExistingData()
    {
        // Uncomment jika hanya admin yang boleh akses
        // if (!$this->isAdmin()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Unauthorized - Hanya admin yang dapat mengakses'
        //     ], 403);
        // }

        $updatedCount = SimpegDataKeluargaPegawai::whereNotNull('anak_ke')
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data anak dari semua pegawai",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data anak
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataAnak = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->find($id);

        if (!$dataAnak) {
            return response()->json([
                'success' => false,
                'message' => 'Data anak tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($pegawai->load([
                'unitKerja', 'statusAktif',
                'jabatanFungsional',
                'jabatanStruktural.jenisJabatanStruktural',
                'dataPendidikanFormal.jenjangPendidikan'
            ])),
            'data' => $this->formatDataAnak($dataAnak)
        ]);
    }

    // Store new data anak dengan draft/submit mode
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
            'nama' => 'required|string|max:100',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'tempat_lahir' => 'required|string|max:50',
            'tgl_lahir' => 'required|date|before:today',
            'umur' => 'nullable|integer|min:0|max:30',
            'anak_ke' => 'required|integer|min:1|max:20',
            'pekerjaan_anak' => 'nullable|string|max:50',
            'file_akte' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
            'submit_type' => 'sometimes|in:draft,submit', // Optional, default to draft
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if anak_ke already exists
        $existingAnak = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->where('anak_ke', $request->anak_ke)
            ->whereNotNull('anak_ke')
            ->first();

        if ($existingAnak) {
            return response()->json([
                'success' => false,
                'message' => 'Anak ke-'.$request->anak_ke.' sudah ada untuk pegawai ini'
            ], 422);
        }

        $data = $request->except(['file_akte', 'submit_type']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Set status berdasarkan submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data anak berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data anak berhasil disimpan sebagai draft';
        }

        // Calculate age if not provided
        if (!$request->umur && $request->tgl_lahir) {
            $birthDate = new \DateTime($request->tgl_lahir);
            $today = new \DateTime();
            $data['umur'] = $today->diff($birthDate)->y;
        }

        // Handle file upload
        if ($request->hasFile('file_akte')) {
            $file = $request->file('file_akte');
            $fileName = 'akte_anak_'.time().'_'.$pegawai->id.'_'.$request->anak_ke.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/keluarga/akte', $fileName);
            $data['file_akte'] = $fileName;
        }

        $dataAnak = SimpegDataKeluargaPegawai::create($data);

        ActivityLogger::log('create', $dataAnak, $dataAnak->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataAnak($dataAnak),
            'message' => $message
        ], 201);
    }

    // Update data anak dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataAnak = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->find($id);

        if (!$dataAnak) {
            return response()->json([
                'success' => false,
                'message' => 'Data anak tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataAnak->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|string|max:100',
            'jenis_kelamin' => 'sometimes|in:Laki-laki,Perempuan',
            'tempat_lahir' => 'sometimes|string|max:50',
            'tgl_lahir' => 'sometimes|date|before:today',
            'umur' => 'nullable|integer|min:0|max:30',
            'anak_ke' => 'sometimes|integer|min:1|max:20',
            'pekerjaan_anak' => 'nullable|string|max:50',
            'file_akte' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check anak_ke uniqueness
        if ($request->has('anak_ke')) {
            $existingAnak = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
                ->where('anak_ke', $request->anak_ke)
                ->where('id', '!=', $id)
                ->whereNotNull('anak_ke')
                ->first();

            if ($existingAnak) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anak ke-'.$request->anak_ke.' sudah ada untuk pegawai ini'
                ], 422);
            }
        }

        $oldData = $dataAnak->getOriginal();
        $data = $request->except(['file_akte', 'submit_type']);

        // Reset status jika dari ditolak
        if ($dataAnak->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null;
            $data['keterangan'] = $request->keterangan ?? null;
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data anak berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Data anak berhasil diperbarui';
        }

        // Calculate age if tgl_lahir changed
        if ($request->has('tgl_lahir') && !$request->has('umur')) {
            $birthDate = new \DateTime($request->tgl_lahir);
            $today = new \DateTime();
            $data['umur'] = $today->diff($birthDate)->y;
        }

        // Handle file upload
        if ($request->hasFile('file_akte')) {
            if ($dataAnak->file_akte) {
                Storage::delete('public/pegawai/keluarga/akte/'.$dataAnak->file_akte);
            }

            $file = $request->file('file_akte');
            $fileName = 'akte_anak_'.time().'_'.$pegawai->id.'_'.($request->anak_ke ?? $dataAnak->anak_ke).'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/keluarga/akte', $fileName);
            $data['file_akte'] = $fileName;
        }

        $dataAnak->update($data);

        ActivityLogger::log('update', $dataAnak, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataAnak($dataAnak),
            'message' => $message
        ]);
    }

    // Delete data anak
    public function destroy($id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataAnak = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->find($id);

        if (!$dataAnak) {
            return response()->json([
                'success' => false,
                'message' => 'Data anak tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($dataAnak->file_akte) {
            Storage::delete('public/pegawai/keluarga/akte/'.$dataAnak->file_akte);
        }

        $oldData = $dataAnak->toArray();
        $dataAnak->delete();

        ActivityLogger::log('delete', $dataAnak, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data anak berhasil dihapus'
        ]);
    }

    // Submit draft ke diajukan
    public function submitDraft($id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataAnak = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataAnak) {
            return response()->json([
                'success' => false,
                'message' => 'Data anak draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataAnak->getOriginal();
        
        $dataAnak->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataAnak, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data anak berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete data anak
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_keluarga_pegawai,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataAnakList = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataAnakList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data anak tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataAnakList as $dataAnak) {
            try {
                // Delete file if exists
                if ($dataAnak->file_akte) {
                    Storage::delete('public/pegawai/keluarga/akte/'.$dataAnak->file_akte);
                }

                $oldData = $dataAnak->toArray();
                $dataAnak->delete();
                
                ActivityLogger::log('delete', $dataAnak, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataAnak->id,
                    'nama' => $dataAnak->nama,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data anak",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data anak",
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
            'ids.*' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $updatedCount = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data anak untuk persetujuan",
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

        $pegawai = Auth::user()->pegawai;

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

        $updatedCount = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
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
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $statistics = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
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
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $namaAnak = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->distinct()
            ->pluck('nama')
            ->filter()
            ->values();

        $anakKe = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->distinct()
            ->pluck('anak_ke')
            ->filter()
            ->sort()
            ->values();

        $umur = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->distinct()
            ->pluck('umur')
            ->filter()
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'nama_anak' => $namaAnak,
                'anak_ke' => $anakKe,
                'umur' => $umur,
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus data anak ini?',
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua data anak yang dipilih?'
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

    // Helper: Format data anak response
    protected function formatDataAnak($dataAnak, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataAnak->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        $data = [
            'id' => $dataAnak->id,
            'nama' => $dataAnak->nama,
            'jenis_kelamin' => $dataAnak->jenis_kelamin,
            'tempat_lahir' => $dataAnak->tempat_lahir,
            'tgl_lahir' => $dataAnak->tgl_lahir,
            'umur' => $dataAnak->umur,
            'anak_ke' => $dataAnak->anak_ke,
            'pekerjaan_anak' => $dataAnak->pekerjaan_anak,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataAnak->keterangan,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataAnak->tgl_input,
                'tgl_diajukan' => $dataAnak->tgl_diajukan,
                'tgl_disetujui' => $dataAnak->tgl_disetujui,
                'tgl_ditolak' => $dataAnak->tgl_ditolak
            ],
            'dokumen' => $dataAnak->file_akte ? [
                'nama_file' => $dataAnak->file_akte,
                'url' => url('storage/pegawai/keluarga/akte/'.$dataAnak->file_akte)
            ] : null,
            'created_at' => $dataAnak->created_at,
            'updated_at' => $dataAnak->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            // Get URL prefix from request
            $request = request();
            $prefix = $request->segment(2) ?? 'pegawai'; // fallback to 'pegawai'
            
            $data['aksi'] = [
                'detail_url' => url("/api/{$prefix}/data-anak/{$dataAnak->id}"),
                'update_url' => url("/api/{$prefix}/data-anak/{$dataAnak->id}"),
                'delete_url' => url("/api/{$prefix}/data-anak/{$dataAnak->id}"),
                'submit_url' => url("/api/{$prefix}/data-anak/{$dataAnak->id}/submit"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data anak "' . $dataAnak->nama . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data anak "' . $dataAnak->nama . '" untuk persetujuan?'
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