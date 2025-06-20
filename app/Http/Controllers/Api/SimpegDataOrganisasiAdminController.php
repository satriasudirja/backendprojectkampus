<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataOrganisasi;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegJabatanFungsional; // Pastikan ini di-import jika digunakan di format data/relasi
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // Untuk formatting tanggal

class SimpegDataOrganisasiAdminController extends Controller
{
    /**
     * Get all data organisasi for admin with extensive filters.
     * Admin dapat melihat data organisasi untuk pegawai manapun.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $statusPengajuan = $request->status_pengajuan;
        $unitKerjaId = $request->unit_kerja_id; // Menggunakan ID unit kerja dari request
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $namaOrganisasi = $request->nama_organisasi;
        $jenisOrganisasi = $request->jenis_organisasi;
        $periodeMulai = $request->periode_mulai;
        $periodeSelesai = $request->periode_selesai;
        $jabatanDalamOrganisasi = $request->jabatan_dalam_organisasi;
        $pegawaiId = $request->pegawai_id; // Admin bisa memfilter berdasarkan pegawai_id

        $query = SimpegDataOrganisasi::with([
            'pegawai' => function ($q) {
                $q->with([
                    'unitKerja',
                    'dataJabatanFungsional' => function ($subQuery) {
                        $subQuery->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1);
                    }
                ]);
            }
        ]);

        // Filter berdasarkan pegawai_id (jika admin ingin melihat data spesifik pegawai)
        if ($pegawaiId) {
            $query->where('pegawai_id', $pegawaiId);
        }

        // Filter berdasarkan Unit Kerja (Hierarki)
        if ($unitKerjaId) {
            $unitKerjaTarget = SimpegUnitKerja::find($unitKerjaId);

            if ($unitKerjaTarget) {
                $unitIdsInScope = $this->getAllChildUnitIds($unitKerjaTarget);
                $unitIdsInScope[] = $unitKerjaTarget->id; // Tambahkan ID unit target itu sendiri

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

        // Filter by search (NIP, Nama Pegawai, Nama Organisasi, Jabatan dalam Organisasi, Tempat Organisasi)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_organisasi', 'like', '%' . $search . '%')
                    ->orWhere('jabatan_dalam_organisasi', 'like', '%' . $search . '%')
                    ->orWhere('tempat_organisasi', 'like', '%' . $search . '%')
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

        // Filter by Jabatan Fungsional
        if ($jabatanFungsionalId) {
            $query->whereHas('pegawai.dataJabatanFungsional', function ($q) use ($jabatanFungsionalId) {
                $q->where('jabatan_fungsional_id', $jabatanFungsionalId);
            });
        }

        // Additional filters (dari kolom spesifik)
        if ($namaOrganisasi) {
            $query->where('nama_organisasi', 'like', '%' . $namaOrganisasi . '%');
        }
        if ($jenisOrganisasi && $jenisOrganisasi != 'semua') {
            $query->where('jenis_organisasi', $jenisOrganisasi);
        }
        if ($periodeMulai) {
            $query->whereDate('periode_mulai', '>=', $periodeMulai);
        }
        if ($periodeSelesai) {
            $query->whereDate('periode_selesai', '<=', $periodeSelesai);
        }
        if ($jabatanDalamOrganisasi) {
            $query->where('jabatan_dalam_organisasi', 'like', '%' . $jabatanDalamOrganisasi . '%');
        }

        $dataOrganisasi = $query->orderBy('periode_mulai', 'desc')->paginate($perPage);

        $dataOrganisasi->getCollection()->transform(function ($item) {
            return $this->formatDataOrganisasi($item, true); // Admin view includes actions
        });

        return response()->json([
            'success' => true,
            'data' => $dataOrganisasi,
            'empty_data' => $dataOrganisasi->isEmpty(),
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ],
                'jenis_organisasi_options' => $this->getJenisOrganisasiOptions(),
                'unit_kerja_options' => $this->getUnitKerjaOptions(),
                'jabatan_fungsional_options' => $this->getJabatanFungsionalOptions(),
                'nama_organisasi_options' => SimpegDataOrganisasi::distinct()->pluck('nama_organisasi')->filter()->values()->toArray(),
                'jabatan_dalam_organisasi_options' => SimpegDataOrganisasi::distinct()->pluck('jabatan_dalam_organisasi')->filter()->values()->toArray(),
            ],
            'table_columns' => [
                ['field' => 'nip_pegawai', 'label' => 'NIP', 'sortable' => false],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => false],
                ['field' => 'unit_kerja_pegawai', 'label' => 'Unit Kerja', 'sortable' => false],
                ['field' => 'jabatan_fungsional_pegawai', 'label' => 'Jabatan Fungsional', 'sortable' => false],
                ['field' => 'nama_organisasi', 'label' => 'Nama Organisasi', 'sortable' => true, 'sortable_field' => 'nama_organisasi'],
                ['field' => 'jabatan_dalam_organisasi', 'label' => 'Jabatan', 'sortable' => true, 'sortable_field' => 'jabatan_dalam_organisasi'],
                ['field' => 'jenis_organisasi', 'label' => 'Jenis Organisasi', 'sortable' => true, 'sortable_field' => 'jenis_organisasi'],
                ['field' => 'periode', 'label' => 'Periode', 'sortable' => true, 'sortable_field' => 'periode_mulai'],
                ['field' => 'tempat_organisasi', 'label' => 'Tempat', 'sortable' => true, 'sortable_field' => 'tempat_organisasi'],
                ['field' => 'status_pengajuan_info.label', 'label' => 'Status', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/dataorganisasi/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/dataorganisasi/batch/reject"),
                    'method' => 'PATCH',
                    'label' => 'Tolak Terpilih',
                    'icon' => 'times',
                    'color' => 'danger',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menolak data terpilih?',
                    'needs_input' => true,
                    'input_placeholder' => 'Keterangan penolakan (opsional)'
                ],
                'delete' => [
                    'url' => url("/api/admin/dataorganisasi/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data terpilih?',
                ]
            ],
            'pagination' => [
                'current_page' => $dataOrganisasi->currentPage(),
                'per_page' => $dataOrganisasi->perPage(),
                'total' => $dataOrganisasi->total(),
                'last_page' => $dataOrganisasi->lastPage(),
                'from' => $dataOrganisasi->firstItem(),
                'to' => $dataOrganisasi->lastItem()
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * Admin dapat menambahkan data untuk pegawai manapun.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'nama_organisasi' => 'required|string|max:100',
            'jabatan_dalam_organisasi' => 'nullable|string|max:100',
            'jenis_organisasi' => 'nullable|in:lokal,nasional,internasional,lainnya',
            'tempat_organisasi' => 'nullable|string|max:200',
            'periode_mulai' => 'required|date',
            'periode_selesai' => 'nullable|date|after_or_equal:periode_mulai',
            'website' => 'nullable|url|max:200',
            'keterangan' => 'nullable|string|max:1000',
            'file_dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->except(['file_dokumen']);
        $data['tgl_input'] = now()->toDateString();

        if (empty($data['jenis_organisasi'])) {
            $data['jenis_organisasi'] = 'lainnya';
        }

        $data['status_pengajuan'] = $request->input('status_pengajuan', 'draft');
        if ($data['status_pengajuan'] === 'diajukan') {
            $data['tgl_diajukan'] = now();
        } elseif ($data['status_pengajuan'] === 'disetujui') {
            $data['tgl_disetujui'] = now();
            // Perbaikan: Pastikan tgl_diajukan juga diisi jika langsung disetujui
            $data['tgl_diajukan'] = $data['tgl_diajukan'] ?? now(); 
        } elseif ($data['status_pengajuan'] === 'ditolak') {
            $data['tgl_ditolak'] = now();
        }

        if ($request->hasFile('file_dokumen')) {
            $file = $request->file('file_dokumen');
            $fileName = 'dok_organisasi_' . time() . '_' . $request->pegawai_id . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/organisasi', $fileName);
            $data['file_dokumen'] = $fileName;
        }

        $dataOrganisasi = SimpegDataOrganisasi::create($data);

        ActivityLogger::log('admin_create_data_organisasi', $dataOrganisasi, $dataOrganisasi->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataOrganisasi($dataOrganisasi->load('pegawai')),
            'message' => 'Data organisasi berhasil ditambahkan oleh admin'
        ], 201);
    }

    /**
     * Display the specified resource.
     * Admin dapat melihat detail data organisasi untuk pegawai manapun.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $dataOrganisasi = SimpegDataOrganisasi::with([
            'pegawai' => function ($q) {
                $q->with([
                    'unitKerja',
                    'dataJabatanFungsional' => function ($subQuery) {
                        $subQuery->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1);
                    },
                    'dataJabatanStruktural' => function ($subQuery) {
                        $subQuery->with('jabatanStruktural.jenisJabatanStruktural')->orderBy('tgl_mulai', 'desc')->limit(1);
                    },
                    'dataPendidikanFormal' => function ($subQuery) {
                        $subQuery->with('jenjangPendidikan')->orderBy('jenjang_pendidikan_id', 'desc')->limit(1);
                    },
                    'jabatanAkademik',
                    'statusAktif'
                ]);
            }
        ])->find($id);

        if (!$dataOrganisasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data organisasi tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatDataOrganisasi($dataOrganisasi),
            'jenis_organisasi_options' => $this->getJenisOrganisasiOptions(),
            'pegawai_info_detail' => $this->formatPegawaiInfoDetail($dataOrganisasi->pegawai),
        ]);
    }

    /**
     * Update the specified resource in storage.
     * Admin bisa mengedit data apapun tanpa batasan status pengajuan pegawai.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $dataOrganisasi = SimpegDataOrganisasi::find($id);

        if (!$dataOrganisasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data organisasi tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'sometimes|string|max:100',
            'jabatan_dalam_organisasi' => 'nullable|string|max:100',
            'jenis_organisasi' => 'nullable|in:lokal,nasional,internasional,lainnya',
            'tempat_organisasi' => 'nullable|string|max:200',
            'periode_mulai' => 'sometimes|date',
            'periode_selesai' => 'nullable|date|after_or_equal:periode_mulai',
            'website' => 'nullable|url|max:200',
            'keterangan' => 'nullable|string|max:1000',
            'file_dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            'keterangan_penolakan' => 'nullable|string|max:500' // Tambahkan ini untuk admin
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek duplikasi jika nama_organisasi atau periode_mulai diubah
        if ($request->has('nama_organisasi') || $request->has('periode_mulai')) {
             $existingData = SimpegDataOrganisasi::where('pegawai_id', $dataOrganisasi->pegawai_id)
                ->where('nama_organisasi', $request->input('nama_organisasi', $dataOrganisasi->nama_organisasi))
                ->where('periode_mulai', $request->input('periode_mulai', $dataOrganisasi->periode_mulai))
                ->where('id', '!=', $id)
                ->first();

            if ($existingData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data organisasi dengan nama dan periode mulai yang sama sudah ada untuk pegawai ini.'
                ], 422);
            }
        }

        $oldData = $dataOrganisasi->getOriginal();
        $data = $request->except(['file_dokumen', 'file_dokumen_clear']); // Kecualikan flag clear


        if ($request->hasFile('file_dokumen')) {
            if ($dataOrganisasi->file_dokumen) {
                Storage::delete('public/pegawai/organisasi/' . $dataOrganisasi->file_dokumen);
            }

            $file = $request->file('file_dokumen');
            $fileName = 'dok_organisasi_' . time() . '_' . $dataOrganisasi->pegawai_id . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/organisasi', $fileName);
            $data['file_dokumen'] = $fileName;
        } elseif ($request->input('file_dokumen_clear')) { // Tambahkan logika clear file
            if ($dataOrganisasi->file_dokumen) {
                Storage::delete('public/pegawai/organisasi/' . $dataOrganisasi->file_dokumen);
            }
            $data['file_dokumen'] = null; // Set to null jika ingin menghapus file
        } else {
             // Jika tidak ada file baru dan tidak ada clear flag, pertahankan file lama
             $data['file_dokumen'] = $dataOrganisasi->file_dokumen;
        }

        // Handle status_pengajuan dan timestamps terkait
        if (isset($data['status_pengajuan']) && $data['status_pengajuan'] !== $dataOrganisasi->status_pengajuan) {
            switch ($data['status_pengajuan']) {
                case 'diajukan':
                    $data['tgl_diajukan'] = now();
                    $data['tgl_disetujui'] = null;
                    $data['tgl_ditolak'] = null;
                    break;
                case 'disetujui':
                    $data['tgl_disetujui'] = now();
                    $data['tgl_diajukan'] = $dataOrganisasi->tgl_diajukan ?? now(); // Tetap pakai tgl_diajukan lama jika ada
                    $data['tgl_ditolak'] = null;
                    break;
                case 'ditolak':
                    $data['tgl_ditolak'] = now();
                    $data['tgl_diajukan'] = null;
                    $data['tgl_disetujui'] = null;
                    break;
                case 'draft': // Admin bisa mengubah kembali ke draft
                    $data['tgl_diajukan'] = null;
                    $data['tgl_disetujui'] = null;
                    $data['tgl_ditolak'] = null;
                    break;
            }
        }
        
        $dataOrganisasi->update($data);

        ActivityLogger::log('admin_update_data_organisasi', $dataOrganisasi, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataOrganisasi($dataOrganisasi->load('pegawai')),
            'message' => 'Data organisasi berhasil diperbarui oleh admin'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     * Admin bisa menghapus data apapun.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $dataOrganisasi = SimpegDataOrganisasi::find($id);

        if (!$dataOrganisasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data organisasi tidak ditemukan'
            ], 404);
        }

        if ($dataOrganisasi->file_dokumen) {
            Storage::delete('public/pegawai/organisasi/' . $dataOrganisasi->file_dokumen);
        }

        $oldData = $dataOrganisasi->toArray();
        $dataOrganisasi->delete();

        ActivityLogger::log('admin_delete_data_organisasi', $dataOrganisasi, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data organisasi berhasil dihapus oleh admin'
        ]);
    }

    /**
     * Admin: Approve a single data entry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id)
    {
        $dataOrganisasi = SimpegDataOrganisasi::find($id);

        if (!$dataOrganisasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data organisasi tidak ditemukan'
            ], 404);
        }

        if ($dataOrganisasi->status_pengajuan === 'disetujui') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah disetujui sebelumnya'
            ], 409);
        }

        $oldData = $dataOrganisasi->getOriginal();
        $dataOrganisasi->update([
            'status_pengajuan' => 'disetujui',
            'tgl_disetujui' => now(),
            'tgl_ditolak' => null,
            'keterangan_penolakan' => null, // Hapus keterangan penolakan jika disetujui
        ]);

        ActivityLogger::log('admin_approve_data_organisasi', $dataOrganisasi, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data organisasi berhasil disetujui'
        ]);
    }

    /**
     * Admin: Reject a single data entry.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, $id)
    {
        $dataOrganisasi = SimpegDataOrganisasi::find($id);

        if (!$dataOrganisasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data organisasi tidak ditemukan'
            ], 404);
        }

        if ($dataOrganisasi->status_pengajuan === 'ditolak') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah ditolak sebelumnya'
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'keterangan_penolakan' => 'nullable|string|max:500', // Keterangan untuk penolakan
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $dataOrganisasi->getOriginal();
        $dataOrganisasi->update([
            'status_pengajuan' => 'ditolak',
            'tgl_ditolak' => now(),
            'tgl_disetujui' => null,
            'keterangan_penolakan' => $request->keterangan_penolakan,
        ]);

        ActivityLogger::log('admin_reject_data_organisasi', $dataOrganisasi, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data organisasi berhasil ditolak'
        ]);
    }

    /**
     * Admin: Batch delete data organisasi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_organisasi,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataOrganisasiList = SimpegDataOrganisasi::whereIn('id', $request->ids)->get();

        if ($dataOrganisasiList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data organisasi yang ditemukan untuk dihapus'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataOrganisasiList as $dataOrganisasi) {
            try {
                if ($dataOrganisasi->file_dokumen) {
                    Storage::delete('public/pegawai/organisasi/' . $dataOrganisasi->file_dokumen);
                }

                $oldData = $dataOrganisasi->toArray();
                $dataOrganisasi->delete();
                
                ActivityLogger::log('admin_batch_delete_data_organisasi', $dataOrganisasi, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataOrganisasi->id,
                    'nama_organisasi' => $dataOrganisasi->nama_organisasi,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data organisasi",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data organisasi",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }

    /**
     * Admin: Batch approve data organisasi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_organisasi,id' // Pastikan exists validation di sini
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // --- MULAI PERBAIKAN DI SINI UNTUK batchApprove ---
        $dataToProcess = SimpegDataOrganisasi::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', ['draft', 'diajukan', 'ditolak']) // Status yang bisa diapprove
            ->get(); // Ambil koleksi model

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data organisasi yang memenuhi syarat untuk disetujui.'
            ], 404);
        }

        $updatedCount = 0;
        $approvedIds = [];
        DB::beginTransaction(); // Mulai transaksi
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => 'disetujui',
                    'tgl_disetujui' => now(),
                    'tgl_diajukan' => $item->tgl_diajukan ?? now(), // Pertahankan tgl_diajukan jika ada, jika tidak set sekarang
                    'tgl_ditolak' => null,
                    'keterangan_penolakan' => null, // Set keterangan null jika disetujui (pastikan kolom ada)
                ]);
                ActivityLogger::log('admin_approve_data_organisasi', $item, $oldData); // Kirim objek $item
                $updatedCount++;
                $approvedIds[] = $item->id;
            }
            DB::commit(); // Commit transaksi
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback jika ada error
            \Log::error('Error during batch approve data organisasi: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }
        // --- AKHIR PERBAIKAN UNTUK batchApprove ---

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data organisasi",
            'updated_count' => $updatedCount,
            'approved_ids' => $approvedIds // Opsional: kembalikan ID yang berhasil diperbarui
        ]);
    }

    /**
     * Admin: Batch reject data organisasi.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_organisasi,id',
            'keterangan_penolakan' => 'nullable|string|max:500', // Pastikan kolom ada di DB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // --- MULAI PERBAIKAN DI SINI UNTUK batchReject ---
        $dataToProcess = SimpegDataOrganisasi::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', ['draft', 'diajukan', 'disetujui']) // Status yang bisa ditolak
            ->get(); // Ambil koleksi model

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data organisasi yang memenuhi syarat untuk ditolak.'
            ], 404);
        }

        $updatedCount = 0;
        $rejectedIds = [];
        DB::beginTransaction(); // Mulai transaksi
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => 'ditolak',
                    'tgl_ditolak' => now(),
                    'tgl_diajukan' => null, // Hilangkan tgl diajukan jika ditolak
                    'tgl_disetujui' => null, // Hilangkan tgl disetujui jika ditolak
                    'keterangan_penolakan' => $request->keterangan_penolakan, // Gunakan kolom ini
                ]);
                ActivityLogger::log('admin_reject_data_organisasi', $item, $oldData); // Kirim objek $item
                $updatedCount++;
                $rejectedIds[] = $item->id;
            }
            DB::commit(); // Commit transaksi
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback jika ada error
            \Log::error('Error during batch reject data organisasi: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }
        // --- AKHIR PERBAIKAN UNTUK batchReject ---

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data organisasi",
            'updated_count' => $updatedCount,
            'rejected_ids' => $rejectedIds
        ]);
    }

    /**
     * Admin: Get status statistics for dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatusStatistics(Request $request)
    {
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $pegawaiId = $request->pegawai_id;

        $query = SimpegDataOrganisasi::query();

        if ($pegawaiId) {
            $query->where('pegawai_id', $pegawaiId);
        }

        if ($unitKerjaId) {
            $unitKerjaTarget = SimpegUnitKerja::find($unitKerjaId);
            if ($unitKerjaTarget) {
                $unitIdsInScope = $this->getAllChildUnitIds($unitKerjaTarget);
                $unitIdsInScope[] = $unitKerjaTarget->id;

                $query->whereHas('pegawai', function ($q) use ($unitIdsInScope) {
                    $q->whereIn('unit_kerja_id', $unitIdsInScope);
                });
            }
        }

        if ($jabatanFungsionalId) {
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
            'ditolak' => 0
        ];

        $statistics = array_merge($defaultStats, $statistics);
        $statistics['total'] = array_sum($statistics);

        return response()->json([
            'success' => true,
            'statistics' => $statistics
        ]);
    }

    /**
     * Admin: Get all filter options for the admin interface.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFilterOptions(Request $request)
    {
        $namaOrganisasi = SimpegDataOrganisasi::distinct()->pluck('nama_organisasi')->filter()->values()->toArray();
        $jenisOrganisasi = SimpegDataOrganisasi::distinct()->pluck('jenis_organisasi')->filter()->values()->toArray();
        $jabatanDalamOrganisasi = SimpegDataOrganisasi::distinct()->pluck('jabatan_dalam_organisasi')->filter()->values()->toArray();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'nama_organisasi' => $namaOrganisasi,
                'jenis_organisasi_options' => $this->getJenisOrganisasiOptions(),
                'jabatan_dalam_organisasi' => $jabatanDalamOrganisasi,
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ],
                'unit_kerja_options' => $this->getUnitKerjaOptions(),
                'jabatan_fungsional_options' => $this->getJabatanFungsionalOptions(),
                'pegawai_options' => SimpegPegawai::select('id as value', 'nama as label')->orderBy('nama')->get(),
            ]
        ]);
    }

    // --- HELPER FUNCTIONS ---

    /**
     * Helper: Get Jenis Organisasi Options.
     */
    private function getJenisOrganisasiOptions()
    {
        return [
            ['value' => 'semua', 'label' => 'Semua'],
            ['value' => 'lokal', 'label' => 'Lokal'],
            ['value' => 'nasional', 'label' => 'Nasional'],
            ['value' => 'internasional', 'label' => 'Internasional'],
            ['value' => 'lainnya', 'label' => 'Lainnya']
        ];
    }

