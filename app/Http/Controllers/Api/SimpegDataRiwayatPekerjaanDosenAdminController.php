<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataRiwayatPekerjaanDosen; // Correct model for this controller
use App\Models\SimpegDataPendukung;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegJabatanFungsional; // Import for filter options
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator; // <--- ADDED THIS LINE
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder; // Import for type-hinting scopes
use Illuminate\Support\Str; // Import Str for random string generation

class SimpegDataRiwayatPekerjaanDosenAdminController extends Controller
{
    /**
     * Get all data riwayat pekerjaan for admin (all pegawai).
     * Admin can view data for any employee.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $pegawaiId = $request->pegawai_id;
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $instansi = $request->instansi;
        $jenisPekerjaan = $request->jenis_pekerjaan;
        $jabatan = $request->jabatan;
        $bidangUsaha = $request->bidang_usaha;
        $areaPekerjaan = $request->area_pekerjaan; // boolean or 'semua'
        $mulaiBekerja = $request->mulai_bekerja;
        $selesaiBekerja = $request->selesai_bekerja;
        $statusPengajuan = $request->status_pengajuan ?? 'semua'; // Default to 'semua'

        // Eager load all necessary relations
        $query = SimpegDataRiwayatPekerjaanDosen::with([
            'dataPendukung',
            'pegawai' => function ($q) {
                $q->select('id', 'nip', 'nama', 'unit_kerja_id')
                    ->with([
                        'unitKerja:id,nama_unit',
                        'dataJabatanFungsional' => function ($subQuery) {
                            $subQuery->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1);
                        }
                    ]);
            }
        ]);

        // Apply filters using local scopes
        $query->filterByPegawai($pegawaiId)
              ->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanFungsional($jabatanFungsionalId)
              ->filterByInstansi($instansi)
              ->filterByJenisPekerjaan($jenisPekerjaan)
              ->filterByJabatan($jabatan)
              ->filterByBidangUsaha($bidangUsaha)
              ->filterByAreaPekerjaan($areaPekerjaan)
              ->filterByMulaiBekerja($mulaiBekerja)
              ->filterBySelesaiBekerja($selesaiBekerja)
              ->globalSearch($search)
              ->byStatus($statusPengajuan);

        // Execute query with pagination
        $dataRiwayatPekerjaan = $query->orderBy('mulai_bekerja', 'desc')
                                      ->orderBy('instansi', 'asc')
                                      ->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataRiwayatPekerjaan->getCollection()->transform(function ($item) {
            return $this->formatDataRiwayatPekerjaan($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataRiwayatPekerjaan,
            'empty_data' => $dataRiwayatPekerjaan->isEmpty(),
            'filters' => $this->getFilterOptions(),
            'table_columns' => [
                ['field' => 'nip_pegawai', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'pegawai.nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'pegawai.nama'],
                ['field' => 'instansi', 'label' => 'Nama Instansi', 'sortable' => true, 'sortable_field' => 'instansi'],
                ['field' => 'jenis_pekerjaan', 'label' => 'Jenis Pekerjaan', 'sortable' => true, 'sortable_field' => 'jenis_pekerjaan'],
                ['field' => 'area_pekerjaan_label', 'label' => 'Area Pekerjaan', 'sortable' => true, 'sortable_field' => 'area_pekerjaan'],
                ['field' => 'periode_bekerja', 'label' => 'Periode', 'sortable' => true, 'sortable_field' => 'mulai_bekerja'],
                ['field' => 'status_info.label', 'label' => 'Status', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/datariwayatpekerjaanadm/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/datariwayatpekerjaanadm/batch/reject"),
                    'method' => 'PATCH',
                    'label' => 'Tolak Terpilih',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menolak data terpilih?',
                    'needs_input' => true,
                    'input_placeholder' => 'Keterangan penolakan (opsional)'
                ],
                'to_draft' => [ // New action: Change to Draft
                    'url' => url("/api/admin/datariwayatpekerjaanadm/batch/todraft"),
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft Terpilih',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data terpilih menjadi draft?'
                ],
                'delete' => [
                    'url' => url("/api/admin/datariwayatpekerjaanadm/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data terpilih?',
                ]
            ],
            'pagination' => [
                'current_page' => $dataRiwayatPekerjaan->currentPage(),
                'per_page' => $dataRiwayatPekerjaan->perPage(),
                'total' => $dataRiwayatPekerjaan->total(),
                'last_page' => $dataRiwayatPekerjaan->lastPage(),
                'from' => $dataRiwayatPekerjaan->firstItem(),
                'to' => $dataRiwayatPekerjaan->lastItem()
            ]
        ]);
    }

    /**
     * Get detail data riwayat pekerjaan.
     * Admin can view details for any employee's data.
     */
    public function show($id)
    {
        $dataRiwayatPekerjaan = SimpegDataRiwayatPekerjaanDosen::with([
            'dataPendukung',
            'pegawai' => function ($q) {
                $q->with([
                    'unitKerja', 'statusAktif', 'jabatanAkademik',
                    'dataJabatanFungsional' => function ($query) {
                        $query->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1);
                    },
                    'dataJabatanStruktural' => function ($query) {
                        $query->with('jabatanStruktural.jenisJabatanStruktural')->orderBy('tgl_mulai', 'desc')->limit(1);
                    },
                    'dataPendidikanFormal' => function ($query) {
                        $query->with('jenjangPendidikan')->orderBy('jenjang_pendidikan_id', 'desc')->limit(1);
                    }
                ]);
            }
        ])->find($id);

