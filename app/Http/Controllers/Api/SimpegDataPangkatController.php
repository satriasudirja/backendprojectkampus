<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPangkat;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegDaftarJenisSk;
use App\Models\SimpegJenisKenaikanPangkat;
use App\Models\SimpegMasterPangkat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataPangkatController extends Controller
{
    // Get all data pangkat for logged in pegawai
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

        // Query HANYA untuk pegawai yang sedang login (data pangkat)
        $query = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
            ->with(['jenisSk', 'jenisKenaikanPangkat', 'pangkat']);

        // Filter by search (no_sk, pejabat_penetap, tmt_pangkat, tgl_sk)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('no_sk', 'like', '%'.$search.'%')
                  ->orWhere('pejabat_penetap', 'like', '%'.$search.'%')
                  ->orWhere('tmt_pangkat', 'like', '%'.$search.'%')
                  ->orWhere('tgl_sk', 'like', '%'.$search.'%')
                  ->orWhereHas('pangkat', function($query) use ($search) {
                      $query->where('nama_golongan', 'like', '%'.$search.'%');
                  })
                  ->orWhereHas('jenisSk', function($query) use ($search) {
                      $query->where('jenis_sk', 'like', '%'.$search.'%');
                  })
                  ->orWhereHas('jenisKenaikanPangkat', function($query) use ($search) {
                      $query->where('jenis_pangkat', 'like', '%'.$search.'%');
                  });
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('jenis_sk_id')) {
            $query->where('jenis_sk_id', $request->jenis_sk_id);
        }
        if ($request->filled('jenis_kenaikan_pangkat_id')) {
            $query->where('jenis_kenaikan_pangkat_id', $request->jenis_kenaikan_pangkat_id);
        }
        if ($request->filled('pangkat_id')) {
            $query->where('pangkat_id', $request->pangkat_id);
        }
        if ($request->filled('tmt_pangkat')) {
            $query->whereDate('tmt_pangkat', $request->tmt_pangkat);
        }
        if ($request->filled('tgl_sk')) {
            $query->whereDate('tgl_sk', $request->tgl_sk);
        }

        // Execute query dengan pagination
        $dataPangkat = $query->orderBy('tmt_pangkat', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataPangkat->getCollection()->transform(function ($item) {
            return $this->formatDataPangkat($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataPangkat,
            'empty_data' => $dataPangkat->isEmpty(),
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
                ['field' => 'tmt_pangkat', 'label' => 'TMT Pangkat', 'sortable' => true, 'sortable_field' => 'tmt_pangkat'],
                ['field' => 'jenis_sk', 'label' => 'Jenis SK', 'sortable' => true, 'sortable_field' => 'jenis_sk'],
                ['field' => 'jenis_pangkat', 'label' => 'Jenis Kenaikan', 'sortable' => true, 'sortable_field' => 'jenis_pangkat'],
                ['field' => 'nama_golongan', 'label' => 'Pangkat/Golongan', 'sortable' => true, 'sortable_field' => 'nama_golongan'],
                ['field' => 'pejabat_penetap', 'label' => 'Pejabat Penetap', 'sortable' => true, 'sortable_field' => 'pejabat_penetap'],
                ['field' => 'masa_kerja', 'label' => 'Masa Kerja', 'sortable' => false],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_pangkat_url' => url("/api/dosen/pangkat"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/pangkat/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/pangkat/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/pangkat/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataPangkat->currentPage(),
                'per_page' => $dataPangkat->perPage(),
                'total' => $dataPangkat->total(),
                'last_page' => $dataPangkat->lastPage(),
                'from' => $dataPangkat->firstItem(),
                'to' => $dataPangkat->lastItem()
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
        $updatedCount = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data pangkat",
            'updated_count' => $updatedCount
        ]);
    }

    // Bulk fix all existing data
    public function bulkFixExistingData()
    {
        $updatedCount = SimpegDataPangkat::whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data pangkat dari semua pegawai",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data pangkat
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPangkat = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
            ->with(['jenisSk', 'jenisKenaikanPangkat', 'pangkat'])
            ->find($id);

        if (!$dataPangkat) {
            return response()->json([
                'success' => false,
                'message' => 'Data pangkat tidak ditemukan'
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
            'data' => $this->formatDataPangkat($dataPangkat, false)
        ]);
    }

    // Store new data pangkat dengan draft/submit mode
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
            'jenis_sk_id' => 'required|exists:simpeg_daftar_jenis_sk,id',
            'jenis_kenaikan_pangkat_id' => 'required|exists:simpeg_jenis_kenaikan_pangkat,id',
            'pangkat_id' => 'required|exists:simpeg_master_pangkat,id',
            'tmt_pangkat' => 'required|date',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'required|string|max:100',
            'masa_kerja_tahun' => 'nullable|integer|min:0|max:50',
            'masa_kerja_bulan' => 'nullable|integer|min:0|max:11',
            'acuan_masa_kerja' => 'nullable|boolean',
            'file_pangkat' => 'nullable|file|mimes:pdf|max:5120', // Max 5MB
            'submit_type' => 'sometimes|in:draft,submit', // Optional, default to draft
            'is_aktif' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['submit_type', 'file_pangkat']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Handle file upload
        if ($request->hasFile('file_pangkat')) {
            $file = $request->file('file_pangkat');
            $fileName = 'pangkat_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('uploads/pangkat', $fileName, 'public');
            $data['file_pangkat'] = $filePath;
        }

        // Set status berdasarkan submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data pangkat berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data pangkat berhasil disimpan sebagai draft';
        }

        $dataPangkat = SimpegDataPangkat::create($data);

        ActivityLogger::log('create', $dataPangkat, $dataPangkat->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPangkat($dataPangkat->load(['jenisSk', 'jenisKenaikanPangkat', 'pangkat']), false),
            'message' => $message
        ], 201);
    }

    // Update data pangkat dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPangkat = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataPangkat) {
            return response()->json([
                'success' => false,
                'message' => 'Data pangkat tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataPangkat->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'jenis_sk_id' => 'sometimes|exists:simpeg_daftar_jenis_sk,id',
            'jenis_kenaikan_pangkat_id' => 'sometimes|exists:simpeg_jenis_kenaikan_pangkat,id',
            'pangkat_id' => 'sometimes|exists:simpeg_master_pangkat,id',
            'tmt_pangkat' => 'sometimes|date',
            'no_sk' => 'sometimes|string|max:100',
            'tgl_sk' => 'sometimes|date',
            'pejabat_penetap' => 'sometimes|string|max:100',
            'masa_kerja_tahun' => 'nullable|integer|min:0|max:50',
            'masa_kerja_bulan' => 'nullable|integer|min:0|max:11',
            'acuan_masa_kerja' => 'nullable|boolean',
            'file_pangkat' => 'nullable|file|mimes:pdf|max:5120', // Max 5MB
            'submit_type' => 'sometimes|in:draft,submit',
            'is_aktif' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $dataPangkat->getOriginal();
        $data = $request->except(['submit_type', 'file_pangkat']);

        // Handle file upload
        if ($request->hasFile('file_pangkat')) {
            // Delete old file if exists
            if ($dataPangkat->file_pangkat) {
                Storage::disk('public')->delete($dataPangkat->file_pangkat);
            }
            
            $file = $request->file('file_pangkat');
            $fileName = 'pangkat_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('uploads/pangkat', $fileName, 'public');
            $data['file_pangkat'] = $filePath;
        }

        // Reset status jika dari ditolak
        if ($dataPangkat->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null;
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data pangkat berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Data pangkat berhasil diperbarui';
        }

        $dataPangkat->update($data);

        ActivityLogger::log('update', $dataPangkat, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPangkat($dataPangkat->load(['jenisSk', 'jenisKenaikanPangkat', 'pangkat']), false),
            'message' => $message
        ]);
    }

    // Delete data pangkat
    public function destroy($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPangkat = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataPangkat) {
            return response()->json([
                'success' => false,
                'message' => 'Data pangkat tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($dataPangkat->file_pangkat) {
            Storage::disk('public')->delete($dataPangkat->file_pangkat);
        }

        $oldData = $dataPangkat->toArray();
        $dataPangkat->delete();

        ActivityLogger::log('delete', $dataPangkat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data pangkat berhasil dihapus'
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

        $dataPangkat = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataPangkat) {
            return response()->json([
                'success' => false,
                'message' => 'Data pangkat draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataPangkat->getOriginal();
        
        $dataPangkat->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataPangkat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data pangkat berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete data pangkat
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_pangkat,id'
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

        $dataPangkatList = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataPangkatList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data pangkat tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataPangkatList as $dataPangkat) {
            try {
                // Delete file if exists
                if ($dataPangkat->file_pangkat) {
                    Storage::disk('public')->delete($dataPangkat->file_pangkat);
                }

                $oldData = $dataPangkat->toArray();
                $dataPangkat->delete();
                
                ActivityLogger::log('delete', $dataPangkat, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataPangkat->id,
                    'no_sk' => $dataPangkat->no_sk,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data pangkat",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data pangkat",
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

        $updatedCount = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data pangkat untuk persetujuan",
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

        $pegawai = Auth::user();

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

        $updatedCount = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
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
        $jenisSk = SimpegDaftarJenisSk::select('id', 'jenis_sk as nama')
            ->orderBy('jenis_sk')
            ->get();

        $jenisKenaikanPangkat = SimpegJenisKenaikanPangkat::select('id', 'jenis_pangkat as nama')
            ->orderBy('jenis_pangkat')
            ->get();

        $pangkat = SimpegMasterPangkat::select('id', 'nama_golongan as nama')
            ->orderBy('nama_golongan')
            ->get();

        return response()->json([
            'success' => true,
            'form_options' => [
                'jenis_sk' => $jenisSk,
                'jenis_kenaikan_pangkat' => $jenisKenaikanPangkat,
                'pangkat' => $pangkat
            ]
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

        $statistics = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
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

    // Download file pangkat
    public function downloadFile($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPangkat = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataPangkat || !$dataPangkat->file_pangkat) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan'
            ], 404);
        }

        $filePath = storage_path('app/public/' . $dataPangkat->file_pangkat);
        
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

    // Helper: Format data pangkat response
    protected function formatDataPangkat($dataPangkat, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataPangkat->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        // Format masa kerja
        $masaKerja = '';
        if ($dataPangkat->masa_kerja_tahun || $dataPangkat->masa_kerja_bulan) {
            $tahun = $dataPangkat->masa_kerja_tahun ? $dataPangkat->masa_kerja_tahun . ' tahun' : '';
            $bulan = $dataPangkat->masa_kerja_bulan ? $dataPangkat->masa_kerja_bulan . ' bulan' : '';
            $masaKerja = trim($tahun . ' ' . $bulan);
        }
        
        $data = [
            'id' => $dataPangkat->id,
            'jenis_sk_id' => $dataPangkat->jenis_sk_id,
            'jenis_sk' => $dataPangkat->jenisSk ? $dataPangkat->jenisSk->jenis_sk : '-',
            'jenis_kenaikan_pangkat_id' => $dataPangkat->jenis_kenaikan_pangkat_id,
            'jenis_pangkat' => $dataPangkat->jenisKenaikanPangkat ? $dataPangkat->jenisKenaikanPangkat->jenis_pangkat : '-',
            'pangkat_id' => $dataPangkat->pangkat_id,
            'nama_golongan' => $dataPangkat->pangkat ? $dataPangkat->pangkat->nama_golongan : '-',
            'tmt_pangkat' => $dataPangkat->tmt_pangkat,
            'tmt_pangkat_formatted' => $dataPangkat->tmt_pangkat ? $dataPangkat->tmt_pangkat->format('d-m-Y') : '-',
            'no_sk' => $dataPangkat->no_sk,
            'tgl_sk' => $dataPangkat->tgl_sk,
            'tgl_sk_formatted' => $dataPangkat->tgl_sk ? $dataPangkat->tgl_sk->format('d-m-Y') : '-',
            'pejabat_penetap' => $dataPangkat->pejabat_penetap,
            'masa_kerja_tahun' => $dataPangkat->masa_kerja_tahun,
            'masa_kerja_bulan' => $dataPangkat->masa_kerja_bulan,
            'masa_kerja' => $masaKerja ?: '-',
            'acuan_masa_kerja' => $dataPangkat->acuan_masa_kerja,
            'file_pangkat' => $dataPangkat->file_pangkat,
            'file_url' => $dataPangkat->file_pangkat ? Storage::url($dataPangkat->file_pangkat) : null,
            'is_aktif' => $dataPangkat->is_aktif,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataPangkat->tgl_input,
                'tgl_diajukan' => $dataPangkat->tgl_diajukan,
                'tgl_disetujui' => $dataPangkat->tgl_disetujui,
                'tgl_ditolak' => $dataPangkat->tgl_ditolak
            ],
            'created_at' => $dataPangkat->created_at,
            'updated_at' => $dataPangkat->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            // Get URL prefix from request
            $request = request();
            $prefix = $request->segment(2) ?? 'dosen'; // fallback to 'dosen'
            
            $data['aksi'] = [
                'detail_url' => url("/api/{$prefix}/pangkat/{$dataPangkat->id}"),
                'update_url' => url("/api/{$prefix}/pangkat/{$dataPangkat->id}"),
                'delete_url' => url("/api/{$prefix}/pangkat/{$dataPangkat->id}"),
                'submit_url' => url("/api/{$prefix}/pangkat/{$dataPangkat->id}/submit"),
                'download_url' => $dataPangkat->file_pangkat ? url("/api/{$prefix}/pangkat/{$dataPangkat->id}/download") : null,
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data pangkat dengan No. SK "' . $dataPangkat->no_sk . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data pangkat dengan No. SK "' . $dataPangkat->no_sk . '" untuk persetujuan?'
                ];
            }

            // Download action if file exists
            if ($dataPangkat->file_pangkat) {
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