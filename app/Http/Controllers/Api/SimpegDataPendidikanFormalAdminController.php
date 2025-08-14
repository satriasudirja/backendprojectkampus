<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPendidikanFormal;
use App\Models\SimpegDataPendukung; // Assuming SimpegDataPendukung for polymorphic relations
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegJenjangPendidikan;
use App\Models\MasterPerguruanTinggi; // Make sure this model is correctly named and imported
use App\Models\MasterProdiPerguruanTinggi; // Make sure this model is correctly named and imported
use App\Models\MasterGelarAkademik; // Make sure this model is correctly named and imported
use App\Models\SimpegJabatanFungsional; // Import for filter options
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
 // Import for type-hinting scopes
use Illuminate\Support\Str; // Import Str for random string generation

class SimpegDataPendidikanFormalAdminController extends Controller
{
    /**
     * Get all data pendidikan formal for admin (all pegawai).
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
        $jenjangPendidikanId = $request->jenjang_pendidikan_id;
        $perguruanTinggiId = $request->perguruan_tinggi_id;
        $prodiId = $request->prodi_id;
        $tahunMasuk = $request->tahun_masuk;
        $tahunLulus = $request->tahun_lulus;
        $statusPengajuan = $request->status_pengajuan ?? 'semua'; // Default to 'semua'

        // Eager load all necessary relations to avoid N+1 query problem
        $query = SimpegDataPendidikanFormal::with([
            'jenjangPendidikan',
            'perguruanTinggi',
            'prodiPerguruanTinggi',
            'gelarAkademik',
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

        // Filters
        $query->filterByPegawai($pegawaiId)
              ->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanFungsional($jabatanFungsionalId)
              ->filterByJenjangPendidikan($jenjangPendidikanId)
              ->filterByPerguruanTinggi($perguruanTinggiId)
              ->filterByProdi($prodiId)
              ->filterByTahunMasuk($tahunMasuk)
              ->filterByTahunLulus($tahunLulus)
              ->globalSearch($search)
              ->byStatus($statusPengajuan);

        // Execute query with pagination
        $dataPendidikanFormal = $query->orderBy('tanggal_kelulusan', 'desc')
                                      ->orderBy('tahun_lulus', 'desc')
                                      ->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataPendidikanFormal->getCollection()->transform(function ($item) {
            return $this->formatDataPendidikanFormal($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataPendidikanFormal,
            'empty_data' => $dataPendidikanFormal->isEmpty(),
            'filters' => $this->getFilterOptions(),
            'table_columns' => [
                ['field' => 'nip_pegawai', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'pegawai.nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'pegawai.nama'],
                ['field' => 'jenjang_pendidikan_label', 'label' => 'Jenjang', 'sortable' => true, 'sortable_field' => 'jenjang_pendidikan_id'],
                ['field' => 'nama_institusi_label', 'label' => 'Nama Institusi', 'sortable' => true, 'sortable_field' => 'perguruan_tinggi_id'],
                ['field' => 'nama_prodi_label', 'label' => 'Program Studi', 'sortable' => true, 'sortable_field' => 'prodi_perguruan_tinggi_id'],
                ['field' => 'nomor_ijazah', 'label' => 'No. Ijazah', 'sortable' => true, 'sortable_field' => 'nomor_ijazah'],
                ['field' => 'tanggal_kelulusan_formatted', 'label' => 'Tgl. Kelulusan', 'sortable' => true, 'sortable_field' => 'tanggal_kelulusan'],
                ['field' => 'status_info.label', 'label' => 'Status', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/datapendidikanformaladm/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/datapendidikanformaladm/batch/reject"),
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
                    'url' => url("/api/admin/datapendidikanformaladm/batch/todraft"),
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft Terpilih',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data terpilih menjadi draft?'
                ],
                'delete' => [
                    'url' => url("/api/admin/datapendidikanformaladm/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data terpilih?',
                ]
            ],
            'pagination' => [
                'current_page' => $dataPendidikanFormal->currentPage(),
                'per_page' => $dataPendidikanFormal->perPage(),
                'total' => $dataPendidikanFormal->total(),
                'last_page' => $dataPendidikanFormal->lastPage(),
                'from' => $dataPendidikanFormal->firstItem(),
                'to' => $dataPendidikanFormal->lastItem()
            ]
        ]);
    }

    /**
     * Get detail data pendidikan formal.
     * Admin can view details for any employee's data.
     */
    public function show($id)
    {
        $dataPendidikanFormal = SimpegDataPendidikanFormal::with([
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
            },
            'jenjangPendidikan',
            'perguruanTinggi',
            'prodiPerguruanTinggi',
            'gelarAkademik',
            'dokumenPendukung' // Load associated supporting documents
        ])->find($id);