        if (!$dataRiwayatPekerjaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai_info_detail' => $this->formatPegawaiInfo($dataRiwayatPekerjaan->pegawai),
            'data' => $this->formatDataRiwayatPekerjaan($dataRiwayatPekerjaan, false),
            'form_options' => $this->getFormOptions(), // Form options for create/update
            'dokumen_pendukung' => $dataRiwayatPekerjaan->dataPendukung->map(function($dok) {
                return [
                    'id' => $dok->id,
                    'tipe_dokumen' => $dok->tipe_dokumen,
                    'nama_dokumen' => $dok->nama_dokumen,
                    'jenis_dokumen_id' => $dok->jenis_dokumen_id,
                    'keterangan' => $dok->keterangan,
                    'file_url' => $dok->file_url,
                    'file_exists' => $dok->file_exists,
                    'file_size_formatted' => $dok->file_size_formatted,
                    'file_extension' => $dok->file_extension
                ];
            })
        ]);
    }

    /**
     * Store new data riwayat pekerjaan (Admin Operational).
     * Admin can add data for any employee. Auto-sets status to 'disetujui' by default.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id', // Required for admin
            'bidang_usaha' => 'nullable|string|max:100',
            'jenis_pekerjaan' => 'required|string|max:100',
            'jabatan' => 'required|string|max:100',
            'instansi' => 'required|string|max:255',
            'divisi' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string',
            'mulai_bekerja' => 'required|date',
            'selesai_bekerja' => 'nullable|date|after_or_equal:mulai_bekerja',
            'area_pekerjaan' => 'required|boolean', // 0: luar, 1: dalam
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak', // 'ditangguhkan' removed from here
            'keterangan' => 'nullable|string',
            'keterangan_penolakan' => 'nullable|string|max:500', // Admin can add rejection notes
            // Dokumen pendukung (polymorphic)
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.tipe_dokumen' => 'required_with:dokumen_pendukung|string|in:Surat_Keterangan_Kerja,SK_Penempatan,Kontrak_Kerja,Dokumen_Lainnya', // Sesuaikan dengan CHECK constraint di DB Anda
            'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string|max:255',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|integer', // Optional
            'dokumen_pendukung.*.keterangan' => 'nullable|string|max:1000',
            'dokumen_pendukung.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for uniqueness based on pegawai, instansi, jabatan, mulai_bekerja
        $existingPekerjaan = SimpegDataRiwayatPekerjaanDosen::where('pegawai_id', $request->pegawai_id)
            ->where('instansi', $request->instansi)
            ->where('jabatan', $request->jabatan)
            ->where('mulai_bekerja', $request->mulai_bekerja)
            ->first();

        if ($existingPekerjaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan dengan instansi, jabatan, dan tanggal mulai yang sama sudah ada untuk pegawai ini.'
            ], 422);
        }

        $data = $validator->validated();
        unset($data['dokumen_pendukung']); // Exclude nested array from main model creation
        // No need to unset keterangan_penolakan and tgl_ditangguhkan as they are no longer in validatedData if not in request
        // and are removed from fillable of the model

        $data['tgl_input'] = now()->toDateString();
        // Admin can set the status directly, default to 'disetujui'
        $data['status_pengajuan'] = $request->input('status_pengajuan', 'disetujui'); 

        // Set timestamps based on status
        if ($data['status_pengajuan'] === 'disetujui') {
            $data['tgl_disetujui'] = now();
            $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? now(); 
        } elseif ($data['status_pengajuan'] === 'diajukan') {
            $data['tgl_diajukan'] = now();
            $data['tgl_disetujui'] = null;
        } elseif ($data['status_pengajuan'] === 'ditolak') {
            $data['tgl_ditolak'] = now();
            $data['tgl_diajukan'] = null;
            $data['tgl_disetujui'] = null;
        } else { // 'draft'
            $data['tgl_diajukan'] = null;
            $data['tgl_disetujui'] = null;
            $data['tgl_ditolak'] = null;
        }


        DB::beginTransaction();
        try {
            $dataRiwayatPekerjaan = SimpegDataRiwayatPekerjaanDosen::create($data);

            // Handle multiple supporting documents
            if ($request->has('dokumen_pendukung') && is_array($request->dokumen_pendukung)) {
                $this->storeDokumenPendukung($request->dokumen_pendukung, $dataRiwayatPekerjaan, $request->pegawai_id, $request); // Pass $request for file instances
            }

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_create_riwayat_pekerjaan', $dataRiwayatPekerjaan, $dataRiwayatPekerjaan->toArray());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data riwayat pekerjaan berhasil ditambahkan oleh admin',
                'data' => $this->formatDataRiwayatPekerjaan($dataRiwayatPekerjaan->load(['dataPendukung', 'pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional']))
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
     * Update data riwayat pekerjaan (Admin Operational).
     * Admin can edit any data regardless of status.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function update(Request $request, $id)
    {
        $dataRiwayatPekerjaan = SimpegDataRiwayatPekerjaanDosen::find($id);

        if (!$dataRiwayatPekerjaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|integer|exists:simpeg_pegawai,id',
            'bidang_usaha' => 'nullable|string|max:100',
            'jenis_pekerjaan' => 'sometimes|string|max:100',
            'jabatan' => 'sometimes|string|max:100',
            'instansi' => 'sometimes|string|max:255',
            'divisi' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string',
            'mulai_bekerja' => 'sometimes|date',
            'selesai_bekerja' => 'nullable|date|after_or_equal:mulai_bekerja',
            'area_pekerjaan' => 'sometimes|boolean',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak', // 'ditangguhkan' removed from here
            'keterangan' => 'nullable|string',
            'keterangan_penolakan' => 'nullable|string|max:500', // Admin can add rejection notes
            // Dokumen pendukung (polymorphic)
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.id' => 'nullable|integer|exists:simpeg_data_pendukung,id',
            'dokumen_pendukung.*.tipe_dokumen' => 'required_with:dokumen_pendukung|string|in:Surat_Keterangan_Kerja,SK_Penempatan,Kontrak_Kerja,Dokumen_Lainnya', // Sesuaikan dengan CHECK constraint di DB Anda
            'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string|max:255',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|integer',
            'dokumen_pendukung.*.keterangan' => 'nullable|string|max:1000',
            'dokumen_pendukung.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'dokumen_pendukung_to_delete' => 'nullable|array', // Array of IDs to delete
            'dokumen_pendukung_to_delete.*' => 'nullable|integer|exists:simpeg_data_pendukung,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check for uniqueness if updated (pegawai, instansi, jabatan, mulai_bekerja)
        if ($request->hasAny(['pegawai_id', 'instansi', 'jabatan', 'mulai_bekerja'])) {
            $targetPegawaiId = $request->input('pegawai_id', $dataRiwayatPekerjaan->pegawai_id);
            $targetInstansi = $request->input('instansi', $dataRiwayatPekerjaan->instansi);
            $targetJabatan = $request->input('jabatan', $dataRiwayatPekerjaan->jabatan);
            $targetMulaiBekerja = $request->input('mulai_bekerja', $dataRiwayatPekerjaan->mulai_bekerja);

            $existingPekerjaan = SimpegDataRiwayatPekerjaanDosen::where('pegawai_id', $targetPegawaiId)
                ->where('instansi', $targetInstansi)
                ->where('jabatan', $targetJabatan)
                ->where('mulai_bekerja', $targetMulaiBekerja)
                ->where('id', '!=', $id)
                ->first();

            if ($existingPekerjaan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data riwayat pekerjaan dengan instansi, jabatan, dan tanggal mulai yang sama sudah ada untuk pegawai ini.'
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $oldData = $dataRiwayatPekerjaan->getOriginal();
            $data = $validator->validated();
            unset($data['dokumen_pendukung']); // Exclude nested array from main model update
            unset($data['dokumen_pendukung_to_delete']); // Exclude delete array
            // No need to unset keterangan_penolakan and tgl_ditangguhkan as they are no longer in validatedData if not in request
            // and are removed from fillable of the model

            // Handle status_pengajuan and timestamps related
            if (isset($data['status_pengajuan']) && $data['status_pengajuan'] !== $dataRiwayatPekerjaan->status_pengajuan) {
                switch ($data['status_pengajuan']) {
                    case 'diajukan':
                        $data['tgl_diajukan'] = now();
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                    case 'disetujui':
                        $data['tgl_disetujui'] = now();
                        $data['tgl_diajukan'] = $dataRiwayatPekerjaan->tgl_diajukan ?? now(); 
                        $data['tgl_ditolak'] = null;
                        break;
                    case 'ditolak':
                        $data['tgl_ditolak'] = now();
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        break;
                    case 'ditangguhkan': // This case should theoretically not be reachable if status is removed from model/DB
                                       // But if it was sent by client for existing data, ensure it's handled gracefully
                        // Convert ditangguhkan to draft for admin
                        $data['status_pengajuan'] = 'draft';
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                    case 'draft':
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                }
            } else {
                // If status is not changed, retain existing timestamps
                $data['tgl_diajukan'] = $dataRiwayatPekerjaan->tgl_diajukan;
                $data['tgl_disetujui'] = $dataRiwayatPekerjaan->tgl_disetujui;
                $data['tgl_ditolak'] = $dataRiwayatPekerjaan->tgl_ditolak;
            }
            // Retain existing 'keterangan_penolakan' if not explicitly updated to null or a new value
            if (!isset($data['keterangan_penolakan'])) {
                $data['keterangan_penolakan'] = $dataRiwayatPekerjaan->keterangan_penolakan;
            }


            $dataRiwayatPekerjaan->update($data);

            // Handle multiple supporting documents (polymorphic)
            $this->updateDokumenPendukung($request, $dataRiwayatPekerjaan, $dataRiwayatPekerjaan->pegawai_id);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_update_riwayat_pekerjaan', $dataRiwayatPekerjaan, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data riwayat pekerjaan berhasil diperbarui oleh admin',
                'data' => $this->formatDataRiwayatPekerjaan($dataRiwayatPekerjaan->load(['dataPendukung', 'pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional']))
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
     * Delete data riwayat pekerjaan.
     * Admin can delete any data.
     *
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function destroy($id)
    {
        $dataRiwayatPekerjaan = SimpegDataRiwayatPekerjaanDosen::find($id);

        if (!$dataRiwayatPekerjaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete associated supporting documents (polymorphic)
            $dokumenPendukung = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataRiwayatPekerjaanDosen')
                ->where('pendukungable_id', $id)
                ->get();

            foreach ($dokumenPendukung as $dokumen) {
                if (method_exists($dokumen, 'deleteFile')) {
                    $dokumen->deleteFile(); // Delete actual file from storage
                } else {
                    Storage::disk('public')->delete($dokumen->file_path); // Fallback
                }
                $dokumen->delete(); // Delete record from DB
            }

            $oldData = $dataRiwayatPekerjaan->toArray();
            $dataRiwayatPekerjaan->delete();

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_delete_riwayat_pekerjaan', $dataRiwayatPekerjaan, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data riwayat pekerjaan berhasil dihapus'
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
     * @return \Illuminate->Http->JsonResponse
     */
    public function approve($id)
    {
        $dataRiwayatPekerjaan = SimpegDataRiwayatPekerjaanDosen::find($id);

        if (!$dataRiwayatPekerjaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan tidak ditemukan'
            ], 404);
        }

        if ($dataRiwayatPekerjaan->status_pengajuan === 'disetujui') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah disetujui sebelumnya'
            ], 409);
        }

        $oldData = $dataRiwayatPekerjaan->getOriginal();
        $dataRiwayatPekerjaan->update([
            'status_pengajuan' => 'disetujui',
            'tgl_disetujui' => now(),
            'tgl_ditolak' => null,
            // 'tgl_ditangguhkan' => null, // Dihapus
            'keterangan_penolakan' => null, // Clear rejection note
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_approve_riwayat_pekerjaan', $dataRiwayatPekerjaan, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat pekerjaan berhasil disetujui'
        ]);
    }

    /**
     * Admin: Reject a single data entry.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function reject(Request $request, $id)
    {
        $dataRiwayatPekerjaan = SimpegDataRiwayatPekerjaanDosen::find($id);

        if (!$dataRiwayatPekerjaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan tidak ditemukan'
            ], 404);
        }

        if ($dataRiwayatPekerjaan->status_pengajuan === 'ditolak') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah ditolak sebelumnya'
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'keterangan_penolakan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $dataRiwayatPekerjaan->getOriginal();
        $dataRiwayatPekerjaan->update([
            'status_pengajuan' => 'ditolak',
            'tgl_ditolak' => now(),
            'tgl_diajukan' => null,
            'tgl_disetujui' => null,
            // 'tgl_ditangguhkan' => null, // Dihapus
            'keterangan_penolakan' => $request->keterangan_penolakan,
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_reject_riwayat_pekerjaan', $dataRiwayatPekerjaan, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat pekerjaan berhasil ditolak'
        ]);
    }

    /**
     * Admin: Change status to 'draft' for a single data entry.
     *
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function toDraft($id)
    {
        $dataRiwayatPekerjaan = SimpegDataRiwayatPekerjaanDosen::find($id);

        if (!$dataRiwayatPekerjaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan tidak ditemukan'
            ], 404);
        }

        if ($dataRiwayatPekerjaan->status_pengajuan === 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah dalam status draft'
            ], 409);
        }

        $oldData = $dataRiwayatPekerjaan->getOriginal();
        $dataRiwayatPekerjaan->update([
            'status_pengajuan' => 'draft',
            'tgl_diajukan' => null,
            'tgl_disetujui' => null,
            'tgl_ditolak' => null,
            // 'tgl_ditangguhkan' => null, // Dihapus
            'keterangan_penolakan' => null, // Clear rejection note
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_to_draft_riwayat_pekerjaan', $dataRiwayatPekerjaan, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status riwayat pekerjaan berhasil diubah menjadi draft'
        ]);
    }

    /**
     * Admin: Batch delete data riwayat pekerjaan.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_riwayat_pekerjaan,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataRiwayatPekerjaanList = SimpegDataRiwayatPekerjaanDosen::whereIn('id', $request->ids)->get();

        if ($dataRiwayatPekerjaanList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data riwayat pekerjaan yang ditemukan untuk dihapus'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataRiwayatPekerjaanList as $dataRiwayatPekerjaan) {
                try {
                    // Delete associated supporting documents (polymorphic)
                    $dokumenPendukung = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataRiwayatPekerjaanDosen')
                        ->where('pendukungable_id', $dataRiwayatPekerjaan->id)
                        ->get();

                    foreach ($dokumenPendukung as $dokumen) {
                        if (method_exists($dokumen, 'deleteFile')) {
                            $dokumen->deleteFile(); // Delete actual file from storage
                        } else {
                            Storage::disk('public')->delete($dokumen->file_path); // Fallback
                        }
                        $dokumen->delete(); // Delete record from DB
                    }

                    $oldData = $dataRiwayatPekerjaan->toArray();
                    $dataRiwayatPekerjaan->delete();
                    
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_delete_riwayat_pekerjaan', $dataRiwayatPekerjaan, $oldData);
                    }
                    $deletedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $dataRiwayatPekerjaan->id,
                        'instansi' => $dataRiwayatPekerjaan->instansi,
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
                'message' => "Berhasil menghapus {$deletedCount} data riwayat pekerjaan",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data riwayat pekerjaan",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }

    /**
     * Admin: Batch approve data riwayat pekerjaan.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_riwayat_pekerjaan,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataRiwayatPekerjaanDosen::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', ['draft', 'diajukan', 'ditolak']) // 'ditangguhkan' removed from here
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data riwayat pekerjaan yang memenuhi syarat untuk disetujui.'
            ], 404);
        }

        $updatedCount = 0;
        $approvedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => 'disetujui',
                    'tgl_disetujui' => now(),
                    'tgl_ditolak' => null,
                    // 'tgl_ditangguhkan' => null, // Dihapus
                    'keterangan_penolakan' => null, // Clear rejection note
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_approve_riwayat_pekerjaan', $item, $oldData);
                }
                $updatedCount++;
                $approvedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch approve riwayat pekerjaan: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data riwayat pekerjaan",
            'updated_count' => $updatedCount,
            'approved_ids' => $approvedIds
        ]);
    }

    /**
     * Admin: Batch reject data riwayat pekerjaan.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_riwayat_pekerjaan,id',
            'keterangan_penolakan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataRiwayatPekerjaanDosen::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', ['draft', 'diajukan', 'disetujui']) // 'ditangguhkan' removed from here
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data riwayat pekerjaan yang memenuhi syarat untuk ditolak.'
            ], 404);
        }

        $updatedCount = 0;
        $rejectedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => 'ditolak',
                    'tgl_ditolak' => now(),
                    'tgl_diajukan' => null,
                    'tgl_disetujui' => null,
                    // 'tgl_ditangguhkan' => null, // Dihapus
                    'keterangan_penolakan' => $request->keterangan_penolakan,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_reject_riwayat_pekerjaan', $item, $oldData);
                }
                $updatedCount++;
                $rejectedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch reject riwayat pekerjaan: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data riwayat pekerjaan",
            'updated_count' => $updatedCount,
            'rejected_ids' => $rejectedIds
        ]);
    }

    /**
     * Admin: Batch change status to 'draft'.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function batchToDraft(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_riwayat_pekerjaan,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataRiwayatPekerjaanDosen::whereIn('id', $request->ids)
            ->where('status_pengajuan', '!=', 'draft') // Only process if not already draft
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data riwayat pekerjaan yang memenuhi syarat untuk diubah menjadi draft.'
            ], 404);
        }

        $updatedCount = 0;
        $draftedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => 'draft',
                    'tgl_diajukan' => null,
                    'tgl_disetujui' => null,
                    'tgl_ditolak' => null,
                    // 'tgl_ditangguhkan' => null, // Dihapus
                    'keterangan_penolakan' => null, // Clear rejection note
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_batch_to_draft_riwayat_pekerjaan', $item, $oldData);
                }
                $updatedCount++;
                $draftedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch change to draft for riwayat pekerjaan: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah status ke draft secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengubah {$updatedCount} data riwayat pekerjaan menjadi draft",
            'updated_count' => $updatedCount,
            'drafted_ids' => $draftedIds
        ]);
    }

    /**
     * Get status statistics for dashboard.
     * Admin can filter statistics by unit, functional position, and employee.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function getStatusStatistics(Request $request)
    {
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $pegawaiId = $request->pegawai_id;

        $query = SimpegDataRiwayatPekerjaanDosen::query();

        if ($pegawaiId && $pegawaiId != 'semua') {
            $query->where('pegawai_id', $pegawaiId);
        }

        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $unitKerjaTarget = SimpegUnitKerja::find($unitKerjaId);
            if ($unitKerjaTarget) {
                $unitIdsInScope = SimpegUnitKerja::getAllChildIdsRecursively($unitKerjaTarget);
                $query->whereHas('pegawai', function ($q) use ($unitIdsInScope) {
                    $q->whereIn('unit_kerja_id', $unitIdsInScope);
                });
            }
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
            // 'ditangguhkan' => 0, // Dihapus dari default stats
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
     * @return \Illuminate->Http->JsonResponse
     */
    public function getFilterOptions()
    {
        // Get distinct values for specific filters
        $instansiList = SimpegDataRiwayatPekerjaanDosen::distinct()->pluck('instansi')->filter()->values()->map(function($item) { return ['id' => $item, 'nama' => $item]; })->prepend(['id' => 'semua', 'nama' => 'Semua Instansi'])->toArray();
        $jenisPekerjaanList = SimpegDataRiwayatPekerjaanDosen::distinct()->pluck('jenis_pekerjaan')->filter()->values()->map(function($item) { return ['id' => $item, 'nama' => $item]; })->prepend(['id' => 'semua', 'nama' => 'Semua Jenis Pekerjaan'])->toArray();
        $jabatanList = SimpegDataRiwayatPekerjaanDosen::distinct()->pluck('jabatan')->filter()->values()->map(function($item) { return ['id' => $item, 'nama' => $item]; })->prepend(['id' => 'semua', 'nama' => 'Semua Jabatan'])->toArray();
        $bidangUsahaList = SimpegDataRiwayatPekerjaanDosen::distinct()->pluck('bidang_usaha')->filter()->values()->map(function($item) { return ['id' => $item, 'nama' => $item]; })->prepend(['id' => 'semua', 'nama' => 'Semua Bidang Usaha'])->toArray();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'instansi' => $instansiList,
                'jenis_pekerjaan' => $jenisPekerjaanList,
                'jabatan' => $jabatanList,
                'bidang_usaha' => $bidangUsahaList,
                'area_pekerjaan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => true, 'nama' => 'Dalam Negeri'],
                    ['id' => false, 'nama' => 'Luar Negeri']
                ],
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                    // ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan'], // Dihapus dari filter options
                ],
                'unit_kerja_options' => SimpegUnitKerja::select('id as id', 'nama_unit as nama')->orderBy('nama_unit')->get()->prepend(['id' => 'semua', 'nama' => 'Semua Unit Kerja']),
                'jabatan_fungsional_options' => SimpegJabatanFungsional::select('id', 'nama_jabatan_fungsional as nama')->orderBy('nama_jabatan_fungsional')->get()->prepend(['id' => 'semua', 'nama' => 'Semua Jabatan Fungsional']),
                'pegawai_options' => SimpegPegawai::select('id as value', 'nama as label', 'nip')->orderBy('nama')->get()->map(function($item) { return ['value' => $item->value, 'label' => $item->nip . ' - ' . $item->label]; })->prepend(['value' => 'semua', 'label' => 'Semua Pegawai']),
            ]
        ]);
    }

    /**
     * Get form options for create/update forms.
     * This includes basic validation rules and field notes.
     */
    public function getFormOptions()
    {
        // Contoh untuk tipe_dokumen, SESUAIKAN DENGAN CHECK CONSTRAINT DI DATABASE ANDA
        $tipeDokumenOptions = [
            ['id' => 'Surat_Keterangan_Kerja', 'nama' => 'Surat Keterangan Kerja'],
            ['id' => 'SK_Penempatan', 'nama' => 'SK Penempatan'],
            ['id' => 'Kontrak_Kerja', 'nama' => 'Kontrak Kerja'],
            ['id' => 'Dokumen_Lainnya', 'nama' => 'Dokumen Lainnya'],
        ];

        return [
            'form_options' => [
                'area_pekerjaan' => [
                    ['id' => true, 'nama' => 'Dalam Negeri'],
                    ['id' => false, 'nama' => 'Luar Negeri']
                ],
                'status_pengajuan' => [ // Admin can select status directly
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                    // ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan'], // Dihapus dari form options
                ],
                'tipe_dokumen_options' => $tipeDokumenOptions, // For dynamic document upload form
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|integer',
                'bidang_usaha' => 'nullable|string|max:100',
                'jenis_pekerjaan' => 'required|string|max:100',
                'jabatan' => 'required|string|max:100',
                'instansi' => 'required|string|max:255',
                'divisi' => 'nullable|string|max:100',
                'deskripsi' => 'nullable|string',
                'mulai_bekerja' => 'required|date',
                'selesai_bekerja' => 'nullable|date|after_or_equal:mulai_bekerja',
                'area_pekerjaan' => 'required|boolean',
                'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak', // 'ditangguhkan' removed from here
                'keterangan' => 'nullable|string|max:1000',
                'keterangan_penolakan' => 'nullable|string|max:500',
                'dokumen_pendukung' => 'nullable|array',
                'dokumen_pendukung.*.id' => 'nullable|integer|exists:simpeg_data_pendukung,id',
                'dokumen_pendukung.*.tipe_dokumen' => 'required_with:dokumen_pendukung|string|in:Surat_Keterangan_Kerja,SK_Penempatan,Kontrak_Kerja,Dokumen_Lainnya', // SESUAIKAN
                'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string|max:255',
                'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|integer',
                'dokumen_pendukung.*.keterangan' => 'nullable|string|max:1000',
                'dokumen_pendukung.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
                'dokumen_pendukung_to_delete' => 'nullable|array',
                'dokumen_pendukung_to_delete.*' => 'nullable|integer|exists:simpeg_data_pendukung,id'
            ],
            'field_notes' => [
                'pegawai_id' => 'Pilih pegawai yang bersangkutan.',
                'bidang_usaha' => 'Bidang usaha atau industri tempat bekerja.',
                'jenis_pekerjaan' => 'Jenis pekerjaan atau profesi (contoh: Dosen, Peneliti, Pegawai Swasta).',
                'jabatan' => 'Jabatan terakhir atau posisi yang diduduki.',
                'instansi' => 'Nama institusi/perusahaan tempat bekerja.',
                'divisi' => 'Divisi atau departemen (jika ada).',
                'deskripsi' => 'Deskripsi singkat pekerjaan atau tanggung jawab.',
                'mulai_bekerja' => 'Tanggal mulai bekerja.',
                'selesai_bekerja' => 'Tanggal selesai bekerja (kosongkan jika masih bekerja).',
                'area_pekerjaan' => 'Lokasi area pekerjaan (Dalam Negeri / Luar Negeri).',
                'keterangan' => 'Keterangan tambahan mengenai riwayat pekerjaan.',
                'dokumen_pendukung' => 'Unggah dokumen pendukung seperti surat keterangan kerja, SK penempatan, dll.',
                'keterangan_penolakan' => 'Keterangan jika pengajuan ditolak.',
            ],
        ];
    }

    /**
     * Store supporting documents.
     *
     * @param array $dokumenArray
     * @param SimpegDataRiwayatPekerjaanDosen $riwayatPekerjaan
     * @param int $pegawaiId The ID of the associated employee
     * @param Request $request The full request object to get file instances
     * @return void
     */
    private function storeDokumenPendukung($dokumenArray, $riwayatPekerjaan, $pegawaiId, Request $request)
    {
        foreach ($dokumenArray as $index => $dokumen) {
            $dokumenData = [
                'tipe_dokumen' => $dokumen['tipe_dokumen'],
                'nama_dokumen' => $dokumen['nama_dokumen'],
                'jenis_dokumen_id' => $dokumen['jenis_dokumen_id'] ?? null,
                'keterangan' => $dokumen['keterangan'] ?? null,
                'pendukungable_type' => 'App\Models\SimpegDataRiwayatPekerjaanDosen',
                'pendukungable_id' => $riwayatPekerjaan->id
            ];

            // Get the file instance from the request using its nested path
            $file = $request->file("dokumen_pendukung.{$index}.file");

            if ($file && $file instanceof \Illuminate\Http\UploadedFile) {
                $fileName = 'riwayat_pekerjaan_dok_' . $pegawaiId . '_' . $riwayatPekerjaan->id . '_' . time() . '_' . $index . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('riwayat_pekerjaan_dokumen', $fileName, 'public'); // path: storage/app/public/riwayat_pekerjaan_dokumen
                $dokumenData['file_path'] = $filePath;
            } else {
                $dokumenData['file_path'] = null;
            }

            SimpegDataPendukung::create($dokumenData);
        }
    }

    /**
     * Update supporting documents (add new, update existing, delete old).
     *
     * @param Request $request
     * @param SimpegDataRiwayatPekerjaanDosen $riwayatPekerjaan
     * @param int $pegawaiId The ID of the associated employee
     * @return void
     */
    private function updateDokumenPendukung(Request $request, $riwayatPekerjaan, $pegawaiId)
    {
        // 1. Delete documents flagged for deletion
        if ($request->has('dokumen_pendukung_to_delete') && is_array($request->dokumen_pendukung_to_delete)) {
            $deleteIds = $request->dokumen_pendukung_to_delete;
            $oldDokumen = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataRiwayatPekerjaanDosen')
                ->where('pendukungable_id', $riwayatPekerjaan->id)
                ->whereIn('id', $deleteIds)
                ->get();

            foreach ($oldDokumen as $dok) {
                if (method_exists($dok, 'deleteFile')) {
                    $dok->deleteFile();
                } else {
                    Storage::disk('public')->delete($dok->file_path);
                }
                $dok->delete();
            }
        }

        // 2. Add/Update new/existing documents
        if ($request->has('dokumen_pendukung') && is_array($request->dokumen_pendukung)) {
            foreach ($request->dokumen_pendukung as $index => $dokumenData) {
                $file = $request->file('dokumen_pendukung.' . $index . '.file'); // Get file from nested input

                $payload = [
                    'tipe_dokumen' => $dokumenData['tipe_dokumen'],
                    'nama_dokumen' => $dokumenData['nama_dokumen'],
                    'jenis_dokumen_id' => $dokumenData['jenis_dokumen_id'] ?? null,
                    'keterangan' => $dokumenData['keterangan'] ?? null,
                    'pendukungable_type' => 'App\Models\SimpegDataRiwayatPekerjaanDosen',
                    'pendukungable_id' => $riwayatPekerjaan->id
                ];

                if ($file) {
                    $fileName = 'riwayat_pekerjaan_dok_' . $pegawaiId . '_' . $riwayatPekerjaan->id . '_' . time() . '_' . $index . '.' . $file->getClientOriginalExtension();
                    $filePath = $file->storeAs('riwayat_pekerjaan_dokumen', $fileName, 'public');
                    $payload['file_path'] = $filePath;
                }

                if (isset($dokumenData['id']) && $dokumenData['id']) {
                    // Update existing document
                    $existingDok = SimpegDataPendukung::find($dokumenData['id']);
                    if ($existingDok) {
                        // If new file is uploaded, delete old one before updating
                        if ($file && $existingDok->file_path) {
                            Storage::disk('public')->delete($existingDok->file_path);
                        }
                        $existingDok->update($payload);
                    }
                } else {
                    // Create new document
                    SimpegDataPendukung::create($payload);
                }
            }
        }
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
            $highestEducation = $pegawai->dataPendidikanFormal->sortByDesc('jenjang_pendidikan_id')->first();
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

    /**
     * Helper: Format data riwayat pekerjaan response for display.
     */
    protected function formatDataRiwayatPekerjaan($dataRiwayatPekerjaan, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataRiwayatPekerjaan->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $pegawai = $dataRiwayatPekerjaan->pegawai;
        $nipPegawai = $pegawai ? $pegawai->nip : '-';
        $namaPegawai = $pegawai ? $pegawai->nama : '-';

        $periodeBekerja = '';
        if ($dataRiwayatPekerjaan->mulai_bekerja) {
            $periodeBekerja = Carbon::parse($dataRiwayatPekerjaan->mulai_bekerja)->format('d M Y');
            if ($dataRiwayatPekerjaan->selesai_bekerja) {
                $periodeBekerja .= ' - ' . Carbon::parse($dataRiwayatPekerjaan->selesai_bekerja)->format('d M Y');
            } else {
                $periodeBekerja .= ' - Sekarang';
            }
        }

        $data = [
            'id' => $dataRiwayatPekerjaan->id,
            'pegawai_id' => $dataRiwayatPekerjaan->pegawai_id,
            'nip_pegawai' => $nipPegawai,
            'nama_pegawai' => $namaPegawai,
            'bidang_usaha' => $dataRiwayatPekerjaan->bidang_usaha,
            'jenis_pekerjaan' => $dataRiwayatPekerjaan->jenis_pekerjaan,
            'jabatan' => $dataRiwayatPekerjaan->jabatan,
            'instansi' => $dataRiwayatPekerjaan->instansi,
            'divisi' => $dataRiwayatPekerjaan->divisi,
            'deskripsi' => $dataRiwayatPekerjaan->deskripsi,
            'mulai_bekerja' => $dataRiwayatPekerjaan->mulai_bekerja,
            'mulai_bekerja_formatted' => $dataRiwayatPekerjaan->mulai_bekerja ? Carbon::parse($dataRiwayatPekerjaan->mulai_bekerja)->format('d M Y') : '-',
            'selesai_bekerja' => $dataRiwayatPekerjaan->selesai_bekerja,
            'selesai_bekerja_formatted' => $dataRiwayatPekerjaan->selesai_bekerja ? Carbon::parse($dataRiwayatPekerjaan->selesai_bekerja)->format('d M Y') : '-',
            'periode_bekerja' => $periodeBekerja,
            'area_pekerjaan' => $dataRiwayatPekerjaan->area_pekerjaan,
            'area_pekerjaan_label' => $dataRiwayatPekerjaan->area_pekerjaan ? 'Dalam Negeri' : 'Luar Negeri',
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataRiwayatPekerjaan->keterangan,
            'keterangan_penolakan' => $dataRiwayatPekerjaan->keterangan_penolakan ?? null, // Include rejection note (assuming it exists in DB for historical data)
            'timestamps' => [
                'tgl_input' => $dataRiwayatPekerjaan->tgl_input,
                'tgl_diajukan' => $dataRiwayatPekerjaan->tgl_diajukan ?? null,
                'tgl_disetujui' => $dataRiwayatPekerjaan->tgl_disetujui ?? null,
                'tgl_ditolak' => $dataRiwayatPekerjaan->tgl_ditolak ?? null,
                // 'tgl_ditangguhkan' => $dataRiwayatPekerjaan->tgl_ditangguhkan ?? null, // Dihapus
            ],
            'created_at' => $dataRiwayatPekerjaan->created_at,
            'updated_at' => $dataRiwayatPekerjaan->updated_at
        ];

        // Add action URLs if requested (for admin view)
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datariwayatpekerjaanadm/{$dataRiwayatPekerjaan->id}"),
                'update_url' => url("/api/admin/datariwayatpekerjaanadm/{$dataRiwayatPekerjaan->id}"),
                'delete_url' => url("/api/admin/datariwayatpekerjaanadm/{$dataRiwayatPekerjaan->id}"),
                'approve_url' => url("/api/admin/datariwayatpekerjaanadm/{$dataRiwayatPekerjaan->id}/approve"),
                'reject_url' => url("/api/admin/datariwayatpekerjaanadm/{$dataRiwayatPekerjaan->id}/reject"),
                'to_draft_url' => url("/api/admin/datariwayatpekerjaanadm/{$dataRiwayatPekerjaan->id}/todraft"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data riwayat pekerjaan "' . $dataRiwayatPekerjaan->instansi . ' - ' . $dataRiwayatPekerjaan->jabatan . '"?'
                ],
            ];

            // Admin specific actions based on status
            // Changed: ditangguhkan removed from this array as it no longer exists
            if (in_array($status, ['diajukan', 'ditolak', 'draft'])) {
                 $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data riwayat pekerjaan "' . $dataRiwayatPekerjaan->instansi . ' - ' . $dataRiwayatPekerjaan->jabatan . '"?'
                ];
            }

            // Changed: ditangguhkan removed from this array as it no longer exists
            if (in_array($status, ['diajukan', 'disetujui', 'draft'])) {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Tolak',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data riwayat pekerjaan "' . $dataRiwayatPekerjaan->instansi . ' - ' . $dataRiwayatPekerjaan->jabatan . '"?',
                    'needs_input' => true,
                    'input_placeholder' => 'Masukkan keterangan penolakan (opsional)'
                ];
            }

            // "Ditangguhkan jadi draft saja" is implemented here as a specific admin action.
            // Admin can always force to draft from any non-draft status
            if ($status !== 'draft') {
                $data['actions']['to_draft'] = [
                    'url' => $data['aksi']['to_draft_url'],
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data riwayat pekerjaan "' . $dataRiwayatPekerjaan->instansi . ' - ' . $dataRiwayatPekerjaan->jabatan . '" menjadi draft?'
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
            // 'ditangguhkan' => [ // Dihapus dari status map
            //     'label' => 'Ditangguhkan',
            //     'color' => 'warning',
            //     'icon' => 'pause-circle',
            //     'description' => 'Dalam peninjauan/ditangguhkan sementara'
            // ]
        ];

        return $statusMap[$status] ?? [
            'label' => ucfirst($status),
            'color' => 'secondary',
            'icon' => 'circle',
            'description' => ''
        ];
    }
}
