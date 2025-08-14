<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPenghargaan;
use App\Models\SimpegJenisPenghargaan;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegPenghargaanDosenController extends Controller
{
    // Get all penghargaan for logged in pegawai
    public function index(Request $request) 
    {
        // Ensure user is logged in
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        // Eager load all necessary relationships
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

        // Query ONLY for logged in pegawai
        $query = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->with(['jenisPenghargaan']);

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_penghargaan', 'like', '%'.$search.'%')
                  ->orWhere('no_sk', 'like', '%'.$search.'%')
                  ->orWhere('instansi_pemberi', 'like', '%'.$search.'%')
                  ->orWhere('tanggal_penghargaan', 'like', '%'.$search.'%')
                  ->orWhereHas('jenisPenghargaan', function($jq) use ($search) {
                      $jq->where('nama', 'like', '%'.$search.'%')
                        ->orWhere('kode', 'like', '%'.$search.'%');
                  });
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('jenis_penghargaan_id')) {
            $query->where('jenis_penghargaan_id', $request->jenis_penghargaan_id);
        }
        if ($request->filled('nama_penghargaan')) {
            $query->where('nama_penghargaan', 'like', '%'.$request->nama_penghargaan.'%');
        }
        if ($request->filled('instansi_pemberi')) {
            $query->where('instansi_pemberi', 'like', '%'.$request->instansi_pemberi.'%');
        }
        if ($request->filled('tanggal_penghargaan')) {
            $query->whereDate('tanggal_penghargaan', $request->tanggal_penghargaan);
        }
        if ($request->filled('no_sk')) {
            $query->where('no_sk', 'like', '%'.$request->no_sk.'%');
        }

        // Execute query with pagination
        $dataPenghargaan = $query->orderBy('tanggal_penghargaan', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataPenghargaan->getCollection()->transform(function ($item) {
            return $this->formatDataPenghargaan($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataPenghargaan,
            'empty_data' => $dataPenghargaan->isEmpty(),
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                ],
                'jenis_penghargaan' => SimpegJenisPenghargaan::select('id', 'nama', 'kode')
                    ->orderBy('nama')
                    ->get()
                    ->toArray()
            ],
            'table_columns' => [
                ['field' => 'jenis_penghargaan', 'label' => 'Jenis Penghargaan', 'sortable' => true, 'sortable_field' => 'jenis_penghargaan_id'],
                ['field' => 'nama_penghargaan', 'label' => 'Nama Penghargaan', 'sortable' => true],
                ['field' => 'instansi_pemberi', 'label' => 'instansi_pemberi', 'sortable' => true],
                ['field' => 'tanggal_penghargaan', 'label' => 'Tanggal Penghargaan', 'sortable' => true],
                ['field' => 'no_sk', 'label' => 'Nomor SK', 'sortable' => true],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_url' => url("/api/dosen/penghargaandosen"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/penghargaandosen/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/penghargaandosen/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/penghargaandosen/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataPenghargaan->currentPage(),
                'per_page' => $dataPenghargaan->perPage(),
                'total' => $dataPenghargaan->total(),
                'last_page' => $dataPenghargaan->lastPage(),
                'from' => $dataPenghargaan->firstItem(),
                'to' => $dataPenghargaan->lastItem()
            ]
        ]);
    }

    // Fix existing data with null status_pengajuan
    public function fixExistingData()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        // Update data with null status_pengajuan to draft
        $updatedCount = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data penghargaan",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail penghargaan
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPenghargaan = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->with(['jenisPenghargaan'])
            ->find($id);

        if (!$dataPenghargaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghargaan tidak ditemukan'
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
            'data' => $this->formatDataPenghargaan($dataPenghargaan)
        ]);
    }

    // Store new penghargaan with draft/submit mode
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
            'jenis_penghargaan_id' => 'required|uuid|exists:simpeg_jenis_penghargaan,id',
            'nama_penghargaan' => 'required|string|max:100',
            'instansi_pemberi' => 'required|string|max:255',
            'tanggal_penghargaan' => 'required|date|before_or_equal:today',
            'no_sk' => 'nullable|string|max:50',
            'tanggal_sk' => 'nullable|date|before_or_equal:today',
            'file_penghargaan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['file_penghargaan', 'submit_type']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Set status based on submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data penghargaan berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data penghargaan berhasil disimpan sebagai draft';
        }

        // Handle file upload
        if ($request->hasFile('file_penghargaan')) {
            $file = $request->file('file_penghargaan');
            $fileName = 'penghargaan_'.time().'_'.$pegawai->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/penghargaan/dokumen', $fileName);
            $data['file_penghargaan'] = $fileName;
        }

        $dataPenghargaan = SimpegDataPenghargaan::create($data);

        ActivityLogger::log('create', $dataPenghargaan, $dataPenghargaan->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPenghargaan($dataPenghargaan->load('jenisPenghargaan')),
            'message' => $message
        ], 201);
    }

    // Update penghargaan with status validation
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPenghargaan = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataPenghargaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghargaan tidak ditemukan'
            ], 404);
        }

        // Validate if editable based on status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataPenghargaan->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'jenis_penghargaan_id' => 'sometimes|uuid|exists:simpeg_jenis_penghargaan,id',
            'nama_penghargaan' => 'sometimes|string|max:100',
            'instansi_pemberi' => 'sometimes|string|max:255',
            'tanggal_penghargaan' => 'sometimes|date|before_or_equal:today',
            'no_sk' => 'nullable|string|max:50',
            'tanggal_sk' => 'nullable|date|before_or_equal:today',
            'file_penghargaan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $dataPenghargaan->getOriginal();
        $data = $request->except(['file_penghargaan', 'submit_type']);

        // Reset status if from ditolak or ditangguhkan
        if (in_array($dataPenghargaan->status_pengajuan, ['ditolak'])) {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null;
            $data['keterangan'] = $request->keterangan ?? null;
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data penghargaan berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Data penghargaan berhasil diperbarui';
        }

        // Handle file upload
        if ($request->hasFile('file_penghargaan')) {
            if ($dataPenghargaan->file_penghargaan) {
                Storage::delete('public/pegawai/penghargaan/dokumen/'.$dataPenghargaan->file_penghargaan);
            }

            $file = $request->file('file_penghargaan');
            $fileName = 'penghargaan_'.time().'_'.$pegawai->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/penghargaan/dokumen', $fileName);
            $data['file_penghargaan'] = $fileName;
        }

        $dataPenghargaan->update($data);

        ActivityLogger::log('update', $dataPenghargaan, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPenghargaan($dataPenghargaan->load('jenisPenghargaan')),
            'message' => $message
        ]);
    }

    // Delete penghargaan
    public function destroy($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPenghargaan = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataPenghargaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghargaan tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($dataPenghargaan->file_penghargaan) {
            Storage::delete('public/pegawai/penghargaan/dokumen/'.$dataPenghargaan->file_penghargaan);
        }

        $oldData = $dataPenghargaan->toArray();
        $dataPenghargaan->delete();

        ActivityLogger::log('delete', $dataPenghargaan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data penghargaan berhasil dihapus'
        ]);
    }

    // Submit draft to diajukan
    public function submitDraft($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPenghargaan = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataPenghargaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghargaan draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataPenghargaan->getOriginal();
        
        $dataPenghargaan->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataPenghargaan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data penghargaan berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete penghargaan
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_penghargaan,id'
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

        $dataPenghargaanList = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataPenghargaanList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghargaan tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataPenghargaanList as $dataPenghargaan) {
            try {
                // Delete file if exists
                if ($dataPenghargaan->file_penghargaan) {
                    Storage::delete('public/pegawai/penghargaan/dokumen/'.$dataPenghargaan->file_penghargaan);
                }

                $oldData = $dataPenghargaan->toArray();
                $dataPenghargaan->delete();
                
                ActivityLogger::log('delete', $dataPenghargaan, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataPenghargaan->id,
                    'nama_penghargaan' => $dataPenghargaan->nama_penghargaan,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data penghargaan",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data penghargaan",
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

        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $updatedCount = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data penghargaan untuk persetujuan",
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

        $updatedCount = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui',
            'updated_count' => $updatedCount
        ]);
    }

    // Get status statistics for dashboard
    public function getStatusStatistics()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $statistics = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->selectRaw('status_pengajuan, COUNT(*) as total')
            ->groupBy('status_pengajuan')
            ->get()
            ->pluck('total', 'status_pengajuan')
            ->toArray();

        $defaultStats = [
            'draft' => 0,
            'diajukan' => 0,
            'disetujui' => 0,
            'ditolak' => 0,
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
                ],
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

        $jenisPenghargaanList = SimpegJenisPenghargaan::select('id', 'nama', 'kode')
            ->orderBy('nama')
            ->get()
            ->toArray();

        $instansi_pemberiList = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('instansi_pemberi')
            ->filter()
            ->values();

        $namaPenghargaanList = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('nama_penghargaan')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'jenis_penghargaan' => $jenisPenghargaanList,
                'instansi_pemberi' => $instansi_pemberiList,
                'nama_penghargaan' => $namaPenghargaanList,
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus data penghargaan ini?',
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua data penghargaan yang dipilih?'
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
                ['value' => 'ditolak', 'label' => 'Ditolak', 'color' => 'danger'],
            ]
        ]);
    }

    // Get list jenis penghargaan for dropdown
    public function getJenisPenghargaan()
    {
        $jenisPenghargaanList = SimpegJenisPenghargaan::select('id', 'kode', 'nama')
            ->orderBy('nama')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jenisPenghargaanList
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

    // Helper: Format data penghargaan response
    protected function formatDataPenghargaan($dataPenghargaan, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataPenghargaan->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak',]);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak',]);
        
        $data = [
            'id' => $dataPenghargaan->id,
            'jenis_penghargaan_id' => $dataPenghargaan->jenis_penghargaan_id,
            'jenis_penghargaan' => $dataPenghargaan->jenisPenghargaan ? $dataPenghargaan->jenisPenghargaan->nama : '-',
            'nama_penghargaan' => $dataPenghargaan->nama_penghargaan,
            'instansi_pemberi' => $dataPenghargaan->instansi_pemberi,
            'tanggal_penghargaan' => $dataPenghargaan->tanggal_penghargaan,
            'no_sk' => $dataPenghargaan->no_sk,
            'tanggal_sk' => $dataPenghargaan->tanggal_sk,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataPenghargaan->keterangan,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataPenghargaan->tgl_input,
                'tgl_diajukan' => $dataPenghargaan->tgl_diajukan ?? null,
                'tgl_disetujui' => $dataPenghargaan->tgl_disetujui ?? null,
                'tgl_ditolak' => $dataPenghargaan->tgl_ditolak ?? null,
            ],
            'dokumen' => $dataPenghargaan->file_penghargaan ? [
                'nama_file' => $dataPenghargaan->file_penghargaan,
                'url' => url('storage/pegawai/penghargaan/dokumen/'.$dataPenghargaan->file_penghargaan)
            ] : null,
            'created_at' => $dataPenghargaan->created_at,
            'updated_at' => $dataPenghargaan->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/dosen/penghargaandosen/{$dataPenghargaan->id}"),
                'update_url' => url("/api/dosen/penghargaandosen/{$dataPenghargaan->id}"),
                'delete_url' => url("/api/dosen/penghargaandosen/{$dataPenghargaan->id}"),
                'submit_url' => url("/api/dosen/penghargaandosen/{$dataPenghargaan->id}/submit"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data penghargaan "' . $dataPenghargaan->nama_penghargaan . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data penghargaan "' . $dataPenghargaan->nama_penghargaan . '" untuk persetujuan?'
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
            ],
        ];

        return $statusMap[$status] ?? [
            'label' => ucfirst($status),
            'color' => 'secondary',
            'icon' => 'circle',
            'description' => ''
        ];
    }
}