        if (!$dataPendidikanFormal) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai_info_detail' => $this->formatPegawaiInfo($dataPendidikanFormal->pegawai),
            'data' => $this->formatDataPendidikanFormal($dataPendidikanFormal, false),
            'form_options' => $this->getFormOptions(), // Form options for create/update
            'dokumen_pendukung' => $dataPendidikanFormal->dokumenPendukung->map(function($dok) {
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
     * Store new data pendidikan formal (Admin Operational).
     * Admin can add data for any employee. Auto-sets status to 'disetujui' by default.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|uuid|exists:simpeg_pegawai,id', // Required for admin
            'lokasi_studi' => 'required|string|max:100',
            'jenjang_pendidikan_id' => 'required|uuid|exists:simpeg_jenjang_pendidikan,id',
            'perguruan_tinggi_id' => 'required|uuid|exists:simpeg_master_perguruan_tinggi,id',
            'prodi_perguruan_tinggi_id' => 'required|uuid|exists:simpeg_master_prodi_perguruan_tinggi,id',
            'gelar_akademik_id' => 'nullable|uuid|exists:simpeg_master_gelar_akademik,id',
            'bidang_studi' => 'nullable|string|max:255',
            'nisn' => 'nullable|string|max:20',
            'konsentrasi' => 'nullable|string|max:100',
            'tahun_masuk' => 'required|integer|min:1900|max:' . (date('Y')),
            'tanggal_kelulusan' => 'nullable|date|before_or_equal:today',
            'tahun_lulus' => 'required|integer|min:1900|max:' . (date('Y')) . '|after_or_equal:tahun_masuk',
            'nomor_ijazah' => 'required|string|max:50',
            'tanggal_ijazah' => 'required|date|before_or_equal:today|after_or_equal:tanggal_kelulusan',
            'file_ijazah' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // Only one file for now
            'file_transkrip' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // Only one file for now
            'nomor_ijazah_negara' => 'nullable|string|max:50',
            'gelar_ijazah_negara' => 'nullable|string|max:50',
            'tanggal_ijazah_negara' => 'nullable|date',
            'nomor_induk' => 'nullable|string|max:50',
            'judul_tugas' => 'nullable|string|max:500',
            'letak_gelar' => 'nullable|in:depan,belakang,tidak_ada',
            'jumlah_semster_ditempuh' => 'nullable|integer|min:1',
            'jumlah_sks_kelulusan' => 'nullable|integer|min:0',
            'ipk_kelulusan' => 'nullable|numeric|min:0|max:4.00',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan', // Allow admin to set status
            'keterangan_penolakan' => 'nullable|string|max:500', // Admin can add rejection notes
            // Dokumen pendukung (polymorphic)
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.tipe_dokumen' => 'required_with:dokumen_pendukung|string|in:Ijazah,Transkrip,Surat_Keterangan,Dokumen_Lainnya', // Sesuaikan dengan CHECK constraint di DB Anda
            'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string|max:255',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|uuid', // Optional, if you have a master for jenis_dokumen
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

        // Check for uniqueness based on pegawai, jenjang, perguruan_tinggi, prodi (common uniqueness criteria)
        $existingPendidikan = SimpegDataPendidikanFormal::where('pegawai_id', $request->pegawai_id)
            ->where('jenjang_pendidikan_id', $request->jenjang_pendidikan_id)
            ->where('perguruan_tinggi_id', $request->perguruan_tinggi_id)
            ->where('prodi_perguruan_tinggi_id', $request->prodi_perguruan_tinggi_id)
            ->first();

        if ($existingPendidikan) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal dengan jenjang, perguruan tinggi, dan program studi yang sama sudah ada untuk pegawai ini.'
            ], 422);
        }

        $data = $validator->validated();
        // Remove file_ijazah and file_transkrip from $data to handle them via dokumen_pendukung
        unset($data['file_ijazah']); 
        unset($data['file_transkrip']);
        unset($data['dokumen_pendukung']); // Exclude nested array from main model creation

        $data['tgl_input'] = now()->toDateString();
        // Admin can set the status directly, default to 'disetujui'
        $data['status_pengajuan'] = $request->input('status_pengajuan', 'disetujui'); 

        // Set timestamps based on status
        if ($data['status_pengajuan'] === 'disetujui') {
            $data['tanggal_disetujui'] = now();
            $data['tanggal_diajukan'] = $data['tanggal_diajukan'] ?? now(); 
        } elseif ($data['status_pengajuan'] === 'diajukan') {
            $data['tanggal_diajukan'] = now();
            $data['tanggal_disetujui'] = null;
        } elseif ($data['status_pengajuan'] === 'ditolak') {
            $data['tanggal_ditolak'] = now();
            $data['tanggal_diajukan'] = null;
            $data['tanggal_disetujui'] = null;
        } elseif ($data['status_pengajuan'] === 'ditangguhkan') {
            $data['tanggal_ditangguhkan'] = now();
            $data['tanggal_diajukan'] = null;
            $data['tanggal_disetujui'] = null;
            $data['tanggal_ditolak'] = null;
        } else { // 'draft'
            $data['tanggal_diajukan'] = null;
            $data['tanggal_disetujui'] = null;
            $data['tanggal_ditolak'] = null;
            $data['tanggal_ditangguhkan'] = null;
        }


        DB::beginTransaction();
        try {
            $dataPendidikanFormal = SimpegDataPendidikanFormal::create($data);

            // Handle file_ijazah and file_transkrip if still present as direct files (legacy)
            // Or prioritize dokumen_pendukung structure
            if ($request->hasFile('file_ijazah')) {
                // If you want to convert old single file uploads to new polymorphic structure
                $this->storeSingleFileAsDokumenPendukung($request->file('file_ijazah'), $dataPendidikanFormal, $request->pegawai_id, 'Ijazah', 'Dokumen Ijazah Utama');
            }
            if ($request->hasFile('file_transkrip')) {
                $this->storeSingleFileAsDokumenPendukung($request->file('file_transkrip'), $dataPendidikanFormal, $request->pegawai_id, 'Transkrip', 'Dokumen Transkrip Nilai');
            }

            // Handle multiple supporting documents
            if ($request->has('dokumen_pendukung') && is_array($request->dokumen_pendukung)) {
                $this->storeDokumenPendukung($request->dokumen_pendukung, $dataPendidikanFormal, $request->pegawai_id, $request); // Pass $request for file instances
            }

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_create_pendidikan_formal', $dataPendidikanFormal, $dataPendidikanFormal->toArray());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data pendidikan formal berhasil ditambahkan oleh admin',
                'data' => $this->formatDataPendidikanFormal($dataPendidikanFormal->load(['jenjangPendidikan', 'perguruanTinggi', 'prodiPerguruanTinggi', 'gelarAkademik', 'dokumenPendukung', 'pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional']))
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
     * Update data pendidikan formal (Admin Operational).
     * Admin can edit any data regardless of status.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function update(Request $request, $id)
    {
        $dataPendidikanFormal = SimpegDataPendidikanFormal::find($id);

        if (!$dataPendidikanFormal) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|uuid|exists:simpeg_pegawai,id',
            'lokasi_studi' => 'sometimes|string|max:100',
            'jenjang_pendidikan_id' => 'sometimes|uuid|exists:simpeg_jenjang_pendidikan,id',
            'perguruan_tinggi_id' => 'sometimes|uuid|exists:simpeg_master_perguruan_tinggi,id',
            'prodi_perguruan_tinggi_id' => 'sometimes|uuid|exists:simpeg_master_prodi_perguruan_tinggi,id',
            'gelar_akademik_id' => 'nullable|uuid|exists:simpeg_master_gelar_akademik,id',
            'bidang_studi' => 'nullable|string|max:255',
            'nisn' => 'nullable|string|max:20',
            'konsentrasi' => 'nullable|string|max:100',
            'tahun_masuk' => 'sometimes|integer|min:1900|max:' . (date('Y')),
            'tanggal_kelulusan' => 'nullable|date|before_or_equal:today',
            'tahun_lulus' => 'sometimes|integer|min:1900|max:' . (date('Y')) . '|after_or_equal:tahun_masuk',
            'nomor_ijazah' => 'sometimes|string|max:50',
            'tanggal_ijazah' => 'sometimes|date|before_or_equal:today',
            'file_ijazah' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'file_transkrip' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'nomor_ijazah_negara' => 'nullable|string|max:50',
            'gelar_ijazah_negara' => 'nullable|string|max:50',
            'tanggal_ijazah_negara' => 'nullable|date',
            'nomor_induk' => 'nullable|string|max:50',
            'judul_tugas' => 'nullable|string|max:500',
            'letak_gelar' => 'nullable|in:depan,belakang,tidak_ada',
            'jumlah_semster_ditempuh' => 'nullable|integer|min:1',
            'jumlah_sks_kelulusan' => 'nullable|integer|min:0',
            'ipk_kelulusan' => 'nullable|numeric|min:0|max:4.00',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
            'keterangan_penolakan' => 'nullable|string|max:500',
            // Dokumen pendukung (polymorphic)
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.id' => 'nullable|uuid|exists:simpeg_data_pendukung,id', // For existing documents
            'dokumen_pendukung.*.tipe_dokumen' => 'required_with:dokumen_pendukung|string|in:Ijazah,Transkrip,Surat_Keterangan,Dokumen_Lainnya', // Sesuaikan dengan CHECK constraint di DB Anda
            'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string|max:255',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|uuid',
            'dokumen_pendukung.*.keterangan' => 'nullable|string|max:1000',
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

        // Check for uniqueness based on pegawai, jenjang, perguruan_tinggi, prodi if updated
        if ($request->hasAny(['pegawai_id', 'jenjang_pendidikan_id', 'perguruan_tinggi_id', 'prodi_perguruan_tinggi_id'])) {
            $targetPegawaiId = $request->input('pegawai_id', $dataPendidikanFormal->pegawai_id);
            $targetJenjangId = $request->input('jenjang_pendidikan_id', $dataPendidikanFormal->jenjang_pendidikan_id);
            $targetPTId = $request->input('perguruan_tinggi_id', $dataPendidikanFormal->perguruan_tinggi_id);
            $targetProdiId = $request->input('prodi_perguruan_tinggi_id', $dataPendidikanFormal->prodi_perguruan_tinggi_id);

            $existingPendidikan = SimpegDataPendidikanFormal::where('pegawai_id', $targetPegawaiId)
                ->where('jenjang_pendidikan_id', $targetJenjangId)
                ->where('perguruan_tinggi_id', $targetPTId)
                ->where('prodi_perguruan_tinggi_id', $targetProdiId)
                ->where('id', '!=', $id)
                ->first();

            if ($existingPendidikan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pendidikan formal dengan jenjang, perguruan tinggi, dan program studi yang sama sudah ada untuk pegawai ini.'
                ], 422);
            }
        }


        DB::beginTransaction();
        try {
            $oldData = $dataPendidikanFormal->getOriginal();
            $data = $validator->validated();
            // Remove file_ijazah, file_transkrip, and dokumen_pendukung from $data before updating main model
            unset($data['file_ijazah']); 
            unset($data['file_transkrip']);
            unset($data['dokumen_pendukung']);
            unset($data['dokumen_pendukung_to_delete']);


            // Handle status_pengajuan and timestamps related
            if (isset($data['status_pengajuan']) && $data['status_pengajuan'] !== $dataPendidikanFormal->status_pengajuan) {
                switch ($data['status_pengajuan']) {
                    case 'diajukan':
                        $data['tanggal_diajukan'] = now();
                        $data['tanggal_disetujui'] = null;
                        $data['tanggal_ditolak'] = null;
                        $data['tanggal_ditangguhkan'] = null;
                        break;
                    case 'disetujui':
                        $data['tanggal_disetujui'] = now();
                        $data['tanggal_diajukan'] = $dataPendidikanFormal->tanggal_diajukan ?? now(); 
                        $data['tanggal_ditolak'] = null;
                        $data['tanggal_ditangguhkan'] = null;
                        break;
                    case 'ditolak':
                        $data['tanggal_ditolak'] = now();
                        $data['tanggal_diajukan'] = null;
                        $data['tanggal_disetujui'] = null;
                        $data['tanggal_ditangguhkan'] = null;
                        break;
                    case 'ditangguhkan':
                        $data['tanggal_ditangguhkan'] = now();
                        $data['tanggal_diajukan'] = null;
                        $data['tanggal_disetujui'] = null;
                        $data['tanggal_ditolak'] = null;
                        break;
                    case 'draft':
                        $data['tanggal_diajukan'] = null;
                        $data['tanggal_disetujui'] = null;
                        $data['tanggal_ditolak'] = null;
                        $data['tanggal_ditangguhkan'] = null;
                        break;
                }
            } else {
                // If status is not changed, retain existing timestamps
                $data['tanggal_diajukan'] = $dataPendidikanFormal->tanggal_diajukan;
                $data['tanggal_disetujui'] = $dataPendidikanFormal->tanggal_disetujui;
                $data['tanggal_ditolak'] = $dataPendidikanFormal->tanggal_ditolak;
                $data['tanggal_ditangguhkan'] = $dataPendidikanFormal->tanggal_ditangguhkan;
            }
            // Retain existing 'keterangan_penolakan' if not explicitly updated to null or a new value
            if (!isset($data['keterangan_penolakan'])) {
                $data['keterangan_penolakan'] = $dataPendidikanFormal->keterangan_penolakan;
            }

            $dataPendidikanFormal->update($data);

            // Handle file_ijazah and file_transkrip if still present as direct files (legacy update)
            if ($request->hasFile('file_ijazah')) {
                // Assuming you want to convert these to polymorphic documents if uploaded via old fields
                $this->updateSingleFileAsDokumenPendukung($request->file('file_ijazah'), $dataPendidikanFormal, $dataPendidikanFormal->pegawai_id, 'Ijazah', 'Dokumen Ijazah Utama');
            } elseif ($request->input('file_ijazah_clear')) {
                // Add logic to clear old single file if a clear flag is sent
                 $this->deleteSingleFileDokumenPendukung($dataPendidikanFormal, 'Ijazah');
            }

            if ($request->hasFile('file_transkrip')) {
                $this->updateSingleFileAsDokumenPendukung($request->file('file_transkrip'), $dataPendidikanFormal, $dataPendidikanFormal->pegawai_id, 'Transkrip', 'Dokumen Transkrip Nilai');
            } elseif ($request->input('file_transkrip_clear')) {
                // Add logic to clear old single file if a clear flag is sent
                $this->deleteSingleFileDokumenPendukung($dataPendidikanFormal, 'Transkrip');
            }


            // Handle multiple supporting documents (polymorphic)
            $this->updateDokumenPendukung($request, $dataPendidikanFormal, $dataPendidikanFormal->pegawai_id);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_update_pendidikan_formal', $dataPendidikanFormal, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatDataPendidikanFormal($dataPendidikanFormal->load(['jenjangPendidikan', 'perguruanTinggi', 'prodiPerguruanTinggi', 'gelarAkademik', 'dokumenPendukung', 'pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional'])),
                'message' => 'Data pendidikan formal berhasil diperbarui oleh admin'
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
     * Delete data pendidikan formal.
     * Admin can delete any data.
     *
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function destroy($id)
    {
        $dataPendidikanFormal = SimpegDataPendidikanFormal::find($id);

        if (!$dataPendidikanFormal) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete associated supporting documents (polymorphic)
            $dokumenPendukung = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataPendidikanFormal')
                ->where('pendukungable_id', $id)
                ->get();

            foreach ($dokumenPendukung as $dokumen) {
                if (method_exists($dokumen, 'deleteFile')) {
                    $dokumen->deleteFile(); // Delete actual file from storage
                } else {
                    // Fallback if deleteFile is not defined in SimpegDataPendukung
                    Storage::disk('public')->delete($dokumen->file_path);
                }
                $dokumen->delete(); // Delete record from DB
            }

            $oldData = $dataPendidikanFormal->toArray();
            $dataPendidikanFormal->delete();

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_delete_pendidikan_formal', $dataPendidikanFormal, $oldData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data pendidikan formal berhasil dihapus'
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
        $dataPendidikanFormal = SimpegDataPendidikanFormal::find($id);

        if (!$dataPendidikanFormal) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal tidak ditemukan'
            ], 404);
        }

        if ($dataPendidikanFormal->status_pengajuan === 'disetujui') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah disetujui sebelumnya'
            ], 409);
        }

        $oldData = $dataPendidikanFormal->getOriginal();
        $dataPendidikanFormal->update([
            'status_pengajuan' => 'disetujui',
            'tanggal_disetujui' => now(),
            'tanggal_ditolak' => null,
            'tanggal_ditangguhkan' => null, // Clear if approved
            'keterangan_penolakan' => null, // Clear rejection note
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_approve_pendidikan_formal', $dataPendidikanFormal, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data pendidikan formal berhasil disetujui'
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
        $dataPendidikanFormal = SimpegDataPendidikanFormal::find($id);

        if (!$dataPendidikanFormal) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal tidak ditemukan'
            ], 404);
        }

        if ($dataPendidikanFormal->status_pengajuan === 'ditolak') {
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

        $oldData = $dataPendidikanFormal->getOriginal();
        $dataPendidikanFormal->update([
            'status_pengajuan' => 'ditolak',
            'tanggal_ditolak' => now(),
            'tanggal_diajukan' => null,
            'tanggal_disetujui' => null,
            'tanggal_ditangguhkan' => null,
            'keterangan_penolakan' => $request->keterangan_penolakan,
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_reject_pendidikan_formal', $dataPendidikanFormal, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data pendidikan formal berhasil ditolak'
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
        $dataPendidikanFormal = SimpegDataPendidikanFormal::find($id);

        if (!$dataPendidikanFormal) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal tidak ditemukan'
            ], 404);
        }

        if ($dataPendidikanFormal->status_pengajuan === 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah dalam status draft'
            ], 409);
        }

        $oldData = $dataPendidikanFormal->getOriginal();
        $dataPendidikanFormal->update([
            'status_pengajuan' => 'draft',
            'tanggal_diajukan' => null,
            'tanggal_disetujui' => null,
            'tanggal_ditolak' => null,
            'tanggal_ditangguhkan' => null, // Clear all other timestamps if moved to draft
            'keterangan_penolakan' => null, // Clear rejection note
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_to_draft_pendidikan_formal', $dataPendidikanFormal, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status pendidikan formal berhasil diubah menjadi draft'
        ]);
    }

    /**
     * Admin: Batch delete data pendidikan formal.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_pendidikan_formal,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataPendidikanFormalList = SimpegDataPendidikanFormal::whereIn('id', $request->ids)->get();

        if ($dataPendidikanFormalList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data pendidikan formal yang ditemukan untuk dihapus'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataPendidikanFormalList as $dataPendidikanFormal) {
                try {
                    // Delete associated supporting documents (polymorphic)
                    $dokumenPendukung = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataPendidikanFormal')
                        ->where('pendukungable_id', $dataPendidikanFormal->id)
                        ->get();

                    foreach ($dokumenPendukung as $dokumen) {
                        if (method_exists($dokumen, 'deleteFile')) {
                            $dokumen->deleteFile(); // Delete actual file from storage
                        } else {
                            Storage::disk('public')->delete($dokumen->file_path); // Fallback
                        }
                        $dokumen->delete(); // Delete record from DB
                    }

                    $oldData = $dataPendidikanFormal->toArray();
                    $dataPendidikanFormal->delete();
                    
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('admin_batch_delete_pendidikan_formal', $dataPendidikanFormal, $oldData);
                    }
                    $deletedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $dataPendidikanFormal->id,
                        'nomor_ijazah' => $dataPendidikanFormal->nomor_ijazah,
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
                'message' => "Berhasil menghapus {$deletedCount} data pendidikan formal",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data pendidikan formal",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }

    /**
     * Admin: Batch approve data pendidikan formal.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_pendidikan_formal,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataPendidikanFormal::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', ['draft', 'diajukan', 'ditolak', 'ditangguhkan']) // All statuses can be approved by admin
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data pendidikan formal yang memenuhi syarat untuk disetujui.'
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
                    'tanggal_disetujui' => now(),
                    'tanggal_diajukan' => $item->tanggal_diajukan ?? now(), // Retain if already submitted, otherwise set now
                    'tanggal_ditolak' => null,
                    'tanggal_ditangguhkan' => null,
                    'keterangan_penolakan' => null,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_approve_pendidikan_formal', $item, $oldData);
                }
                $updatedCount++;
                $approvedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch approve pendidikan formal: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data pendidikan formal",
            'updated_count' => $updatedCount,
            'approved_ids' => $approvedIds
        ]);
    }

    /**
     * Admin: Batch reject data pendidikan formal.
     *
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_pendidikan_formal,id',
            'keterangan_penolakan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataPendidikanFormal::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', ['draft', 'diajukan', 'disetujui', 'ditangguhkan']) // All statuses can be rejected by admin
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data pendidikan formal yang memenuhi syarat untuk ditolak.'
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
                    'tanggal_ditolak' => now(),
                    'tanggal_diajukan' => null,
                    'tanggal_disetujui' => null,
                    'tanggal_ditangguhkan' => null,
                    'keterangan_penolakan' => $request->keterangan_penolakan,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_reject_pendidikan_formal', $item, $oldData);
                }
                $updatedCount++;
                $rejectedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch reject pendidikan formal: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data pendidikan formal",
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
            'ids.*' => 'required|uuid|exists:simpeg_data_pendidikan_formal,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataPendidikanFormal::whereIn('id', $request->ids)
            ->where('status_pengajuan', '!=', 'draft') // Only process if not already draft
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data pendidikan formal yang memenuhi syarat untuk diubah menjadi draft.'
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
                    'tanggal_diajukan' => null,
                    'tanggal_disetujui' => null,
                    'tanggal_ditolak' => null,
                    'tanggal_ditangguhkan' => null,
                    'keterangan_penolakan' => null,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_batch_to_draft_pendidikan_formal', $item, $oldData);
                }
                $updatedCount++;
                $draftedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error during batch change to draft for pendidikan formal: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah status ke draft secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengubah {$updatedCount} data pendidikan formal menjadi draft",
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

        $query = SimpegDataPendidikanFormal::query();

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
        $jenjangPendidikanList = SimpegJenjangPendidikan::select('id', 'jenjang_pendidikan as nama')
            ->orderBy('jenjang_pendidikan')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jenjang Pendidikan']);

        $perguruanTinggiList = MasterPerguruanTinggi::select('id', 'nama_universitas as nama')
            ->orderBy('nama_universitas')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Perguruan Tinggi']);

        $prodiPerguruanTinggiList = MasterProdiPerguruanTinggi::select('id', 'nama_prodi as nama')
            ->orderBy('nama_prodi')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Program Studi']);

        $gelarAkademikList = MasterGelarAkademik::select('id', 'nama_gelar as nama')
            ->orderBy('nama_gelar')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Gelar Akademik']);
            
        $tahunMasukOptions = SimpegDataPendidikanFormal::distinct()->pluck('tahun_masuk')->filter()->sortDesc()->values()->map(function($item) { return ['id' => $item, 'nama' => $item]; })->prepend(['id' => 'semua', 'nama' => 'Semua Tahun Masuk'])->toArray();
        $tahunLulusOptions = SimpegDataPendidikanFormal::distinct()->pluck('tahun_lulus')->filter()->sortDesc()->values()->map(function($item) { return ['id' => $item, 'nama' => $item]; })->prepend(['id' => 'semua', 'nama' => 'Semua Tahun Lulus'])->toArray();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'jenjang_pendidikan' => $jenjangPendidikanList,
                'perguruan_tinggi' => $perguruanTinggiList,
                'prodi_perguruan_tinggi' => $prodiPerguruanTinggiList,
                'gelar_akademik' => $gelarAkademikList,
                'tahun_masuk' => $tahunMasukOptions,
                'tahun_lulus' => $tahunLulusOptions,
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
        $jenjangPendidikanOptions = SimpegJenjangPendidikan::select('id', 'jenjang_pendidikan as nama')
            ->orderBy('jenjang_pendidikan')
            ->get();
        
        $perguruanTinggiOptions = MasterPerguruanTinggi::select('id', 'nama_universitas as nama')
            ->orderBy('nama_universitas')
            ->get();

        $prodiPerguruanTinggiOptions = MasterProdiPerguruanTinggi::select('id', 'nama_prodi as nama')
            ->orderBy('nama_prodi')
            ->get();

        $gelarAkademikOptions = MasterGelarAkademik::select('id', 'gelar', 'nama_gelar')
            ->orderBy('nama_gelar')
            ->get()
            ->map(function($item) {
                return ['id' => $item->id, 'label' => $item->nama_gelar . ($item->gelar ? ' (' . $item->gelar . ')' : '')];
            });

        // Contoh untuk tipe_dokumen, SESUAIKAN DENGAN CHECK CONSTRAINT DI DATABASE ANDA
        $tipeDokumenOptions = [
            ['id' => 'Ijazah', 'nama' => 'Ijazah'],
            ['id' => 'Transkrip', 'nama' => 'Transkrip'],
            ['id' => 'Surat_Keterangan', 'nama' => 'Surat Keterangan'],
            ['id' => 'Dokumen_Lainnya', 'nama' => 'Dokumen Lainnya'],
        ];

        return [
            'form_options' => [
                'jenjang_pendidikan' => $jenjangPendidikanOptions,
                'perguruan_tinggi' => $perguruanTinggiOptions,
                'prodi_perguruan_tinggi' => $prodiPerguruanTinggiOptions,
                'gelar_akademik' => $gelarAkademikOptions,
                'letak_gelar' => [
                    ['id' => 'depan', 'nama' => 'Depan'],
                    ['id' => 'belakang', 'nama' => 'Belakang'],
                    ['id' => 'tidak_ada', 'nama' => 'Tidak Ada'],
                ],
                'status_pengajuan' => [
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                    ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan'],
                ],
                'tipe_dokumen_options' => $tipeDokumenOptions, // For dynamic document upload form
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|uuid',
                'lokasi_studi' => 'required|string|max:100',
                'jenjang_pendidikan_id' => 'required|uuid|exists:simpeg_jenjang_pendidikan,id',
                'perguruan_tinggi_id' => 'required|uuid|exists:simpeg_master_perguruan_tinggi,id',
                'prodi_perguruan_tinggi_id' => 'required|uuid|exists:simpeg_master_prodi_perguruan_tinggi,id',
                'gelar_akademik_id' => 'nullable|uuid|exists:simpeg_master_gelar_akademik,id',
                'bidang_studi' => 'nullable|string|max:255',
                'nisn' => 'nullable|string|max:20',
                'konsentrasi' => 'nullable|string|max:100',
                'tahun_masuk' => 'required|integer|min:1900|max:' . (date('Y')),
                'tanggal_kelulusan' => 'nullable|date|before_or_equal:today',
                'tahun_lulus' => 'required|integer|min:1900|max:' . (date('Y')) . '|after_or_equal:tahun_masuk',
                'nomor_ijazah' => 'required|string|max:50',
                'tanggal_ijazah' => 'required|date|before_or_equal:today|after_or_equal:tanggal_kelulusan',
                'file_ijazah' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // Still here for backward compatibility or direct upload
                'file_transkrip' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // Still here for backward compatibility or direct upload
                'nomor_ijazah_negara' => 'nullable|string|max:50',
                'gelar_ijazah_negara' => 'nullable|string|max:50',
                'tanggal_ijazah_negara' => 'nullable|date',
                'nomor_induk' => 'nullable|string|max:50',
                'judul_tugas' => 'nullable|string|max:500',
                'letak_gelar' => 'nullable|in:depan,belakang,tidak_ada',
                'jumlah_semster_ditempuh' => 'nullable|integer|min:1',
                'jumlah_sks_kelulusan' => 'nullable|integer|min:0',
                'ipk_kelulusan' => 'nullable|numeric|min:0|max:4.00',
                'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
                'keterangan_penolakan' => 'nullable|string|max:500',
                'dokumen_pendukung' => 'nullable|array',
                'dokumen_pendukung.*.id' => 'nullable|uuid|exists:simpeg_data_pendukung,id',
                'dokumen_pendukung.*.tipe_dokumen' => 'required_with:dokumen_pendukung|string|in:Ijazah,Transkrip,Surat_Keterangan,Dokumen_Lainnya', // SESUAIKAN
                'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string|max:255',
                'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|uuid',
                'dokumen_pendukung.*.keterangan' => 'nullable|string|max:1000',
                'dokumen_pendukung.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
                'dokumen_pendukung_to_delete' => 'nullable|array',
                'dokumen_pendukung_to_delete.*' => 'nullable|uuid|exists:simpeg_data_pendukung,id'
            ],
            'field_notes' => [
                'pegawai_id' => 'Pilih pegawai yang bersangkutan.',
                'lokasi_studi' => 'Lokasi tempat studi (contoh: Dalam Negeri, Luar Negeri).',
                'jenjang_pendidikan_id' => 'Pilih jenjang pendidikan dari daftar.',
                'perguruan_tinggi_id' => 'Pilih perguruan tinggi dari daftar.',
                'prodi_perguruan_tinggi_id' => 'Pilih program studi dari daftar.',
                'gelar_akademik_id' => 'Pilih gelar akademik yang diperoleh.',
                'bidang_studi' => 'Bidang studi utama.',
                'nisn' => 'Nomor Induk Siswa Nasional (jika ada).',
                'konsentrasi' => 'Konsentrasi atau peminatan studi (jika ada).',
                'tahun_masuk' => 'Tahun masuk perguruan tinggi.',
                'tanggal_kelulusan' => 'Tanggal resmi kelulusan.',
                'tahun_lulus' => 'Tahun lulus pendidikan.',
                'nomor_ijazah' => 'Nomor ijazah.',
                'tanggal_ijazah' => 'Tanggal penerbitan ijazah.',
                'file_ijazah' => 'Unggah file ijazah utama (PDF/gambar).',
                'file_transkrip' => 'Unggah file transkrip nilai (PDF/gambar).',
                'nomor_ijazah_negara' => 'Nomor ijazah negara (jika ada).',
                'gelar_ijazah_negara' => 'Gelar ijazah negara (jika ada).',
                'tanggal_ijazah_negara' => 'Tanggal ijazah negara (jika ada).',
                'nomor_induk' => 'Nomor induk mahasiswa/peserta didik.',
                'judul_tugas' => 'Judul skripsi/tesis/disertasi atau tugas akhir.',
                'letak_gelar' => 'Posisi gelar pada nama.',
                'jumlah_semster_ditempuh' => 'Jumlah semester yang ditempuh.',
                'jumlah_sks_kelulusan' => 'Jumlah SKS kelulusan.',
                'ipk_kelulusan' => 'Indeks Prestasi Kumulatif saat kelulusan.',
                'keterangan_penolakan' => 'Keterangan jika pengajuan ditolak.',
                'dokumen_pendukung' => 'Unggah dokumen pendukung lainnya (sertifikat, surat keterangan, dll).',
            ],
        ];
    }

    /**
     * Store supporting documents.
     *
     * @param array $dokumenArray
     * @param SimpegDataPendidikanFormal $pendidikan
     * @param int $pegawaiId The ID of the associated employee
     * @param Request $request The full request object to get file instances
     * @return void
     */
    private function storeDokumenPendukung($dokumenArray, $pendidikan, $pegawaiId, Request $request)
    {
        foreach ($dokumenArray as $index => $dokumen) {
            $dokumenData = [
                'tipe_dokumen' => $dokumen['tipe_dokumen'],
                'nama_dokumen' => $dokumen['nama_dokumen'],
                'jenis_dokumen_id' => $dokumen['jenis_dokumen_id'] ?? null,
                'keterangan' => $dokumen['keterangan'] ?? null,
                'pendukungable_type' => 'App\Models\SimpegDataPendidikanFormal',
                'pendukungable_id' => $pendidikan->id
            ];

            // Get the file instance from the request using its nested path
            $file = $request->file("dokumen_pendukung.{$index}.file");

            if ($file && $file instanceof \Illuminate\Http\UploadedFile) {
                $fileName = 'pendidikan_dok_' . $pegawaiId . '_' . $pendidikan->id . '_' . time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('pendidikan_dokumen', $fileName, 'public'); // path: storage/app/public/pendidikan_dokumen
                $dokumenData['file_path'] = $filePath;
            } else {
                // If file is not present for this nested document item, skip adding file_path
                $dokumenData['file_path'] = null;
            }

            SimpegDataPendukung::create($dokumenData);
        }
    }

    /**
     * Helper to store a single legacy file upload (ijazah/transkrip) as a polymorphic document.
     *
     * @param \Illuminate\Http\UploadedFile|null $file
     * @param SimpegDataPendidikanFormal $pendidikan
     * @param int $pegawaiId
     * @param string $tipeDokumen
     * @param string $namaDokumen
     * @return void
     */
    private function storeSingleFileAsDokumenPendukung($file, $pendidikan, $pegawaiId, $tipeDokumen, $namaDokumen)
    {
        if (!$file || !$file instanceof \Illuminate\Http\UploadedFile) {
            return;
        }

        $fileName = 'pendidikan_single_dok_' . $pegawaiId . '_' . $pendidikan->id . '_' . time() . '_' . Str::random(5) . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('pendidikan_dokumen', $fileName, 'public');

        SimpegDataPendukung::create([
            'tipe_dokumen' => $tipeDokumen,
            'nama_dokumen' => $namaDokumen,
            'keterangan' => 'Uploaded via legacy ' . strtolower($tipeDokumen) . ' field',
            'file_path' => $filePath,
            'pendukungable_type' => 'App\Models\SimpegDataPendidikanFormal',
            'pendukungable_id' => $pendidikan->id,
        ]);
    }

    /**
     * Helper to delete a single legacy file document by type.
     *
     * @param SimpegDataPendidikanFormal $pendidikan
     * @param string $tipeDokumen
     * @return void
     */
    private function deleteSingleFileDokumenPendukung($pendidikan, $tipeDokumen)
    {
        $dokumen = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataPendidikanFormal')
                                    ->where('pendukungable_id', $pendidikan->id)
                                    ->where('tipe_dokumen', $tipeDokumen)
                                    ->first();
        if ($dokumen) {
            $dokumen->deleteFile();
            $dokumen->delete();
        }
    }

    /**
     * Update supporting documents (add new, update existing, delete old).
     *
     * @param Request $request
     * @param SimpegDataPendidikanFormal $pendidikan
     * @param int $pegawaiId The ID of the associated employee
     * @return void
     */
    private function updateDokumenPendukung(Request $request, $pendidikan, $pegawaiId)
    {
        // 1. Delete documents flagged for deletion
        if ($request->has('dokumen_pendukung_to_delete') && is_array($request->dokumen_pendukung_to_delete)) {
            $deleteIds = $request->dokumen_pendukung_to_delete;
            $oldDokumen = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataPendidikanFormal')
                ->where('pendukungable_id', $pendidikan->id)
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
                    'pendukungable_type' => 'App\Models\SimpegDataPendidikanFormal',
                    'pendukungable_id' => $pendidikan->id
                ];

                if ($file) {
                    $fileName = 'pendidikan_dok_' . $pegawaiId . '_' . $pendidikan->id . '_' . time() . '_' . $index . '.' . $file->getClientOriginalExtension();
                    $filePath = $file->storeAs('pendidikan_dokumen', $fileName, 'public');
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
     * Helper: Format data pendidikan formal response for display.
     */
    protected function formatDataPendidikanFormal($dataPendidikanFormal, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataPendidikanFormal->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $pegawai = $dataPendidikanFormal->pegawai;
        $nipPegawai = $pegawai ? $pegawai->nip : '-';
        $namaPegawai = $pegawai ? $pegawai->nama : '-';

        $data = [
            'id' => $dataPendidikanFormal->id,
            'pegawai_id' => $dataPendidikanFormal->pegawai_id,
            'nip_pegawai' => $nipPegawai,
            'nama_pegawai' => $namaPegawai,
            'jenjang_pendidikan_id' => $dataPendidikanFormal->jenjang_pendidikan_id,
            'jenjang_pendidikan_label' => $dataPendidikanFormal->jenjangPendidikan ? $dataPendidikanFormal->jenjangPendidikan->jenjang_pendidikan : '-',
            'lokasi_studi' => $dataPendidikanFormal->lokasi_studi,
            'perguruan_tinggi_id' => $dataPendidikanFormal->perguruan_tinggi_id,
            'nama_institusi_label' => $dataPendidikanFormal->perguruanTinggi ? $dataPendidikanFormal->perguruanTinggi->nama_universitas : '-',
            'prodi_perguruan_tinggi_id' => $dataPendidikanFormal->prodiPerguruanTinggi ? $dataPendidikanFormal->prodiPerguruanTinggi->id : null, // Ensure ID is returned
            'nama_prodi_label' => $dataPendidikanFormal->prodiPerguruanTinggi ? $dataPendidikanFormal->prodiPerguruanTinggi->nama_prodi : '-',
            'gelar_akademik_id' => $dataPendidikanFormal->gelar_akademik_id,
            'gelar_akademik_label' => $dataPendidikanFormal->gelarAkademik ? ($dataPendidikanFormal->gelarAkademik->nama_gelar . ($dataPendidikanFormal->gelarAkademik->gelar ? ' (' . $dataPendidikanFormal->gelarAkademik->gelar . ')' : '')) : '-',
            'bidang_studi' => $dataPendidikanFormal->bidang_studi,
            'nisn' => $dataPendidikanFormal->nisn,
            'konsentrasi' => $dataPendidikanFormal->konsentrasi,
            'tahun_masuk' => $dataPendidikanFormal->tahun_masuk,
            'tanggal_kelulusan' => $dataPendidikanFormal->tanggal_kelulusan,
            'tanggal_kelulusan_formatted' => $dataPendidikanFormal->tanggal_kelulusan ? Carbon::parse($dataPendidikanFormal->tanggal_kelulusan)->format('d M Y') : '-',
            'tahun_lulus' => $dataPendidikanFormal->tahun_lulus,
            'nomor_ijazah' => $dataPendidikanFormal->nomor_ijazah,
            'tanggal_ijazah' => $dataPendidikanFormal->tanggal_ijazah,
            'tanggal_ijazah_formatted' => $dataPendidikanFormal->tanggal_ijazah ? Carbon::parse($dataPendidikanFormal->tanggal_ijazah)->format('d M Y') : '-',
            'file_ijazah' => $dataPendidikanFormal->file_ijazah, // Legacy, can be null if using polymorphic
            'file_ijazah_url' => $dataPendidikanFormal->file_ijazah ? url('storage/' . $dataPendidikanFormal->file_ijazah) : null,
            'file_transkrip' => $dataPendidikanFormal->file_transkrip, // Legacy, can be null if using polymorphic
            'file_transkrip_url' => $dataPendidikanFormal->file_transkrip ? url('storage/' . $dataPendidikanFormal->file_transkrip) : null,
            'nomor_ijazah_negara' => $dataPendidikanFormal->nomor_ijazah_negara,
            'gelar_ijazah_negara' => $dataPendidikanFormal->gelar_ijazah_negara,
            'tanggal_ijazah_negara' => $dataPendidikanFormal->tanggal_ijazah_negara,
            'tanggal_ijazah_negara_formatted' => $dataPendidikanFormal->tanggal_ijazah_negara ? Carbon::parse($dataPendidikanFormal->tanggal_ijazah_negara)->format('d M Y') : '-',
            'nomor_induk' => $dataPendidikanFormal->nomor_induk,
            'judul_tugas' => $dataPendidikanFormal->judul_tugas,
            'letak_gelar' => $dataPendidikanFormal->letak_gelar,
            'jumlah_semster_ditempuh' => $dataPendidikanFormal->jumlah_semster_ditempuh,
            'jumlah_sks_kelulusan' => $dataPendidikanFormal->jumlah_sks_kelulusan,
            'ipk_kelulusan' => $dataPendidikanFormal->ipk_kelulusan,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan_penolakan' => $dataPendidikanFormal->keterangan_penolakan, // Include rejection note
            'timestamps' => [
                'tgl_input' => $dataPendidikanFormal->tgl_input,
                'tanggal_diajukan' => $dataPendidikanFormal->tanggal_diajukan ?? null,
                'tanggal_disetujui' => $dataPendidikanFormal->tanggal_disetujui ?? null,
                'tanggal_ditolak' => $dataPendidikanFormal->tanggal_ditolak ?? null,
                'tanggal_ditangguhkan' => $dataPendidikanFormal->tanggal_ditangguhkan ?? null,
            ],
            'created_at' => $dataPendidikanFormal->created_at,
            'updated_at' => $dataPendidikanFormal->updated_at
        ];

        // Add action URLs if requested (for admin view)
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datapendidikanformaladm/{$dataPendidikanFormal->id}"),
                'update_url' => url("/api/admin/datapendidikanformaladm/{$dataPendidikanFormal->id}"),
                'delete_url' => url("/api/admin/datapendidikanformaladm/{$dataPendidikanFormal->id}"),
                'approve_url' => url("/api/admin/datapendidikanformaladm/{$dataPendidikanFormal->id}/approve"),
                'reject_url' => url("/api/admin/datapendidikanformaladm/{$dataPendidikanFormal->id}/reject"),
                'to_draft_url' => url("/api/admin/datapendidikanformaladm/{$dataPendidikanFormal->id}/todraft"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data pendidikan formal "' . $dataPendidikanFormal->nomor_ijazah . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data pendidikan formal "' . $dataPendidikanFormal->nomor_ijazah . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data pendidikan formal "' . $dataPendidikanFormal->nomor_ijazah . '"?',
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data pendidikan formal "' . $dataPendidikanFormal->nomor_ijazah . '" menjadi draft?'
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
