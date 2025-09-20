<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataJabatanStruktural;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJabatanStruktural; // Model for Jabatan Struktural list
use App\Models\SimpegJabatanAkademik; // For pegawai info detail
use App\Models\HubunganKerja; // For pegawai info detail
use App\Models\SimpegStatusAktif; // For pegawai info detail
use App\Models\JenisJabatanStruktural; // For populating form options or detail

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger; // Assuming this service exists
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class SimpegDataJabatanStrukturalAdminController extends Controller
{
    /**
     * Get all data jabatan struktural for admin (all pegawai).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search; // For NIP, Nama Pegawai, No SK
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanStrukturalId = $request->jabatan_struktural_id; // Filter by specific jabatan struktural
        $statusPengajuan = $request->status_pengajuan ?? 'semua';
        $tglMulai = $request->tgl_mulai;
        $tglSelesai = $request->tgl_selesai;
        $noSk = $request->no_sk;
        $tglDisetujui = $request->tgl_disetujui;

        // Eager load necessary relations
        $query = SimpegDataJabatanStruktural::with([
            'pegawai' => function ($q) {
                $q->select('id', 'nip', 'nama', 'gelar_depan', 'gelar_belakang', 'unit_kerja_id')
                    ->with('unitKerja:kode_unit,nama_unit');
            },
            'jabatanStruktural' => function($q) {
                $q->with('jenisJabatanStruktural'); // Load jenis jabatan struktural if available
            }
        ]);

        // Apply filters
        $query->filterByNipNamaPegawai($search)
              ->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanStrukturalId($jabatanStrukturalId)
              ->byStatus($statusPengajuan)
              ->filterByTglMulai($tglMulai)
              ->filterByTglSelesai($tglSelesai)
              ->filterByNoSk($noSk)
              ->filterByTglDisetujui($tglDisetujui);

        // Order results
        $dataJabatanStruktural = $query->latest('tgl_input')
                                     ->paginate($perPage);

        // Transform collection for frontend
        $dataJabatanStruktural->getCollection()->transform(function ($item) {
            return $this->formatDataJabatanStruktural($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataJabatanStruktural,
            'empty_data' => $dataJabatanStruktural->isEmpty(),
            'filters' => $this->getFilterOptions(),
            'table_columns' => [
                ['field' => 'nip_pegawai', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'pegawai.nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'pegawai.nama'],
                ['field' => 'tgl_mulai_formatted', 'label' => 'Tgl Mulai', 'sortable' => true, 'sortable_field' => 'tgl_mulai'],
                ['field' => 'tgl_selesai_formatted', 'label' => 'Tgl Selesai', 'sortable' => true, 'sortable_field' => 'tgl_selesai'],
                ['field' => 'nama_jabatan_struktural', 'label' => 'Nama Jabatan', 'sortable' => true, 'sortable_field' => 'jabatan_struktural_id'],
                ['field' => 'file_jabatan_link', 'label' => 'File Jabatan', 'sortable' => false],
                ['field' => 'tgl_disetujui_formatted', 'label' => 'Tgl Disetujui', 'sortable' => true, 'sortable_field' => 'tgl_disetujui'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/datajabatanstrukturaladm/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/datajabatanstrukturaladm/batch/reject"),
                    'method' => 'PATCH',
                    'label' => 'Tolak Terpilih',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menolak data terpilih?',
                ],
                'to_draft' => [
                    'url' => url("/api/admin/datajabatanstrukturaladm/batch/todraft"),
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft Terpilih',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data terpilih menjadi draft?'
                ],
                'delete' => [
                    'url' => url("/api/admin/datajabatanstrukturaladm/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data terpilih?',
                ]
            ],
            'pagination' => [
                'current_page' => $dataJabatanStruktural->currentPage(),
                'per_page' => $dataJabatanStruktural->perPage(),
                'total' => $dataJabatanStruktural->total(),
                'last_page' => $dataJabatanStruktural->lastPage(),
                'from' => $dataJabatanStruktural->firstItem(),
                'to' => $dataJabatanStruktural->lastItem()
            ]
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $dataJabatanStruktural = SimpegDataJabatanStruktural::with([
            'pegawai' => function ($q) {
                $q->with([
                    'unitKerja', 'statusAktif', 'jabatanAkademik',
                    'dataJabatanFungsional' => function ($query) {
                        $query->with('jabatanFungsional')->latest('tmt_jabatan')->limit(1);
                    },
                    'dataJabatanStruktural' => function ($query) {
                        $query->with('jabatanStruktural.jenisJabatanStruktural')->latest('tgl_mulai')->limit(1);
                    },
                    'dataPendidikanFormal' => function ($query) {
                        $query->with('jenjangPendidikan')->orderBy('jenjang_pendidikan_id', 'desc')->limit(1);
                    },
                    'dataHubunganKerja' => function($query) {
                        $query->with('hubunganKerja')->latest('tgl_awal')->limit(1);
                    },
                    'dataJabatanAkademik' => function($query) {
                        $query->with('jabatanAkademik')->latest('tmt_jabatan')->limit(1);
                    }
                ]);
            },
            'jabatanStruktural' => function($q) {
                $q->with('jenisJabatanStruktural');
            }
        ])->find($id);

        if (!$dataJabatanStruktural) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Struktural tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai_info_detail' => $this->formatPegawaiInfo($dataJabatanStruktural->pegawai),
            'data' => $this->formatDataJabatanStruktural($dataJabatanStruktural, false),
            'form_options' => $this->getFormOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|uuid|exists:simpeg_pegawai,id',
            'jabatan_struktural_id' => 'required|uuid|exists:simpeg_jabatan_struktural,id',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'nullable|date|after_or_equal:tgl_mulai',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'file_jabatan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['tgl_input'] = now()->toDateString();
        $data['status_pengajuan'] = $request->input('status_pengajuan', SimpegDataJabatanStruktural::STATUS_DISETUJUI);

        // Handle timestamps based on status
        if ($data['status_pengajuan'] === SimpegDataJabatanStruktural::STATUS_DISETUJUI) {
            $data['tgl_disetujui'] = now();
            $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? now();
        } elseif ($data['status_pengajuan'] === SimpegDataJabatanStruktural::STATUS_DIAJUKAN) {
            $data['tgl_diajukan'] = now();
            $data['tgl_disetujui'] = null;
            $data['tgl_ditolak'] = null;
        } elseif ($data['status_pengajuan'] === SimpegDataJabatanStruktural::STATUS_DITOLAK) {
            $data['tgl_ditolak'] = now();
            $data['tgl_diajukan'] = null;
            $data['tgl_disetujui'] = null;
        } else { // draft
            $data['tgl_diajukan'] = null;
            $data['tgl_disetujui'] = null;
            $data['tgl_ditolak'] = null;
        }

        DB::beginTransaction();
        try {
            if ($request->hasFile('file_jabatan')) {
                $file = $request->file('file_jabatan');
                $fileName = 'jabatan_struktural_' . $data['pegawai_id'] . '_' . Carbon::now()->timestamp . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('jabatan_struktural_files', $fileName, 'public');
                $data['file_jabatan'] = $filePath;
            }

            $dataJabatanStruktural = SimpegDataJabatanStruktural::create($data);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_create_jabatan_struktural', $dataJabatanStruktural, $dataJabatanStruktural->toArray());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data Jabatan Struktural berhasil ditambahkan oleh admin',
                'data' => $this->formatDataJabatanStruktural($dataJabatanStruktural->load(['pegawai.unitKerja', 'jabatanStruktural.jenisJabatanStruktural']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $dataJabatanStruktural = SimpegDataJabatanStruktural::find($id);

        if (!$dataJabatanStruktural) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Struktural tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|uuid|exists:simpeg_pegawai,id',
            'jabatan_struktural_id' => 'sometimes|uuid|exists:simpeg_jabatan_struktural,id',
            'tgl_mulai' => 'sometimes|date',
            'tgl_selesai' => 'nullable|date|after_or_equal:tgl_mulai',
            'no_sk' => 'sometimes|string|max:100',
            'tgl_sk' => 'sometimes|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'file_jabatan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            'clear_file_jabatan' => 'nullable|boolean', // Added for explicit file clearing
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldData = $dataJabatanStruktural->getOriginal();
            $data = $validator->validated();

            // Handle file_jabatan upload
            if ($request->hasFile('file_jabatan')) {
                if ($dataJabatanStruktural->file_jabatan && Storage::disk('public')->exists($dataJabatanStruktural->file_jabatan)) {
                    Storage::disk('public')->delete($dataJabatanStruktural->file_jabatan);
                }
                $file = $request->file('file_jabatan');
                $fileName = 'jabatan_struktural_' . ($data['pegawai_id'] ?? $dataJabatanStruktural->pegawai_id) . '_' . Carbon::now()->timestamp . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('jabatan_struktural_files', $fileName, 'public');
                $data['file_jabatan'] = $filePath;
            } elseif ($request->input('clear_file_jabatan')) {
                if ($dataJabatanStruktural->file_jabatan && Storage::disk('public')->exists($dataJabatanStruktural->file_jabatan)) {
                    Storage::disk('public')->delete($dataJabatanStruktural->file_jabatan);
                }
                $data['file_jabatan'] = null;
            } else {
                $data['file_jabatan'] = $dataJabatanStruktural->file_jabatan;
            }
            unset($data['clear_file_jabatan']);

            // Handle status_pengajuan and timestamps related
            if (isset($data['status_pengajuan']) && $data['status_pengajuan'] !== $dataJabatanStruktural->status_pengajuan) {
                switch ($data['status_pengajuan']) {
                    case SimpegDataJabatanStruktural::STATUS_DIAJUKAN:
                        $data['tgl_diajukan'] = now();
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                    case SimpegDataJabatanStruktural::STATUS_DISETUJUI:
                        $data['tgl_disetujui'] = now();
                        $data['tgl_diajukan'] = $dataJabatanStruktural->tgl_diajukan ?? now();
                        $data['tgl_ditolak'] = null;
                        break;
                    case SimpegDataJabatanStruktural::STATUS_DITOLAK:
                        $data['tgl_ditolak'] = now();
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        break;
                    case SimpegDataJabatanStruktural::STATUS_DRAFT:
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                }
            } else {
                $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? $dataJabatanStruktural->tgl_diajukan;
                $data['tgl_disetujui'] = $data['tgl_disetujui'] ?? $dataJabatanStruktural->tgl_disetujui;
                $data['tgl_ditolak'] = $data['tgl_ditolak'] ?? $dataJabatanStruktural->tgl_ditolak;
            }

            $dataJabatanStruktural->update($data);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_update_jabatan_struktural', $dataJabatanStruktural, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatDataJabatanStruktural($dataJabatanStruktural->load(['pegawai.unitKerja', 'jabatanStruktural.jenisJabatanStruktural'])),
                'message' => 'Data Jabatan Struktural berhasil diperbarui oleh admin'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $dataJabatanStruktural = SimpegDataJabatanStruktural::find($id);

        if (!$dataJabatanStruktural) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Struktural tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete associated file
            if ($dataJabatanStruktural->file_jabatan && Storage::disk('public')->exists($dataJabatanStruktural->file_jabatan)) {
                Storage::disk('public')->delete($dataJabatanStruktural->file_jabatan);
            }

            $oldData = $dataJabatanStruktural->toArray();
            $dataJabatanStruktural->delete();

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_delete_jabatan_struktural', $dataJabatanStruktural, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data Jabatan Struktural berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Approve a single data entry.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id)
    {
        $dataJabatanStruktural = SimpegDataJabatanStruktural::find($id);

        if (!$dataJabatanStruktural) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Struktural tidak ditemukan'
            ], 404);
        }

        DB:beginTransaction();
        try{
            $admin = Auth::user()->pegawai;
            if (!$admin) {
                throw new \Exception('Data admin tidak ditemukan.');
            }

            SimpegPegawai::where('id', $dataJabatanStruktural->pegawai_id)
                ->update([
                    'jabatan_struktural_id'=> $dataJabatanStruktural->jabatan_struktural_id
                ]);
            
            $dataJabatanStruktural->update([
                'status_pengajuan' => 'disetujui',
                'tgl_disetujui' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data hubungan kerja berhasil disetujui dan disinkronkan.',
                'data' => $dataJabatanStruktural->fresh('pegawai', 'jabatanStruktural')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyetujui data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Admin: Reject a single data entry.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, $id)
    {
        $dataJabatanStruktural = SimpegDataJabatanStruktural::find($id);

        if (!$dataJabatanStruktural) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Struktural tidak ditemukan'
            ], 404);
        }
        
        // No validation for rejection reason, as no field is expected to store it

        if ($dataJabatanStruktural->reject()) { // Pass no argument
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_reject_jabatan_struktural', $dataJabatanStruktural, $dataJabatanStruktural->getOriginal());
            }
            return response()->json([
                'success' => true,
                'message' => 'Data Jabatan Struktural berhasil ditolak'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Jabatan Struktural tidak dapat ditolak dari status saat ini.'
        ], 409);
    }

    /**
     * Admin: Change status to 'draft' for a single data entry.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toDraft($id)
    {
        $dataJabatanStruktural = SimpegDataJabatanStruktural::find($id);

        if (!$dataJabatanStruktural) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Struktural tidak ditemukan'
            ], 404);
        }

        if ($dataJabatanStruktural->toDraft()) { // Use model's toDraft method
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_to_draft_jabatan_struktural', $dataJabatanStruktural, $dataJabatanStruktural->getOriginal());
            }
            return response()->json([
                'success' => true,
                'message' => 'Status Jabatan Struktural berhasil diubah menjadi draft'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Jabatan Struktural sudah dalam status draft.'
        ], 409);
    }

    /**
     * Admin: Batch delete data.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_jabatan_struktural,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToDelete = SimpegDataJabatanStruktural::whereIn('id', $request->ids)->get();

        if ($dataToDelete->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Struktural yang ditemukan untuk dihapus'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataToDelete as $item) {
                try {
                    if ($item->file_jabatan && Storage::disk('public')->exists($item->file_jabatan)) {
                        Storage::disk('public')->delete($item->file_jabatan);
                    }
                    $oldData = $item->toArray();
                    $item->delete();
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_delete_jabatan_struktural', $item, $oldData);
                    }
                    $deletedCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $item->id,
                        'no_sk' => $item->no_sk,
                        'error' => 'Gagal menghapus: ' . $e->getMessage()
                    ];
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch delete jabatan struktural: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data secara batch: ' . $e->getMessage(),
                'errors' => $errors
            ], 500);
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data Jabatan Struktural",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data Jabatan Struktural",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }

    /**
     * Admin: Batch approve data.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_jabatan_struktural,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataJabatanStruktural::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', [SimpegDataJabatanStruktural::STATUS_DRAFT, SimpegDataJabatanStruktural::STATUS_DIAJUKAN, SimpegDataJabatanStruktural::STATUS_DITOLAK])
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Struktural yang memenuhi syarat untuk disetujui.'
            ], 404);
        }

        $updatedCount = 0;
        $approvedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                if ($item->approve()) { // Use model's approve method
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_approve_jabatan_struktural', $item, $oldData);
                    }
                    $updatedCount++;
                    $approvedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch approve jabatan struktural: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data Jabatan Struktural",
            'updated_count' => $updatedCount,
            'approved_ids' => $approvedIds
        ]);
    }

    /**
     * Admin: Batch reject data.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_jabatan_struktural,id',
            // No rejection reason field is expected
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataJabatanStruktural::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', [SimpegDataJabatanStruktural::STATUS_DRAFT, SimpegDataJabatanStruktural::STATUS_DIAJUKAN, SimpegDataJabatanStruktural::STATUS_DISETUJUI])
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Struktural yang memenuhi syarat untuk ditolak.'
            ], 404);
        }

        $updatedCount = 0;
        $rejectedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                if ($item->reject()) { // Pass no argument
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_reject_jabatan_struktural', $item, $oldData);
                    }
                    $updatedCount++;
                    $rejectedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch reject jabatan struktural: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data Jabatan Struktural",
            'updated_count' => $updatedCount,
            'rejected_ids' => $rejectedIds
        ]);
    }

    /**
     * Admin: Batch change status to 'draft'.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchToDraft(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_jabatan_struktural,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataJabatanStruktural::whereIn('id', $request->ids)
            ->where('status_pengajuan', '!=', SimpegDataJabatanStruktural::STATUS_DRAFT)
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Struktural yang memenuhi syarat untuk diubah menjadi draft.'
            ], 404);
        }

        $updatedCount = 0;
        $draftedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                if ($item->toDraft()) { // Use model's toDraft method
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_to_draft_jabatan_struktural', $item, $oldData);
                    }
                    $updatedCount++;
                    $draftedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch change to draft for jabatan struktural: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah status ke draft secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengubah {$updatedCount} data Jabatan Struktural menjadi draft",
            'updated_count' => $updatedCount,
            'drafted_ids' => $draftedIds
        ]);
    }

    /**
     * Get status statistics for dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatusStatistics(Request $request)
    {
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanStrukturalId = $request->jabatan_struktural_id;
        $pegawaiId = $request->pegawai_id;

        $query = SimpegDataJabatanStruktural::query();

        if ($pegawaiId && $pegawaiId != 'semua') {
            $query->where('pegawai_id', $pegawaiId);
        }

        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $query->whereHas('pegawai', function ($q) use ($unitKerjaId) {
                $q->where('unit_kerja_id', $unitKerjaId);
            });
        }

        if ($jabatanStrukturalId && $jabatanStrukturalId != 'semua') {
            $query->where('jabatan_struktural_id', $jabatanStrukturalId);
        }

        $statistics = $query->selectRaw('status_pengajuan, COUNT(*) as total')
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

    /**
     * Get filter options for the admin interface.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFilterOptions()
    {
        $unitKerjaOptions = SimpegUnitKerja::select('kode_unit as id', 'nama_unit as nama')
            ->orderBy('nama_unit')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Unit Kerja']);

        // FIX: Adjusted COALESCE to use existing columns: jenis_jabatan_struktural, singkatan, kode
        $jabatanStrukturalOptions = SimpegJabatanStruktural::leftJoin('simpeg_jenis_jabatan_struktural', 'simpeg_jabatan_struktural.jenis_jabatan_struktural_id', '=', 'simpeg_jenis_jabatan_struktural.id')
            ->select('simpeg_jabatan_struktural.id',
                     DB::raw("COALESCE(simpeg_jenis_jabatan_struktural.jenis_jabatan_struktural, simpeg_jabatan_struktural.singkatan, simpeg_jabatan_struktural.kode) as nama_jabatan_display")) // Adjusted COALESCE order and columns
            ->orderBy('nama_jabatan_display') // Order by the new alias
            ->get()
            ->map(function($item) { // Map to 'nama' for consistency
                return ['id' => $item->id, 'nama' => $item->nama_jabatan_display];
            })
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jabatan Struktural']);

        $statusPengajuanOptions = [
            ['id' => 'semua', 'nama' => 'Semua'],
            ['id' => 'draft', 'nama' => 'Draft'],
            ['id' => 'diajukan', 'nama' => 'Diajukan'],
            ['id' => 'disetujui', 'nama' => 'Disetujui'],
            ['id' => 'ditolak', 'nama' => 'Ditolak'],
        ];

        // Retrieve existing years for date fields using EXTRACT for PostgreSQL
        $yearsTglMulai = SimpegDataJabatanStruktural::distinct()
                                                   ->select(DB::raw("EXTRACT(YEAR FROM tgl_mulai) as year_value"))
                                                   ->get()
                                                   ->filter(function($item) { return $item->year_value !== null; })
                                                   ->sortByDesc('year_value')
                                                   ->values()
                                                   ->map(function($item) { return ['id' => $item->year_value, 'nama' => $item->year_value]; })
                                                   ->prepend(['id' => 'semua', 'nama' => 'Semua Tgl Mulai'])
                                                   ->toArray();

        $yearsTglSelesai = SimpegDataJabatanStruktural::distinct()
                                               ->select(DB::raw("EXTRACT(YEAR FROM tgl_selesai) as year_value"))
                                               ->get()
                                               ->filter(function($item) { return $item->year_value !== null; })
                                               ->sortByDesc('year_value')
                                               ->values()
                                               ->map(function($item) { return ['id' => $item->year_value, 'nama' => $item->year_value]; })
                                               ->prepend(['id' => 'semua', 'nama' => 'Semua Tgl Selesai'])
                                                   ->toArray();

        $yearsTglDisetujui = SimpegDataJabatanStruktural::whereNotNull('tgl_disetujui')
                                                      ->distinct()
                                                      ->select(DB::raw("EXTRACT(YEAR FROM tgl_disetujui) as year_value"))
                                                      ->get()
                                                      ->filter(function($item) { return $item->year_value !== null; })
                                                      ->sortByDesc('year_value')
                                                      ->values()
                                                      ->map(function($item) { return ['id' => $item->year_value, 'nama' => $item->year_value]; })
                                                      ->prepend(['id' => 'semua', 'nama' => 'Semua Tgl Disetujui'])
                                                      ->toArray();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'unit_kerja' => $unitKerjaOptions,
                'jabatan_struktural' => $jabatanStrukturalOptions,
                'status_pengajuan' => $statusPengajuanOptions,
                'tahun_tgl_mulai' => $yearsTglMulai,
                'tahun_tgl_selesai' => $yearsTglSelesai,
                'tahun_tgl_disetujui' => $yearsTglDisetujui,
                'pegawai_options' => SimpegPegawai::select('id as value', 'nama as label', 'nip')
                                                ->orderBy('nama')
                                                ->get()
                                                ->map(function($item) { return ['value' => $item->value, 'label' => $item->nip . ' - ' . $item->label]; })
                                                ->prepend(['value' => 'semua', 'label' => 'Semua Pegawai']),
            ]
        ]);
    }

    /**
     * Get form options for create/update forms.
     */
    public function getFormOptions()
    {
        // FIX: Adjusted COALESCE to use existing columns: jenis_jabatan_struktural, singkatan, kode
        $jabatanStrukturalOptions = SimpegJabatanStruktural::leftJoin('simpeg_jenis_jabatan_struktural', 'simpeg_jabatan_struktural.jenis_jabatan_struktural_id', '=', 'simpeg_jenis_jabatan_struktural.id')
            ->select('simpeg_jabatan_struktural.id',
                     DB::raw("COALESCE(simpeg_jenis_jabatan_struktural.jenis_jabatan_struktural, simpeg_jabatan_struktural.singkatan, simpeg_jabatan_struktural.kode) as nama_jabatan_display")) // Adjusted COALESCE order and columns
            ->orderBy('nama_jabatan_display') // Order by new alias
            ->get()
            ->map(function($item) { // Map to 'nama' for consistency
                return ['id' => $item->id, 'nama' => $item->nama_jabatan_display];
            });


        $statusPengajuanOptions = [
            ['id' => 'draft', 'nama' => 'Draft'],
            ['id' => 'diajukan', 'nama' => 'Diajukan'],
            ['id' => 'disetujui', 'nama' => 'Disetujui'],
            ['id' => 'ditolak', 'nama' => 'Ditolak'],
        ];

        return [
            'form_options' => [
                'jabatan_struktural' => $jabatanStrukturalOptions,
                'status_pengajuan' => $statusPengajuanOptions,
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|uuid',
                'jabatan_struktural_id' => 'required|uuid',
                'tgl_mulai' => 'required|date',
                'tgl_selesai' => 'nullable|date|after_or_equal:tgl_mulai',
                'no_sk' => 'required|string|max:100',
                'tgl_sk' => 'required|date',
                'pejabat_penetap' => 'nullable|string|max:255',
                'file_jabatan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            ],
            'field_notes' => [
                'pegawai_id' => 'Pilih pegawai yang bersangkutan.',
                'jabatan_struktural_id' => 'Pilih jenis Jabatan Struktural.',
                'tgl_mulai' => 'Tanggal Mulai menjabat.',
                'tgl_selesai' => 'Tanggal Selesai menjabat (kosongkan jika tidak ada batas waktu).',
                'no_sk' => 'Nomor Surat Keputusan Jabatan Struktural.',
                'tgl_sk' => 'Tanggal Surat Keputusan Jabatan Struktural.',
                'pejabat_penetap' => 'Pejabat yang menetapkan SK.',
                'file_jabatan' => 'Unggah file dokumen SK Jabatan Struktural (PDF/gambar).',
                'status_pengajuan' => 'Status pengajuan data.',
            ],
        ];
    }

    /**
     * Helper: Format pegawai info for display in details
     */
    private function formatPegawaiInfo($pegawai)
    {
        if (!$pegawai) {
            return null;
        }

        $jabatanAkademikNama = '-';
        if ($pegawai->dataJabatanAkademik && $pegawai->dataJabatanAkademik->isNotEmpty()) { // Use dataJabatanAkademik relation
            $jabatanAkademik = $pegawai->dataJabatanAkademik->sortByDesc('tmt_jabatan')->first();
            if ($jabatanAkademik && $jabatanAkademik->jabatanAkademik) {
                $jabatanAkademikNama = $jabatanAkademik->jabatanAkademik->jabatan_akademik ?? '-';
            }
        } else if ($pegawai->jabatanAkademik) { // Fallback to direct jabatanAkademik on pegawai table
            $jabatanAkademikNama = $pegawai->jabatanAkademik->jabatan_akademik ?? '-';
        }


        $jabatanFungsionalNama = '-';
        if ($pegawai->dataJabatanFungsional && $pegawai->dataJabatanFungsional->isNotEmpty()) {
            $jabatanFungsional = $pegawai->dataJabatanFungsional->sortByDesc('tmt_jabatan')->first();
            if ($jabatanFungsional && $jabatanFungsional->jabatanFungsional) {
                $jabatanFungsionalNama = $jabatanFungsional->jabatanFungsional->nama_jabatan_fungsional ?? '-';
            }
        }

        $jabatanStrukturalNama = '-';
        if ($pegawai->dataJabatanStruktural && $pegawai->dataJabatanStruktural->isNotEmpty()) {
            $jabatanStruktural = $pegawai->dataJabatanStruktural->sortByDesc('tgl_mulai')->first();
            if ($jabatanStruktural && $jabatanStruktural->jabatanStruktural) {
                // Prioritize jenis_jabatan_struktural if available
                if ($jabatanStruktural->jabatanStruktural->jenisJabatanStruktural) {
                     $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural;
                } else {
                     $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->nama_jabatan ?? $jabatanStruktural->jabatanStruktural->singkatan ?? '-';
                }
            }
        }

        $jenjangPendidikanNama = '-';
        if ($pegawai->dataPendidikanFormal && $pegawai->dataPendidikanFormal->isNotEmpty()) {
            $highestEducation = $pegawai->dataPendidikanFormal->sortByDesc('jenjang_pendidikan_id')->first();
            if ($highestEducation && $highestEducation->jenjangPendidikan) {
                $jenjangPendidikanNama = $highestEducation->jenjangPendidikan->jenjang_pendidikan ?? '-';
            }
        }

        $unitKerjaNama = 'Tidak Ada';
        if ($pegawai->unitKerja) {
            $unitKerjaNama = $pegawai->unitKerja->nama_unit;
        } elseif ($pegawai->unit_kerja_id) {
            $unitKerja = SimpegUnitKerja::where('kode_unit', $pegawai->unit_kerja_id)->first();
            $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $pegawai->unit_kerja_id;
        }

        $hubunganKerjaNama = '-';
        if ($pegawai->dataHubunganKerja && $pegawai->dataHubunganKerja->isNotEmpty()) {
            $latestHubunganKerja = $pegawai->dataHubunganKerja->sortByDesc('tgl_awal')->first();
            if ($latestHubunganKerja && $latestHubunganKerja->hubunganKerja) {
                $hubunganKerjaNama = $latestHubunganKerja->hubunganKerja->nama_hub_kerja ?? '-';
            }
        }

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama_lengkap' => ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : ''),
            'unit_kerja' => $unitKerjaNama,
            'status_aktif_pegawai' => $pegawai->statusAktif ? $pegawai->statusAktif->nama_status_aktif : '-',
            'jab_akademik_pegawai' => $jabatanAkademikNama,
            'jab_fungsional_pegawai' => $jabatanFungsionalNama,
            'jab_struktural_pegawai' => $jabatanStrukturalNama,
            'pendidikan_terakhir' => $jenjangPendidikanNama,
            'hubungan_kerja_pegawai' => $hubunganKerjaNama,
        ];
    }

    /**
     * Helper: Format data jabatan struktural response for display.
     */
    protected function formatDataJabatanStruktural($dataJabatanStruktural, $includeActions = true)
    {
        $status = $dataJabatanStruktural->status_pengajuan ?? SimpegDataJabatanStruktural::STATUS_DRAFT;
        $statusInfo = $this->getStatusInfo($status);

        $pegawai = $dataJabatanStruktural->pegawai;
        $nipPegawai = $pegawai ? $pegawai->nip : '-';
        $namaPegawai = $pegawai ? ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : '') : '-';

        $namaJabatanStruktural = '-';
        if ($dataJabatanStruktural->jabatanStruktural) {
            if ($dataJabatanStruktural->jabatanStruktural->jenisJabatanStruktural) {
                $namaJabatanStruktural = $dataJabatanStruktural->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural;
            } else {
                $namaJabatanStruktural = $dataJabatanStruktural->jabatanStruktural->nama_jabatan ?? $dataJabatanStruktural->jabatanStruktural->singkatan ?? '-';
            }
        }

        $data = [
            'id' => $dataJabatanStruktural->id,
            'pegawai_id' => $dataJabatanStruktural->pegawai_id,
            'nip_pegawai' => $nipPegawai,
            'nama_pegawai' => $namaPegawai,
            'jabatan_struktural_id' => $dataJabatanStruktural->jabatan_struktural_id,
            'nama_jabatan_struktural' => $namaJabatanStruktural,
            'tgl_mulai' => $dataJabatanStruktural->tgl_mulai,
            'tgl_mulai_formatted' => $dataJabatanStruktural->tgl_mulai ? Carbon::parse($dataJabatanStruktural->tgl_mulai)->format('d M Y') : '-',
            'tgl_selesai' => $dataJabatanStruktural->tgl_selesai,
            'tgl_selesai_formatted' => $dataJabatanStruktural->tgl_selesai ? Carbon::parse($dataJabatanStruktural->tgl_selesai)->format('d M Y') : '-',
            'no_sk' => $dataJabatanStruktural->no_sk,
            'tgl_sk' => $dataJabatanStruktural->tgl_sk,
            'tgl_sk_formatted' => $dataJabatanStruktural->tgl_sk ? Carbon::parse($dataJabatanStruktural->tgl_sk)->format('d M Y') : '-',
            'pejabat_penetap' => $dataJabatanStruktural->pejabat_penetap,
            'file_jabatan' => $dataJabatanStruktural->file_jabatan,
            'file_jabatan_link' => $dataJabatanStruktural->file_jabatan ? Storage::url($dataJabatanStruktural->file_jabatan) : null,
            'tgl_input' => $dataJabatanStruktural->tgl_input,
            'tgl_input_formatted' => $dataJabatanStruktural->tgl_input ? Carbon::parse($dataJabatanStruktural->tgl_input)->format('d M Y') : '-',
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'tgl_diajukan' => $dataJabatanStruktural->tgl_diajukan,
            'tgl_diajukan_formatted' => $dataJabatanStruktural->tgl_diajukan ? Carbon::parse($dataJabatanStruktural->tgl_diajukan)->format('d M Y H:i:s') : '-',
            'tgl_disetujui' => $dataJabatanStruktural->tgl_disetujui,
            'tgl_disetujui_formatted' => $dataJabatanStruktural->tgl_disetujui ? Carbon::parse($dataJabatanStruktural->tgl_disetujui)->format('d M Y H:i:s') : '-',
            'tgl_ditolak' => $dataJabatanStruktural->tgl_ditolak,
            'tgl_ditolak_formatted' => $dataJabatanStruktural->tgl_ditolak ? Carbon::parse($dataJabatanStruktural->tgl_ditolak)->format('d M Y H:i:s') : '-',
            'created_at' => $dataJabatanStruktural->created_at,
            'updated_at' => $dataJabatanStruktural->updated_at
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datajabatanstrukturaladm/{$dataJabatanStruktural->id}"),
                'update_url' => url("/api/admin/datajabatanstrukturaladm/{$dataJabatanStruktural->id}"),
                'delete_url' => url("/api/admin/datajabatanstrukturaladm/{$dataJabatanStruktural->id}"),
                'approve_url' => url("/api/admin/datajabatanstrukturaladm/{$dataJabatanStruktural->id}/approve"),
                'reject_url' => url("/api/admin/datajabatanstrukturaladm/{$dataJabatanStruktural->id}/reject"),
                'to_draft_url' => url("/api/admin/datajabatanstrukturaladm/{$dataJabatanStruktural->id}/todraft"),
            ];

            $data['actions'] = [
                'view' => [
                    'url' => $data['aksi']['detail_url'],
                    'method' => 'GET',
                    'label' => 'Lihat Detail',
                    'icon' => 'eye',
                    'color' => 'info'
                ],
                'edit' => [
                    'url' => $data['aksi']['update_url'],
                    'method' => 'PUT',
                    'label' => 'Edit',
                    'icon' => 'edit',
                    'color' => 'warning'
                ],
                'delete' => [
                    'url' => $data['aksi']['delete_url'],
                    'method' => 'DELETE',
                    'label' => 'Hapus',
                    'icon' => 'trash',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data Jabatan Struktural "' . $dataJabatanStruktural->no_sk . '"?'
                ],
            ];

            if (in_array($status, [SimpegDataJabatanStruktural::STATUS_DIAJUKAN, SimpegDataJabatanStruktural::STATUS_DITOLAK, SimpegDataJabatanStruktural::STATUS_DRAFT])) {
                $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data Jabatan Struktural "' . $dataJabatanStruktural->no_sk . '"?'
                ];
            }

            if (in_array($status, [SimpegDataJabatanStruktural::STATUS_DIAJUKAN, SimpegDataJabatanStruktural::STATUS_DISETUJUI, SimpegDataJabatanStruktural::STATUS_DRAFT])) {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Tolak',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data Jabatan Struktural "' . $dataJabatanStruktural->no_sk . '"?',
                ];
            }

            if ($status !== SimpegDataJabatanStruktural::STATUS_DRAFT) {
                $data['actions']['to_draft'] = [
                    'url' => $data['aksi']['to_draft_url'],
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data Jabatan Struktural "' . $dataJabatanStruktural->no_sk . '" menjadi draft?'
                ];
            }
        }

        return $data;
    }

    /**
     * Helper: Get status info and styling.
     */
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

    /**
     * Get form options for create/update forms.
     */


    /**
     * Helper: Format pegawai info for display in details
     */
  

    /**
     * Helper: Format data jabatan struktural response for display.
     */

    /**
     * Helper: Get status info and styling.
     */
  
}