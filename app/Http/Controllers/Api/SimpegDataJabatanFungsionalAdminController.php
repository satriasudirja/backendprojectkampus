<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataJabatanFungsional;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJabatanFungsional; // Model for Jabatan Fungsional list
use App\Models\SimpegJabatanAkademik; // For pegawai info detail
use App\Models\HubunganKerja; // For pegawai info detail
use App\Models\SimpegStatusAktif; // For pegawai info detail

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger; // Assuming this service exists
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class SimpegDataJabatanFungsionalAdminController extends Controller
{
    /**
     * Get all data jabatan fungsional for admin (all pegawai).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search; // For NIP, Nama Pegawai, No SK
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id; // Filter by specific jabatan fungsional
        $statusPengajuan = $request->status_pengajuan ?? 'semua';
        $tmtJabatan = $request->tmt_jabatan;
        $tglSk = $request->tgl_sk;
        $noSk = $request->no_sk;
        $tglDisetujui = $request->tgl_disetujui;

        // Eager load necessary relations
        $query = SimpegDataJabatanFungsional::with([
            'pegawai' => function ($q) {
                $q->select('id', 'nip', 'nama', 'gelar_depan', 'gelar_belakang', 'unit_kerja_id')
                    ->with('unitKerja:kode_unit,nama_unit');
            },
            'jabatanFungsional:id,nama_jabatan_fungsional'
        ]);

        // Apply filters
        $query->filterByNipNamaPegawai($search)
              ->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanFungsionalId($jabatanFungsionalId)
              ->byStatus($statusPengajuan)
              ->filterByTmtJabatan($tmtJabatan)
              ->filterByTanggalSk($tglSk) // Using filterByTanggalSk
              ->filterByNoSk($noSk)
              ->filterByTglDisetujui($tglDisetujui);

        // Order results
        $dataJabatanFungsional = $query->latest('tgl_input')
                                     ->paginate($perPage);

        // Transform collection for frontend
        $dataJabatanFungsional->getCollection()->transform(function ($item) {
            return $this->formatDataJabatanFungsional($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataJabatanFungsional,
            'empty_data' => $dataJabatanFungsional->isEmpty(),
            'filters' => $this->getFilterOptions(),
            'table_columns' => [
                ['field' => 'nip_pegawai', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'pegawai.nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'pegawai.nama'],
                ['field' => 'tmt_jabatan_formatted', 'label' => 'TMT Jabatan', 'sortable' => true, 'sortable_field' => 'tmt_jabatan'],
                ['field' => 'nama_jabatan_fungsional', 'label' => 'Nama Jabatan', 'sortable' => true, 'sortable_field' => 'jabatan_fungsional_id'],
                ['field' => 'tanggal_sk_formatted', 'label' => 'Tgl SK', 'sortable' => true, 'sortable_field' => 'tanggal_sk'],
                ['field' => 'no_sk', 'label' => 'No SK', 'sortable' => true, 'sortable_field' => 'no_sk'],
                ['field' => 'file_sk_jabatan_link', 'label' => 'File Jabatan', 'sortable' => false],
                ['field' => 'tgl_disetujui_formatted', 'label' => 'Tgl Disetujui', 'sortable' => true, 'sortable_field' => 'tgl_disetujui'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/datajabatanfungsionaladm/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/datajabatanfungsionaladm/batch/reject"),
                    'method' => 'PATCH',
                    'label' => 'Tolak Terpilih',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menolak data terpilih?',
                ],
                'to_draft' => [
                    'url' => url("/api/admin/datajabatanfungsionaladm/batch/todraft"),
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft Terpilih',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data terpilih menjadi draft?'
                ],
                'delete' => [
                    'url' => url("/api/admin/datajabatanfungsionaladm/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data terpilih?',
                ]
            ],
            'pagination' => [
                'current_page' => $dataJabatanFungsional->currentPage(),
                'per_page' => $dataJabatanFungsional->perPage(),
                'total' => $dataJabatanFungsional->total(),
                'last_page' => $dataJabatanFungsional->lastPage(),
                'from' => $dataJabatanFungsional->firstItem(),
                'to' => $dataJabatanFungsional->lastItem()
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
        $dataJabatanFungsional = SimpegDataJabatanFungsional::with([
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
                    'dataJabatanAkademik' => function($query) { // Load current academic position for detail
                        $query->with('jabatanAkademik')->latest('tmt_jabatan')->limit(1);
                    }
                ]);
            },
            'jabatanFungsional'
        ])->find($id);

        if (!$dataJabatanFungsional) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Fungsional tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai_info_detail' => $this->formatPegawaiInfo($dataJabatanFungsional->pegawai),
            'data' => $this->formatDataJabatanFungsional($dataJabatanFungsional, false),
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
            'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id',
            'jabatan_fungsional_id' => 'required|integer|exists:simpeg_jabatan_fungsional,id',
            'tmt_jabatan' => 'required|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'no_sk' => 'required|string|max:100',
            'tanggal_sk' => 'required|date',
            'file_sk_jabatan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
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
        $data['status_pengajuan'] = $request->input('status_pengajuan', SimpegDataJabatanFungsional::STATUS_DISETUJUI);

        // Handle timestamps based on status
        if ($data['status_pengajuan'] === SimpegDataJabatanFungsional::STATUS_DISETUJUI) {
            $data['tgl_disetujui'] = now();
            $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? now();
        } elseif ($data['status_pengajuan'] === SimpegDataJabatanFungsional::STATUS_DIAJUKAN) {
            $data['tgl_diajukan'] = now();
            $data['tgl_disetujui'] = null;
            $data['tgl_ditolak'] = null;
        } elseif ($data['status_pengajuan'] === SimpegDataJabatanFungsional::STATUS_DITOLAK) {
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
            if ($request->hasFile('file_sk_jabatan')) {
                $file = $request->file('file_sk_jabatan');
                $fileName = 'jabatan_fungsional_' . $data['pegawai_id'] . '_' . Carbon::now()->timestamp . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('jabatan_fungsional_files', $fileName, 'public');
                $data['file_sk_jabatan'] = $filePath;
            }

            $dataJabatanFungsional = SimpegDataJabatanFungsional::create($data);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_create_jabatan_fungsional', $dataJabatanFungsional, $dataJabatanFungsional->toArray());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data Jabatan Fungsional berhasil ditambahkan oleh admin',
                'data' => $this->formatDataJabatanFungsional($dataJabatanFungsional->load(['pegawai.unitKerja', 'jabatanFungsional']))
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
        $dataJabatanFungsional = SimpegDataJabatanFungsional::find($id);

        if (!$dataJabatanFungsional) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Fungsional tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|integer|exists:simpeg_pegawai,id',
            'jabatan_fungsional_id' => 'sometimes|integer|exists:simpeg_jabatan_fungsional,id',
            'tmt_jabatan' => 'sometimes|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'no_sk' => 'sometimes|string|max:100',
            'tanggal_sk' => 'sometimes|date',
            'file_sk_jabatan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            'clear_file_sk_jabatan' => 'nullable|boolean', // Added for explicit file clearing
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
            $oldData = $dataJabatanFungsional->getOriginal();
            $data = $validator->validated();

            // Handle file_sk_jabatan upload
            if ($request->hasFile('file_sk_jabatan')) {
                if ($dataJabatanFungsional->file_sk_jabatan && Storage::disk('public')->exists($dataJabatanFungsional->file_sk_jabatan)) {
                    Storage::disk('public')->delete($dataJabatanFungsional->file_sk_jabatan);
                }
                $file = $request->file('file_sk_jabatan');
                $fileName = 'jabatan_fungsional_' . ($data['pegawai_id'] ?? $dataJabatanFungsional->pegawai_id) . '_' . Carbon::now()->timestamp . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('jabatan_fungsional_files', $fileName, 'public');
                $data['file_sk_jabatan'] = $filePath;
            } elseif ($request->input('clear_file_sk_jabatan')) {
                if ($dataJabatanFungsional->file_sk_jabatan && Storage::disk('public')->exists($dataJabatanFungsional->file_sk_jabatan)) {
                    Storage::disk('public')->delete($dataJabatanFungsional->file_sk_jabatan);
                }
                $data['file_sk_jabatan'] = null;
            } else {
                $data['file_sk_jabatan'] = $dataJabatanFungsional->file_sk_jabatan;
            }
            unset($data['clear_file_sk_jabatan']);

            // Handle status_pengajuan and timestamps related
            if (isset($data['status_pengajuan']) && $data['status_pengajuan'] !== $dataJabatanFungsional->status_pengajuan) {
                switch ($data['status_pengajuan']) {
                    case SimpegDataJabatanFungsional::STATUS_DIAJUKAN:
                        $data['tgl_diajukan'] = now();
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                    case SimpegDataJabatanFungsional::STATUS_DISETUJUI:
                        $data['tgl_disetujui'] = now();
                        $data['tgl_diajukan'] = $dataJabatanFungsional->tgl_diajukan ?? now();
                        $data['tgl_ditolak'] = null;
                        break;
                    case SimpegDataJabatanFungsional::STATUS_DITOLAK:
                        $data['tgl_ditolak'] = now();
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        break;
                    case SimpegDataJabatanFungsional::STATUS_DRAFT:
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                }
            } else {
                $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? $dataJabatanFungsional->tgl_diajukan;
                $data['tgl_disetujui'] = $data['tgl_disetujui'] ?? $dataJabatanFungsional->tgl_disetujui;
                $data['tgl_ditolak'] = $data['tgl_ditolak'] ?? $dataJabatanFungsional->tgl_ditolak;
            }

            $dataJabatanFungsional->update($data);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_update_jabatan_fungsional', $dataJabatanFungsional, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatDataJabatanFungsional($dataJabatanFungsional->load(['pegawai.unitKerja', 'jabatanFungsional'])),
                'message' => 'Data Jabatan Fungsional berhasil diperbarui oleh admin'
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
        $dataJabatanFungsional = SimpegDataJabatanFungsional::find($id);

        if (!$dataJabatanFungsional) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Fungsional tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete associated file
            if ($dataJabatanFungsional->file_sk_jabatan && Storage::disk('public')->exists($dataJabatanFungsional->file_sk_jabatan)) {
                Storage::disk('public')->delete($dataJabatanFungsional->file_sk_jabatan);
            }

            $oldData = $dataJabatanFungsional->toArray();
            $dataJabatanFungsional->delete();

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_delete_jabatan_fungsional', $dataJabatanFungsional, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data Jabatan Fungsional berhasil dihapus'
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
        $dataJabatanFungsional = SimpegDataJabatanFungsional::find($id);

        if (!$dataJabatanFungsional) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Fungsional tidak ditemukan'
            ], 404);
        }

        if ($dataJabatanFungsional->approve()) { // Use model's approve method
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_approve_jabatan_fungsional', $dataJabatanFungsional, $dataJabatanFungsional->getOriginal());
            }
            return response()->json([
                'success' => true,
                'message' => 'Data Jabatan Fungsional berhasil disetujui'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Jabatan Fungsional tidak dapat disetujui dari status saat ini.'
        ], 409);
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
        $dataJabatanFungsional = SimpegDataJabatanFungsional::find($id);

        if (!$dataJabatanFungsional) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Fungsional tidak ditemukan'
            ], 404);
        }

        // $validator = Validator::make($request->all(), [
        //     'alasan_penolakan' => 'nullable|string|max:500', // Uncomment if you have this field
        // ]);
        // if ($validator->fails()) {
        //     return response()->json([
        //         'success' => false,
        //         'errors' => $validator->errors()
        //     ], 422);
        // }

        if ($dataJabatanFungsional->reject(/* $request->alasan_penolakan */)) { // Pass null or no argument as $reason is removed
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_reject_jabatan_fungsional', $dataJabatanFungsional, $dataJabatanFungsional->getOriginal());
            }
            return response()->json([
                'success' => true,
                'message' => 'Data Jabatan Fungsional berhasil ditolak'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Jabatan Fungsional tidak dapat ditolak dari status saat ini.'
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
        $dataJabatanFungsional = SimpegDataJabatanFungsional::find($id);

        if (!$dataJabatanFungsional) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Fungsional tidak ditemukan'
            ], 404);
        }

        if ($dataJabatanFungsional->toDraft()) { // Use model's toDraft method
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_to_draft_jabatan_fungsional', $dataJabatanFungsional, $dataJabatanFungsional->getOriginal());
            }
            return response()->json([
                'success' => true,
                'message' => 'Status Jabatan Fungsional berhasil diubah menjadi draft'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Jabatan Fungsional sudah dalam status draft.'
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
            'ids.*' => 'required|integer|exists:simpeg_data_jabatan_fungsional,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToDelete = SimpegDataJabatanFungsional::whereIn('id', $request->ids)->get();

        if ($dataToDelete->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Fungsional yang ditemukan untuk dihapus'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataToDelete as $item) {
                try {
                    if ($item->file_sk_jabatan && Storage::disk('public')->exists($item->file_sk_jabatan)) {
                        Storage::disk('public')->delete($item->file_sk_jabatan);
                    }
                    $oldData = $item->toArray();
                    $item->delete();
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_delete_jabatan_fungsional', $item, $oldData);
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
            \Log::error('Error during batch delete jabatan fungsional: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data secara batch: ' . $e->getMessage(),
                'errors' => $errors
            ], 500);
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data Jabatan Fungsional",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data Jabatan Fungsional",
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
            'ids.*' => 'required|integer|exists:simpeg_data_jabatan_fungsional,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataJabatanFungsional::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', [SimpegDataJabatanFungsional::STATUS_DRAFT, SimpegDataJabatanFungsional::STATUS_DIAJUKAN, SimpegDataJabatanFungsional::STATUS_DITOLAK])
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Fungsional yang memenuhi syarat untuk disetujui.'
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
                        ActivityLogger::log('admin_batch_approve_jabatan_fungsional', $item, $oldData);
                    }
                    $updatedCount++;
                    $approvedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch approve jabatan fungsional: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data Jabatan Fungsional",
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
            'ids.*' => 'required|integer|exists:simpeg_data_jabatan_fungsional,id',
            // 'alasan_penolakan' => 'nullable|string|max:500', // Uncomment if you have this field
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataJabatanFungsional::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', [SimpegDataJabatanFungsional::STATUS_DRAFT, SimpegDataJabatanFungsional::STATUS_DIAJUKAN, SimpegDataJabatanFungsional::STATUS_DISETUJUI])
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Fungsional yang memenuhi syarat untuk ditolak.'
            ], 404);
        }

        $updatedCount = 0;
        $rejectedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                if ($item->reject(/* $request->alasan_penolakan */)) { // Pass null or no argument
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_reject_jabatan_fungsional', $item, $oldData);
                    }
                    $updatedCount++;
                    $rejectedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch reject jabatan fungsional: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data Jabatan Fungsional",
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
            'ids.*' => 'required|integer|exists:simpeg_data_jabatan_fungsional,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataJabatanFungsional::whereIn('id', $request->ids)
            ->where('status_pengajuan', '!=', SimpegDataJabatanFungsional::STATUS_DRAFT)
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Fungsional yang memenuhi syarat untuk diubah menjadi draft.'
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
                        ActivityLogger::log('admin_batch_to_draft_jabatan_fungsional', $item, $oldData);
                    }
                    $updatedCount++;
                    $draftedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch change to draft for jabatan fungsional: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah status ke draft secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengubah {$updatedCount} data Jabatan Fungsional menjadi draft",
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
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $pegawaiId = $request->pegawai_id;

        $query = SimpegDataJabatanFungsional::query();

        if ($pegawaiId && $pegawaiId != 'semua') {
            $query->where('pegawai_id', $pegawaiId);
        }

        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $query->whereHas('pegawai', function ($q) use ($unitKerjaId) {
                $q->where('unit_kerja_id', $unitKerjaId);
            });
        }

        if ($jabatanFungsionalId && $jabatanFungsionalId != 'semua') {
            $query->where('jabatan_fungsional_id', $jabatanFungsionalId);
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

        $jabatanFungsionalOptions = SimpegJabatanFungsional::select('id', 'nama_jabatan_fungsional as nama')
            ->orderBy('nama_jabatan_fungsional')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jabatan Fungsional']);

        $statusPengajuanOptions = [
            ['id' => 'semua', 'nama' => 'Semua'],
            ['id' => 'draft', 'nama' => 'Draft'],
            ['id' => 'diajukan', 'nama' => 'Diajukan'],
            ['id' => 'disetujui', 'nama' => 'Disetujui'],
            ['id' => 'ditolak', 'nama' => 'Ditolak'],
        ];

        // Retrieve existing years for date fields using EXTRACT for PostgreSQL
        $yearsTmtJabatan = SimpegDataJabatanFungsional::distinct()
                                                   ->select(DB::raw("EXTRACT(YEAR FROM tmt_jabatan) as year_value")) // CHANGE: Use select and alias
                                                   ->get() // CHANGE: Get the collection of objects
                                                   ->filter(function($item) { return $item->year_value !== null; }) // Filter nulls if any
                                                   ->sortByDesc('year_value') // Sort by the aliased column
                                                   ->values()
                                                   ->map(function($item) { return ['id' => $item->year_value, 'nama' => $item->year_value]; })
                                                   ->prepend(['id' => 'semua', 'nama' => 'Semua TMT Jabatan'])
                                                   ->toArray();

        $yearsTanggalSk = SimpegDataJabatanFungsional::distinct()
                                               ->select(DB::raw("EXTRACT(YEAR FROM tanggal_sk) as year_value")) // CHANGE: Use select and alias
                                               ->get() // CHANGE: Get the collection of objects
                                               ->filter(function($item) { return $item->year_value !== null; }) // Filter nulls if any
                                               ->sortByDesc('year_value') // Sort by the aliased column
                                               ->values()
                                               ->map(function($item) { return ['id' => $item->year_value, 'nama' => $item->year_value]; })
                                               ->prepend(['id' => 'semua', 'nama' => 'Semua Tanggal SK'])
                                               ->toArray();

        $yearsTglDisetujui = SimpegDataJabatanFungsional::whereNotNull('tgl_disetujui')
                                                      ->distinct()
                                                      ->select(DB::raw("EXTRACT(YEAR FROM tgl_disetujui) as year_value")) // CHANGE: Use select and alias
                                                      ->get() // CHANGE: Get the collection of objects
                                                      ->filter(function($item) { return $item->year_value !== null; }) // Filter nulls if any
                                                      ->sortByDesc('year_value') // Sort by the aliased column
                                                      ->values()
                                                      ->map(function($item) { return ['id' => $item->year_value, 'nama' => $item->year_value]; })
                                                      ->prepend(['id' => 'semua', 'nama' => 'Semua Tgl Disetujui'])
                                                      ->toArray();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'unit_kerja' => $unitKerjaOptions,
                'jabatan_fungsional' => $jabatanFungsionalOptions,
                'status_pengajuan' => $statusPengajuanOptions,
                'tahun_tmt_jabatan' => $yearsTmtJabatan,
                'tahun_tanggal_sk' => $yearsTanggalSk,
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
        $jabatanFungsionalOptions = SimpegJabatanFungsional::select('id', 'nama_jabatan_fungsional as nama')
            ->orderBy('nama_jabatan_fungsional')
            ->get();

        $statusPengajuanOptions = [
            ['id' => 'draft', 'nama' => 'Draft'],
            ['id' => 'diajukan', 'nama' => 'Diajukan'],
            ['id' => 'disetujui', 'nama' => 'Disetujui'],
            ['id' => 'ditolak', 'nama' => 'Ditolak'],
        ];

        return [
            'form_options' => [
                'jabatan_fungsional' => $jabatanFungsionalOptions,
                'status_pengajuan' => $statusPengajuanOptions,
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|integer',
                'jabatan_fungsional_id' => 'required|integer',
                'tmt_jabatan' => 'required|date',
                'pejabat_penetap' => 'nullable|string|max:255',
                'no_sk' => 'required|string|max:100',
                'tanggal_sk' => 'required|date',
                'file_sk_jabatan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            ],
            'field_notes' => [
                'pegawai_id' => 'Pilih pegawai yang bersangkutan.',
                'jabatan_fungsional_id' => 'Pilih jenis Jabatan Fungsional.',
                'tmt_jabatan' => 'Tanggal Mulai Tugas Jabatan Fungsional.',
                'pejabat_penetap' => 'Pejabat yang menetapkan SK.',
                'no_sk' => 'Nomor Surat Keputusan Jabatan Fungsional.',
                'tanggal_sk' => 'Tanggal Surat Keputusan Jabatan Fungsional.',
                'file_sk_jabatan' => 'Unggah file dokumen SK Jabatan Fungsional (PDF/gambar).',
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
        if ($pegawai->jabatanAkademik) {
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
            if ($jabatanStruktural && $jabatanStruktural->jabatanStruktural && $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural;
            } elseif ($jabatanStruktural && isset($jabatanStruktural->jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->nama_jabatan;
            } elseif ($jabatanStruktural && isset($jabatanStruktural->jabatanStruktural->singkatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->singkatan;
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
     * Helper: Format data jabatan fungsional response for display.
     */
    protected function formatDataJabatanFungsional($dataJabatanFungsional, $includeActions = true)
    {
        $status = $dataJabatanFungsional->status_pengajuan ?? SimpegDataJabatanFungsional::STATUS_DRAFT;
        $statusInfo = $this->getStatusInfo($status);

        $pegawai = $dataJabatanFungsional->pegawai;
        $nipPegawai = $pegawai ? $pegawai->nip : '-';
        $namaPegawai = $pegawai ? ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : '') : '-';

        $data = [
            'id' => $dataJabatanFungsional->id,
            'pegawai_id' => $dataJabatanFungsional->pegawai_id,
            'nip_pegawai' => $nipPegawai,
            'nama_pegawai' => $namaPegawai,
            'jabatan_fungsional_id' => $dataJabatanFungsional->jabatan_fungsional_id,
            'nama_jabatan_fungsional' => $dataJabatanFungsional->jabatanFungsional ? $dataJabatanFungsional->jabatanFungsional->nama_jabatan_fungsional : '-',
            'tmt_jabatan' => $dataJabatanFungsional->tmt_jabatan,
            'tmt_jabatan_formatted' => $dataJabatanFungsional->tmt_jabatan ? Carbon::parse($dataJabatanFungsional->tmt_jabatan)->format('d M Y') : '-',
            'pejabat_penetap' => $dataJabatanFungsional->pejabat_penetap,
            'no_sk' => $dataJabatanFungsional->no_sk,
            'tanggal_sk' => $dataJabatanFungsional->tanggal_sk,
            'tanggal_sk_formatted' => $dataJabatanFungsional->tanggal_sk ? Carbon::parse($dataJabatanFungsional->tanggal_sk)->format('d M Y') : '-',
            'file_sk_jabatan' => $dataJabatanFungsional->file_sk_jabatan,
            'file_sk_jabatan_link' => $dataJabatanFungsional->file_sk_jabatan ? Storage::url($dataJabatanFungsional->file_sk_jabatan) : null,
            'tgl_input' => $dataJabatanFungsional->tgl_input,
            'tgl_input_formatted' => $dataJabatanFungsional->tgl_input ? Carbon::parse($dataJabatanFungsional->tgl_input)->format('d M Y') : '-',
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'tgl_diajukan' => $dataJabatanFungsional->tgl_diajukan,
            'tgl_diajukan_formatted' => $dataJabatanFungsional->tgl_diajukan ? Carbon::parse($dataJabatanFungsional->tgl_diajukan)->format('d M Y H:i:s') : '-',
            'tgl_disetujui' => $dataJabatanFungsional->tgl_disetujui,
            'tgl_disetujui_formatted' => $dataJabatanFungsional->tgl_disetujui ? Carbon::parse($dataJabatanFungsional->tgl_disetujui)->format('d M Y H:i:s') : '-',
            'tgl_ditolak' => $dataJabatanFungsional->tgl_ditolak,
            'tgl_ditolak_formatted' => $dataJabatanFungsional->tgl_ditolak ? Carbon::parse($dataJabatanFungsional->tgl_ditolak)->format('d M Y H:i:s') : '-',
            // 'alasan_penolakan' => $dataJabatanFungsional->alasan_penolakan, // Uncomment if you have this field
            'created_at' => $dataJabatanFungsional->created_at,
            'updated_at' => $dataJabatanFungsional->updated_at
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datajabatanfungsionaladm/{$dataJabatanFungsional->id}"),
                'update_url' => url("/api/admin/datajabatanfungsionaladm/{$dataJabatanFungsional->id}"),
                'delete_url' => url("/api/admin/datajabatanfungsionaladm/{$dataJabatanFungsional->id}"),
                'approve_url' => url("/api/admin/datajabatanfungsionaladm/{$dataJabatanFungsional->id}/approve"),
                'reject_url' => url("/api/admin/datajabatanfungsionaladm/{$dataJabatanFungsional->id}/reject"),
                'to_draft_url' => url("/api/admin/datajabatanfungsionaladm/{$dataJabatanFungsional->id}/todraft"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data Jabatan Fungsional "' . $dataJabatanFungsional->no_sk . '"?'
                ],
            ];

            if (in_array($status, [SimpegDataJabatanFungsional::STATUS_DIAJUKAN, SimpegDataJabatanFungsional::STATUS_DITOLAK, SimpegDataJabatanFungsional::STATUS_DRAFT])) {
                $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data Jabatan Fungsional "' . $dataJabatanFungsional->no_sk . '"?'
                ];
            }

            if (in_array($status, [SimpegDataJabatanFungsional::STATUS_DIAJUKAN, SimpegDataJabatanFungsional::STATUS_DISETUJUI, SimpegDataJabatanFungsional::STATUS_DRAFT])) {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Tolak',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data Jabatan Fungsional "' . $dataJabatanFungsional->no_sk . '"?',
                    // 'needs_input' => true, // Uncomment if you want input for rejection reason
                    // 'input_placeholder' => 'Alasan penolakan (opsional)' // Uncomment if you want input for rejection reason
                ];
            }

            if ($status !== SimpegDataJabatanFungsional::STATUS_DRAFT) {
                $data['actions']['to_draft'] = [
                    'url' => $data['aksi']['to_draft_url'],
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data Jabatan Fungsional "' . $dataJabatanFungsional->no_sk . '" menjadi draft?'
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
}