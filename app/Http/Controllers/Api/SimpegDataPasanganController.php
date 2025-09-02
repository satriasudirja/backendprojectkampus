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

class SimpegDataPasanganController extends Controller
{
    // Get all data pasangan for logged in pegawai
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
        
        $pegawai = Auth::user()->pegawai;
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

        // Query HANYA untuk pegawai yang sedang login (data pasangan)
        $query = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan'); // Filter only spouse data

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_pasangan', 'like', '%'.$search.'%')
                  ->orWhere('tempat_lahir', 'like', '%'.$search.'%')
                  ->orWhere('jenis_pekerjaan', 'like', '%'.$search.'%')
                  ->orWhere('tgl_lahir', 'like', '%'.$search.'%');
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('nama_pasangan')) {
            $query->where('nama_pasangan', 'like', '%'.$request->nama_pasangan.'%');
        }
        if ($request->filled('tgl_lahir')) {
            $query->whereDate('tgl_lahir', $request->tgl_lahir);
        }
        if ($request->filled('jenis_pekerjaan')) {
            $query->where('jenis_pekerjaan', 'like', '%'.$request->jenis_pekerjaan.'%');
        }
        if ($request->filled('status_kepegawaian')) {
            $query->where('status_kepegawaian', $request->status_kepegawaian);
        }

        // Execute query dengan pagination
        $dataPasangan = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataPasangan->getCollection()->transform(function ($item) {
            return $this->formatDataPasangan($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataPasangan,
            'empty_data' => $dataPasangan->isEmpty(),
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
                ['field' => 'nama_pasangan', 'label' => 'Nama Pasangan', 'sortable' => true, 'sortable_field' => 'nama_pasangan'],
                ['field' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'sortable' => true, 'sortable_field' => 'tempat_lahir'],
                ['field' => 'tgl_lahir', 'label' => 'Tanggal Lahir', 'sortable' => true, 'sortable_field' => 'tgl_lahir'],
                ['field' => 'jenis_pekerjaan', 'label' => 'Pekerjaan', 'sortable' => true, 'sortable_field' => 'jenis_pekerjaan'],
                ['field' => 'status_kepegawaian', 'label' => 'Status Kepegawaian', 'sortable' => true, 'sortable_field' => 'status_kepegawaian'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_pasangan_url' => url("/api/dosen/pasangan"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/pasangan/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/pasangan/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/pasangan/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataPasangan->currentPage(),
                'per_page' => $dataPasangan->perPage(),
                'total' => $dataPasangan->total(),
                'last_page' => $dataPasangan->lastPage(),
                'from' => $dataPasangan->firstItem(),
                'to' => $dataPasangan->lastItem()
            ]
        ]);
    }

    // Fix existing data dengan status_pengajuan null
    public function fixExistingData()
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        // Update data yang status_pengajuan-nya null menjadi draft
        $updatedCount = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data pasangan",
            'updated_count' => $updatedCount
        ]);
    }

    // Bulk fix all existing data
    public function bulkFixExistingData()
    {
        $updatedCount = SimpegDataKeluargaPegawai::whereNotNull('nama_pasangan')
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data pasangan dari semua pegawai",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data pasangan
    public function show($id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPasangan = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->find($id);

        if (!$dataPasangan) {
            return response()->json([
                'success' => false,
                'message' => 'Data pasangan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($pegawai->load([
                'unitKerja', 'statusAktif', 'jabatanFungsional',
                'jabatanStruktural.jenisJabatanStruktural',
                'dataPendidikanFormal.jenjangPendidikan'
            ])),
            'data' => $this->formatDataPasangan($dataPasangan)
        ]);
    }

    // Store new data pasangan dengan draft/submit mode
    public function store(Request $request)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_pasangan' => 'required|string|max:100',
            'pasangan_berkerja_dalam_satu_instansi' => 'required|boolean',
            'tempat_lahir' => 'nullable|string|max:50',
            'tgl_lahir' => 'nullable|date|before:today',
            'jenis_pekerjaan' => 'nullable|string|max:100',
            'status_kepegawaian' => 'nullable|string|max:50',
            'karpeg_pasangan' => 'nullable|string|max:100',
            'file_karpeg_pasangan' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
            'tempat_nikah' => 'nullable|string|max:50',
            'tgl_nikah' => 'nullable|date|before:today',
            'no_akta_nikah' => 'nullable|string|max:20',
            'kartu_nikah' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
            'submit_type' => 'sometimes|in:draft,submit', // Optional, default to draft
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['file_karpeg_pasangan', 'kartu_nikah', 'submit_type']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Set status berdasarkan submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data pasangan berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data pasangan berhasil disimpan sebagai draft';
        }

        // Handle file uploads
        if ($request->hasFile('file_karpeg_pasangan')) {
            $file = $request->file('file_karpeg_pasangan');
            $fileName = 'karpeg_pasangan_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/keluarga/karpeg', $fileName);
            $data['file_karpeg_pasangan'] = $fileName;
        }

        if ($request->hasFile('kartu_nikah')) {
            $file = $request->file('kartu_nikah');
            $fileName = 'kartu_nikah_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/keluarga/nikah', $fileName);
            $data['kartu_nikah'] = $fileName;
        }

        $dataPasangan = SimpegDataKeluargaPegawai::create($data);

        ActivityLogger::log('create', $dataPasangan, $dataPasangan->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPasangan($dataPasangan),
            'message' => $message
        ], 201);
    }

    // Update data pasangan dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPasangan = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->find($id);

        if (!$dataPasangan) {
            return response()->json([
                'success' => false,
                'message' => 'Data pasangan tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataPasangan->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'nama_pasangan' => 'sometimes|string|max:100',
            'pasangan_berkerja_dalam_satu_instansi' => 'sometimes|boolean',
            'tempat_lahir' => 'nullable|string|max:50',
            'tgl_lahir' => 'nullable|date|before:today',
            'jenis_pekerjaan' => 'nullable|string|max:100',
            'status_kepegawaian' => 'nullable|string|max:50',
            'karpeg_pasangan' => 'nullable|string|max:100',
            'file_karpeg_pasangan' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
            'tempat_nikah' => 'nullable|string|max:50',
            'tgl_nikah' => 'nullable|date|before:today',
            'no_akta_nikah' => 'nullable|string|max:20',
            'kartu_nikah' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $dataPasangan->getOriginal();
        $data = $request->except(['file_karpeg_pasangan', 'kartu_nikah', 'submit_type']);

        // Reset status jika dari ditolak
        if ($dataPasangan->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null;
            $data['keterangan'] = $request->keterangan ?? null;
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data pasangan berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Data pasangan berhasil diperbarui';
        }

        // Handle file uploads
        if ($request->hasFile('file_karpeg_pasangan')) {
            if ($dataPasangan->file_karpeg_pasangan) {
                Storage::delete('public/pegawai/keluarga/karpeg/'.$dataPasangan->file_karpeg_pasangan);
            }

            $file = $request->file('file_karpeg_pasangan');
            $fileName = 'karpeg_pasangan_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/keluarga/karpeg', $fileName);
            $data['file_karpeg_pasangan'] = $fileName;
        }

        if ($request->hasFile('kartu_nikah')) {
            if ($dataPasangan->kartu_nikah) {
                Storage::delete('public/pegawai/keluarga/nikah/'.$dataPasangan->kartu_nikah);
            }

            $file = $request->file('kartu_nikah');
            $fileName = 'kartu_nikah_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/keluarga/nikah', $fileName);
            $data['kartu_nikah'] = $fileName;
        }

        $dataPasangan->update($data);

        ActivityLogger::log('update', $dataPasangan, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPasangan($dataPasangan),
            'message' => $message
        ]);
    }

    // Delete data pasangan
    public function destroy($id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPasangan = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->find($id);

        if (!$dataPasangan) {
            return response()->json([
                'success' => false,
                'message' => 'Data pasangan tidak ditemukan'
            ], 404);
        }

        // Delete files if exist
        if ($dataPasangan->file_karpeg_pasangan) {
            Storage::delete('public/pegawai/keluarga/karpeg/'.$dataPasangan->file_karpeg_pasangan);
        }
        if ($dataPasangan->kartu_nikah) {
            Storage::delete('public/pegawai/keluarga/nikah/'.$dataPasangan->kartu_nikah);
        }

        $oldData = $dataPasangan->toArray();
        $dataPasangan->delete();

        ActivityLogger::log('delete', $dataPasangan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data pasangan berhasil dihapus'
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

        $dataPasangan = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataPasangan) {
            return response()->json([
                'success' => false,
                'message' => 'Data pasangan draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataPasangan->getOriginal();
        
        $dataPasangan->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataPasangan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data pasangan berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete data pasangan
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

        $dataPasanganList = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataPasanganList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data pasangan tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataPasanganList as $dataPasangan) {
            try {
                // Delete files if exist
                if ($dataPasangan->file_karpeg_pasangan) {
                    Storage::delete('public/pegawai/keluarga/karpeg/'.$dataPasangan->file_karpeg_pasangan);
                }
                if ($dataPasangan->kartu_nikah) {
                    Storage::delete('public/pegawai/keluarga/nikah/'.$dataPasangan->kartu_nikah);
                }

                $oldData = $dataPasangan->toArray();
                $dataPasangan->delete();
                
                ActivityLogger::log('delete', $dataPasangan, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataPasangan->id,
                    'nama' => $dataPasangan->nama_pasangan,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data pasangan",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data pasangan",
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
            ->whereNotNull('nama_pasangan')
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data pasangan untuk persetujuan",
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
            ->whereNotNull('nama_pasangan')
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
            ->whereNotNull('nama_pasangan')
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

        $namaPasangan = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->distinct()
            ->pluck('nama_pasangan')
            ->filter()
            ->values();

        $jenisPekerjaan = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->distinct()
            ->pluck('jenis_pekerjaan')
            ->filter()
            ->values();

        $statusKepegawaian = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->distinct()
            ->pluck('status_kepegawaian')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'nama_pasangan' => $namaPasangan,
                'jenis_pekerjaan' => $jenisPekerjaan,
                'status_kepegawaian' => $statusKepegawaian,
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus data pasangan ini?',
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua data pasangan yang dipilih?'
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

    // Search existing pegawai for pasangan
    public function searchPegawai(Request $request)
    {
        $search = $request->search;

        if (!$search) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $query = SimpegPegawai::with([
            'unitKerja',
            'statusAktif',
            'jabatanAkademik',
            'dataJabatanFungsional.jabatanFungsional',
            'dataJabatanStruktural.jabatanStruktural',
            'dataPendidikanFormal.jenjangPendidikan'
        ])
        ->where(function($q) use ($search) {
            $q->where('nip', 'like', '%'.$search.'%')
              ->orWhere('nama', 'like', '%'.$search.'%');
        });

        $pegawaiList = $query->limit(20)->get();

        return response()->json([
            'success' => true,
            'data' => $pegawaiList->map(function($pegawai) {
                return $this->formatPegawaiInfo($pegawai);
            })
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

    // Helper: Format data pasangan response
    protected function formatDataPasangan($dataPasangan, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataPasangan->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        $data = [
            'id' => $dataPasangan->id,
            'nama_pasangan' => $dataPasangan->nama_pasangan,
            'pasangan_berkerja_dalam_satu_instansi' => $dataPasangan->pasangan_berkerja_dalam_satu_instansi,
            'tempat_lahir' => $dataPasangan->tempat_lahir,
            'tgl_lahir' => $dataPasangan->tgl_lahir,
            'jenis_pekerjaan' => $dataPasangan->jenis_pekerjaan,
            'status_kepegawaian' => $dataPasangan->status_kepegawaian,
            'karpeg_pasangan' => $dataPasangan->karpeg_pasangan,
            'tempat_nikah' => $dataPasangan->tempat_nikah,
            'tgl_nikah' => $dataPasangan->tgl_nikah,
            'no_akta_nikah' => $dataPasangan->no_akta_nikah,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataPasangan->keterangan,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataPasangan->tgl_input,
                'tgl_diajukan' => $dataPasangan->tgl_diajukan,
                'tgl_disetujui' => $dataPasangan->tgl_disetujui,
                'tgl_ditolak' => $dataPasangan->tgl_ditolak
            ],
            'dokumen' => [
                'karpeg_pasangan' => $dataPasangan->file_karpeg_pasangan ? [
                    'nama_file' => $dataPasangan->file_karpeg_pasangan,
                    'url' => url('storage/pegawai/keluarga/karpeg/'.$dataPasangan->file_karpeg_pasangan)
                ] : null,
                'kartu_nikah' => $dataPasangan->kartu_nikah ? [
                    'nama_file' => $dataPasangan->kartu_nikah,
                    'url' => url('storage/pegawai/keluarga/nikah/'.$dataPasangan->kartu_nikah)
                ] : null
            ],
            'created_at' => $dataPasangan->created_at,
            'updated_at' => $dataPasangan->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            // Get URL prefix from request
            $request = request();
            $prefix = $request->segment(2) ?? 'dosen'; // fallback to 'dosen'
            
            $data['aksi'] = [
                'detail_url' => url("/api/{$prefix}/pasangan/{$dataPasangan->id}"),
                'update_url' => url("/api/{$prefix}/pasangan/{$dataPasangan->id}"),
                'delete_url' => url("/api/{$prefix}/pasangan/{$dataPasangan->id}"),
                'submit_url' => url("/api/{$prefix}/pasangan/{$dataPasangan->id}/submit"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data pasangan "' . $dataPasangan->nama_pasangan . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data pasangan "' . $dataPasangan->nama_pasangan . '" untuk persetujuan?'
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