<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataSertifikasi;
use App\Models\SimpegDataPendukung;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegMasterJenisSertifikasi;
use App\Models\RumpunBidangIlmu; // Make sure this model is correctly named and imported
use App\Models\SimpegJabatanFungsional; // Import for filter options
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder; // Import for type-hinting scopes

class SimpegDataSertifikasiAdminController extends Controller
{
    /**
     * Get all data sertifikasi for admin (all pegawai).
     * Admin can view data for any employee.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $pegawaiId = $request->pegawai_id; // Admin can filter by pegawai_id
        $unitKerjaId = $request->unit_kerja_id; // Admin can filter by unit_kerja_id
        $jabatanFungsionalId = $request->jabatan_fungsional_id; // Admin can filter by jabatan_fungsional_id
        $jenisSertifikasiId = $request->jenis_sertifikasi_id;
        $bidangIlmuId = $request->bidang_ilmu_id;
        $tglSertifikasi = $request->tgl_sertifikasi;
        $lingkup = $request->lingkup;
        $penyelenggara = $request->penyelenggara;
        $statusPengajuan = $request->status_pengajuan ?? 'semua'; // Default to 'semua'

        // Eager load all necessary relations to avoid N+1 query problem
        $query = SimpegDataSertifikasi::with([
            'jenisSertifikasi',
            'bidangIlmu',
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

        // Filter by pegawai_id (if admin wants to see specific employee data)
        if ($pegawaiId && $pegawaiId != 'semua') {
            $query->where('pegawai_id', $pegawaiId);
        }

        // Filter by Unit Kerja (Hierarchy)
        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $unitKerjaTarget = SimpegUnitKerja::find($unitKerjaId);

            if ($unitKerjaTarget) {
                $unitIdsInScope = SimpegUnitKerja::getAllChildIdsRecursively($unitKerjaTarget);
                $query->whereHas('pegawai', function ($q) use ($unitIdsInScope) {
                    $q->whereIn('unit_kerja_id', $unitIdsInScope);
                });
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unit Kerja yang dipilih tidak ditemukan.'
                ], 404);
            }
        }

        // Filter by Jabatan Fungsional
        if ($jabatanFungsionalId && $jabatanFungsionalId != 'semua') {
            $query->whereHas('pegawai.dataJabatanFungsional', function ($q) use ($jabatanFungsionalId) {
                $q->where('jabatan_fungsional_id', $jabatanFungsionalId);
            });
        }

        // Global search (NIP, Nama Pegawai, No. Sertifikasi, Penyelenggara, Tempat, Jenis Sertifikasi, Bidang Ilmu)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_sertifikasi', 'like', '%' . $search . '%')
                    ->orWhere('no_registrasi', 'like', '%' . $search . '%')
                    ->orWhere('no_peserta', 'like', '%' . $search . '%')
                    ->orWhere('peran', 'like', '%' . $search . '%')
                    ->orWhere('penyelenggara', 'like', '%' . $search . '%')
                    ->orWhere('tempat', 'like', '%' . $search . '%')
                    ->orWhereHas('jenisSertifikasi', function ($jq) use ($search) {
                        $jq->where('nama_sertifikasi', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('bidangIlmu', function ($jq) use ($search) {
                        $jq->where('nama_bidang', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('pegawai', function ($q2) use ($search) {
                        $q2->where('nip', 'like', '%' . $search . '%')
                            ->orWhere('nama', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($jenisSertifikasiId && $jenisSertifikasiId != 'semua') {
            $query->where('jenis_sertifikasi_id', $jenisSertifikasiId);
        }
        if ($bidangIlmuId && $bidangIlmuId != 'semua') {
            $query->where('bidang_ilmu_id', $bidangIlmuId);
        }
        if ($tglSertifikasi) {
            $query->whereDate('tgl_sertifikasi', $tglSertifikasi);
        }
        if ($lingkup && $lingkup != 'semua') {
            $query->where('lingkup', $lingkup);
        }
        if ($penyelenggara) {
            $query->where('penyelenggara', 'like', '%' . $penyelenggara . '%');
        }

        // Execute query with pagination
        $dataSertifikasi = $query->orderBy('tgl_sertifikasi', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataSertifikasi->getCollection()->transform(function ($item) {
            return $this->formatDataSertifikasi($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataSertifikasi,
            'empty_data' => $dataSertifikasi->isEmpty(),
            'filters' => $this->getFilterOptions(),
            'table_columns' => [
                ['field' => 'nip_pegawai', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'pegawai.nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'pegawai.nama'],
                ['field' => 'jenis_sertifikasi_label', 'label' => 'Jenis Sertifikasi', 'sortable' => true, 'sortable_field' => 'jenis_sertifikasi_id'],
                ['field' => 'no_sertifikasi', 'label' => 'Nomor Sertifikasi', 'sortable' => true, 'sortable_field' => 'no_sertifikasi'],
                ['field' => 'bidang_ilmu_label', 'label' => 'Bidang Ilmu', 'sortable' => true, 'sortable_field' => 'bidang_ilmu_id'],
                ['field' => 'tgl_sertifikasi', 'label' => 'Tanggal Sertifikasi', 'sortable' => true, 'sortable_field' => 'tgl_sertifikasi'],
                ['field' => 'penyelenggara', 'label' => 'Penyelenggara', 'sortable' => true, 'sortable_field' => 'penyelenggara'],
                ['field' => 'lingkup', 'label' => 'Lingkup', 'sortable' => true, 'sortable_field' => 'lingkup'],
                ['field' => 'status_info.label', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/datasertifikasiadm/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/datasertifikasiadm/batch/reject"),
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
                    'url' => url("/api/admin/datasertifikasiadm/batch/todraft"),
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft Terpilih',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data terpilih menjadi draft?'
                ],
                'delete' => [
                    'url' => url("/api/admin/datasertifikasiadm/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data terpilih?',
                ]
            ],
            'pagination' => [
                'current_page' => $dataSertifikasi->currentPage(),
                'per_page' => $dataSertifikasi->perPage(),
                'total' => $dataSertifikasi->total(),
                'last_page' => $dataSertifikasi->lastPage(),
                'from' => $dataSertifikasi->firstItem(),
                'to' => $dataSertifikasi->lastItem()
            ]
        ]);
    }

    /**
     * Get detail data sertifikasi.
     * Admin can view details for any employee's data.
     */
    public function show($id)
    {
        $dataSertifikasi = SimpegDataSertifikasi::with([
            'jenisSertifikasi',
            'bidangIlmu',
            'dokumenPendukung', // Load supporting documents
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

        if (!$dataSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data sertifikasi tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai_info_detail' => $this->formatPegawaiInfo($dataSertifikasi->pegawai),
            'data' => $this->formatDataSertifikasi($dataSertifikasi, false),
            'form_options' => $this->getFormOptions(), // Form options for create/update
            'dokumen_pendukung' => $dataSertifikasi->dokumenPendukung->map(function($dok) {
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
     * Store new data sertifikasi (Admin Operational).
     * Admin can add data for any employee. Auto-sets status to 'disetujui' by default.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|uuid|exists:simpeg_pegawai,id', // Required for admin
            'jenis_sertifikasi_id' => 'required|uuid|exists:simpeg_master_jenis_sertifikasi,id',
            'bidang_ilmu_id' => 'required|uuid|exists:simpeg_rumpun_bidang_ilmu,id',
            'no_sertifikasi' => 'required|string|max:50',
            'tgl_sertifikasi' => 'required|date|before_or_equal:today',
            'no_registrasi' => 'required|string|max:20',
            'no_peserta' => 'required|string|max:50',
            'peran' => 'required|string|max:100',
            'penyelenggara' => 'required|string|max:100',
            'tempat' => 'required|string|max:100',
            'lingkup' => 'required|in:Nasional,Internasional,Lokal',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan', // Allow admin to set status
            'keterangan' => 'nullable|string',
            // Dokumen pendukung
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.tipe_dokumen' => 'required_with:dokumen_pendukung|string|in:Sertifikat_Contoh1,Sertifikat_Contoh2,Sertifikat_Contoh3', // <-- UPDATED VALIDATION
            'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|uuid',
            'dokumen_pendukung.*.keterangan' => 'nullable|string',
            'dokumen_pendukung.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if no_sertifikasi already exists for the selected pegawai
        $existingSertifikasi = SimpegDataSertifikasi::where('pegawai_id', $request->pegawai_id)
            ->where('no_sertifikasi', $request->no_sertifikasi)
            ->first();

        if ($existingSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor sertifikasi "'.$request->no_sertifikasi.'" sudah ada untuk pegawai ini'
            ], 422);
        }

        $data = $request->except(['dokumen_pendukung']); // Removed submit_type as admin directly sets status
        $data['tgl_input'] = now()->toDateString();

        // Admin can set the status directly
        $data['status_pengajuan'] = $request->input('status_pengajuan', 'disetujui'); // Default to disetujui for admin input

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
        } elseif ($data['status_pengajuan'] === 'ditangguhkan') {
            $data['tgl_ditangguhkan'] = now();
            $data['tgl_diajukan'] = null;
            $data['tgl_disetujui'] = null;
            $data['tgl_ditolak'] = null;
        } else { // 'draft'
            $data['tgl_diajukan'] = null;
            $data['tgl_disetujui'] = null;
            $data['tgl_ditolak'] = null;
            $data['tgl_ditangguhkan'] = null;
        }

        DB::beginTransaction();
        try {
            $dataSertifikasi = SimpegDataSertifikasi::create($data);

            // Handle dokumen pendukung
            if ($request->has('dokumen_pendukung') && is_array($request->dokumen_pendukung)) {
                $this->storeDokumenPendukung($request->dokumen_pendukung, $dataSertifikasi, $request->pegawai_id);
            }

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_create_sertifikasi', $dataSertifikasi, $dataSertifikasi->toArray());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatDataSertifikasi($dataSertifikasi->load(['jenisSertifikasi', 'bidangIlmu', 'pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional'])),
                'message' => 'Data sertifikasi berhasil ditambahkan oleh admin'
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
     * Update data sertifikasi (Admin Operational).
     * Admin can edit any data regardless of status.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function update(Request $request, $id)
    {
        $dataSertifikasi = SimpegDataSertifikasi::find($id);

        if (!$dataSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data sertifikasi tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|uuid|exists:simpeg_pegawai,id', // Can update pegawai_id
            'jenis_sertifikasi_id' => 'sometimes|uuid|exists:simpeg_master_jenis_sertifikasi,id',
            'bidang_ilmu_id' => 'sometimes|uuid|exists:simpeg_rumpun_bidang_ilmu,id',
            'no_sertifikasi' => 'sometimes|string|max:50',
            'tgl_sertifikasi' => 'sometimes|date|before_or_equal:today',
            'no_registrasi' => 'sometimes|string|max:20',
            'no_peserta' => 'sometimes|string|max:50',
            'peran' => 'sometimes|string|max:100',
            'penyelenggara' => 'sometimes|string|max:100',
            'tempat' => 'sometimes|string|max:100',
            'lingkup' => 'sometimes|in:Nasional,Internasional,Lokal',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
            'keterangan' => 'nullable|string',
            'keterangan_penolakan' => 'nullable|string|max:500', // Added for admin reject notes
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.id' => 'nullable|uuid', // For existing documents
            'dokumen_pendukung.*.tipe_dokumen' => 'required_with:dokumen_pendukung|string|in:Sertifikat_Contoh1,Sertifikat_Contoh2,Sertifikat_Contoh3', // <-- UPDATED VALIDATION
            'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|uuid',
            'dokumen_pendukung.*.keterangan' => 'nullable|string',
            'dokumen_pendukung.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'dokumen_pendukung_to_delete' => 'nullable|array', // Array of IDs to delete
            'dokumen_pendukung_to_delete.*' => 'nullable|uuid|exists:simpeg_data_pendukung,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check no_sertifikasi uniqueness if updated
        if ($request->has('no_sertifikasi')) {
            $targetPegawaiId = $request->input('pegawai_id', $dataSertifikasi->pegawai_id);
            $existingSertifikasi = SimpegDataSertifikasi::where('pegawai_id', $targetPegawaiId)
                ->where('no_sertifikasi', $request->no_sertifikasi)
                ->where('id', '!=', $id)
                ->first();

            if ($existingSertifikasi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor sertifikasi "' . $request->no_sertifikasi . '" sudah ada untuk pegawai ini'
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $oldData = $dataSertifikasi->getOriginal();
            $data = $request->except(['dokumen_pendukung', 'dokumen_pendukung_to_delete']); // Exclude nested data

            // Handle status_pengajuan and timestamps related
            if (isset($data['status_pengajuan']) && $data['status_pengajuan'] !== $dataSertifikasi->status_pengajuan) {
                switch ($data['status_pengajuan']) {
                    case 'diajukan':
                        $data['tgl_diajukan'] = now();
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        $data['tgl_ditangguhkan'] = null;
                        break;
                    case 'disetujui':
                        $data['tgl_disetujui'] = now();
                        $data['tgl_diajukan'] = $dataSertifikasi->tgl_diajukan ?? now(); 
                        $data['tgl_ditolak'] = null;
                        $data['tgl_ditangguhkan'] = null;
                        break;
                    case 'ditolak':
                        $data['tgl_ditolak'] = now();
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditangguhkan'] = null;
                        break;
                    case 'ditangguhkan':
                        $data['tgl_ditangguhkan'] = now();
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        break;
                    case 'draft':
                        $data['tgl_diajukan'] = null;
                        $data['tgl_disetujui'] = null;
                        $data['tgl_ditolak'] = null;
                        $data['tgl_ditangguhkan'] = null;
                        break;
                }
            } else {
                // If status is not changed, retain existing timestamps
                $data['tgl_diajukan'] = $dataSertifikasi->tgl_diajukan;
                $data['tgl_disetujui'] = $dataSertifikasi->tgl_disetujui;
                $data['tgl_ditolak'] = $dataSertifikasi->tgl_ditolak;
                $data['tgl_ditangguhkan'] = $dataSertifikasi->tgl_ditangguhkan;
            }
            // Retain existing 'keterangan_penolakan' if not explicitly updated to null or a new value
            if (!isset($data['keterangan_penolakan'])) {
                $data['keterangan_penolakan'] = $dataSertifikasi->keterangan_penolakan;
            }

            $dataSertifikasi->update($data);

            // Handle dokumen pendukung update/delete/create
            $this->updateDokumenPendukung($request, $dataSertifikasi, $dataSertifikasi->pegawai_id); // Pass request for file access

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_update_sertifikasi', $dataSertifikasi, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatDataSertifikasi($dataSertifikasi->load(['jenisSertifikasi', 'bidangIlmu', 'pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional'])),
                'message' => 'Data sertifikasi berhasil diperbarui oleh admin'
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
     * Delete data sertifikasi.
     * Admin can delete any data.
     *
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function destroy($id)
    {
        $dataSertifikasi = SimpegDataSertifikasi::find($id);

        if (!$dataSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data sertifikasi tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete dokumen pendukung related to this sertifikasi
            $dokumenPendukung = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataSertifikasi')
                ->where('pendukungable_id', $id)
                ->get();

            foreach ($dokumenPendukung as $dokumen) {
                // Assuming deleteFile() method exists in SimpegDataPendukung to delete actual file
                if (method_exists($dokumen, 'deleteFile')) {
                    $dokumen->deleteFile();
                } else {
                    // Fallback if deleteFile is not defined in SimpegDataPendukung
                    Storage::disk('public')->delete($dokumen->file_path);
                }
                $dokumen->delete();
            }

            $oldData = $dataSertifikasi->toArray();
            $dataSertifikasi->delete();

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_delete_sertifikasi', $dataSertifikasi, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data sertifikasi berhasil dihapus'
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
        $dataSertifikasi = SimpegDataSertifikasi::find($id);

        if (!$dataSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data sertifikasi tidak ditemukan'
            ], 404);
        }

        if ($dataSertifikasi->status_pengajuan === 'disetujui') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah disetujui sebelumnya'
            ], 409);
        }

        $oldData = $dataSertifikasi->getOriginal();
        $dataSertifikasi->update([
            'status_pengajuan' => 'disetujui',
            'tgl_disetujui' => now(),
            'tgl_ditolak' => null,
            'tgl_ditangguhkan' => null, // Clear if approved
            'keterangan_penolakan' => null, // Clear rejection note
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_approve_sertifikasi', $dataSertifikasi, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data sertifikasi berhasil disetujui'
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
        $dataSertifikasi = SimpegDataSertifikasi::find($id);

        if (!$dataSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data sertifikasi tidak ditemukan'
            ], 404);
        }

        if ($dataSertifikasi->status_pengajuan === 'ditolak') {
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

        $oldData = $dataSertifikasi->getOriginal();
        $dataSertifikasi->update([
            'status_pengajuan' => 'ditolak',
            'tgl_ditolak' => now(),
            'tgl_diajukan' => null,
            'tgl_disetujui' => null,
            'tgl_ditangguhkan' => null,
            'keterangan_penolakan' => $request->keterangan_penolakan,
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_reject_sertifikasi', $dataSertifikasi, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data sertifikasi berhasil ditolak'
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
        $dataSertifikasi = SimpegDataSertifikasi::find($id);

        if (!$dataSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data sertifikasi tidak ditemukan'
            ], 404);
        }

        if ($dataSertifikasi->status_pengajuan === 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah dalam status draft'
            ], 409);
        }

        $oldData = $dataSertifikasi->getOriginal();
        $dataSertifikasi->update([
            'status_pengajuan' => 'draft',
            'tgl_diajukan' => null,
            'tgl_disetujui' => null,
            'tgl_ditolak' => null,
            'tgl_ditangguhkan' => null, // Clear all other timestamps if moved to draft
            'keterangan_penolakan' => null, // Clear rejection note
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_to_draft_sertifikasi', $dataSertifikasi, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status sertifikasi berhasil diubah menjadi draft'
        ]);
    }

    /**
     * Admin: Batch delete data sertifikasi.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_sertifikasi,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataSertifikasiList = SimpegDataSertifikasi::whereIn('id', $request->ids)->get();

        if ($dataSertifikasiList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data sertifikasi yang ditemukan untuk dihapus'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataSertifikasiList as $dataSertifikasi) {
                try {
                    // Delete associated supporting documents
                    $dokumenPendukung = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataSertifikasi')
                        ->where('pendukungable_id', $dataSertifikasi->id)
                        ->get();

                    foreach ($dokumenPendukung as $dokumen) {
                        if (method_exists($dokumen, 'deleteFile')) {
                            $dokumen->deleteFile(); // Delete actual file
                        } else {
                            Storage::disk('public')->delete($dokumen->file_path); // Fallback
                        }
                        $dokumen->delete(); // Delete record from DB
                    }

                    $oldData = $dataSertifikasi->toArray();
                    $dataSertifikasi->delete();
                    
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_delete_sertifikasi', $dataSertifikasi, $oldData);
                    }
                    $deletedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $dataSertifikasi->id,
                        'no_sertifikasi' => $dataSertifikasi->no_sertifikasi,
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
                'message' => "Berhasil menghapus {$deletedCount} data sertifikasi",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data sertifikasi",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }

    /**
     * Admin: Batch approve data sertifikasi.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_sertifikasi,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataSertifikasi::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', ['draft', 'diajukan', 'ditolak', 'ditangguhkan']) // All statuses can be approved by admin
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data sertifikasi yang memenuhi syarat untuk disetujui.'
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
                    'tgl_diajukan' => $item->tgl_diajukan ?? now(), // Retain if already submitted, otherwise set now
                    'tgl_ditolak' => null,
                    'tgl_ditangguhkan' => null,
                    'keterangan_penolakan' => null,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_approve_sertifikasi', $item, $oldData);
                }
                $updatedCount++;
                $approvedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch approve sertifikasi: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data sertifikasi",
            'updated_count' => $updatedCount,
            'approved_ids' => $approvedIds
        ]);
    }

    /**
     * Admin: Batch reject data sertifikasi.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_sertifikasi,id',
            'keterangan_penolakan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataSertifikasi::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', ['draft', 'diajukan', 'disetujui', 'ditangguhkan']) // All statuses can be rejected by admin
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data sertifikasi yang memenuhi syarat untuk ditolak.'
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
                    'tgl_ditangguhkan' => null,
                    'keterangan_penolakan' => $request->keterangan_penolakan,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_reject_sertifikasi', $item, $oldData);
                }
                $updatedCount++;
                $rejectedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch reject sertifikasi: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data sertifikasi",
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
            'ids.*' => 'required|uuid|exists:simpeg_data_sertifikasi,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataSertifikasi::whereIn('id', $request->ids)
            ->where('status_pengajuan', '!=', 'draft') // Only process if not already draft
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data sertifikasi yang memenuhi syarat untuk diubah menjadi draft.'
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
                    'tgl_ditangguhkan' => null,
                    'keterangan_penolakan' => null,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_batch_to_draft_sertifikasi', $item, $oldData);
                }
                $updatedCount++;
                $draftedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch change to draft for sertifikasi: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah status ke draft secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengubah {$updatedCount} data sertifikasi menjadi draft",
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

        $query = SimpegDataSertifikasi::query();

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
            'ditangguhkan' => 0, // Include ditangguhkan in default stats
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
        $jenisSertifikasiList = SimpegMasterJenisSertifikasi::select('id', 'nama_sertifikasi as nama')
            ->orderBy('nama_sertifikasi')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jenis Sertifikasi']);

        $bidangIlmuList = RumpunBidangIlmu::select('id', 'nama_bidang as nama', 'kode')
            ->orderBy('nama_bidang')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Bidang Ilmu']);

        $penyelenggaraList = SimpegDataSertifikasi::distinct()->pluck('penyelenggara')->filter()->values()->map(function($item) { return ['id' => $item, 'nama' => $item]; })->prepend(['id' => 'semua', 'nama' => 'Semua Penyelenggara'])->toArray();
        
        return response()->json([
            'success' => true,
            'filter_options' => [
                'jenis_sertifikasi' => $jenisSertifikasiList,
                'bidang_ilmu' => $bidangIlmuList,
                'penyelenggara' => $penyelenggaraList,
                'lingkup' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'Nasional', 'nama' => 'Nasional'],
                    ['id' => 'Internasional', 'nama' => 'Internasional'],
                    ['id' => 'Lokal', 'nama' => 'Lokal']
                ],
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                    ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan'], // Include ditangguhkan in filter options
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
        $jenisSertifikasiOptions = SimpegMasterJenisSertifikasi::select('id', 'nama_sertifikasi as nama', 'kode')
            ->orderBy('nama_sertifikasi')
            ->get()
            ->map(function ($item) {
                return ['id' => $item->id, 'label' => $item->nama_sertifikasi . ($item->kode ? ' (' . $item->kode . ')' : '')];
            });

        $bidangIlmuOptions = RumpunBidangIlmu::select('id', 'nama_bidang as nama', 'kode')
            ->orderBy('nama_bidang')
            ->get()
            ->map(function ($item) {
                return ['id' => $item->id, 'label' => $item->nama_bidang . ($item->kode ? ' (' . $item->kode . ')' : '')];
            });

        return [
            'form_options' => [
                'jenis_sertifikasi' => $jenisSertifikasiOptions,
                'bidang_ilmu' => $bidangIlmuOptions,
                'lingkup' => [
                    ['id' => 'Nasional', 'nama' => 'Nasional'],
                    ['id' => 'Internasional', 'nama' => 'Internasional'],
                    ['id' => 'Lokal', 'nama' => 'Lokal']
                ],
                'status_pengajuan' => [ // Admin can select status directly
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                    ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan'],
                ]
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|uuid',
                'jenis_sertifikasi_id' => 'required|uuid|exists:simpeg_master_jenis_sertifikasi,id',
                'bidang_ilmu_id' => 'required|uuid|exists:simpeg_rumpun_bidang_ilmu,id',
                'no_sertifikasi' => 'required|string|max:50',
                'tgl_sertifikasi' => 'required|date|before_or_equal:today',
                'no_registrasi' => 'required|string|max:20',
                'no_peserta' => 'required|string|max:50',
                'peran' => 'required|string|max:100',
                'penyelenggara' => 'required|string|max:100',
                'tempat' => 'required|string|max:100',
                'lingkup' => 'required|in:Nasional,Internasional,Lokal',
                'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
                'keterangan' => 'nullable|string|max:1000',
                'dokumen_pendukung' => 'nullable|array',
                'dokumen_pendukung.*.tipe_dokumen' => 'required_with:dokumen_pendukung|string|in:Sertifikat_Contoh1,Sertifikat_Contoh2,Sertifikat_Contoh3', // <-- UPDATED VALIDATION
                'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string',
                'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|uuid',
                'dokumen_pendukung.*.keterangan' => 'nullable|string',
                'dokumen_pendukung.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120'
            ],
            'field_notes' => [
                'pegawai_id' => 'Pilih pegawai yang bersangkutan.',
                'jenis_sertifikasi_id' => 'Pilih jenis sertifikasi dari daftar.',
                'bidang_ilmu_id' => 'Pilih rumpun bidang ilmu yang relevan.',
                'no_sertifikasi' => 'Nomor unik sertifikasi.',
                'tgl_sertifikasi' => 'Tanggal penerbitan sertifikasi.',
                'no_registrasi' => 'Nomor registrasi sertifikasi (jika ada).',
                'no_peserta' => 'Nomor peserta ujian/program (jika ada).',
                'peran' => 'Peran dalam sertifikasi (contoh: Peserta, Narasumber).',
                'penyelenggara' => 'Institusi yang mengeluarkan sertifikasi.',
                'tempat' => 'Lokasi penyelenggaraan sertifikasi.',
                'lingkup' => 'Cakupan wilayah sertifikasi (Nasional, Internasional, Lokal).',
                'keterangan' => 'Keterangan tambahan mengenai sertifikasi.',
                'dokumen_pendukung' => 'Unggah dokumen pendukung seperti sertifikat, transkrip, dll.',
            ],
        ];
    }

    /**
     * Store supporting documents.
     *
     * @param array $dokumenArray
     * @param SimpegDataSertifikasi $sertifikasi
     * @param int $pegawaiId The ID of the associated employee
     * @return void
     */
    private function storeDokumenPendukung($dokumenArray, $sertifikasi, $pegawaiId)
    {
        foreach ($dokumenArray as $index => $dokumen) {
            $dokumenData = [
                'tipe_dokumen' => $dokumen['tipe_dokumen'],
                'nama_dokumen' => $dokumen['nama_dokumen'],
                'jenis_dokumen_id' => $dokumen['jenis_dokumen_id'] ?? null,
                'keterangan' => $dokumen['keterangan'] ?? null,
                'pendukungable_type' => 'App\Models\SimpegDataSertifikasi',
                'pendukungable_id' => $sertifikasi->id
            ];

            if (isset($dokumen['file']) && $dokumen['file'] instanceof \Illuminate\Http\UploadedFile) {
                $file = $dokumen['file'];
                // Store in a general admin folder, organized by pegawaiId and sertifikasiId for clarity
                $fileName = 'sertifikasi_dok_' . $pegawaiId . '_' . $sertifikasi->id . '_' . time() . '_' . $index . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('sertifikasi_dokumen', $fileName, 'public'); // path: storage/app/public/sertifikasi_dokumen
                $dokumenData['file_path'] = $filePath;
            }

            SimpegDataPendukung::create($dokumenData);
        }
    }

    /**
     * Update supporting documents (add new, update existing, delete old).
     *
     * @param Request $request
     * @param SimpegDataSertifikasi $sertifikasi
     * @param int $pegawaiId The ID of the associated employee
     * @return void
     */
    private function updateDokumenPendukung(Request $request, $sertifikasi, $pegawaiId)
    {
        // 1. Delete documents flagged for deletion
        if ($request->has('dokumen_pendukung_to_delete') && is_array($request->dokumen_pendukung_to_delete)) {
            $deleteIds = $request->dokumen_pendukung_to_delete;
            $oldDokumen = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataSertifikasi')
                ->where('pendukungable_id', $sertifikasi->id)
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
                // Ensure to get the file instance correctly from the request's nested structure
                $file = $request->file('dokumen_pendukung.' . $index . '.file'); 

                $payload = [
                    'tipe_dokumen' => $dokumenData['tipe_dokumen'],
                    'nama_dokumen' => $dokumenData['nama_dokumen'],
                    'jenis_dokumen_id' => $dokumenData['jenis_dokumen_id'] ?? null,
                    'keterangan' => $dokumenData['keterangan'] ?? null,
                    'pendukungable_type' => 'App\Models\SimpegDataSertifikasi',
                    'pendukungable_id' => $sertifikasi->id
                ];

                // Only set file_path if a new file is uploaded
                if ($file) {
                    $fileName = 'sertifikasi_dok_' . $pegawaiId . '_' . $sertifikasi->id . '_' . time() . '_' . $index . '.' . $file->getClientOriginalExtension();
                    $filePath = $file->storeAs('sertifikasi_dokumen', $fileName, 'public');
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
     * Helper: Format data sertifikasi response for display.
     */
    protected function formatDataSertifikasi($dataSertifikasi, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataSertifikasi->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $pegawai = $dataSertifikasi->pegawai;
        $nipPegawai = $pegawai ? $pegawai->nip : '-';
        $namaPegawai = $pegawai ? $pegawai->nama : '-';

        $data = [
            'id' => $dataSertifikasi->id,
            'pegawai_id' => $dataSertifikasi->pegawai_id,
            'nip_pegawai' => $nipPegawai,
            'nama_pegawai' => $namaPegawai,
            'jenis_sertifikasi_id' => $dataSertifikasi->jenis_sertifikasi_id,
            'jenis_sertifikasi_label' => $dataSertifikasi->jenisSertifikasi ? $dataSertifikasi->jenisSertifikasi->nama_sertifikasi : '-',
            'bidang_ilmu_id' => $dataSertifikasi->bidang_ilmu_id,
            'bidang_ilmu_label' => $dataSertifikasi->bidangIlmu ? $dataSertifikasi->bidangIlmu->nama_bidang : '-',
            'no_sertifikasi' => $dataSertifikasi->no_sertifikasi,
            'tgl_sertifikasi' => $dataSertifikasi->tgl_sertifikasi,
            'tgl_sertifikasi_formatted' => $dataSertifikasi->tgl_sertifikasi ? Carbon::parse($dataSertifikasi->tgl_sertifikasi)->format('d M Y') : '-',
            'no_registrasi' => $dataSertifikasi->no_registrasi,
            'no_peserta' => $dataSertifikasi->no_peserta,
            'peran' => $dataSertifikasi->peran,
            'penyelenggara' => $dataSertifikasi->penyelenggara,
            'tempat' => $dataSertifikasi->tempat,
            'lingkup' => $dataSertifikasi->lingkup,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan_penolakan' => $dataSertifikasi->keterangan_penolakan, // Include rejection note
            'timestamps' => [
                'tgl_input' => $dataSertifikasi->tgl_input,
                'tgl_diajukan' => $dataSertifikasi->tgl_diajukan ?? null,
                'tgl_disetujui' => $dataSertifikasi->tgl_disetujui ?? null,
                'tgl_ditolak' => $dataSertifikasi->tgl_ditolak ?? null,
                'tgl_ditangguhkan' => $dataSertifikasi->tgl_ditangguhkan ?? null,
            ],
            'created_at' => $dataSertifikasi->created_at,
            'updated_at' => $dataSertifikasi->updated_at
        ];

        // Add action URLs if requested (for admin view)
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datasertifikasiadm/{$dataSertifikasi->id}"),
                'update_url' => url("/api/admin/datasertifikasiadm/{$dataSertifikasi->id}"),
                'delete_url' => url("/api/admin/datasertifikasiadm/{$dataSertifikasi->id}"),
                'approve_url' => url("/api/admin/datasertifikasiadm/{$dataSertifikasi->id}/approve"),
                'reject_url' => url("/api/admin/datasertifikasiadm/{$dataSertifikasi->id}/reject"),
                'to_draft_url' => url("/api/admin/datasertifikasiadm/{$dataSertifikasi->id}/todraft"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data sertifikasi "' . $dataSertifikasi->no_sertifikasi . '"?'
                ],
            ];

            // Admin specific actions based on status
            if (in_array($status, ['diajukan', 'ditolak', 'draft', 'ditangguhkan'])) {
                 $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data sertifikasi "' . $dataSertifikasi->no_sertifikasi . '"?'
                ];
            }

            if (in_array($status, ['diajukan', 'disetujui', 'draft', 'ditangguhkan'])) {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Tolak',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data sertifikasi "' . $dataSertifikasi->no_sertifikasi . '"?',
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data sertifikasi "' . $dataSertifikasi->no_sertifikasi . '" menjadi draft?'
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
            'ditangguhkan' => [ // Kept as a status, but admin actions allow changing it to draft or other states
                'label' => 'Ditangguhkan',
                'color' => 'warning',
                'icon' => 'pause-circle',
                'description' => 'Dalam peninjauan/ditangguhkan sementara'
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
