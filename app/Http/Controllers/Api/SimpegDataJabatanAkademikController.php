<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataJabatanAkademik;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegJabatanAkademik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataJabatanAkademikController extends Controller
{
    // Get all data jabatan akademik for logged in pegawai
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

        // Query HANYA untuk pegawai yang sedang login (data jabatan akademik)
        $query = SimpegDataJabatanAkademik::where('pegawai_id', $pegawai->id)
            ->with(['jabatanAkademik']);

        // Filter by search (no_sk, pejabat_penetap, tmt_jabatan, tgl_sk)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('no_sk', 'like', '%'.$search.'%')
                  ->orWhere('pejabat_penetap', 'like', '%'.$search.'%')
                  ->orWhere('tmt_jabatan', 'like', '%'.$search.'%')
                  ->orWhere('tgl_sk', 'like', '%'.$search.'%')
                  ->orWhereHas('jabatanAkademik', function($query) use ($search) {
                      $query->where('jabatan_akademik', 'like', '%'.$search.'%');
                  });
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('jabatan_akademik_id')) {
            $query->where('jabatan_akademik_id', $request->jabatan_akademik_id);
        }
        if ($request->filled('tmt_jabatan')) {
            $query->whereDate('tmt_jabatan', $request->tmt_jabatan);
        }
        if ($request->filled('tgl_sk')) {
            $query->whereDate('tgl_sk', $request->tgl_sk);
        }

        // Execute query dengan pagination
        $dataJabatanAkademik = $query->orderBy('tmt_jabatan', 'desc')
                                   ->orderBy('created_at', 'desc')
                                   ->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataJabatanAkademik->getCollection()->transform(function ($item) {
            return $this->formatDataJabatanAkademik($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataJabatanAkademik,
            'empty_data' => $dataJabatanAkademik->isEmpty(),
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
                ['field' => 'no_sk', 'label' => 'No. SK', 'sortable' => true, 'sortable_field' => 'no_sk'],
                ['field' => 'tgl_sk', 'label' => 'Tanggal SK', 'sortable' => true, 'sortable_field' => 'tgl_sk'],
                ['field' => 'tmt_jabatan', 'label' => 'TMT Jabatan', 'sortable' => true, 'sortable_field' => 'tmt_jabatan'],
                ['field' => 'jabatan_akademik', 'label' => 'Jabatan Akademik', 'sortable' => true, 'sortable_field' => 'jabatan_akademik'],
                ['field' => 'pejabat_penetap', 'label' => 'Pejabat Penetap', 'sortable' => true, 'sortable_field' => 'pejabat_penetap'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_jabatan_akademik_url' => url("/api/dosen/jabatanakademik"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/jabatanakademik/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/jabatanakademik/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/jabatanakademik/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataJabatanAkademik->currentPage(),
                'per_page' => $dataJabatanAkademik->perPage(),
                'total' => $dataJabatanAkademik->total(),
                'last_page' => $dataJabatanAkademik->lastPage(),
                'from' => $dataJabatanAkademik->firstItem(),
                'to' => $dataJabatanAkademik->lastItem()
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
        $updatedCount = SimpegDataJabatanAkademik::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data jabatan akademik",
            'updated_count' => $updatedCount
        ]);
    }

    // Bulk fix all existing data
    public function bulkFixExistingData()
    {
        $updatedCount = SimpegDataJabatanAkademik::whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data jabatan akademik dari semua pegawai",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data jabatan akademik
    public function show($id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataJabatanAkademik = SimpegDataJabatanAkademik::where('pegawai_id', $pegawai->id)
            ->with(['jabatanAkademik'])
            ->find($id);

        if (!$dataJabatanAkademik) {
            return response()->json([
                'success' => false,
                'message' => 'Data jabatan akademik tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($pegawai->load([
                'unitKerja', 'statusAktif', 'jabatanFungsional',
                'jabatanStruktural.jenisJabatanStruktural',
                'dataPendidikanFormal.jenjangPendidikan'
            ])),
            'data' => $this->formatDataJabatanAkademik($dataJabatanAkademik, false)
        ]);
    }

    // Store new data jabatan akademik dengan draft/submit mode
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
            'jabatan_akademik_id' => 'required|exists:simpeg_jabatan_akademik,id',
            'tmt_jabatan' => 'required|date',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'required|string|max:100',
            'file_jabatan' => 'nullable|file|mimes:pdf|max:5120', // Max 5MB
            'submit_type' => 'sometimes|in:draft,submit' // Optional, default to draft
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['submit_type', 'file_jabatan']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Handle file upload
        if ($request->hasFile('file_jabatan')) {
            $file = $request->file('file_jabatan');
            $fileName = 'jabatan_akademik_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('uploads/jabatan_akademik', $fileName, 'public');
            $data['file_jabatan'] = $filePath;
        }

        // Set status berdasarkan submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data jabatan akademik berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data jabatan akademik berhasil disimpan sebagai draft';
        }

        $dataJabatanAkademik = SimpegDataJabatanAkademik::create($data);

        ActivityLogger::log('create', $dataJabatanAkademik, $dataJabatanAkademik->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataJabatanAkademik($dataJabatanAkademik->load(['jabatanAkademik']), false),
            'message' => $message
        ], 201);
    }

    // Update data jabatan akademik dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataJabatanAkademik = SimpegDataJabatanAkademik::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataJabatanAkademik) {
            return response()->json([
                'success' => false,
                'message' => 'Data jabatan akademik tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataJabatanAkademik->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'jabatan_akademik_id' => 'sometimes|exists:simpeg_jabatan_akademik,id',
            'tmt_jabatan' => 'sometimes|date',
            'no_sk' => 'sometimes|string|max:100',
            'tgl_sk' => 'sometimes|date',
            'pejabat_penetap' => 'sometimes|string|max:100',
            'file_jabatan' => 'nullable|file|mimes:pdf|max:5120', // Max 5MB
            'submit_type' => 'sometimes|in:draft,submit'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $dataJabatanAkademik->getOriginal();
        $data = $request->except(['submit_type', 'file_jabatan']);

        // Handle file upload
        if ($request->hasFile('file_jabatan')) {
            // Delete old file if exists
            if ($dataJabatanAkademik->file_jabatan) {
                Storage::disk('public')->delete($dataJabatanAkademik->file_jabatan);
            }
            
            $file = $request->file('file_jabatan');
            $fileName = 'jabatan_akademik_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('uploads/jabatan_akademik', $fileName, 'public');
            $data['file_jabatan'] = $filePath;
        }

        // Reset status jika dari ditolak
        if ($dataJabatanAkademik->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null;
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data jabatan akademik berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Data jabatan akademik berhasil diperbarui';
        }

        $dataJabatanAkademik->update($data);

        ActivityLogger::log('update', $dataJabatanAkademik, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataJabatanAkademik($dataJabatanAkademik->load(['jabatanAkademik']), false),
            'message' => $message
        ]);
    }

    // Delete data jabatan akademik
    public function destroy($id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataJabatanAkademik = SimpegDataJabatanAkademik::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataJabatanAkademik) {
            return response()->json([
                'success' => false,
                'message' => 'Data jabatan akademik tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($dataJabatanAkademik->file_jabatan) {
            Storage::disk('public')->delete($dataJabatanAkademik->file_jabatan);
        }

        $oldData = $dataJabatanAkademik->toArray();
        $dataJabatanAkademik->delete();

        ActivityLogger::log('delete', $dataJabatanAkademik, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data jabatan akademik berhasil dihapus'
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

        $dataJabatanAkademik = SimpegDataJabatanAkademik::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataJabatanAkademik) {
            return response()->json([
                'success' => false,
                'message' => 'Data jabatan akademik draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataJabatanAkademik->getOriginal();
        
        $dataJabatanAkademik->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataJabatanAkademik, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data jabatan akademik berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete data jabatan akademik
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_jabatan_akademik,id'
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

        $dataJabatanAkademikList = SimpegDataJabatanAkademik::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataJabatanAkademikList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data jabatan akademik tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataJabatanAkademikList as $dataJabatanAkademik) {
            try {
                // Delete file if exists
                if ($dataJabatanAkademik->file_jabatan) {
                    Storage::disk('public')->delete($dataJabatanAkademik->file_jabatan);
                }

                $oldData = $dataJabatanAkademik->toArray();
                $dataJabatanAkademik->delete();
                
                ActivityLogger::log('delete', $dataJabatanAkademik, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataJabatanAkademik->id,
                    'no_sk' => $dataJabatanAkademik->no_sk,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data jabatan akademik",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data jabatan akademik",
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

        $updatedCount = SimpegDataJabatanAkademik::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data jabatan akademik untuk persetujuan",
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
            'status_pengajuan' => $request->status_pengajuan
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

        $updatedCount = SimpegDataJabatanAkademik::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui',
            'updated_count' => $updatedCount
        ]);
    }

    // Get dropdown options for create/update forms
    public function getFormOptions()
    {
        $jabatanAkademik = SimpegJabatanAkademik::select('id', 'jabatan_akademik as nama')
            ->orderBy('jabatan_akademik')
            ->get();

        return response()->json([
            'success' => true,
            'form_options' => [
                'jabatan_akademik' => $jabatanAkademik
            ]
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

        $statistics = SimpegDataJabatanAkademik::where('pegawai_id', $pegawai->id)
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
            'auto_submit_reminder_days' => env('AUTO_SUBMIT_REMINDER_DAYS', 7),
            'max_file_size' => 5120, // 5MB in KB
            'allowed_file_types' => ['pdf']
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

    // Download file jabatan akademik
    public function downloadFile($id)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataJabatanAkademik = SimpegDataJabatanAkademik::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataJabatanAkademik || !$dataJabatanAkademik->file_jabatan) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan'
            ], 404);
        }

        $filePath = storage_path('app/public/' . $dataJabatanAkademik->file_jabatan);
        
        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan di storage'
            ], 404);
        }

        return response()->download($filePath);
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

    // Helper: Format data jabatan akademik response
    protected function formatDataJabatanAkademik($dataJabatanAkademik, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataJabatanAkademik->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        $data = [
            'id' => $dataJabatanAkademik->id,
            'jabatan_akademik_id' => $dataJabatanAkademik->jabatan_akademik_id,
            'jabatan_akademik' => $dataJabatanAkademik->jabatanAkademik ? $dataJabatanAkademik->jabatanAkademik->jabatan_akademik : '-',
            'tmt_jabatan' => $dataJabatanAkademik->tmt_jabatan,
            'tmt_jabatan_formatted' => $dataJabatanAkademik->tmt_jabatan ? $dataJabatanAkademik->tmt_jabatan->format('d-m-Y') : '-',
            'no_sk' => $dataJabatanAkademik->no_sk,
            'tgl_sk' => $dataJabatanAkademik->tgl_sk,
            'tgl_sk_formatted' => $dataJabatanAkademik->tgl_sk ? $dataJabatanAkademik->tgl_sk->format('d-m-Y') : '-',
            'pejabat_penetap' => $dataJabatanAkademik->pejabat_penetap,
            'file_jabatan' => $dataJabatanAkademik->file_jabatan,
            'file_url' => $dataJabatanAkademik->file_jabatan ? Storage::url($dataJabatanAkademik->file_jabatan) : null,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataJabatanAkademik->tgl_input,
                'tgl_diajukan' => $dataJabatanAkademik->tgl_diajukan ?? null,
                'tgl_disetujui' => $dataJabatanAkademik->tgl_disetujui ?? null,
                'tgl_ditolak' => $dataJabatanAkademik->tgl_ditolak ?? null
            ],
            'created_at' => $dataJabatanAkademik->created_at,
            'updated_at' => $dataJabatanAkademik->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            // Get URL prefix from request
            $request = request();
            $prefix = $request->segment(2) ?? 'dosen'; // fallback to 'dosen'
            
            $data['aksi'] = [
                'detail_url' => url("/api/{$prefix}/jabatanakademik/{$dataJabatanAkademik->id}"),
                'update_url' => url("/api/{$prefix}/jabatanakademik/{$dataJabatanAkademik->id}"),
                'delete_url' => url("/api/{$prefix}/jabatanakademik/{$dataJabatanAkademik->id}"),
                'submit_url' => url("/api/{$prefix}/jabatanakademik/{$dataJabatanAkademik->id}/submit"),
                'download_url' => $dataJabatanAkademik->file_jabatan ? url("/api/{$prefix}/jabatanakademik/{$dataJabatanAkademik->id}/download") : null,
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data jabatan akademik dengan No. SK "' . $dataJabatanAkademik->no_sk . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data jabatan akademik dengan No. SK "' . $dataJabatanAkademik->no_sk . '" untuk persetujuan?'
                ];
            }

            // Download action if file exists
            if ($dataJabatanAkademik->file_jabatan) {
                $data['actions']['download'] = [
                    'url' => $data['aksi']['download_url'],
                    'method' => 'GET',
                    'label' => 'Download File',
                    'icon' => 'download',
                    'color' => 'success'
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