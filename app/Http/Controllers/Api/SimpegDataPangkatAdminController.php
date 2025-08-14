<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPangkat;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegMasterPangkat; // Model for Pangkat list
use App\Models\SimpegDaftarJenisSk; // Model for Jenis SK list
use App\Models\SimpegJenisKenaikanPangkat; // Model for Jenis Kenaikan Pangkat list

// Import other models for pegawai info detail (if needed)
use App\Models\SimpegJabatanAkademik;
use App\Models\SimpegDataJabatanFungsional;
use App\Models\SimpegDataJabatanStruktural;
use App\Models\SimpegDataPendidikanFormal;
use App\Models\HubunganKerja;
use App\Models\SimpegStatusAktif;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger; // Assuming this service exists
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class SimpegDataPangkatAdminController extends Controller
{
    /**
     * Get all data pangkat for admin (all pegawai).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search; // For NIP, Nama Pegawai, No SK
        $unitKerjaId = $request->unit_kerja_id;
        $statusPengajuan = $request->status_pengajuan ?? 'semua';
        $pangkatId = $request->pangkat_id; // Filter by specific pangkat
        $jenisSkId = $request->jenis_sk_id;
        $jenisKenaikanPangkatId = $request->jenis_kenaikan_pangkat_id;
        $tmtPangkat = $request->tmt_pangkat;
        $tglSk = $request->tgl_sk;
        $noSk = $request->no_sk;
        $tglDisetujui = $request->tgl_disetujui;


        // Eager load necessary relations
        $query = SimpegDataPangkat::with([
            'pegawai' => function ($q) {
                $q->select('id', 'nip', 'nama', 'gelar_depan', 'gelar_belakang', 'unit_kerja_id')
                    ->with('unitKerja:kode_unit,nama_unit');
            },
            'pangkat:id,pangkat,nama_golongan', // Select specific fields for pangkat
            'jenisSk:id,jenis_sk', // Select specific fields for jenis SK
            'jenisKenaikanPangkat:id,jenis_pangkat', // Select specific fields for jenis kenaikan pangkat
        ]);

        // Apply filters
        $query->filterByNipNamaPegawai($search)
              ->filterByUnitKerja($unitKerjaId)
              ->byStatus($statusPengajuan)
              ->filterByPangkatId($pangkatId)
              ->filterByJenisSkId($jenisSkId)
              ->filterByJenisKenaikanPangkatId($jenisKenaikanPangkatId)
              ->filterByTmtPangkat($tmtPangkat)
              ->filterByNoSk($noSk)
              ->filterByTglSk($tglSk)
              ->filterByTglDisetujui($tglDisetujui);

        // Order results
        $dataPangkat = $query->latest('tgl_input')
                                ->paginate($perPage);

        // Transform collection for frontend
        $dataPangkat->getCollection()->transform(function ($item) {
            return $this->formatDataPangkat($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataPangkat,
            'empty_data' => $dataPangkat->isEmpty(),
            'filters' => $this->getFilterOptions(),
            'table_columns' => [
                ['field' => 'nip_pegawai', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'pegawai.nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'pegawai.nama'],
                ['field' => 'nama_pangkat', 'label' => 'Nama Pangkat', 'sortable' => true, 'sortable_field' => 'pangkat_id'],
                ['field' => 'tmt_pangkat_formatted', 'label' => 'TMT Pangkat', 'sortable' => true, 'sortable_field' => 'tmt_pangkat'],
                ['field' => 'jenis_sk_label', 'label' => 'Jenis SK', 'sortable' => true, 'sortable_field' => 'jenis_sk_id'],
                ['field' => 'file_pangkat_link', 'label' => 'File Pangkat', 'sortable' => false],
                ['field' => 'tgl_disetujui_formatted', 'label' => 'Tgl Disetujui', 'sortable' => true, 'sortable_field' => 'tgl_disetujui'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/datapangkatadm/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/datapangkatadm/batch/reject"),
                    'method' => 'PATCH',
                    'label' => 'Tolak Terpilih',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menolak data terpilih?',
                ],
                'to_draft' => [
                    'url' => url("/api/admin/datapangkatadm/batch/todraft"),
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft Terpilih',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data terpilih menjadi draft?'
                ],
                'delete' => [
                    'url' => url("/api/admin/datapangkatadm/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data terpilih?',
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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $dataPangkat = SimpegDataPangkat::with([
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
            'pangkat',
            'jenisSk',
            'jenisKenaikanPangkat',
        ])->find($id);

        if (!$dataPangkat) {
            return response()->json([
                'success' => false,
                'message' => 'Data Pangkat tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai_info_detail' => $this->formatPegawaiInfo($dataPangkat->pegawai),
            'data' => $this->formatDataPangkat($dataPangkat, false),
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
            'jenis_sk_id' => 'required|uuid|exists:simpeg_daftar_jenis_sk,id',
            'jenis_kenaikan_pangkat_id' => 'required|uuid|exists:simpeg_jenis_kenaikan_pangkat,id',
            'pangkat_id' => 'required|uuid|exists:simpeg_master_pangkat,id',
            'tmt_pangkat' => 'required|date',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'masa_kerja_tahun' => 'required|integer|min:0',
            'masa_kerja_bulan' => 'required|integer|min:0|max:11',
            'acuan_masa_kerja' => 'required|boolean',
            'file_pangkat' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            'is_aktif' => 'sometimes|boolean',
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
        $data['status_pengajuan'] = $request->input('status_pengajuan', SimpegDataPangkat::STATUS_DISETUJUI);

        // Handle timestamps based on status
        if ($data['status_pengajuan'] === SimpegDataPangkat::STATUS_DISETUJUI) {
            $data['tgl_disetujui'] = now();
            // Assuming tgl_diajukan should be set if directly approved, or if it was submitted previously
            $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? now();
        } elseif ($data['status_pengajuan'] === SimpegDataPangkat::STATUS_DIAJUKAN) {
            $data['tgl_diajukan'] = now();
            $data['tgl_disetujui'] = null;
            $data['tgl_ditolak'] = null;
        } elseif ($data['status_pengajuan'] === SimpegDataPangkat::STATUS_DITOLAK) {
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
            if ($request->hasFile('file_pangkat')) {
                $file = $request->file('file_pangkat');
                $fileName = 'pangkat_' . $data['pegawai_id'] . '_' . Carbon::now()->timestamp . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('pangkat_files', $fileName, 'public');
                $data['file_pangkat'] = $filePath;
            }

            $dataPangkat = SimpegDataPangkat::create($data);

            // If 'is_aktif' is true, deactivate other active ranks for this employee
            if (isset($data['is_aktif']) && $data['is_aktif']) {
                SimpegDataPangkat::where('pegawai_id', $dataPangkat->pegawai_id)
                                 ->where('id', '!=', $dataPangkat->id)
                                 ->update(['is_aktif' => false]);
            }


            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_create_pangkat', $dataPangkat, $dataPangkat->toArray());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data Pangkat berhasil ditambahkan oleh admin',
                'data' => $this->formatDataPangkat($dataPangkat->load(['pegawai.unitKerja', 'pangkat', 'jenisSk', 'jenisKenaikanPangkat']))
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
        $dataPangkat = SimpegDataPangkat::find($id);

        if (!$dataPangkat) {
            return response()->json([
                'success' => false,
                'message' => 'Data Pangkat tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|uuid|exists:simpeg_pegawai,id',
            'jenis_sk_id' => 'sometimes|uuid|exists:simpeg_daftar_jenis_sk,id',
            'jenis_kenaikan_pangkat_id' => 'sometimes|uuid|exists:simpeg_jenis_kenaikan_pangkat,id',
            'pangkat_id' => 'sometimes|uuid|exists:simpeg_master_pangkat,id',
            'tmt_pangkat' => 'sometimes|date',
            'no_sk' => 'sometimes|string|max:100',
            'tgl_sk' => 'sometimes|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'masa_kerja_tahun' => 'sometimes|integer|min:0',
            'masa_kerja_bulan' => 'sometimes|integer|min:0|max:11',
            'acuan_masa_kerja' => 'sometimes|boolean',
            'file_pangkat' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            'is_aktif' => 'sometimes|boolean',
            'clear_file_pangkat' => 'nullable|boolean', // Added for explicit file clearing
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
            $oldData = $dataPangkat->getOriginal();
            $data = $validator->validated();

            // Handle file_pangkat upload
            if ($request->hasFile('file_pangkat')) {
                if ($dataPangkat->file_pangkat && Storage::disk('public')->exists($dataPangkat->file_pangkat)) {
                    Storage::disk('public')->delete($dataPangkat->file_pangkat);
                }
                $file = $request->file('file_pangkat');
                $fileName = 'pangkat_' . ($data['pegawai_id'] ?? $dataPangkat->pegawai_id) . '_' . Carbon::now()->timestamp . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('pangkat_files', $fileName, 'public');
                $data['file_pangkat'] = $filePath;
            } elseif ($request->input('clear_file_pangkat')) {
                if ($dataPangkat->file_pangkat && Storage::disk('public')->exists($dataPangkat->file_pangkat)) {
                    Storage::disk('public')->delete($dataPangkat->file_pangkat);
                }
                $data['file_pangkat'] = null;
            } else {
                $data['file_pangkat'] = $dataPangkat->file_pangkat;
            }
            unset($data['clear_file_pangkat']);

            // Handle status_pengajuan and timestamps related
            if (isset($data['status_pengajuan']) && $data['status_pengajuan'] !== $dataPangkat->status_pengajuan) {
                switch ($data['status_pengajuan']) {
                    case SimpegDataPangkat::STATUS_DIAJUKAN:
                        $data['tgl_diajukan'] = now();
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                    case SimpegDataPangkat::STATUS_DISETUJUI:
                        $data['tgl_disetujui'] = now();
                        $data['tgl_diajukan'] = $dataPangkat->tgl_diajukan ?? now();
                        $data['tgl_ditolak'] = null;
                        break;
                    case SimpegDataPangkat::STATUS_DITOLAK:
                        $data['tgl_ditolak'] = now();
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        break;
                    case SimpegDataPangkat::STATUS_DRAFT:
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                }
            } else {
                $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? $dataPangkat->tgl_diajukan;
                $data['tgl_disetujui'] = $data['tgl_disetujui'] ?? $dataPangkat->tgl_disetujui;
                $data['tgl_ditolak'] = $data['tgl_ditolak'] ?? $dataPangkat->tgl_ditolak;
            }

            $dataPangkat->update($data);

            // If 'is_aktif' is set to true in the request, activate it and deactivate others
            if (isset($data['is_aktif']) && $data['is_aktif']) {
                SimpegDataPangkat::where('pegawai_id', $dataPangkat->pegawai_id)
                                 ->where('id', '!=', $dataPangkat->id)
                                 ->update(['is_aktif' => false]);
            }

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_update_pangkat', $dataPangkat, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatDataPangkat($dataPangkat->load(['pegawai.unitKerja', 'pangkat', 'jenisSk', 'jenisKenaikanPangkat'])),
                'message' => 'Data Pangkat berhasil diperbarui oleh admin'
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
        $dataPangkat = SimpegDataPangkat::find($id);

        if (!$dataPangkat) {
            return response()->json([
                'success' => false,
                'message' => 'Data Pangkat tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete associated file
            if ($dataPangkat->file_pangkat && Storage::disk('public')->exists($dataPangkat->file_pangkat)) {
                Storage::disk('public')->delete($dataPangkat->file_pangkat);
            }

            $oldData = $dataPangkat->toArray();
            $dataPangkat->delete();

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_delete_pangkat', $dataPangkat, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data Pangkat berhasil dihapus'
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
        $dataPangkat = SimpegDataPangkat::find($id);

        if (!$dataPangkat) {
            return response()->json([
                'success' => false,
                'message' => 'Data Pangkat tidak ditemukan'
            ], 404);
        }

        if ($dataPangkat->approve()) { // Use model's approve method
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_approve_pangkat', $dataPangkat, $dataPangkat->getOriginal());
            }
            return response()->json([
                'success' => true,
                'message' => 'Data Pangkat berhasil disetujui'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Pangkat tidak dapat disetujui dari status saat ini.'
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
        $dataPangkat = SimpegDataPangkat::find($id);

        if (!$dataPangkat) {
            return response()->json([
                'success' => false,
                'message' => 'Data Pangkat tidak ditemukan'
            ], 404);
        }

        if ($dataPangkat->reject()) { // Pass no argument
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_reject_pangkat', $dataPangkat, $dataPangkat->getOriginal());
            }
            return response()->json([
                'success' => true,
                'message' => 'Data Pangkat berhasil ditolak'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Pangkat tidak dapat ditolak dari status saat ini.'
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
        $dataPangkat = SimpegDataPangkat::find($id);

        if (!$dataPangkat) {
            return response()->json([
                'success' => false,
                'message' => 'Data Pangkat tidak ditemukan'
            ], 404);
        }

        if ($dataPangkat->toDraft()) { // Use model's toDraft method
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_to_draft_pangkat', $dataPangkat, $dataPangkat->getOriginal());
            }
            return response()->json([
                'success' => true,
                'message' => 'Status Pangkat berhasil diubah menjadi draft'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Pangkat sudah dalam status draft.'
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
            'ids.*' => 'required|uuid|exists:simpeg_data_pangkat,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToDelete = SimpegDataPangkat::whereIn('id', $request->ids)->get();

        if ($dataToDelete->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Pangkat yang ditemukan untuk dihapus'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataToDelete as $item) {
                try {
                    if ($item->file_pangkat && Storage::disk('public')->exists($item->file_pangkat)) {
                        Storage::disk('public')->delete($item->file_pangkat);
                    }
                    $oldData = $item->toArray();
                    $item->delete();
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_delete_pangkat', $item, $oldData);
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
            \Log::error('Error during batch delete pangkat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data secara batch: ' . $e->getMessage(),
                'errors' => $errors
            ], 500);
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data Pangkat",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data Pangkat",
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
            'ids.*' => 'required|uuid|exists:simpeg_data_pangkat,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataPangkat::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', [SimpegDataPangkat::STATUS_DRAFT, SimpegDataPangkat::STATUS_DIAJUKAN, SimpegDataPangkat::STATUS_DITOLAK])
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Pangkat yang memenuhi syarat untuk disetujui.'
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
                        ActivityLogger::log('admin_batch_approve_pangkat', $item, $oldData);
                    }
                    $updatedCount++;
                    $approvedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch approve pangkat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data Pangkat",
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
            'ids.*' => 'required|uuid|exists:simpeg_data_pangkat,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataPangkat::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', [SimpegDataPangkat::STATUS_DRAFT, SimpegDataPangkat::STATUS_DIAJUKAN, SimpegDataPangkat::STATUS_DISETUJUI])
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Pangkat yang memenuhi syarat untuk ditolak.'
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
                        ActivityLogger::log('admin_batch_reject_pangkat', $item, $oldData);
                    }
                    $updatedCount++;
                    $rejectedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch reject pangkat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data Pangkat",
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
            'ids.*' => 'required|uuid|exists:simpeg_data_pangkat,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataPangkat::whereIn('id', $request->ids)
            ->where('status_pengajuan', '!=', SimpegDataPangkat::STATUS_DRAFT)
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data Pangkat yang memenuhi syarat untuk diubah menjadi draft.'
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
                        ActivityLogger::log('admin_batch_to_draft_pangkat', $item, $oldData);
                    }
                    $updatedCount++;
                    $draftedIds[] = $item->id;
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch change to draft for pangkat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah status ke draft secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengubah {$updatedCount} data Pangkat menjadi draft",
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
        $pangkatId = $request->pangkat_id; // Use pangkat_id for filtering
        $pegawaiId = $request->pegawai_id;

        $query = SimpegDataPangkat::query();

        if ($pegawaiId && $pegawaiId != 'semua') {
            $query->where('pegawai_id', $pegawaiId);
        }

        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $query->whereHas('pegawai', function ($q) use ($unitKerjaId) {
                $q->where('unit_kerja_id', $unitKerjaId);
            });
        }

        if ($pangkatId && $pangkatId != 'semua') { // Filter by pangkat_id
            $query->where('pangkat_id', $pangkatId);
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

        $pangkatOptions = SimpegMasterPangkat::select('id', DB::raw("CONCAT(pangkat, ' (', nama_golongan, ')') as nama_pangkat_display"))
            ->orderBy('nama_golongan')
            ->get()
            ->map(function($item) {
                return ['id' => $item->id, 'nama' => $item->nama_pangkat_display];
            })
            ->prepend(['id' => 'semua', 'nama' => 'Semua Pangkat']);
        
        $jenisSkOptions = SimpegDaftarJenisSk::select('id', 'jenis_sk as nama')
            ->orderBy('jenis_sk')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jenis SK']);

        $jenisKenaikanPangkatOptions = SimpegJenisKenaikanPangkat::select('id', 'jenis_pangkat as nama')
            ->orderBy('jenis_pangkat')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jenis Kenaikan Pangkat']);

        $statusPengajuanOptions = [
            ['id' => 'semua', 'nama' => 'Semua'],
            ['id' => 'draft', 'nama' => 'Draft'],
            ['id' => 'diajukan', 'nama' => 'Diajukan'],
            ['id' => 'disetujui', 'nama' => 'Disetujui'],
            ['id' => 'ditolak', 'nama' => 'Ditolak'],
        ];

        // Retrieve existing years for date fields using EXTRACT for PostgreSQL
        $yearsTmtPangkat = SimpegDataPangkat::distinct()
                                                   ->select(DB::raw("EXTRACT(YEAR FROM tmt_pangkat) as year_value"))
                                                   ->get()
                                                   ->filter(function($item) { return $item->year_value !== null; })
                                                   ->sortByDesc('year_value')
                                                   ->values()
                                                   ->map(function($item) { return ['id' => $item->year_value, 'nama' => $item->year_value]; })
                                                   ->prepend(['id' => 'semua', 'nama' => 'Semua TMT Pangkat'])
                                                   ->toArray();

        $yearsTglSk = SimpegDataPangkat::distinct()
                                               ->select(DB::raw("EXTRACT(YEAR FROM tgl_sk) as year_value"))
                                               ->get()
                                               ->filter(function($item) { return $item->year_value !== null; })
                                               ->sortByDesc('year_value')
                                               ->values()
                                               ->map(function($item) { return ['id' => $item->year_value, 'nama' => $item->year_value]; })
                                               ->prepend(['id' => 'semua', 'nama' => 'Semua Tgl SK'])
                                               ->toArray();

        $yearsTglDisetujui = SimpegDataPangkat::whereNotNull('tgl_disetujui')
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
                'pangkat' => $pangkatOptions,
                'jenis_sk' => $jenisSkOptions,
                'jenis_kenaikan_pangkat' => $jenisKenaikanPangkatOptions,
                'status_pengajuan' => $statusPengajuanOptions,
                'tahun_tmt_pangkat' => $yearsTmtPangkat,
                'tahun_tgl_sk' => $yearsTglSk,
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
        $pangkatOptions = SimpegMasterPangkat::select('id', DB::raw("CONCAT(pangkat, ' (', nama_golongan, ')') as nama"))
            ->orderBy('nama')
            ->get();
        
        $jenisSkOptions = SimpegDaftarJenisSk::select('id', 'jenis_sk as nama')
            ->orderBy('jenis_sk')
            ->get();

        $jenisKenaikanPangkatOptions = SimpegJenisKenaikanPangkat::select('id', 'jenis_pangkat as nama')
            ->orderBy('jenis_pangkat')
            ->get();

        $statusPengajuanOptions = [
            ['id' => 'draft', 'nama' => 'Draft'],
            ['id' => 'diajukan', 'nama' => 'Diajukan'],
            ['id' => 'disetujui', 'nama' => 'Disetujui'],
            ['id' => 'ditolak', 'nama' => 'Ditolak'],
        ];

        return [
            'form_options' => [
                'pangkat' => $pangkatOptions,
                'jenis_sk' => $jenisSkOptions,
                'jenis_kenaikan_pangkat' => $jenisKenaikanPangkatOptions,
                'status_pengajuan' => $statusPengajuanOptions,
                'acuan_masa_kerja_options' => [
                    ['id' => true, 'nama' => 'Ya'],
                    ['id' => false, 'nama' => 'Tidak'],
                ]
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|uuid',
                'jenis_sk_id' => 'required|uuid',
                'jenis_kenaikan_pangkat_id' => 'required|uuid',
                'pangkat_id' => 'required|uuid',
                'tmt_pangkat' => 'required|date',
                'no_sk' => 'required|string|max:100',
                'tgl_sk' => 'required|date',
                'pejabat_penetap' => 'nullable|string|max:255',
                'masa_kerja_tahun' => 'required|integer|min:0',
                'masa_kerja_bulan' => 'required|integer|min:0|max:11',
                'acuan_masa_kerja' => 'required|boolean',
                'file_pangkat' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
                'is_aktif' => 'sometimes|boolean',
            ],
            'field_notes' => [
                'pegawai_id' => 'Pilih pegawai yang bersangkutan.',
                'jenis_sk_id' => 'Pilih jenis SK.',
                'jenis_kenaikan_pangkat_id' => 'Pilih jenis kenaikan pangkat.',
                'pangkat_id' => 'Pilih pangkat yang diberikan.',
                'tmt_pangkat' => 'Tanggal Mulai Tugas Pangkat.',
                'no_sk' => 'Nomor Surat Keputusan Pangkat.',
                'tgl_sk' => 'Tanggal Surat Keputusan Pangkat.',
                'pejabat_penetap' => 'Pejabat yang menetapkan SK.',
                'masa_kerja_tahun' => 'Masa kerja tahun (integer).',
                'masa_kerja_bulan' => 'Masa kerja bulan (integer, 0-11).',
                'acuan_masa_kerja' => 'Apakah ini merupakan acuan masa kerja?',
                'file_pangkat' => 'Unggah file dokumen SK Pangkat (PDF/gambar).',
                'status_pengajuan' => 'Status pengajuan data.',
                'is_aktif' => 'Apakah pangkat ini adalah pangkat aktif saat ini?',
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
        if ($pegawai->dataJabatanAkademik && $pegawai->dataJabatanAkademik->isNotEmpty()) {
            $jabatanAkademik = $pegawai->dataJabatanAkademik->sortByDesc('tmt_jabatan')->first();
            if ($jabatanAkademik && $jabatanAkademik->jabatanAkademik) {
                $jabatanAkademikNama = $jabatanAkademik->jabatanAkademik->jabatan_akademik ?? '-';
            }
        } else if ($pegawai->jabatanAkademik) {
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
     * Helper: Format data pangkat response for display.
     */
    protected function formatDataPangkat($dataPangkat, $includeActions = true)
    {
        $status = $dataPangkat->status_pengajuan ?? SimpegDataPangkat::STATUS_DRAFT;
        $statusInfo = $this->getStatusInfo($status);

        $pegawai = $dataPangkat->pegawai;
        $nipPegawai = $pegawai ? $pegawai->nip : '-';
        $namaPegawai = $pegawai ? ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : '') : '-';

        $namaPangkat = $dataPangkat->pangkat ? ($dataPangkat->pangkat->pangkat . ' (' . $dataPangkat->pangkat->nama_golongan . ')') : '-';
        $jenisSkLabel = $dataPangkat->jenisSk ? $dataPangkat->jenisSk->jenis_sk : '-';
        $jenisKenaikanPangkatLabel = $dataPangkat->jenisKenaikanPangkat ? $dataPangkat->jenisKenaikanPangkat->jenis_pangkat : '-';

        $data = [
            'id' => $dataPangkat->id,
            'pegawai_id' => $dataPangkat->pegawai_id,
            'nip_pegawai' => $nipPegawai,
            'nama_pegawai' => $namaPegawai,
            'jenis_sk_id' => $dataPangkat->jenis_sk_id,
            'jenis_sk_label' => $jenisSkLabel,
            'jenis_kenaikan_pangkat_id' => $dataPangkat->jenis_kenaikan_pangkat_id,
            'jenis_kenaikan_pangkat_label' => $jenisKenaikanPangkatLabel,
            'pangkat_id' => $dataPangkat->pangkat_id,
            'nama_pangkat' => $namaPangkat,
            'tmt_pangkat' => $dataPangkat->tmt_pangkat,
            'tmt_pangkat_formatted' => $dataPangkat->tmt_pangkat ? Carbon::parse($dataPangkat->tmt_pangkat)->format('d M Y') : '-',
            'no_sk' => $dataPangkat->no_sk,
            'tgl_sk' => $dataPangkat->tgl_sk,
            'tgl_sk_formatted' => $dataPangkat->tgl_sk ? Carbon::parse($dataPangkat->tgl_sk)->format('d M Y') : '-',
            'pejabat_penetap' => $dataPangkat->pejabat_penetap,
            'masa_kerja_tahun' => $dataPangkat->masa_kerja_tahun,
            'masa_kerja_bulan' => $dataPangkat->masa_kerja_bulan,
            'acuan_masa_kerja' => (bool)$dataPangkat->acuan_masa_kerja,
            'file_pangkat' => $dataPangkat->file_pangkat,
            'file_pangkat_link' => $dataPangkat->file_pangkat ? Storage::url($dataPangkat->file_pangkat) : null,
            'tgl_input' => $dataPangkat->tgl_input,
            'tgl_input_formatted' => $dataPangkat->tgl_input ? Carbon::parse($dataPangkat->tgl_input)->format('d M Y') : '-',
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'is_aktif' => (bool)$dataPangkat->is_aktif,
            'tgl_diajukan' => $dataPangkat->tgl_diajukan,
            'tgl_diajukan_formatted' => $dataPangkat->tgl_diajukan ? Carbon::parse($dataPangkat->tgl_diajukan)->format('d M Y H:i:s') : '-',
            'tgl_disetujui' => $dataPangkat->tgl_disetujui,
            'tgl_disetujui_formatted' => $dataPangkat->tgl_disetujui ? Carbon::parse($dataPangkat->tgl_disetujui)->format('d M Y H:i:s') : '-',
            'tgl_ditolak' => $dataPangkat->tgl_ditolak,
            'tgl_ditolak_formatted' => $dataPangkat->tgl_ditolak ? Carbon::parse($dataPangkat->tgl_ditolak)->format('d M Y H:i:s') : '-',
            'created_at' => $dataPangkat->created_at,
            'updated_at' => $dataPangkat->updated_at
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datapangkatadm/{$dataPangkat->id}"),
                'update_url' => url("/api/admin/datapangkatadm/{$dataPangkat->id}"),
                'delete_url' => url("/api/admin/datapangkatadm/{$dataPangkat->id}"),
                'approve_url' => url("/api/admin/datapangkatadm/{$dataPangkat->id}/approve"),
                'reject_url' => url("/api/admin/datapangkatadm/{$dataPangkat->id}/reject"),
                'to_draft_url' => url("/api/admin/datapangkatadm/{$dataPangkat->id}/todraft"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data Pangkat "' . $dataPangkat->no_sk . '"?'
                ],
            ];

            if (in_array($status, [SimpegDataPangkat::STATUS_DIAJUKAN, SimpegDataPangkat::STATUS_DITOLAK, SimpegDataPangkat::STATUS_DRAFT])) {
                $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data Pangkat "' . $dataPangkat->no_sk . '"?'
                ];
            }

            if (in_array($status, [SimpegDataPangkat::STATUS_DIAJUKAN, SimpegDataPangkat::STATUS_DISETUJUI, SimpegDataPangkat::STATUS_DRAFT])) {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Tolak',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data Pangkat "' . $dataPangkat->no_sk . '"?',
                ];
            }

            if ($status !== SimpegDataPangkat::STATUS_DRAFT) {
                $data['actions']['to_draft'] = [
                    'url' => $data['aksi']['to_draft_url'],
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data Pangkat "' . $dataPangkat->no_sk . '" menjadi draft?'
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