    /**
     * Helper: Get Unit Kerja Options.
     */
    private function getUnitKerjaOptions()
    {
        return SimpegUnitKerja::select('id as value', 'nama_unit as label')
            ->orderBy('nama_unit')
            ->get();
    }

    /**
     * Helper: Get Jabatan Fungsional Options.
     */
    private function getJabatanFungsionalOptions()
    {
        return SimpegJabatanFungsional::select('id as value', 'nama_jabatan_fungsional as label')
            ->orderBy('nama_jabatan_fungsional')
            ->get();
    }

    /**
     * Helper: Recursive function to get all child unit IDs.
     */
    private function getAllChildUnitIds(SimpegUnitKerja $unit)
    {
        $childIds = [];
        foreach ($unit->children as $child) {
            $childIds[] = $child->id; 
            $childIds = array_merge($childIds, $this->getAllChildUnitIds($child));
        }
        return $childIds;
    }

    /**
     * Helper: Format data organisasi for response.
     */
    protected function formatDataOrganisasi(SimpegDataOrganisasi $dataOrganisasi, $includeActions = true)
    {
        $status = $dataOrganisasi->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $pegawai = $dataOrganisasi->pegawai;
        $nipPegawai = $pegawai ? $pegawai->nip : '-';
        $namaPegawai = $pegawai ? $pegawai->nama : '-';

        $unitKerjaNama = 'Tidak Ada';
        if ($pegawai && $pegawai->unitKerja) {
            $unitKerjaNama = $pegawai->unitKerja->nama_unit;
        } elseif ($pegawai && $pegawai->unit_kerja_id) {
            $unitKerja = SimpegUnitKerja::find($pegawai->unit_kerja_id);
            $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $pegawai->unit_kerja_id;
        }

        $jabatanFungsionalNama = '-';
        if ($pegawai && $pegawai->dataJabatanFungsional && $pegawai->dataJabatanFungsional->isNotEmpty()) {
            $jabatanFungsional = $pegawai->dataJabatanFungsional->first()->jabatanFungsional;
            if ($jabatanFungsional) {
                $jabatanFungsionalNama = $jabatanFungsional->nama_jabatan_fungsional ?? $jabatanFungsional->nama ?? '-';
            }
        }

        $periode = '';
        if ($dataOrganisasi->periode_mulai) {
            $periode = Carbon::parse($dataOrganisasi->periode_mulai)->format('d/m/Y');
            if ($dataOrganisasi->periode_selesai) {
                $periode .= ' - ' . Carbon::parse($dataOrganisasi->periode_selesai)->format('d/m/Y');
            } else {
                $periode .= ' - Sekarang';
            }
        }
        
        $data = [
            'id' => $dataOrganisasi->id,
            'pegawai_id' => $dataOrganisasi->pegawai_id,
            'nip_pegawai' => $nipPegawai,
            'nama_pegawai' => $namaPegawai,
            'unit_kerja_pegawai' => $unitKerjaNama,
            'jabatan_fungsional_pegawai' => $jabatanFungsionalNama,
            'nama_organisasi' => $dataOrganisasi->nama_organisasi,
            'jabatan_dalam_organisasi' => $dataOrganisasi->jabatan_dalam_organisasi,
            'jenis_organisasi' => $dataOrganisasi->jenis_organisasi,
            'tempat_organisasi' => $dataOrganisasi->tempat_organisasi,
            'periode_mulai' => $dataOrganisasi->periode_mulai,
            'periode_selesai' => $dataOrganisasi->periode_selesai,
            'periode' => $periode,
            'website' => $dataOrganisasi->website,
            'keterangan' => $dataOrganisasi->keterangan,
            'status_pengajuan' => $status,
            'status_pengajuan_info' => $statusInfo,
            'keterangan_penolakan' => $dataOrganisasi->keterangan_penolakan,
            'timestamps' => [
                'tgl_input' => $dataOrganisasi->tgl_input,
                'tgl_diajukan' => $dataOrganisasi->tgl_diajukan,
                'tgl_disetujui' => $dataOrganisasi->tgl_disetujui,
                'tgl_ditolak' => $dataOrganisasi->tgl_ditolak
            ],
            'dokumen' => $dataOrganisasi->file_dokumen ? [
                'nama_file' => $dataOrganisasi->file_dokumen,
                'url' => url('storage/pegawai/organisasi/' . $dataOrganisasi->file_dokumen)
            ] : null,
            'created_at' => $dataOrganisasi->created_at,
            'updated_at' => $dataOrganisasi->updated_at
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/dataorganisasi/{$dataOrganisasi->id}"),
                'update_url' => url("/api/admin/dataorganisasi/{$dataOrganisasi->id}"),
                'delete_url' => url("/api/admin/dataorganisasi/{$dataOrganisasi->id}"),
                'approve_url' => url("/api/admin/dataorganisasi/{$dataOrganisasi->id}/approve"),
                'reject_url' => url("/api/admin/dataorganisasi/{$dataOrganisasi->id}/reject"),
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
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data organisasi "' . $dataOrganisasi->nama_organisasi . '"?'
                ],
            ];

            if ($status === 'diajukan' || $status === 'ditolak' || $status === 'draft') {
                $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data organisasi "' . $dataOrganisasi->nama_organisasi . '"?'
                ];
            }

            if ($status === 'diajukan' || $status === 'disetujui' || $status === 'draft') {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Tolak',
                    'icon' => 'times',
                    'color' => 'danger',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data organisasi "' . $dataOrganisasi->nama_organisasi . '"?',
                    'needs_input' => true,
                    'input_placeholder' => 'Masukkan keterangan penolakan (opsional)'
                ];
            }
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