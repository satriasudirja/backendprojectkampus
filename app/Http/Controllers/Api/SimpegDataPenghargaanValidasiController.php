<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPenghargaanAdm; // Menggunakan model yang sama
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJabatanFungsional; // Pastikan ini diimport
use App\Models\SimpegPegawai;         // Pastikan ini diimport
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegDataPenghargaanValidasiController extends Controller
{
    /**
     * Get all data penghargaan untuk validasi (default status Diajukan)
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $statusPengajuan = $request->status_pengajuan ?? 'disetujui'; // DEFAULT: hanya yang "diajukan"
        $pegawaiId = $request->pegawai_id; // Admin bisa filter by pegawai ID

        $query = SimpegDataPenghargaanAdm::with([
            'pegawai' => function ($q) {
                $q->select('id', 'nip', 'nama', 'unit_kerja_id')
                    ->with([
                        'unitKerja:id,nama_unit', // Asumsi ID Unit Kerja adalah 'id'
                        'dataJabatanFungsional' => function ($subQuery) {
                            $subQuery->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1);
                        }
                    ]);
            }
        ]);

        if ($statusPengajuan !== 'semua') {
            $query->byStatus($statusPengajuan);
        }

        $query->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanFungsional($jabatanFungsionalId)
              ->globalSearch($search);
        
        if ($pegawaiId) {
            $query->where('pegawai_id', $pegawaiId);
        }

        $dataPenghargaan = $query->orderBy('tanggal_penghargaan', 'desc')
                                 ->orderBy('created_at', 'desc')
                                 ->paginate($perPage);

        $dataPenghargaan->getCollection()->transform(function ($item) {
            return $this->formatDataPenghargaan($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataPenghargaan,
            'empty_data' => $dataPenghargaan->isEmpty(),
            'filters' => $this->getFilterOptions($request), // <-- PERBAIKAN DI SINI! Teruskan $request
            'table_columns' => [
                ['field' => 'nip', 'label' => 'NIP', 'sortable' => false],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => false],
                ['field' => 'unit_kerja', 'label' => 'Unit Kerja', 'sortable' => false],
                ['field' => 'jabatan_fungsional_pegawai', 'label' => 'Jabatan Fungsional', 'sortable' => false],
                ['field' => 'nama_penghargaan', 'label' => 'Nama Penghargaan', 'sortable' => true, 'sortable_field' => 'nama_penghargaan'],
                ['field' => 'tanggal_penghargaan_formatted', 'label' => 'Tgl. Penghargaan', 'sortable' => true, 'sortable_field' => 'tanggal_penghargaan'],
                ['field' => 'no_sk', 'label' => 'No SK', 'sortable' => true, 'sortable_field' => 'no_sk'],
                ['field' => 'status_info.label', 'label' => 'Status', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/validasi-penghargaan/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/validasi-penghargaan/batch/reject"),
                    'method' => 'PATCH',
                    'label' => 'Tolak Terpilih',
                    'icon' => 'times',
                    'color' => 'danger',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menolak data terpilih?',
                    'needs_input' => true,
                    'input_placeholder' => 'Keterangan penolakan (opsional)'
                ],
                'tangguhkan' => [
                    'url' => url("/api/admin/validasi-penghargaan/batch/tangguhkan"),
                    'method' => 'PATCH',
                    'label' => 'Tanggguhkan Terpilih',
                    'icon' => 'pause',
                    'color' => 'warning',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menangguhkan data terpilih?',
                ],
            ],
            'pagination' => [
                'current_page' => $dataPenghargaan->currentPage(),
                'per_page' => $dataPenghargaan->perPage(),
                'total' => $dataPenghargaan->total(),
                'last_page' => $dataPenghargaan->lastPage(),
                'from' => $dataPenghargaan->firstItem(),
                'to' => $dataPenghargaan->lastItem()
            ]
        ]);
    }

    /**
     * Display the specified resource.
     * Admin dapat melihat detail data organisasi untuk pegawai manapun.
     */
    public function show($id)
    {
        $dataPenghargaan = SimpegDataPenghargaanAdm::with([
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

        if (!$dataPenghargaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghargaan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($dataPenghargaan->pegawai),
            'data' => $this->formatDataPenghargaan($dataPenghargaan, true), // Tetap true untuk melihat aksi approve/reject/tangguhkan di detail
            'status_pengajuan_options' => $this->getStatusOptions(), // Opsi status pengajuan untuk admin validasi
        ]);
    }

    // Method store, update, destroy di controller validasi biasanya tidak ada atau hanya untuk mengubah status.
    public function store(Request $request)
    {
        return response()->json(['success' => false, 'message' => 'Operation not allowed for Validation Admin. Use approve/reject/tangguhkan actions.'], 405);
    }
    public function update(Request $request, $id)
    {
        return response()->json(['success' => false, 'message' => 'Operation not allowed for Validation Admin. Use approve/reject/tangguhkan actions.'], 405);
    }
    public function destroy($id)
    {
        return response()->json(['success' => false, 'message' => 'Operation not allowed for Validation Admin. Only batch delete might be allowed.'], 405);
    }

    /**
     * Admin Validasi: Approve a single data entry.
     */
    public function approve($id)
    {
        $dataPenghargaan = SimpegDataPenghargaanAdm::find($id);

        if (!$dataPenghargaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghargaan tidak ditemukan'
            ], 404);
        }

        if ($dataPenghargaan->status_pengajuan === 'disetujui') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah disetujui sebelumnya'
            ], 409);
        }

        $oldData = $dataPenghargaan->getOriginal();
        $dataPenghargaan->update([
            'status_pengajuan' => 'disetujui',
            'tgl_disetujui' => now(),
            'tgl_diajukan' => $dataPenghargaan->tgl_diajukan ?? now(),
            'tgl_ditolak' => null,
            'tgl_ditangguhkan' => null,
        ]);

        ActivityLogger::log('validasi_approve_penghargaan', $dataPenghargaan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data penghargaan berhasil disetujui'
        ]);
    }

    /**
     * Admin Validasi: Reject a single data entry.
     */
    public function reject(Request $request, $id)
    {
        $dataPenghargaan = SimpegDataPenghargaanAdm::find($id);

        if (!$dataPenghargaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghargaan tidak ditemukan'
            ], 404);
        }

        if ($dataPenghargaan->status_pengajuan === 'ditolak') {
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

        $oldData = $dataPenghargaan->getOriginal();
        $dataPenghargaan->update([
            'status_pengajuan' => 'ditolak',
            'tgl_ditolak' => now(),
            'tgl_diajukan' => null,
            'tgl_disetujui' => null,
            'tgl_ditangguhkan' => null,
            'keterangan_penolakan' => $request->keterangan_penolakan,
        ]);

        ActivityLogger::log('validasi_reject_penghargaan', $dataPenghargaan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data penghargaan berhasil ditolak'
        ]);
    }

    /**
     * Admin Validasi: Tangguhkan (Suspend) a single data entry.
     */
    public function tangguhkan($id)
    {
        $dataPenghargaan = SimpegDataPenghargaanAdm::find($id);

        if (!$dataPenghargaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghargaan tidak ditemukan'
            ], 404);
        }

        if ($dataPenghargaan->status_pengajuan === 'ditangguhkan') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah ditangguhkan sebelumnya'
            ], 409);
        }

        $oldData = $dataPenghargaan->getOriginal();
        $dataPenghargaan->update([
            'status_pengajuan' => 'ditangguhkan',
            'tgl_ditangguhkan' => now(),
            'tgl_diajukan' => null,
            'tgl_disetujui' => null,
            'tgl_ditolak' => null,
        ]);

        ActivityLogger::log('validasi_tangguhkan_penghargaan', $dataPenghargaan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data penghargaan berhasil ditangguhkan'
        ]);
    }

    /**
     * Admin Validasi: Batch delete data penghargaan.
     */
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_penghargaan,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $dataPenghargaanList = SimpegDataPenghargaanAdm::whereIn('id', $request->ids)->get();

        if ($dataPenghargaanList->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tidak ada data penghargaan yang ditemukan untuk dihapus'], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataPenghargaanList as $dataPenghargaan) {
                if ($dataPenghargaan->file_penghargaan) {
                    Storage::delete('public/penghargaan/' . $dataPenghargaan->file_penghargaan);
                }

                $oldData = $dataPenghargaan->toArray();
                $dataPenghargaan->delete();
                
                ActivityLogger::log('validasi_batch_delete_penghargaan', $dataPenghargaan, $oldData);
                $deletedCount++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error during batch delete penghargaan (validasi): ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data secara batch: ' . $e->getMessage(),
                'errors' => $errors
            ], 500);
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data penghargaan",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data penghargaan",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }

    /**
     * Admin Validasi: Batch approve data penghargaan.
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_penghargaan,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $dataToProcess = SimpegDataPenghargaanAdm::whereIn('id', $request->ids)
                                                ->whereIn('status_pengajuan', ['draft', 'diajukan', 'ditolak', 'ditangguhkan'])
                                                ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tidak ada data penghargaan yang memenuhi syarat untuk disetujui.'], 404);
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
                    'tgl_diajukan' => $item->tgl_diajukan ?? now(),
                    'tgl_ditolak' => null,
                    'tgl_ditangguhkan' => null,
                ]);
                ActivityLogger::log('validasi_approve_penghargaan', $item, $oldData);
                $updatedCount++;
                $approvedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error during batch approve penghargaan (validasi): ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data penghargaan",
            'updated_count' => $updatedCount,
            'approved_ids' => $approvedIds
        ]);
    }

    /**
     * Admin Validasi: Batch reject data penghargaan.
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_penghargaan,id',
            'keterangan_penolakan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $dataToProcess = SimpegDataPenghargaanAdm::whereIn('id', $request->ids)
                                                ->whereIn('status_pengajuan', ['draft', 'diajukan', 'disetujui', 'ditangguhkan'])
                                                ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tidak ada data penghargaan yang memenuhi syarat untuk ditolak.'], 404);
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
                ActivityLogger::log('validasi_reject_penghargaan', $item, $oldData);
                $updatedCount++;
                $rejectedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error during batch reject penghargaan (validasi): ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data penghargaan",
            'updated_count' => $updatedCount,
            'rejected_ids' => $rejectedIds
        ]);
    }

    /**
     * Admin Validasi: Batch tangguhkan data penghargaan.
     */
    public function batchTangguhkan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_penghargaan,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $dataToProcess = SimpegDataPenghargaanAdm::whereIn('id', $request->ids)
                                                ->whereIn('status_pengajuan', ['draft', 'diajukan', 'disetujui', 'ditolak'])
                                                ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tidak ada data penghargaan yang memenuhi syarat untuk ditangguhkan.'], 404);
        }

        $updatedCount = 0;
        $suspendedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => 'ditangguhkan',
                    'tgl_ditangguhkan' => now(),
                    'tgl_diajukan' => null,
                    'tgl_disetujui' => null,
                    'tgl_ditolak' => null,
                ]);
                ActivityLogger::log('validasi_tangguhkan_penghargaan', $item, $oldData);
                $updatedCount++;
                $suspendedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error during batch tangguhkan penghargaan (validasi): ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menangguhkan data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menangguhkan {$updatedCount} data penghargaan",
            'updated_count' => $updatedCount,
            'suspended_ids' => $suspendedIds
        ]);
    }


    /**
     * Get filter options untuk dropdown validasi
     */
    public function getFilterOptions(Request $request) // <-- DEFINISI method ini membutuhkan $request
    {
        // ... (kode di dalamnya tetap sama) ...
        $statusPengajuanOptions = [
            ['id' => 'semua', 'nama' => 'Semua Status'],
            ['id' => 'diajukan', 'nama' => 'Diajukan'],
            ['id' => 'disetujui', 'nama' => 'Disetujui'],
            ['id' => 'ditolak', 'nama' => 'Ditolak'],
            ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan'],
            ['id' => 'draft', 'nama' => 'Draft'],
        ];

        $unitKerja = SimpegUnitKerja::select('id as id', 'nama_unit as nama')
            ->orderBy('nama_unit')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Unit Kerja']);

        $jabatanFungsional = SimpegJabatanFungsional::select('id', 'nama_jabatan_fungsional as nama')
            ->orderBy('nama_jabatan_fungsional')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jabatan Fungsional']);

        return response()->json([
            'success' => true,
            'filter_options' => [
                'status_pengajuan' => $statusPengajuanOptions,
                'unit_kerja' => $unitKerja,
                'jabatan_fungsional' => $jabatanFungsional,
                'pegawai_options' => SimpegPegawai::select('id as value', 'nama as label', 'nip')->orderBy('nama')->get()->map(function($peg){
                    return ['value' => $peg->id, 'label' => $peg->nama . ' (' . $peg->nip . ')'];
                }),
                'nama_penghargaan_options' => SimpegDataPenghargaanAdm::distinct()->pluck('nama_penghargaan')->filter()->values()->toArray(),
                'jenis_penghargaan_options' => SimpegDataPenghargaanAdm::distinct()->pluck('jenis_penghargaan')->filter()->values()->toArray(),
            ]
        ]);
    }

    // Helper: Mendapatkan opsi status pengajuan (sama seperti di controller Operasional)
    private function getStatusOptions()
    {
        return [
            ['value' => 'draft', 'label' => 'Draft', 'color' => 'secondary'],
            ['value' => 'diajukan', 'label' => 'Diajukan', 'color' => 'info'],
            ['value' => 'disetujui', 'label' => 'Disetujui', 'color' => 'success'],
            ['value' => 'ditolak', 'label' => 'Ditolak', 'color' => 'danger'],
            ['value' => 'ditangguhkan', 'label' => 'Ditangguhkan', 'color' => 'warning'],
        ];
    }

    // --- HELPER FUNCTIONS ---

    // Helper: Format detail info pegawai untuk display di view.
    private function formatPegawaiInfo(?SimpegPegawai $pegawai)
    {
        // ... (kode ini sama seperti sebelumnya) ...
        if (!$pegawai) {
            return [
                'id' => null,
                'nip' => '-',
                'nama' => '-',
                'unit_kerja' => 'Tidak Ada',
                'status' => '-',
                'jab_akademik' => '-',
                'jab_fungsional' => '-',
                'jab_struktural' => '-',
                'pendidikan' => '-',
            ];
        }

        $jabatanAkademikNama = $pegawai->jabatanAkademik ? $pegawai->jabatanAkademik->jabatan_akademik : '-';

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

    // Helper: Format data penghargaan response (sama seperti di controller Operasional)
    protected function formatDataPenghargaan(SimpegDataPenghargaanAdm $dataPenghargaan, $includeActions = true)
    {
        $pegawai = $dataPenghargaan->pegawai;
        $jabatanFungsionalPegawai = '-';
        if ($pegawai && $pegawai->dataJabatanFungsional && $pegawai->dataJabatanFungsional->isNotEmpty()) {
            $jabFung = $pegawai->dataJabatanFungsional->first()->jabatanFungsional;
            $jabatanFungsionalPegawai = $jabFung->nama_jabatan_fungsional ?? $jabFung->nama ?? '-';
        }

        $status = $dataPenghargaan->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);

        $data = [
            'id' => $dataPenghargaan->id,
            'pegawai_id' => $dataPenghargaan->pegawai_id,
            'nip' => $dataPenghargaan->pegawai->nip ?? '-',
            'nama_pegawai' => $dataPenghargaan->pegawai->nama ?? '-',
            'unit_kerja' => $dataPenghargaan->pegawai->unitKerja->nama_unit ?? '-',
            'jabatan_akademik' => $dataPenghargaan->pegawai->jabatanAkademik->jabatan_akademik ?? '-',
            'jabatan_fungsional_pegawai' => $jabatanFungsionalPegawai,
            'jenis_penghargaan' => $dataPenghargaan->jenis_penghargaan ?? '-',
            'nama_penghargaan' => $dataPenghargaan->nama_penghargaan ?? '-',
            'no_sk' => $dataPenghargaan->no_sk ?? '-',
            'tanggal_sk' => $dataPenghargaan->tanggal_sk,
            'tanggal_sk_formatted' => $dataPenghargaan->tanggal_sk ? Carbon::parse($dataPenghargaan->tanggal_sk)->format('d M Y') : '-',
            'tanggal_penghargaan' => $dataPenghargaan->tanggal_penghargaan,
            'tanggal_penghargaan_formatted' => $dataPenghargaan->tanggal_penghargaan ? Carbon::parse($dataPenghargaan->tanggal_penghargaan)->format('d M Y') : '-',
            'keterangan' => $dataPenghargaan->keterangan ?? '-',
            'file_penghargaan' => $dataPenghargaan->file_penghargaan,
            'file_penghargaan_url' => $dataPenghargaan->file_penghargaan ? url('storage/' . $dataPenghargaan->file_penghargaan) : null,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'tgl_diajukan' => $dataPenghargaan->tgl_diajukan,
            'tgl_disetujui' => $dataPenghargaan->tgl_disetujui,
            'tgl_ditolak' => $dataPenghargaan->tgl_ditolak,
            'tgl_ditangguhkan' => $dataPenghargaan->tgl_ditangguhkan,
            'created_at' => $dataPenghargaan->created_at,
            'updated_at' => $dataPenghargaan->updated_at
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/validasi-penghargaan/{$dataPenghargaan->id}"),
                'approve_url' => url("/api/admin/validasi-penghargaan/{$dataPenghargaan->id}/approve"),
                'reject_url' => url("/api/admin/validasi-penghargaan/{$dataPenghargaan->id}/reject"),
                'tangguhkan_url' => url("/api/admin/validasi-penghargaan/{$dataPenghargaan->id}/tangguhkan"),
            ];

            $data['actions'] = [
                'view' => [
                    'url' => $data['aksi']['detail_url'],
                    'method' => 'GET',
                    'label' => 'Lihat Detail',
                    'icon' => 'eye',
                    'color' => 'info'
                ],
            ];
            
            if (in_array($status, ['diajukan', 'ditolak', 'ditangguhkan', 'draft'])) {
                $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data penghargaan "' . $dataPenghargaan->nama_penghargaan . '"?',
                ];
            }
            if (in_array($status, ['diajukan', 'disetujui', 'ditangguhkan', 'draft'])) {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Tolak',
                    'icon' => 'times',
                    'color' => 'danger',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data penghargaan "' . $dataPenghargaan->nama_penghargaan . '"?',
                    'needs_input' => true,
                    'input_placeholder' => 'Masukkan keterangan penolakan (opsional)'
                ];
            }
            if (in_array($status, ['diajukan', 'disetujui', 'ditolak', 'draft'])) {
                $data['actions']['tangguhkan'] = [
                    'url' => $data['aksi']['tangguhkan_url'],
                    'method' => 'PATCH',
                    'label' => 'Tanggguhkan',
                    'icon' => 'pause',
                    'color' => 'warning',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENANGGUHKAN data penghargaan "' . $dataPenghargaan->nama_penghargaan . '"?',
                ];
            }
        }
        return $data;
    }

    // Helper: Get status info (sama seperti di controller Operasional)
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
            'ditangguhkan' => [
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

    /**
     * Recursive helper to get all child unit IDs for hierarchical filtering.
     * Pindahkan ini ke model SimpegUnitKerja sebagai static method.
     */
    private function getAllChildUnitIds(SimpegUnitKerja $unit)
    {
        // Asumsi ini sudah dipindahkan ke SimpegUnitKerja::getAllChildIdsRecursively()
        // Atau Anda bisa menempatkan helper ini di Trait jika sering digunakan.
        $childIds = [];
        foreach ($unit->children as $child) {
            $childIds[] = $child->id;
            $childIds = array_merge($childIds, $this->getAllChildUnitIds($child));
        }
        return $childIds;
    }
}