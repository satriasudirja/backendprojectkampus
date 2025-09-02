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

class SimpegDataOrangTuaController extends Controller
{
    // Get all data orang tua for logged in pegawai
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

        // Query HANYA untuk pegawai yang sedang login (data orang tua)
        $query = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('status_orangtua'); // Filter only parent data

        // Filter by search (nama, status_orangtua, tgl_lahir, tempat_lahir, pekerjaan)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', '%'.$search.'%')
                  ->orWhere('status_orangtua', 'like', '%'.$search.'%')
                  ->orWhere('tgl_lahir', 'like', '%'.$search.'%')
                  ->orWhere('tempat_lahir', 'like', '%'.$search.'%')
                  ->orWhere('pekerjaan', 'like', '%'.$search.'%');
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('status_orangtua')) {
            $query->where('status_orangtua', $request->status_orangtua);
        }
        if ($request->filled('tgl_lahir')) {
            $query->whereDate('tgl_lahir', $request->tgl_lahir);
        }
        if ($request->filled('pekerjaan')) {
            $query->where('pekerjaan', 'like', '%'.$request->pekerjaan.'%');
        }

        // Execute query dengan pagination
        $dataOrangTua = $query->orderBy('status_orangtua', 'asc')
                             ->orderBy('created_at', 'desc')
                             ->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataOrangTua->getCollection()->transform(function ($item) {
            return $this->formatDataOrangTua($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataOrangTua,
            'empty_data' => $dataOrangTua->isEmpty(),
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ],
                'status_orangtua' => [
                    ['id' => 'Ayah', 'nama' => 'Ayah'],
                    ['id' => 'Ibu', 'nama' => 'Ibu'],
                    ['id' => 'Ayah Kandung', 'nama' => 'Ayah Kandung'],
                    ['id' => 'Ibu Kandung', 'nama' => 'Ibu Kandung'],
                    ['id' => 'Ayah Tiri', 'nama' => 'Ayah Tiri'],
                    ['id' => 'Ibu Tiri', 'nama' => 'Ibu Tiri']
                ]
            ],
            'table_columns' => [
                ['field' => 'nama', 'label' => 'Nama', 'sortable' => true, 'sortable_field' => 'nama'],
                ['field' => 'status_orangtua', 'label' => 'Status Orang Tua', 'sortable' => true, 'sortable_field' => 'status_orangtua'],
                ['field' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'sortable' => true, 'sortable_field' => 'tempat_lahir'],
                ['field' => 'tgl_lahir', 'label' => 'Tanggal Lahir', 'sortable' => true, 'sortable_field' => 'tgl_lahir'],
                ['field' => 'umur', 'label' => 'Umur', 'sortable' => true, 'sortable_field' => 'umur'],
                ['field' => 'pekerjaan', 'label' => 'Pekerjaan', 'sortable' => true, 'sortable_field' => 'pekerjaan'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_orangtua_url' => url("/api/dosen/orangtua"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/orangtua/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/orangtua/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/orangtua/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataOrangTua->currentPage(),
                'per_page' => $dataOrangTua->perPage(),
                'total' => $dataOrangTua->total(),
                'last_page' => $dataOrangTua->lastPage(),
                'from' => $dataOrangTua->firstItem(),
                'to' => $dataOrangTua->lastItem()
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
            ->whereNotNull('status_orangtua')
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data orang tua",
            'updated_count' => $updatedCount
        ]);
    }

    // Bulk fix all existing data
    public function bulkFixExistingData()
    {
        $updatedCount = SimpegDataKeluargaPegawai::whereNotNull('status_orangtua')
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data orang tua dari semua pegawai",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data orang tua
    public function show($id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataOrangTua = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('status_orangtua')
            ->find($id);

        if (!$dataOrangTua) {
            return response()->json([
                'success' => false,
                'message' => 'Data orang tua tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($pegawai->load([
                'unitKerja', 'statusAktif', 'jabatanFungsional',
                'jabatanStruktural.jenisJabatanStruktural',
                'dataPendidikanFormal.jenjangPendidikan'
            ])),
            'data' => $this->formatDataOrangTua($dataOrangTua, false)
        ]);
    }

    // Store new data orang tua dengan draft/submit mode
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
            'nama' => 'required|string|max:100',
            'status_orangtua' => 'required|in:Ayah,Ibu,Ayah Kandung,Ibu Kandung,Ayah Tiri,Ibu Tiri',
            'tempat_lahir' => 'nullable|string|max:50',
            'tgl_lahir' => 'nullable|date|before:today',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string|max:55',
            'pekerjaan' => 'nullable|string|max:100',
            'submit_type' => 'sometimes|in:draft,submit', // Optional, default to draft
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if status_orangtua already exists for this pegawai
        $existingOrangTua = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->where('status_orangtua', $request->status_orangtua)
            ->whereNotNull('status_orangtua')
            ->first();

        if ($existingOrangTua) {
            return response()->json([
                'success' => false,
                'message' => 'Data '.$request->status_orangtua.' sudah ada untuk pegawai ini'
            ], 422);
        }

        $data = $request->except(['submit_type']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Calculate age if tgl_lahir provided
        if ($request->tgl_lahir) {
            $birthDate = new \DateTime($request->tgl_lahir);
            $today = new \DateTime();
            $data['umur'] = $today->diff($birthDate)->y;
        }

        // Set status berdasarkan submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data orang tua berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data orang tua berhasil disimpan sebagai draft';
        }

        $dataOrangTua = SimpegDataKeluargaPegawai::create($data);

        ActivityLogger::log('create', $dataOrangTua, $dataOrangTua->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataOrangTua($dataOrangTua, false),
            'message' => $message
        ], 201);
    }

    // Update data orang tua dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataOrangTua = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('status_orangtua')
            ->find($id);

        if (!$dataOrangTua) {
            return response()->json([
                'success' => false,
                'message' => 'Data orang tua tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataOrangTua->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'sometimes|string|max:100',
            'status_orangtua' => 'sometimes|in:Ayah,Ibu,Ayah Kandung,Ibu Kandung,Ayah Tiri,Ibu Tiri',
            'tempat_lahir' => 'nullable|string|max:50',
            'tgl_lahir' => 'nullable|date|before:today',
            'alamat' => 'nullable|string',
            'telepon' => 'nullable|string|max:55',
            'pekerjaan' => 'nullable|string|max:100',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if status_orangtua already exists for this pegawai (excluding current record)
        if ($request->has('status_orangtua')) {
            $existingOrangTua = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
                ->where('status_orangtua', $request->status_orangtua)
                ->where('id', '!=', $id)
                ->whereNotNull('status_orangtua')
                ->first();

            if ($existingOrangTua) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data '.$request->status_orangtua.' sudah ada untuk pegawai ini'
                ], 422);
            }
        }

        $oldData = $dataOrangTua->getOriginal();
        $data = $request->except(['submit_type']);

        // Calculate age if tgl_lahir changed
        if ($request->has('tgl_lahir') && $request->tgl_lahir) {
            $birthDate = new \DateTime($request->tgl_lahir);
            $today = new \DateTime();
            $data['umur'] = $today->diff($birthDate)->y;
        }

        // Reset status jika dari ditolak
        if ($dataOrangTua->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null;
            $data['keterangan'] = $request->keterangan ?? null;
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data orang tua berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Data orang tua berhasil diperbarui';
        }

        $dataOrangTua->update($data);

        ActivityLogger::log('update', $dataOrangTua, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataOrangTua($dataOrangTua, false),
            'message' => $message
        ]);
    }

    // Delete data orang tua
    public function destroy($id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataOrangTua = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('status_orangtua')
            ->find($id);

        if (!$dataOrangTua) {
            return response()->json([
                'success' => false,
                'message' => 'Data orang tua tidak ditemukan'
            ], 404);
        }

        $oldData = $dataOrangTua->toArray();
        $dataOrangTua->delete();

        ActivityLogger::log('delete', $dataOrangTua, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data orang tua berhasil dihapus'
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

        $dataOrangTua = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('status_orangtua')
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataOrangTua) {
            return response()->json([
                'success' => false,
                'message' => 'Data orang tua draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataOrangTua->getOriginal();
        
        $dataOrangTua->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataOrangTua, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data orang tua berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete data orang tua
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

        $dataOrangTuaList = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('status_orangtua')
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataOrangTuaList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data orang tua tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataOrangTuaList as $dataOrangTua) {
            try {
                $oldData = $dataOrangTua->toArray();
                $dataOrangTua->delete();
                
                ActivityLogger::log('delete', $dataOrangTua, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataOrangTua->id,
                    'nama' => $dataOrangTua->nama,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data orang tua",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data orang tua",
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
            ->whereNotNull('status_orangtua')
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data orang tua untuk persetujuan",
            'updated_count' => $updatedCount
        ]);
    }

    // Batch update status
    public function batchUpdateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string'
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

        $updateData = [
            'status_pengajuan' => $request->status_pengajuan,
            'keterangan' => $request->keterangan
        ];

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
            ->whereNotNull('status_orangtua')
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
            ->whereNotNull('status_orangtua')
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

        $jenisOrangTua = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('status_orangtua')
            ->distinct()
            ->pluck('status_orangtua')
            ->filter()
            ->values();

        $jenisPekerjaan = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('status_orangtua')
            ->distinct()
            ->pluck('pekerjaan')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'jenis_orangtua' => $jenisOrangTua,
                'jenis_pekerjaan' => $jenisPekerjaan,
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ]
            ],
            'available_status_orangtua' => [
                'Ayah',
                'Ibu', 
                'Ayah Kandung',
                'Ibu Kandung',
                'Ayah Tiri',
                'Ibu Tiri'
            ]
        ]);
    }

    // Check available parent status
    public function checkAvailableStatus()
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $existingStatus = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('status_orangtua')
            ->pluck('status_orangtua')
            ->toArray();

        $allStatus = [
            'Ayah',
            'Ibu', 
            'Ayah Kandung',
            'Ibu Kandung',
            'Ayah Tiri',
            'Ibu Tiri'
        ];

        $availableStatus = array_diff($allStatus, $existingStatus);

        return response()->json([
            'success' => true,
            'available_status' => array_values($availableStatus),
            'existing_status' => $existingStatus
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus data orang tua ini?',
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua data orang tua yang dipilih?'
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

    // Helper: Format data orang tua response
    protected function formatDataOrangTua($dataOrangTua, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataOrangTua->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        $data = [
            'id' => $dataOrangTua->id,
            'nama' => $dataOrangTua->nama,
            'status_orangtua' => $dataOrangTua->status_orangtua,
            'tempat_lahir' => $dataOrangTua->tempat_lahir,
            'tgl_lahir' => $dataOrangTua->tgl_lahir,
            'umur' => $dataOrangTua->umur,
            'alamat' => $dataOrangTua->alamat,
            'telepon' => $dataOrangTua->telepon,
            'pekerjaan' => $dataOrangTua->pekerjaan,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataOrangTua->keterangan,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataOrangTua->tgl_input,
                'tgl_diajukan' => $dataOrangTua->tgl_diajukan,
                'tgl_disetujui' => $dataOrangTua->tgl_disetujui,
                'tgl_ditolak' => $dataOrangTua->tgl_ditolak
            ],
            'created_at' => $dataOrangTua->created_at,
            'updated_at' => $dataOrangTua->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            // Get URL prefix from request
            $request = request();
            $prefix = $request->segment(2) ?? 'dosen'; // fallback to 'dosen'
            
            $data['aksi'] = [
                'detail_url' => url("/api/{$prefix}/orangtua/{$dataOrangTua->id}"),
                'update_url' => url("/api/{$prefix}/orangtua/{$dataOrangTua->id}"),
                'delete_url' => url("/api/{$prefix}/orangtua/{$dataOrangTua->id}"),
                'submit_url' => url("/api/{$prefix}/orangtua/{$dataOrangTua->id}/submit"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data orang tua "' . $dataOrangTua->nama . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data orang tua "' . $dataOrangTua->nama . '" untuk persetujuan?'
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