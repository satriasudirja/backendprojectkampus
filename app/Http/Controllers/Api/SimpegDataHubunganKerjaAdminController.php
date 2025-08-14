<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataHubunganKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJabatanFungsional;
use App\Models\HubunganKerja; // Model for Hubungan Kerja type
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger; // Assuming this service exists
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SimpegDataHubunganKerjaAdminController extends Controller
{
    /**
     * Get all data hubungan kerja for admin (all pegawai).
     * Admin can view data for any employee.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search; // For NIP and Nama Pegawai
        $unitKerjaId = $request->unit_kerja_id;
        $statusPengajuan = $request->status_pengajuan ?? 'semua';
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $tglMulai = $request->tgl_mulai;
        $tglSelesai = $request->tgl_selesai;
        $hubunganKerjaId = $request->hubungan_kerja_id;
        $tglDisetujui = $request->tgl_disetujui; // For filter 'tgl_disetujui'

        // Eager load all necessary relations
        $query = SimpegDataHubunganKerja::with([
            'pegawai' => function ($q) {
                // Select only necessary fields to optimize query
                $q->select('id', 'nip', 'nama', 'unit_kerja_id', 'gelar_depan', 'gelar_belakang')
                    ->with([
                        // Use kode_unit for SimpegUnitKerja if it's the foreign key on pegawai
                        'unitKerja:kode_unit,nama_unit',
                        'dataJabatanFungsional' => function ($subQuery) {
                            $subQuery->with('jabatanFungsional:id,nama_jabatan_fungsional') // Select specific fields
                                     ->latest('tmt_jabatan')
                                     ->limit(1);
                        }
                    ]);
            },
            'hubunganKerja:id,nama_hub_kerja' // Select specific fields
        ]);

        // Apply filters
        $query->filterByNipNamaPegawai($search)
              ->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanFungsional($jabatanFungsionalId)
              ->byStatus($statusPengajuan)
              ->filterByTglMulai($tglMulai)
              ->filterByTglSelesai($tglSelesai)
              ->filterByHubunganKerjaId($hubunganKerjaId)
              ->filterByTglDisetujui($tglDisetujui); // Apply the new filter scope

        // Order by latest input date or SK date
        $dataHubunganKerja = $query->latest('tgl_input')
                                   ->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataHubunganKerja->getCollection()->transform(function ($item) {
            return $this->formatDataHubunganKerja($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataHubunganKerja,
            'empty_data' => $dataHubunganKerja->isEmpty(),
            'filters' => $this->getFilterOptions(),
            'table_columns' => [
                ['field' => 'nip_pegawai', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'pegawai.nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'pegawai.nama'],
                ['field' => 'tgl_mulai_formatted', 'label' => 'Tgl Mulai', 'sortable' => true, 'sortable_field' => 'tgl_awal'],
                ['field' => 'tgl_selesai_formatted', 'label' => 'Tgl Selesai', 'sortable' => true, 'sortable_field' => 'tgl_akhir'],
                ['field' => 'hubungan_kerja_label', 'label' => 'Hubungan Kerja', 'sortable' => true, 'sortable_field' => 'hubungan_kerja_id'],
                ['field' => 'keterangan', 'label' => 'Keterangan', 'sortable' => false],
                ['field' => 'file_hubungan_kerja_link', 'label' => 'File Hubungan Kerja', 'sortable' => false],
                ['field' => 'tgl_disetujui_formatted', 'label' => 'Tgl Disetujui', 'sortable' => true, 'sortable_field' => 'tgl_disetujui'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/datahubungankerjaadm/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/datahubungankerjaadm/batch/reject"),
                    'method' => 'PATCH',
                    'label' => 'Tolak Terpilih',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menolak data terpilih?',
                    'needs_input' => true,
                    'input_placeholder' => 'Keterangan penolakan (opsional)'
                ],
                'to_draft' => [
                    'url' => url("/api/admin/datahubungankerjaadm/batch/todraft"),
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft Terpilih',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data terpilih menjadi draft?'
                ],
                'delete' => [
                    'url' => url("/api/admin/datahubungankerjaadm/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data terpilih?',
                ]
            ],
            'pagination' => [
                'current_page' => $dataHubunganKerja->currentPage(),
                'per_page' => $dataHubunganKerja->perPage(),
                'total' => $dataHubunganKerja->total(),
                'last_page' => $dataHubunganKerja->lastPage(),
                'from' => $dataHubunganKerja->firstItem(),
                'to' => $dataHubunganKerja->lastItem()
            ]
        ]);
    }

    /**
     * Get detail data hubungan kerja.
     * Admin can view details for any employee's data.
     */
    public function show($id)
    {
        $dataHubunganKerja = SimpegDataHubunganKerja::with([
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
                    }
                ]);
            },
            'hubunganKerja',
            'statusAktif'
        ])->find($id);

        if (!$dataHubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai_info_detail' => $this->formatPegawaiInfo($dataHubunganKerja->pegawai),
            'data' => $this->formatDataHubunganKerja($dataHubunganKerja, false),
            'form_options' => $this->getFormOptions(), // Form options for create/update if needed
        ]);
    }

    /**
     * Store new data hubungan kerja (Admin Operational).
     * Admin can add data for any employee. Auto-sets status to 'disetujui' by default.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|uuid|exists:simpeg_pegawai,id',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'tgl_awal' => 'required|date',
            'tgl_akhir' => 'nullable|date|after_or_equal:tgl_awal',
            'pejabat_penetap' => 'nullable|string|max:255',
            'file_hubungan_kerja' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'hubungan_kerja_id' => 'required|uuid|exists:simpeg_hubungan_kerja,id',
            'status_aktif_id' => 'nullable|uuid|exists:simpeg_status_aktif,id',
            'is_aktif' => 'boolean', // Allow admin to set 'is_aktif' directly
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string|max:1000',
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
        $data['status_pengajuan'] = $request->input('status_pengajuan', SimpegDataHubunganKerja::STATUS_DISETUJUI);

        // Handle timestamps based on status
        if ($data['status_pengajuan'] === SimpegDataHubunganKerja::STATUS_DISETUJUI) {
            $data['tgl_disetujui'] = now();
            // If already set to diajukan, preserve it, otherwise set to now
            $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? now();
        } elseif ($data['status_pengajuan'] === SimpegDataHubunganKerja::STATUS_DIAJUKAN) {
            $data['tgl_diajukan'] = now();
            $data['tgl_disetujui'] = null;
            $data['tgl_ditolak'] = null;
        } elseif ($data['status_pengajuan'] === SimpegDataHubunganKerja::STATUS_DITOLAK) {
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
            if ($request->hasFile('file_hubungan_kerja')) {
                $file = $request->file('file_hubungan_kerja');
                $fileName = 'hubungan_kerja_' . $data['pegawai_id'] . '_' . time() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('hubungan_kerja_files', $fileName, 'public');
                $data['file_hubungan_kerja'] = $filePath;
            }

            $hubunganKerja = SimpegDataHubunganKerja::create($data);

            // If 'is_aktif' is true, deactivate other active relationships for this employee
            if (isset($data['is_aktif']) && $data['is_aktif']) {
                $hubunganKerja->activate();
            }

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_create_hubungan_kerja', $hubunganKerja, $hubunganKerja->toArray());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data hubungan kerja berhasil ditambahkan oleh admin',
                'data' => $this->formatDataHubunganKerja($hubunganKerja->load(['pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional', 'hubunganKerja', 'statusAktif']))
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
     * Update data hubungan kerja (Admin Operational).
     * Admin can edit any data regardless of status.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $hubunganKerja = SimpegDataHubunganKerja::find($id);

        if (!$hubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|uuid|exists:simpeg_pegawai,id',
            'no_sk' => 'sometimes|string|max:100',
            'tgl_sk' => 'sometimes|date',
            'tgl_awal' => 'sometimes|date',
            'tgl_akhir' => 'nullable|date|after_or_equal:tgl_awal',
            'pejabat_penetap' => 'nullable|string|max:255',
            'file_hubungan_kerja' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'hubungan_kerja_id' => 'sometimes|uuid|exists:simpeg_hubungan_kerja,id',
            'status_aktif_id' => 'nullable|uuid|exists:simpeg_status_aktif,id',
            'is_aktif' => 'sometimes|boolean',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string|max:1000',
            'clear_file_hubungan_kerja' => 'nullable|boolean', // Added for explicit file clearing
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
            $oldData = $hubunganKerja->getOriginal();
            $data = $validator->validated();

            // Handle file_hubungan_kerja upload
            if ($request->hasFile('file_hubungan_kerja')) {
                // Delete old file if exists
                if ($hubunganKerja->file_hubungan_kerja && Storage::disk('public')->exists($hubunganKerja->file_hubungan_kerja)) {
                    Storage::disk('public')->delete($hubunganKerja->file_hubungan_kerja);
                }
                $file = $request->file('file_hubungan_kerja');
                $fileName = 'hubungan_kerja_' . ($data['pegawai_id'] ?? $hubunganKerja->pegawai_id) . '_' . time() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('hubungan_kerja_files', $fileName, 'public');
                $data['file_hubungan_kerja'] = $filePath;
            } elseif ($request->input('clear_file_hubungan_kerja')) { // Add a flag to clear the file
                if ($hubunganKerja->file_hubungan_kerja && Storage::disk('public')->exists($hubunganKerja->file_hubungan_kerja)) {
                    Storage::disk('public')->delete($hubunganKerja->file_hubungan_kerja);
                }
                $data['file_hubungan_kerja'] = null;
            } else {
                // Preserve existing file path if no new file is uploaded and not flagged for clearing
                $data['file_hubungan_kerja'] = $hubunganKerja->file_hubungan_kerja;
            }
            // Remove 'clear_file_hubungan_kerja' from $data as it's a control flag, not a model field
            unset($data['clear_file_hubungan_kerja']);

            // Handle status_pengajuan and timestamps related
            if (isset($data['status_pengajuan']) && $data['status_pengajuan'] !== $hubunganKerja->status_pengajuan) {
                switch ($data['status_pengajuan']) {
                    case SimpegDataHubunganKerja::STATUS_DIAJUKAN:
                        $data['tgl_diajukan'] = now();
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                    case SimpegDataHubunganKerja::STATUS_DISETUJUI:
                        $data['tgl_disetujui'] = now();
                        // If already submitted, retain original submitted date, else set now
                        $data['tgl_diajukan'] = $hubunganKerja->tgl_diajukan ?? now();
                        $data['tgl_ditolak'] = null;
                        break;
                    case SimpegDataHubunganKerja::STATUS_DITOLAK:
                        $data['tgl_ditolak'] = now();
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        break;
                    case SimpegDataHubunganKerja::STATUS_DRAFT:
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                }
            } else {
                // If status is not changed, retain existing timestamps unless explicitly changed
                $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? $hubunganKerja->tgl_diajukan;
                $data['tgl_disetujui'] = $data['tgl_disetujui'] ?? $hubunganKerja->tgl_disetujui;
                $data['tgl_ditolak'] = $data['tgl_ditolak'] ?? $hubunganKerja->tgl_ditolak;
            }
            // Retain existing 'keterangan' if not explicitly updated to null or a new value
            if (!isset($data['keterangan'])) {
                $data['keterangan'] = $hubunganKerja->keterangan;
            }

            $hubunganKerja->update($data);

            // If 'is_aktif' is set to true in the request, activate it and deactivate others
            // Only trigger if 'is_aktif' was explicitly sent in the request
            if (isset($data['is_aktif']) && $data['is_aktif']) {
                $hubunganKerja->activate();
            }

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_update_hubungan_kerja', $hubunganKerja, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatDataHubunganKerja($hubunganKerja->load(['pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional', 'hubunganKerja', 'statusAktif'])),
                'message' => 'Data hubungan kerja berhasil diperbarui oleh admin'
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
     * Delete data hubungan kerja.
     * Admin can delete any data.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $hubunganKerja = SimpegDataHubunganKerja::find($id);

        if (!$hubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete associated file
            if ($hubunganKerja->file_hubungan_kerja && Storage::disk('public')->exists($hubunganKerja->file_hubungan_kerja)) {
                Storage::disk('public')->delete($hubunganKerja->file_hubungan_kerja);
            }

            $oldData = $hubunganKerja->toArray();
            $hubunganKerja->delete();

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_delete_hubungan_kerja', $hubunganKerja, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data hubungan kerja berhasil dihapus'
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
        $hubunganKerja = SimpegDataHubunganKerja::find($id);

        if (!$hubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
            ], 404);
        }

        if ($hubunganKerja->status_pengajuan === SimpegDataHubunganKerja::STATUS_DISETUJUI) {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah disetujui sebelumnya'
            ], 409);
        }

        $oldData = $hubunganKerja->getOriginal();
        $hubunganKerja->update([
            'status_pengajuan' => SimpegDataHubunganKerja::STATUS_DISETUJUI,
            'tgl_disetujui' => now(),
            'tgl_ditolak' => null,
            'keterangan' => null, // Clear rejection note
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_approve_hubungan_kerja', $hubunganKerja, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data hubungan kerja berhasil disetujui'
        ]);
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
        $hubunganKerja = SimpegDataHubunganKerja::find($id);

        if (!$hubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
            ], 404);
        }

        if ($hubunganKerja->status_pengajuan === SimpegDataHubunganKerja::STATUS_DITOLAK) {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah ditolak sebelumnya'
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'keterangan_penolakan' => 'nullable|string|max:500', // Using 'keterangan_penolakan' for consistency
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $hubunganKerja->getOriginal();
        $hubunganKerja->update([
            'status_pengajuan' => SimpegDataHubunganKerja::STATUS_DITOLAK,
            'tgl_ditolak' => now(),
            'tgl_disetujui' => null,
            'keterangan' => $request->keterangan_penolakan, // Store rejection note in 'keterangan'
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_reject_hubungan_kerja', $hubunganKerja, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data hubungan kerja berhasil ditolak'
        ]);
    }

    /**
     * Admin: Change status to 'draft' for a single data entry.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toDraft($id)
    {
        $hubunganKerja = SimpegDataHubunganKerja::find($id);

        if (!$hubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
            ], 404);
        }

        if ($hubunganKerja->status_pengajuan === SimpegDataHubunganKerja::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah dalam status draft'
            ], 409);
        }

        $oldData = $hubunganKerja->getOriginal();
        $hubunganKerja->update([
            'status_pengajuan' => SimpegDataHubunganKerja::STATUS_DRAFT,
            'tgl_diajukan' => null,
            'tgl_disetujui' => null,
            'tgl_ditolak' => null,
            'keterangan' => null, // Clear any previous notes if moving to draft
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_to_draft_hubungan_kerja', $hubunganKerja, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status hubungan kerja berhasil diubah menjadi draft'
        ]);
    }

    /**
     * Admin: Batch delete data hubungan kerja.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_hubungan_kerja,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToDelete = SimpegDataHubunganKerja::whereIn('id', $request->ids)->get();

        if ($dataToDelete->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data hubungan kerja yang ditemukan untuk dihapus'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataToDelete as $hubunganKerja) {
                try {
                    // Delete associated file
                    if ($hubunganKerja->file_hubungan_kerja && Storage::disk('public')->exists($hubunganKerja->file_hubungan_kerja)) {
                        Storage::disk('public')->delete($hubunganKerja->file_hubungan_kerja);
                    }

                    $oldData = $hubunganKerja->toArray();
                    $hubunganKerja->delete();

                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_delete_hubungan_kerja', $hubunganKerja, $oldData);
                    }
                    $deletedCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $hubunganKerja->id,
                        'no_sk' => $hubunganKerja->no_sk,
                        'error' => 'Gagal menghapus: ' . $e->getMessage()
                    ];
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data secara batch: ' . $e->getMessage(),
                'errors' => $errors
            ], 500);
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data hubungan kerja",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data hubungan kerja",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }

    /**
     * Admin: Batch approve data hubungan kerja.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_hubungan_kerja,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataHubunganKerja::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', [SimpegDataHubunganKerja::STATUS_DRAFT, SimpegDataHubunganKerja::STATUS_DIAJUKAN, SimpegDataHubunganKerja::STATUS_DITOLAK])
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data hubungan kerja yang memenuhi syarat untuk disetujui.'
            ], 404);
        }

        $updatedCount = 0;
        $approvedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => SimpegDataHubunganKerja::STATUS_DISETUJUI,
                    'tgl_disetujui' => now(),
                    'tgl_ditolak' => null,
                    'keterangan' => null,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_batch_approve_hubungan_kerja', $item, $oldData);
                }
                $updatedCount++;
                $approvedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch approve hubungan kerja: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data hubungan kerja",
            'updated_count' => $updatedCount,
            'approved_ids' => $approvedIds
        ]);
    }

    /**
     * Admin: Batch reject data hubungan kerja.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_hubungan_kerja,id',
            'keterangan_penolakan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataHubunganKerja::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', [SimpegDataHubunganKerja::STATUS_DRAFT, SimpegDataHubunganKerja::STATUS_DIAJUKAN, SimpegDataHubunganKerja::STATUS_DISETUJUI])
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data hubungan kerja yang memenuhi syarat untuk ditolak.'
            ], 404);
        }

        $updatedCount = 0;
        $rejectedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => SimpegDataHubunganKerja::STATUS_DITOLAK,
                    'tgl_ditolak' => now(),
                    'tgl_disetujui' => null,
                    'keterangan' => $request->keterangan_penolakan,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_batch_reject_hubungan_kerja', $item, $oldData);
                }
                $updatedCount++;
                $rejectedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch reject hubungan kerja: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data hubungan kerja",
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
            'ids.*' => 'required|uuid|exists:simpeg_data_hubungan_kerja,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataHubunganKerja::whereIn('id', $request->ids)
            ->where('status_pengajuan', '!=', SimpegDataHubunganKerja::STATUS_DRAFT)
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data hubungan kerja yang memenuhi syarat untuk diubah menjadi draft.'
            ], 404);
        }

        $updatedCount = 0;
        $draftedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => SimpegDataHubunganKerja::STATUS_DRAFT,
                    'tgl_diajukan' => null,
                    'tgl_disetujui' => null,
                    'tgl_ditolak' => null,
                    'keterangan' => null,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_batch_to_draft_hubungan_kerja', $item, $oldData);
                }
                $updatedCount++;
                $draftedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch change to draft for hubungan kerja: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah status ke draft secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengubah {$updatedCount} data hubungan kerja menjadi draft",
            'updated_count' => $updatedCount,
            'drafted_ids' => $draftedIds
        ]);
    }


    /**
     * Get status statistics for dashboard.
     * Admin can filter statistics by unit, functional position, and employee.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatusStatistics(Request $request)
    {
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $pegawaiId = $request->pegawai_id;

        $query = SimpegDataHubunganKerja::query();

        if ($pegawaiId && $pegawaiId != 'semua') {
            $query->where('pegawai_id', $pegawaiId);
        }

        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $query->whereHas('pegawai', function ($q) use ($unitKerjaId) {
                $q->where('unit_kerja_id', $unitKerjaId);
            });
        }

        if ($jabatanFungsionalId && $jabatanFungsionalId != 'semua') {
            $query->whereHas('pegawai.dataJabatanFungsional', function ($q) use ($jabatanFungsionalId) {
                $q->where('jabatan_fungsional_id', $jabatanFungsionalId);
            });
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

        $hubunganKerjaOptions = HubunganKerja::select('id', 'nama_hub_kerja as nama')
            ->orderBy('nama_hub_kerja')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Hubungan Kerja']);

        $statusPengajuanOptions = [
            ['id' => 'semua', 'nama' => 'Semua'],
            ['id' => 'draft', 'nama' => 'Draft'],
            ['id' => 'diajukan', 'nama' => 'Diajukan'],
            ['id' => 'disetujui', 'nama' => 'Disetujui'],
            ['id' => 'ditolak', 'nama' => 'Ditolak'],
        ];

        // // Retrieve existing years for tgl_awal and tgl_akhir from the database
        // // FIX: Changed YEAR() to EXTRACT(YEAR FROM) for PostgreSQL compatibility
        // $yearsTglAwal = SimpegDataHubunganKerja::distinct()
        //                                        ->pluck(DB::raw("EXTRACT(YEAR FROM tgl_awal)"))
        //                                        ->filter()
        //                                        ->sortDesc()
        //                                        ->values()
        //                                        ->map(function($item) { return ['id' => $item, 'nama' => $item]; })
        //                                        ->prepend(['id' => 'semua', 'nama' => 'Semua Tanggal Mulai'])
        //                                        ->toArray();

        // $yearsTglAkhir = SimpegDataHubunganKerja::distinct()
        //                                         ->pluck(DB::raw("EXTRACT(YEAR FROM tgl_akhir)"))
        //                                         ->filter()
        //                                         ->sortDesc()
        //                                         ->values()
        //                                         ->map(function($item) { return ['id' => $item, 'nama' => $item]; })
        //                                         ->prepend(['id' => 'semua', 'nama' => 'Semua Tanggal Selesai'])
        //                                         ->toArray();

        // // Assuming tgl_disetujui also exists and can be filtered by year
        // $yearsTglDisetujui = SimpegDataHubunganKerja::whereNotNull('tgl_disetujui')
        //                                             ->distinct()
        //                                             ->pluck(DB::raw("EXTRACT(YEAR FROM tgl_disetujui)"))
        //                                             ->filter()
        //                                             ->sortDesc()
        //                                             ->values()
        //                                             ->map(function($item) { return ['id' => $item, 'nama' => $item]; })
        //                                             ->prepend(['id' => 'semua', 'nama' => 'Semua Tgl Disetujui'])
        //                                             ->toArray();

        $yearsTglAwal = SimpegDataHubunganKerja::distinct()
                                                    ->pluck(DB::raw("EXTRACT(YEAR FROM tgl_awal) as year"))
                                                    ->filter()
                                                    ->sortDesc()
                                                    ->values()
                                                    ->map(function($item) { return ['id' => $item, 'nama' => $item]; })
                                                    ->prepend(['id' => 'semua', 'nama' => 'Semua Tanggal Mulai'])
                                                    ->toArray();

        $yearsTglAkhir = SimpegDataHubunganKerja::distinct()
                                                    ->pluck(DB::raw("EXTRACT(YEAR FROM tgl_akhir) as year"))  
                                                    ->filter()
                                                    ->sortDesc()
                                                    ->values()
                                                    ->map(function($item) { return ['id' => $item, 'nama' => $item]; })
                                                    ->prepend(['id' => 'semua', 'nama' => 'Semua Tanggal Selesai'])
                                                    ->toArray();

        $yearsTglDisetujui = SimpegDataHubunganKerja::whereNotNull('tgl_disetujui')
                                                    ->distinct()
                                                    ->pluck(DB::raw("EXTRACT(YEAR FROM tgl_disetujui) as year"))  
                                                    ->filter()
                                                    ->sortDesc()
                                                    ->values()
                                                    ->map(function($item) { return ['id' => $item, 'nama' => $item]; })
                                                    ->prepend(['id' => 'semua', 'nama' => 'Semua Tgl Disetujui'])
                                                    ->toArray();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'unit_kerja' => $unitKerjaOptions,
                'jabatan_fungsional' => $jabatanFungsionalOptions,
                'hubungan_kerja' => $hubunganKerjaOptions,
                'status_pengajuan' => $statusPengajuanOptions,
                'tahun_tgl_mulai' => $yearsTglAwal,
                'tahun_tgl_selesai' => $yearsTglAkhir,
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
        $hubunganKerjaOptions = HubunganKerja::select('id', 'nama_hub_kerja as nama')
            ->orderBy('nama_hub_kerja')
            ->get();

        $statusAktifOptions = \App\Models\SimpegStatusAktif::select('id', 'nama_status_aktif as nama')
            ->orderBy('nama_status_aktif')
            ->get();

        $statusPengajuanOptions = [
            ['id' => 'draft', 'nama' => 'Draft'],
            ['id' => 'diajukan', 'nama' => 'Diajukan'],
            ['id' => 'disetujui', 'nama' => 'Disetujui'],
            ['id' => 'ditolak', 'nama' => 'Ditolak'],
        ];

        return [
            'form_options' => [
                'hubungan_kerja' => $hubunganKerjaOptions,
                'status_aktif' => $statusAktifOptions,
                'status_pengajuan' => $statusPengajuanOptions,
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|uuid',
                'no_sk' => 'required|string|max:100',
                'tgl_sk' => 'required|date',
                'tgl_awal' => 'required|date',
                'tgl_akhir' => 'nullable|date|after_or_equal:tgl_awal',
                'pejabat_penetap' => 'nullable|string|max:255',
                'file_hubungan_kerja' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'hubungan_kerja_id' => 'required|uuid',
                'status_aktif_id' => 'nullable|uuid',
                'is_aktif' => 'boolean',
                'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
                'keterangan' => 'nullable|string|max:1000',
            ],
            'field_notes' => [
                'pegawai_id' => 'Pilih pegawai yang bersangkutan.',
                'no_sk' => 'Nomor Surat Keputusan hubungan kerja.',
                'tgl_sk' => 'Tanggal Surat Keputusan hubungan kerja.',
                'tgl_awal' => 'Tanggal mulai hubungan kerja.',
                'tgl_akhir' => 'Tanggal berakhir hubungan kerja (kosongkan jika tidak ada batas waktu).',
                'pejabat_penetap' => 'Pejabat yang menetapkan SK.',
                'file_hubungan_kerja' => 'Unggah file dokumen hubungan kerja (PDF/gambar).',
                'hubungan_kerja_id' => 'Pilih jenis hubungan kerja.',
                'status_aktif_id' => 'Pilih status aktif hubungan kerja.',
                'is_aktif' => 'Centang jika hubungan kerja ini adalah yang aktif saat ini untuk pegawai ini.',
                'status_pengajuan' => 'Status pengajuan data.',
                'keterangan' => 'Keterangan tambahan atau catatan penolakan.'
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
            $jabatanFungsional = $pegawai->dataJabatanFungsional->sortByDesc('tmt_jabatan')->first()->jabatanFungsional;
            if ($jabatanFungsional) {
                $jabatanFungsionalNama = $jabatanFungsional->nama_jabatan_fungsional ?? $jabatanFungsional->nama ?? '-';
            }
        }

        $jabatanStrukturalNama = '-';
        if ($pegawai->dataJabatanStruktural && $pegawai->dataJabatanStruktural->isNotEmpty()) {
            $jabatanStruktural = $pegawai->dataJabatanStruktural->sortByDesc('tgl_mulai')->first();

            if ($jabatanStruktural->jabatanStruktural && $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural;
            } elseif (isset($jabatanStruktural->jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->nama_jabatan;
            } elseif (isset($jabatanStruktural->jabatanStruktural->singkatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->singkatan;
            } elseif (isset($jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->nama_jabatan;
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
            $unitKerja = SimpegUnitKerja::where('kode_unit', $pegawai->unit_kerja_id)->first(); // Use kode_unit for SimpegUnitKerja
            $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $pegawai->unit_kerja_id;
        }

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama' => ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . ($pegawai->nama ?? '-') . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : ''),
            'unit_kerja' => $unitKerjaNama,
            'status' => $pegawai->statusAktif ? $pegawai->statusAktif->nama_status_aktif : '-',
            'jab_akademik' => $jabatanAkademikNama,
            'jab_fungsional' => $jabatanFungsionalNama,
            'jab_struktural' => $jabatanStrukturalNama,
            'pendidikan' => $jenjangPendidikanNama
        ];
    }

    /**
     * Helper: Format data hubungan kerja response for display.
     */
    protected function formatDataHubunganKerja($dataHubunganKerja, $includeActions = true)
    {
        $status = $dataHubunganKerja->status_pengajuan ?? SimpegDataHubunganKerja::STATUS_DRAFT;
        $statusInfo = $this->getStatusInfo($status);

        $pegawai = $dataHubunganKerja->pegawai;
        $nipPegawai = $pegawai ? $pegawai->nip : '-';
        $namaPegawai = $pegawai ? ($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : '') : '-';

        $data = [
            'id' => $dataHubunganKerja->id,
            'pegawai_id' => $dataHubunganKerja->pegawai_id,
            'nip_pegawai' => $nipPegawai,
            'nama_pegawai' => $namaPegawai,
            'no_sk' => $dataHubunganKerja->no_sk,
            'tgl_sk' => $dataHubunganKerja->tgl_sk,
            'tgl_sk_formatted' => $dataHubunganKerja->tgl_sk ? Carbon::parse($dataHubunganKerja->tgl_sk)->format('d M Y') : '-',
            'tgl_awal' => $dataHubunganKerja->tgl_awal,
            'tgl_mulai_formatted' => $dataHubunganKerja->tgl_awal ? Carbon::parse($dataHubunganKerja->tgl_awal)->format('d M Y') : '-',
            'tgl_akhir' => $dataHubunganKerja->tgl_akhir,
            'tgl_selesai_formatted' => $dataHubunganKerja->tgl_akhir ? Carbon::parse($dataHubunganKerja->tgl_akhir)->format('d M Y') : '-',
            'pejabat_penetap' => $dataHubunganKerja->pejabat_penetap,
            'file_hubungan_kerja' => $dataHubunganKerja->file_hubungan_kerja,
            'file_hubungan_kerja_link' => $dataHubunganKerja->file_hubungan_kerja ? Storage::url($dataHubunganKerja->file_hubungan_kerja) : null, // Use Storage::url for public disk paths
            'tgl_input' => $dataHubunganKerja->tgl_input,
            'hubungan_kerja_id' => $dataHubunganKerja->hubungan_kerja_id,
            'hubungan_kerja_label' => $dataHubunganKerja->hubunganKerja ? $dataHubunganKerja->hubunganKerja->nama_hub_kerja : '-',
            'status_aktif_id' => $dataHubunganKerja->status_aktif_id,
            'status_aktif_label' => $dataHubunganKerja->statusAktif ? $dataHubunganKerja->statusAktif->nama_status_aktif : '-',
            'is_aktif' => (bool) $dataHubunganKerja->is_aktif,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataHubunganKerja->keterangan, // This can also be used for rejection notes
            'tgl_diajukan' => $dataHubunganKerja->tgl_diajukan, // Added for full detail
            'tgl_diajukan_formatted' => $dataHubunganKerja->tgl_diajukan ? Carbon::parse($dataHubunganKerja->tgl_diajukan)->format('d M Y H:i:s') : '-',
            'tgl_disetujui' => $dataHubunganKerja->tgl_disetujui,
            'tgl_disetujui_formatted' => $dataHubunganKerja->tgl_disetujui ? Carbon::parse($dataHubunganKerja->tgl_disetujui)->format('d M Y H:i:s') : '-',
            'tgl_ditolak' => $dataHubunganKerja->tgl_ditolak,
            'tgl_ditolak_formatted' => $dataHubunganKerja->tgl_ditolak ? Carbon::parse($dataHubunganKerja->tgl_ditolak)->format('d M Y H:i:s') : '-',
            'created_at' => $dataHubunganKerja->created_at,
            'updated_at' => $dataHubunganKerja->updated_at
        ];

        // Add action URLs if requested (for admin view)
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datahubungankerjaadm/{$dataHubunganKerja->id}"),
                'update_url' => url("/api/admin/datahubungankerjaadm/{$dataHubunganKerja->id}"),
                'delete_url' => url("/api/admin/datahubungankerjaadm/{$dataHubunganKerja->id}"),
                'approve_url' => url("/api/admin/datahubungankerjaadm/{$dataHubunganKerja->id}/approve"),
                'reject_url' => url("/api/admin/datahubungankerjaadm/{$dataHubunganKerja->id}/reject"),
                'to_draft_url' => url("/api/admin/datahubungankerjaadm/{$dataHubunganKerja->id}/todraft"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data hubungan kerja dengan No SK "' . $dataHubunganKerja->no_sk . '"?'
                ],
            ];

            // Admin specific actions based on status
            if (in_array($status, [SimpegDataHubunganKerja::STATUS_DIAJUKAN, SimpegDataHubunganKerja::STATUS_DITOLAK, SimpegDataHubunganKerja::STATUS_DRAFT])) {
                $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data hubungan kerja dengan No SK "' . $dataHubunganKerja->no_sk . '"?'
                ];
            }

            if (in_array($status, [SimpegDataHubunganKerja::STATUS_DIAJUKAN, SimpegDataHubunganKerja::STATUS_DISETUJUI, SimpegDataHubunganKerja::STATUS_DRAFT])) {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Tolak',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data hubungan kerja dengan No SK "' . $dataHubunganKerja->no_sk . '"?',
                    'needs_input' => true,
                    'input_placeholder' => 'Masukkan keterangan penolakan (opsional)'
                ];
            }

            if ($status !== SimpegDataHubunganKerja::STATUS_DRAFT) {
                $data['actions']['to_draft'] = [
                    'url' => $data['aksi']['to_draft_url'],
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data hubungan kerja dengan No SK "' . $dataHubunganKerja->no_sk . '" menjadi draft?'
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