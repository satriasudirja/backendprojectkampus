<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataJabatanAkademik;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJabatanAkademik; // Correct model for jabatan akademik list
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger; // Assuming this service exists
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class SimpegDataJabatanAkademikAdminController extends Controller
{
    /**
     * Get all data jabatan akademik for admin (all pegawai).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search; // For NIP, Nama Pegawai, No SK
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanAkademikId = $request->jabatan_akademik_id; // Filter by specific jabatan akademik
        $statusPengajuan = $request->status_pengajuan ?? 'semua';
        $tmtJabatan = $request->tmt_jabatan;
        $tglSk = $request->tgl_sk;
        $noSk = $request->no_sk;
        $tglDisetujui = $request->tgl_disetujui;

        // Eager load necessary relations
        $query = SimpegDataJabatanAkademik::with([
            'pegawai' => function ($q) {
                $q->select('id', 'nip', 'nama', 'gelar_depan', 'gelar_belakang', 'unit_kerja_id')
                    ->with('unitKerja:kode_unit,nama_unit');
            },
            'jabatanAkademik:id,jabatan_akademik'
        ]);

        // Apply filters
        $query->filterByNipNamaPegawai($search)
              ->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanAkademikId($jabatanAkademikId)
              ->byStatus($statusPengajuan)
              ->filterByTmtJabatan($tmtJabatan)
              ->filterByTglSk($tglSk)
              ->filterByNoSk($noSk)
              ->filterByTglDisetujui($tglDisetujui);

        // Order results
        $dataJabatanAkademik = $query->latest('tgl_input')
                                     ->paginate($perPage);

        // Transform collection for frontend
        $dataJabatanAkademik->getCollection()->transform(function ($item) {
            return $this->formatDataJabatanAkademik($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataJabatanAkademik,
            'empty_data' => $dataJabatanAkademik->isEmpty(),
            'filters' => $this->getFilterOptions(),
            'table_columns' => [
                ['field' => 'nip_pegawai', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'pegawai.nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'pegawai.nama'],
                ['field' => 'tmt_jabatan_formatted', 'label' => 'TMT Jabatan', 'sortable' => true, 'sortable_field' => 'tmt_jabatan'],
                ['field' => 'nama_jabatan_akademik', 'label' => 'Nama Jabatan', 'sortable' => true, 'sortable_field' => 'jabatan_akademik_id'],
                ['field' => 'tgl_sk_formatted', 'label' => 'Tgl SK', 'sortable' => true, 'sortable_field' => 'tgl_sk'],
                ['field' => 'no_sk', 'label' => 'No SK', 'sortable' => true, 'sortable_field' => 'no_sk'],
                ['field' => 'file_jabatan_link', 'label' => 'File Jabatan', 'sortable' => false],
                ['field' => 'tgl_disetujui_formatted', 'label' => 'Tgl Disetujui', 'sortable' => true, 'sortable_field' => 'tgl_disetujui'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/datajabatanakademikadm/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/datajabatanakademikadm/batch/reject"),
                    'method' => 'PATCH',
                    'label' => 'Tolak Terpilih',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menolak data terpilih?',
                    // 'needs_input' => true, // Dihapus
                    // 'input_placeholder' => 'Alasan penolakan (opsional)' // Dihapus
                ],
                'to_draft' => [
                    'url' => url("/api/admin/datajabatanakademikadm/batch/todraft"),
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft Terpilih',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data terpilih menjadi draft?'
                ],
                'delete' => [
                    'url' => url("/api/admin/datajabatanakademikadm/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data terpilih?',
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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $dataJabatanAkademik = SimpegDataJabatanAkademik::with([
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
                    }
                ]);
            },
            'jabatanAkademik'
        ])->find($id);

        if (!$dataJabatanAkademik) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Akademik tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai_info_detail' => $this->formatPegawaiInfo($dataJabatanAkademik->pegawai),
            'data' => $this->formatDataJabatanAkademik($dataJabatanAkademik, false),
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
            'jabatan_akademik_id' => 'required|uuid|exists:simpeg_jabatan_akademik,id',
            'tmt_jabatan' => 'required|date',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'file_jabatan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            // 'alasan_penolakan' => 'nullable|string|max:1000', // Dihapus
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
        $data['status_pengajuan'] = $request->input('status_pengajuan', SimpegDataJabatanAkademik::STATUS_DISETUJUI); // Admin can set directly

        // Handle timestamps based on status
        if ($data['status_pengajuan'] === SimpegDataJabatanAkademik::STATUS_DISETUJUI) {
            $data['tgl_disetujui'] = now();
            $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? now();
        } elseif ($data['status_pengajuan'] === SimpegDataJabatanAkademik::STATUS_DIAJUKAN) {
            $data['tgl_diajukan'] = now();
            $data['tgl_disetujui'] = null;
            $data['tgl_ditolak'] = null;
        } elseif ($data['status_pengajuan'] === SimpegDataJabatanAkademik::STATUS_DITOLAK) {
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
                $fileName = 'jabatan_akademik_' . $data['pegawai_id'] . '_' . time() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('jabatan_akademik_files', $fileName, 'public');
                $data['file_jabatan'] = $filePath;
            }

            $dataJabatanAkademik = SimpegDataJabatanAkademik::create($data);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_create_jabatan_akademik', $dataJabatanAkademik, $dataJabatanAkademik->toArray());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data Jabatan Akademik berhasil ditambahkan oleh admin',
                'data' => $this->formatDataJabatanAkademik($dataJabatanAkademik->load(['pegawai.unitKerja', 'jabatanAkademik']))
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
        $dataJabatanAkademik = SimpegDataJabatanAkademik::find($id);

        if (!$dataJabatanAkademik) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Akademik tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|uuid|exists:simpeg_pegawai,id',
            'jabatan_akademik_id' => 'sometimes|uuid|exists:simpeg_jabatan_akademik,id',
            'tmt_jabatan' => 'sometimes|date',
            'no_sk' => 'sometimes|string|max:100',
            'tgl_sk' => 'sometimes|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'file_jabatan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            // 'alasan_penolakan' => 'nullable|string|max:1000', // Dihapus
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
            $oldData = $dataJabatanAkademik->getOriginal();
            $data = $validator->validated();

            // Handle file_jabatan upload
            if ($request->hasFile('file_jabatan')) {
                if ($dataJabatanAkademik->file_jabatan && Storage::disk('public')->exists($dataJabatanAkademik->file_jabatan)) {
                    Storage::disk('public')->delete($dataJabatanAkademik->file_jabatan);
                }
                $file = $request->file('file_jabatan');
                $fileName = 'jabatan_akademik_' . ($data['pegawai_id'] ?? $dataJabatanAkademik->pegawai_id) . '_' . Carbon::now()->timestamp . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('jabatan_akademik_files', $fileName, 'public');
                $data['file_jabatan'] = $filePath;
            } elseif ($request->input('clear_file_jabatan')) {
                if ($dataJabatanAkademik->file_jabatan && Storage::disk('public')->exists($dataJabatanAkademik->file_jabatan)) {
                    Storage::disk('public')->delete($dataJabatanAkademik->file_jabatan);
                }
                $data['file_jabatan'] = null;
            } else {
                $data['file_jabatan'] = $dataJabatanAkademik->file_jabatan;
            }
            unset($data['clear_file_jabatan']);

            // Handle status_pengajuan and timestamps related
            if (isset($data['status_pengajuan']) && $data['status_pengajuan'] !== $dataJabatanAkademik->status_pengajuan) {
                switch ($data['status_pengajuan']) {
                    case SimpegDataJabatanAkademik::STATUS_DIAJUKAN:
                        $data['tgl_diajukan'] = now();
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                    case SimpegDataJabatanAkademik::STATUS_DISETUJUI:
                        $data['tgl_disetujui'] = now();
                        $data['tgl_diajukan'] = $dataJabatanAkademik->tgl_diajukan ?? now();
                        $data['tgl_ditolak'] = null;
                        break;
                    case SimpegDataJabatanAkademik::STATUS_DITOLAK:
                        $data['tgl_ditolak'] = now();
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        break;
                    case SimpegDataJabatanAkademik::STATUS_DRAFT:
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                }
            } else {
                $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? $dataJabatanAkademik->tgl_diajukan;
                $data['tgl_disetujui'] = $data['tgl_disetujui'] ?? $dataJabatanAkademik->tgl_disetujui;
                $data['tgl_ditolak'] = $data['tgl_ditolak'] ?? $dataJabatanAkademik->tgl_ditolak;
            }

            $dataJabatanAkademik->update($data);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_update_jabatan_akademik', $dataJabatanAkademik, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatDataJabatanAkademik($dataJabatanAkademik->load(['pegawai.unitKerja', 'jabatanAkademik'])),
                'message' => 'Data Jabatan Akademik berhasil diperbarui oleh admin'
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
        $dataJabatanAkademik = SimpegDataJabatanAkademik::find($id);

        if (!$dataJabatanAkademik) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Akademik tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete associated file
            if ($dataJabatanAkademik->file_jabatan && Storage::disk('public')->exists($dataJabatanAkademik->file_jabatan)) {
                Storage::disk('public')->delete($dataJabatanAkademik->file_jabatan);
            }

            $oldData = $dataJabatanAkademik->toArray();
            $dataJabatanAkademik->delete();

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_delete_jabatan_akademik', $dataJabatanAkademik, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data Jabatan Akademik berhasil dihapus'
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
        $dataJabatanAkademik = SimpegDataJabatanAkademik::find($id);

        if (!$dataJabatanAkademik) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Akademik tidak ditemukan'
            ], 404);
        }

        if ($dataJabatanAkademik->approve()) { // Use model's approve method
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_approve_jabatan_akademik', $dataJabatanAkademik, $dataJabatanAkademik->getOriginal());
            }
            return response()->json([
                'success' => true,
                'message' => 'Data Jabatan Akademik berhasil disetujui'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Jabatan Akademik tidak dapat disetujui dari status saat ini.'
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
        $dataJabatanAkademik = SimpegDataJabatanAkademik::find($id);

        if (!$dataJabatanAkademik) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Akademik tidak ditemukan'
            ], 404);
        }

        // Removed validator for alasan_penolakan as it's no longer used
        // $validator = Validator::make($request->all(), [
        //     'alasan_penolakan' => 'nullable|string|max:500',
        // ]);
        // if ($validator->fails()) {
        //     return response()->json([
        //         'success' => false,
        //         'errors' => $validator->errors()
        //     ], 422);
        // }

        if ($dataJabatanAkademik->reject()) { // Pass null or no argument as $reason is removed
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_reject_jabatan_akademik', $dataJabatanAkademik, $dataJabatanAkademik->getOriginal());
            }
            return response()->json([
                'success' => true,
                'message' => 'Data Jabatan Akademik berhasil ditolak'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Jabatan Akademik tidak dapat ditolak dari status saat ini.'
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
        $dataJabatanAkademik = SimpegDataJabatanAkademik::find($id);

        if (!$dataJabatanAkademik) {
            return response()->json([
                'success' => false,
                'message' => 'Data Jabatan Akademik tidak ditemukan'
            ], 404);
        }

        if ($dataJabatanAkademik->toDraft()) { // Use model's toDraft method
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_to_draft_jabatan_akademik', $dataJabatanAkademik, $dataJabatanAkademik->getOriginal());
            }
            return response()->json([
                'success' => true,
                'message' => 'Status Jabatan Akademik berhasil diubah menjadi draft'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Jabatan Akademik sudah dalam status draft.'
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
            'ids.*' => 'required|uuid|exists:simpeg_data_jabatan_akademik,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToDelete = SimpegDataJabatanAkademik::whereIn('id', $request->ids)->get();

        if ($dataToDelete->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Akademik yang ditemukan untuk dihapus'
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
                        ActivityLogger::log('admin_batch_delete_jabatan_akademik', $item, $oldData);
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
            \Log::error('Error during batch delete jabatan akademik: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data secara batch: ' . $e->getMessage(),
                'errors' => $errors
            ], 500);
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data Jabatan Akademik",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data Jabatan Akademik",
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
            'ids.*' => 'required|uuid|exists:simpeg_data_jabatan_akademik,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataJabatanAkademik::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', [SimpegDataJabatanAkademik::STATUS_DRAFT, SimpegDataJabatanAkademik::STATUS_DIAJUKAN, SimpegDataJabatanAkademik::STATUS_DITOLAK])
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Akademik yang memenuhi syarat untuk disetujui.'
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
                        ActivityLogger::log('admin_batch_approve_jabatan_akademik', $item, $oldData);
                    }
                    $updatedCount++;
                    $approvedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch approve jabatan akademik: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data Jabatan Akademik",
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
            'ids.*' => 'required|uuid|exists:simpeg_data_jabatan_akademik,id',
            // 'alasan_penolakan' => 'nullable|string|max:500', // Dihapus
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataJabatanAkademik::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', [SimpegDataJabatanAkademik::STATUS_DRAFT, SimpegDataJabatanAkademik::STATUS_DIAJUKAN, SimpegDataJabatanAkademik::STATUS_DISETUJUI])
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Akademik yang memenuhi syarat untuk ditolak.'
            ], 404);
        }

        $updatedCount = 0;
        $rejectedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                // Pass null or no argument as $reason is removed
                if ($item->reject()) { // Changed from $request->alasan_penolakan to no argument
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_reject_jabatan_akademik', $item, $oldData);
                    }
                    $updatedCount++;
                    $rejectedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch reject jabatan akademik: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data Jabatan Akademik",
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
            'ids.*' => 'required|uuid|exists:simpeg_data_jabatan_akademik,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataJabatanAkademik::whereIn('id', $request->ids)
            ->where('status_pengajuan', '!=', SimpegDataJabatanAkademik::STATUS_DRAFT)
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Jabatan Akademik yang memenuhi syarat untuk diubah menjadi draft.'
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
                        ActivityLogger::log('admin_batch_to_draft_jabatan_akademik', $item, $oldData);
                    }
                    $updatedCount++;
                    $draftedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch change to draft for jabatan akademik: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah status ke draft secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengubah {$updatedCount} data Jabatan Akademik menjadi draft",
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
        $jabatanAkademikId = $request->jabatan_akademik_id;
        $pegawaiId = $request->pegawai_id;

        $query = SimpegDataJabatanAkademik::query();

        if ($pegawaiId && $pegawaiId != 'semua') {
            $query->where('pegawai_id', $pegawaiId);
        }

        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $query->whereHas('pegawai', function ($q) use ($unitKerjaId) {
                $q->where('unit_kerja_id', $unitKerjaId);
            });
        }

        if ($jabatanAkademikId && $jabatanAkademikId != 'semua') {
            $query->where('jabatan_akademik_id', $jabatanAkademikId);
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
        // PERBAIKAN: Menambahkan alias 'tahun' pada query raw untuk pluck.
        $yearsTmtJabatan = SimpegDataJabatanAkademik::selectRaw('EXTRACT(YEAR FROM tmt_jabatan) as tahun')
            ->distinct()
            ->whereNotNull('tmt_jabatan')
            ->orderBy('tahun', 'desc')
            ->pluck('tahun');

        $yearsTglSk = SimpegDataJabatanAkademik::selectRaw('EXTRACT(YEAR FROM tgl_sk) as tahun')
            ->distinct()
            ->whereNotNull('tgl_sk')
            ->orderBy('tahun', 'desc')
            ->pluck('tahun');

        $yearsTglDisetujui = SimpegDataJabatanAkademik::selectRaw('EXTRACT(YEAR FROM tgl_disetujui) as tahun')
            ->distinct()
            ->whereNotNull('tgl_disetujui')
            ->orderBy('tahun', 'desc')
            ->pluck('tahun');

        return response()->json([
            'success' => true,
            'filters' => [
                'unit_kerja' => SimpegUnitKerja::select('kode_unit as id', 'nama_unit as nama')->orderBy('nama_unit')->get(),
                'jabatan_akademik' => SimpegJabatanAkademik::select('id', 'jabatan_akademik as nama')->orderBy('jabatan_akademik')->get(),
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua Status'], ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'], ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                ],
                'tahun_tmt_jabatan' => $yearsTmtJabatan,
                'tahun_tgl_sk' => $yearsTglSk,
                'tahun_tgl_disetujui' => $yearsTglDisetujui,
                'pegawai' => SimpegPegawai::select('id', 'nama', 'nip')->orderBy('nama')->get(),
            ]
        ]);
    }
    
    /**
     * Get form options for create/update forms.
     */
    public function getFormOptions()
    {
        $jabatanAkademikOptions = SimpegJabatanAkademik::select('id', 'jabatan_akademik as nama')
            ->orderBy('jabatan_akademik')
            ->get();

        $statusPengajuanOptions = [
            ['id' => 'draft', 'nama' => 'Draft'],
            ['id' => 'diajukan', 'nama' => 'Diajukan'],
            ['id' => 'disetujui', 'nama' => 'Disetujui'],
            ['id' => 'ditolak', 'nama' => 'Ditolak'],
        ];

        return [
            'form_options' => [
                'jabatan_akademik' => $jabatanAkademikOptions,
                'status_pengajuan' => $statusPengajuanOptions,
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|uuid',
                'jabatan_akademik_id' => 'required|uuid',
                'tmt_jabatan' => 'required|date',
                'no_sk' => 'required|string|max:100',
                'tgl_sk' => 'required|date',
                'pejabat_penetap' => 'nullable|string|max:255',
                'file_jabatan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
                // 'alasan_penolakan' => 'nullable|string|max:1000', // Dihapus
            ],
            'field_notes' => [
                'pegawai_id' => 'Pilih pegawai yang bersangkutan.',
                'jabatan_akademik_id' => 'Pilih jenis Jabatan Akademik.',
                'tmt_jabatan' => 'Tanggal Mulai Tugas Jabatan Akademik.',
                'no_sk' => 'Nomor Surat Keputusan Jabatan Akademik.',
                'tgl_sk' => 'Tanggal Surat Keputusan Jabatan Akademik.',
                'pejabat_penetap' => 'Pejabat yang menetapkan SK.',
                'file_jabatan' => 'Unggah file dokumen SK Jabatan Akademik (PDF/gambar).',
                'status_pengajuan' => 'Status pengajuan data.',
                // 'alasan_penolakan' => 'Alasan jika pengajuan ditolak.', // Dihapus
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
     * Helper: Format data jabatan akademik response for display.
     */
    protected function formatDataJabatanAkademik($dataJabatanAkademik, $includeActions = true)
    {
        $status = $dataJabatanAkademik->status_pengajuan ?? SimpegDataJabatanAkademik::STATUS_DRAFT;
        $statusInfo = $this->getStatusInfo($status);

        $pegawai = $dataJabatanAkademik->pegawai;
        $nipPegawai = $pegawai ? $pegawai->nip : '-';
        $namaPegawai = $pegawai ? ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : '') : '-';

        $data = [
            'id' => $dataJabatanAkademik->id,
            'pegawai_id' => $dataJabatanAkademik->pegawai_id,
            'nip_pegawai' => $nipPegawai,
            'nama_pegawai' => $namaPegawai,
            'jabatan_akademik_id' => $dataJabatanAkademik->jabatan_akademik_id,
            'nama_jabatan_akademik' => $dataJabatanAkademik->jabatanAkademik ? $dataJabatanAkademik->jabatanAkademik->jabatan_akademik : '-',
            'tmt_jabatan' => $dataJabatanAkademik->tmt_jabatan,
            'tmt_jabatan_formatted' => $dataJabatanAkademik->tmt_jabatan ? Carbon::parse($dataJabatanAkademik->tmt_jabatan)->format('d M Y') : '-',
            'no_sk' => $dataJabatanAkademik->no_sk,
            'tgl_sk' => $dataJabatanAkademik->tgl_sk,
            'tgl_sk_formatted' => $dataJabatanAkademik->tgl_sk ? Carbon::parse($dataJabatanAkademik->tgl_sk)->format('d M Y') : '-',
            'pejabat_penetap' => $dataJabatanAkademik->pejabat_penetap,
            'file_jabatan' => $dataJabatanAkademik->file_jabatan,
            'file_jabatan_link' => $dataJabatanAkademik->file_jabatan ? Storage::url($dataJabatanAkademik->file_jabatan) : null,
            'tgl_input' => $dataJabatanAkademik->tgl_input,
            'tgl_input_formatted' => $dataJabatanAkademik->tgl_input ? Carbon::parse($dataJabatanAkademik->tgl_input)->format('d M Y') : '-',
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'tgl_diajukan' => $dataJabatanAkademik->tgl_diajukan,
            'tgl_diajukan_formatted' => $dataJabatanAkademik->tgl_diajukan ? Carbon::parse($dataJabatanAkademik->tgl_diajukan)->format('d M Y H:i:s') : '-',
            'tgl_disetujui' => $dataJabatanAkademik->tgl_disetujui,
            'tgl_disetujui_formatted' => $dataJabatanAkademik->tgl_disetujui ? Carbon::parse($dataJabatanAkademik->tgl_disetujui)->format('d M Y H:i:s') : '-',
            'tgl_ditolak' => $dataJabatanAkademik->tgl_ditolak,
            'tgl_ditolak_formatted' => $dataJabatanAkademik->tgl_ditolak ? Carbon::parse($dataJabatanAkademik->tgl_ditolak)->format('d M Y H:i:s') : '-',
            // 'alasan_penolakan' => $dataJabatanAkademik->alasan_penolakan, // Dihapus
            'created_at' => $dataJabatanAkademik->created_at,
            'updated_at' => $dataJabatanAkademik->updated_at
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datajabatanakademikadm/{$dataJabatanAkademik->id}"),
                'update_url' => url("/api/admin/datajabatanakademikadm/{$dataJabatanAkademik->id}"),
                'delete_url' => url("/api/admin/datajabatanakademikadm/{$dataJabatanAkademik->id}"),
                'approve_url' => url("/api/admin/datajabatanakademikadm/{$dataJabatanAkademik->id}/approve"),
                'reject_url' => url("/api/admin/datajabatanakademikadm/{$dataJabatanAkademik->id}/reject"),
                'to_draft_url' => url("/api/admin/datajabatanakademikadm/{$dataJabatanAkademik->id}/todraft"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data Jabatan Akademik "' . $dataJabatanAkademik->no_sk . '"?'
                ],
            ];

            if (in_array($status, [SimpegDataJabatanAkademik::STATUS_DIAJUKAN, SimpegDataJabatanAkademik::STATUS_DITOLAK, SimpegDataJabatanAkademik::STATUS_DRAFT])) {
                $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data Jabatan Akademik "' . $dataJabatanAkademik->no_sk . '"?'
                ];
            }

            if (in_array($status, [SimpegDataJabatanAkademik::STATUS_DIAJUKAN, SimpegDataJabatanAkademik::STATUS_DISETUJUI, SimpegDataJabatanAkademik::STATUS_DRAFT])) {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Tolak',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data Jabatan Akademik "' . $dataJabatanAkademik->no_sk . '"?',
                    // 'needs_input' => true, // Dihapus
                    // 'input_placeholder' => 'Alasan penolakan (opsional)' // Dihapus
                ];
            }

            if ($status !== SimpegDataJabatanAkademik::STATUS_DRAFT) {
                $data['actions']['to_draft'] = [
                    'url' => $data['aksi']['to_draft_url'],
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data Jabatan Akademik "' . $dataJabatanAkademik->no_sk . '" menjadi draft?'
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