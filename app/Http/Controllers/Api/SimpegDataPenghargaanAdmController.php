<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPenghargaanAdm;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJenisPenghargaan;
use App\Models\SimpegJabatanFungsional;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegDataPenghargaanAdmController extends Controller
{
    /**
     * Get all data penghargaan untuk admin (semua pegawai) - Riwayat Penghargaan
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $jenisPenghargaan = $request->jenis_penghargaan;
        
        // --- MODIFICATION START ---
        // Default to 'semua' if status_pengajuan is not provided in the request
        $statusPengajuan = $request->status_pengajuan ?? 'semua'; 
        // --- MODIFICATION END ---

        // Ensure eager loading 'pegawai' and its relations
        $query = SimpegDataPenghargaanAdm::with([
            'pegawai' => function ($q) {
                $q->select('id', 'nip', 'nama', 'unit_kerja_id')
                    ->with([
                        'unitKerja:id,nama_unit', // IMPORTANT: Selecting 'id' and 'nama_unit' from SimpegUnitKerja
                        'dataJabatanFungsional' => function ($subQuery) {
                            $subQuery->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1);
                        }
                    ]);
            }
        ]);

        // Apply local scopes for filtering
        $query->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanFungsional($jabatanFungsionalId)
              ->filterByJenisPenghargaan($jenisPenghargaan)
              ->globalSearch($search)
              ->byStatus($statusPengajuan); // This scope correctly handles 'semua'

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
            'filters' => $this->getFilterOptions(),
            'table_columns' => [
                ['field' => 'nip', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'pegawai.nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'pegawai.nama'],
                ['field' => 'tanggal_penghargaan_formatted', 'label' => 'Tgl. Penghargaan', 'sortable' => true, 'sortable_field' => 'tanggal_penghargaan'],
                ['field' => 'jenis_penghargaan', 'label' => 'Jenis Penghargaan', 'sortable' => true, 'sortable_field' => 'jenis_penghargaan'],
                ['field' => 'nama_penghargaan', 'label' => 'Nama Penghargaan', 'sortable' => true, 'sortable_field' => 'nama_penghargaan'],
                ['field' => 'status_info.label', 'label' => 'Status', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/admin/datapenghargaan/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ]
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

    // ... (rest of your controller methods remain the same) ...

    /**
     * Get detail data penghargaan
     */
    public function show($id)
    {
        $dataPenghargaan = SimpegDataPenghargaanAdm::with([
            'pegawai' => function ($q) {
                $q->with([
                    'unitKerja',
                    'statusAktif',
                    'jabatanAkademik',
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
            'data' => $this->formatDataPenghargaan($dataPenghargaan, false),
            'form_options' => $this->getFormOptions(),
            'validation_rules' => $this->getFormOptions()['validation_rules'],
            'field_notes' => $this->getFormOptions()['field_notes'],
        ]);
    }

    /**
     * Store new data penghargaan (Admin Operasional - Auto Setujui)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id',
            'jenis_penghargaan_id' => 'required|integer|exists:simpeg_jenis_penghargaan,id',
            'nama_penghargaan' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:100',
            'tanggal_sk' => 'nullable|date',
            'tanggal_penghargaan' => 'nullable|date',
            'keterangan' => 'nullable|string',
            'file_penghargaan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $validatedData = $validator->validated();
            
            $jenisPenghargaan = SimpegJenisPenghargaan::find($validatedData['jenis_penghargaan_id']);

            if ($jenisPenghargaan) {
                $validatedData['jenis_penghargaan'] = $jenisPenghargaan->nama;
            }

            if ($request->hasFile('file_penghargaan')) {
                $filePath = $request->file('file_penghargaan')->store('penghargaan', 'public');
                $validatedData['file_penghargaan'] = $filePath;
            }

            $validatedData['status_pengajuan'] = $request->input('status_pengajuan', 'disetujui');

            if ($validatedData['status_pengajuan'] === 'disetujui') {
                $validatedData['tgl_disetujui'] = now();
                $validatedData['tgl_diajukan'] = $validatedData['tgl_diajukan'] ?? now(); 
            } elseif ($validatedData['status_pengajuan'] === 'diajukan') {
                $validatedData['tgl_diajukan'] = now();
                $validatedData['tgl_disetujui'] = null;
            } elseif ($validatedData['status_pengajuan'] === 'ditolak') {
                $validatedData['tgl_ditolak'] = now();
                $validatedData['tgl_diajukan'] = null;
                $validatedData['tgl_disetujui'] = null;
            } elseif ($validatedData['status_pengajuan'] === 'ditangguhkan') {
                $validatedData['tgl_ditangguhkan'] = now();
                $validatedData['tgl_diajukan'] = null;
                $validatedData['tgl_disetujui'] = null;
                $validatedData['tgl_ditolak'] = null;
            } else { // 'draft'
                $validatedData['tgl_diajukan'] = null;
                $validatedData['tgl_disetujui'] = null;
                $validatedData['tgl_ditolak'] = null;
                $validatedData['tgl_ditangguhkan'] = null;
            }

            $dataPenghargaan = SimpegDataPenghargaanAdm::create($validatedData);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_create_penghargaan', $dataPenghargaan, $dataPenghargaan->toArray());
            }

            return response()->json([
                'success' => true,
                'message' => 'Data penghargaan berhasil ditambahkan',
                'data' => $this->formatDataPenghargaan($dataPenghargaan->load(['pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional']))
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data penghargaan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update data penghargaan
     */
    public function update(Request $request, $id)
    {
        $dataPenghargaan = SimpegDataPenghargaanAdm::find($id);
        if (!$dataPenghargaan) {
            return response()->json(['success' => false, 'message' => 'Data penghargaan tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|integer|exists:simpeg_pegawai,id',
            'jenis_penghargaan_id' => 'sometimes|integer|exists:simpeg_jenis_penghargaan,id',
            'nama_penghargaan' => 'sometimes|string|max:255',
            'no_sk' => 'nullable|string|max:100',
            'tanggal_sk' => 'nullable|date',
            'tanggal_penghargaan' => 'nullable|date',
            'keterangan' => 'nullable|string',
            'file_penghargaan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'file_penghargaan_clear' => 'nullable|boolean',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        try {
            $validatedData = $validator->validated();
            
            if (isset($validatedData['jenis_penghargaan_id'])) {
                $jenisPenghargaan = SimpegJenisPenghargaan::find($validatedData['jenis_penghargaan_id']);
                if ($jenisPenghargaan) {
                    $validatedData['jenis_penghargaan'] = $jenisPenghargaan->nama;
                }
            }

            if ($request->hasFile('file_penghargaan')) {
                if ($dataPenghargaan->file_penghargaan) {
                    Storage::disk('public')->delete($dataPenghargaan->file_penghargaan);
                }
                $filePath = $request->file('file_penghargaan')->store('penghargaan', 'public');
                $validatedData['file_penghargaan'] = $filePath;
            } elseif ($request->has('file_penghargaan_clear') && (bool)$request->file_penghargaan_clear) {
                if ($dataPenghargaan->file_penghargaan) {
                    Storage::disk('public')->delete($dataPenghargaan->file_penghargaan);
                }
                $validatedData['file_penghargaan'] = null;
            } else {
                $validatedData['file_penghargaan'] = $dataPenghargaan->file_penghargaan;
            }

            if (isset($validatedData['status_pengajuan']) && $validatedData['status_pengajuan'] !== $dataPenghargaan->status_pengajuan) {
                switch ($validatedData['status_pengajuan']) {
                    case 'diajukan':
                        $validatedData['tgl_diajukan'] = now();
                        $validatedData['tgl_disetujui'] = null;
                        $validatedData['tgl_ditolak'] = null;
                        $validatedData['tgl_ditangguhkan'] = null;
                        break;
                    case 'disetujui':
                        $validatedData['tgl_disetujui'] = now();
                        $validatedData['tgl_diajukan'] = $dataPenghargaan->tgl_diajukan ?? now(); 
                        $validatedData['tgl_ditolak'] = null;
                        $validatedData['tgl_ditangguhkan'] = null;
                        break;
                    case 'ditolak':
                        $validatedData['tgl_ditolak'] = now();
                        $validatedData['tgl_diajukan'] = null;
                        $validatedData['tgl_disetujui'] = null;
                        $validatedData['tgl_ditangguhkan'] = null;
                        break;
                    case 'ditangguhkan':
                        $validatedData['tgl_ditangguhkan'] = now();
                        $validatedData['tgl_diajukan'] = null;
                        $validatedData['tgl_disetujui'] = null;
                        $validatedData['tgl_ditolak'] = null;
                        break;
                    case 'draft':
                        $validatedData['tgl_diajukan'] = null;
                        $validatedData['tgl_disetujui'] = null;
                        $validatedData['tgl_ditolak'] = null;
                        $validatedData['tgl_ditangguhkan'] = null;
                        break;
                }
            } else {
                $validatedData['tgl_diajukan'] = $dataPenghargaan->tgl_diajukan;
                $validatedData['tgl_disetujui'] = $dataPenghargaan->tgl_disetujui;
                $validatedData['tgl_ditolak'] = $dataPenghargaan->tgl_ditolak;
                $validatedData['tgl_ditangguhkan'] = $dataPenghargaan->tgl_ditangguhkan;
            }
            
            $oldData = $dataPenghargaan->getOriginal();
            
            $dataPenghargaan->update($validatedData);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_update_penghargaan', $dataPenghargaan, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data penghargaan berhasil diperbarui',
                'data' => $this->formatDataPenghargaan($dataPenghargaan->load(['pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data penghargaan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete data penghargaan
     */
    public function destroy($id)
    {
        $dataPenghargaan = SimpegDataPenghargaanAdm::find($id);

        if (!$dataPenghargaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghargaan tidak ditemukan'
            ], 404);
        }

        if ($dataPenghargaan->file_penghargaan) {
            Storage::disk('public')->delete($dataPenghargaan->file_penghargaan);
        }

        $oldData = $dataPenghargaan->toArray();
        $dataPenghargaan->delete();

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_delete_penghargaan', $dataPenghargaan, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data penghargaan berhasil dihapus'
        ]);
    }

    /**
     * Batch delete data penghargaan
     */
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_penghargaan,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataPenghargaanList = SimpegDataPenghargaanAdm::whereIn('id', $request->ids)->get();

        if ($dataPenghargaanList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data penghargaan yang ditemukan untuk dihapus'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataPenghargaanList as $dataPenghargaan) {
                if ($dataPenghargaan->file_penghargaan) {
                    Storage::disk('public')->delete($dataPenghargaan->file_penghargaan);
                }

                $oldData = $dataPenghargaan->toArray();
                $dataPenghargaan->delete();
                
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_batch_delete_penghargaan', $dataPenghargaan, $oldData);
                }
                $deletedCount++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error during batch delete penghargaan: ' . $e->getMessage());
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
     * Get pegawai options for dropdown.
     */
    public function getPegawaiOptions(Request $request)
    {
        $search = $request->search;
        $unitKerjaId = $request->unit_kerja_id;

        $query = SimpegPegawai::select('id', 'nip', 'nama', 'unit_kerja_id')
            ->with('unitKerja:id,nama_unit');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nip', 'like', '%' . $search . '%')
                    ->orWhere('nama', 'like', '%' . $search . '%');
            });
        }

        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $unitKerjaTarget = SimpegUnitKerja::find($unitKerjaId);

            if ($unitKerjaTarget) {
                $unitIdsInScope = SimpegUnitKerja::getAllChildIdsRecursively($unitKerjaTarget);
                $query->whereIn('unit_kerja_id', $unitIdsInScope);
            } else {
                return response()->json(['success' => true, 'data' => [], 'search_info' => ['query' => $search, 'total_results' => 0, 'search_fields' => ['nip', 'nama'], 'message' => 'Unit Kerja tidak ditemukan.']], 200);
            }
        }

        $pegawai = $query->orderBy('nama')
                         ->limit(50)
                         ->get()
                         ->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'nip' => $item->nip,
                                'nama' => $item->nama,
                                'unit_kerja' => $item->unitKerja->nama_unit ?? '-',
                                'label' => $item->nip . ' - ' . $item->nama . ' (' . ($item->unitKerja->nama_unit ?? 'No Unit') . ')',
                                'search_text' => $item->nip . ' ' . $item->nama
                            ];
                         });

        return response()->json([
            'success' => true,
            'data' => $pegawai,
            'search_info' => [
                'query' => $search,
                'total_results' => $pegawai->count(),
                'search_fields' => ['nip', 'nama'],
                'message' => $search ? "Hasil pencarian untuk: '{$search}'" : 'Semua pegawai'
            ]
        ]);
    }

    /**
     * Get filter options for dropdown.
     */
    public function getFilterOptions()
    {
        $unitKerja = SimpegUnitKerja::select('id as id', 'nama_unit as nama')
            ->orderBy('nama_unit')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Unit Kerja']);

        $jabatanFungsional = SimpegJabatanFungsional::select('id', 'nama_jabatan_fungsional as nama')
            ->orderBy('nama_jabatan_fungsional')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jabatan Fungsional']);

        $jenisPenghargaan = SimpegDataPenghargaanAdm::select('jenis_penghargaan')
            ->distinct()
            ->whereNotNull('jenis_penghargaan')
            ->where('jenis_penghargaan', '!=', '')
            ->orderBy('jenis_penghargaan')
            ->pluck('jenis_penghargaan')
            ->map(function ($item) {
                return ['id' => $item, 'nama' => $item];
            })
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jenis Penghargaan']);
        
        $statusPengajuanOptions = [
            ['id' => 'semua', 'nama' => 'Semua Status'],
            ['id' => 'draft', 'nama' => 'Draft'],
            ['id' => 'diajukan', 'nama' => 'Diajukan'],
            ['id' => 'disetujui', 'nama' => 'Disetujui'],
            ['id' => 'ditolak', 'nama' => 'Ditolak'],
            ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan'],
        ];

        return [
            'unit_kerja' => $unitKerja,
            'jabatan_fungsional' => $jabatanFungsional,
            'jenis_penghargaan' => $jenisPenghargaan,
            'status_pengajuan' => $statusPengajuanOptions,
        ];
    }

    /**
     * Get form options for create/update forms.
     */
    public function getFormOptions()
    {
        $unitKerja = SimpegUnitKerja::select('id as id', 'nama_unit as nama')
            ->orderBy('nama_unit')
            ->get();

        $jenisPenghargaanOptions = SimpegJenisPenghargaan::select('id', 'nama')
            ->orderBy('nama')
            ->get();

        return [
            'form_options' => [
                'unit_kerja' => $unitKerja,
                'jenis_penghargaan' => $jenisPenghargaanOptions
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|integer',
                'jenis_penghargaan_id' => 'required|integer', 
                'nama_penghargaan' => 'required|string|max:255',
                'no_sk' => 'nullable|string|max:100',
                'tanggal_sk' => 'nullable|date',
                'tanggal_penghargaan' => 'nullable|date',
                'keterangan' => 'nullable|string|max:255',
                'file_penghargaan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
            ],
            'field_notes' => [
                'jenis_penghargaan_id' => 'Pilih jenis penghargaan dari daftar yang tersedia.',
                'nama_penghargaan' => 'Nama lengkap penghargaan yang diterima',
                'no_sk' => 'Nomor Surat Keputusan penghargaan (jika ada)',
                'tanggal_sk' => 'Tanggal Surat Keputusan diterbitkan',
                'tanggal_penghargaan' => 'Tanggal penghargaan diberikan/diterima',
                'keterangan' => 'Keterangan tambahan mengenai penghargaan',
                'file_penghargaan' => 'Unggah file pendukung (PDF/gambar).',
                'pegawai_search' => 'Cari pegawai berdasarkan NIP atau nama'
            ]
        ];
    }

    /**
     * Get statistics for dashboard.
     */
    public function getStatistics()
    {
        $totalPenghargaan = SimpegDataPenghargaanAdm::count();
        
        $perUnitKerja = SimpegDataPenghargaanAdm::select('simpeg_unit_kerja.nama_unit', DB::raw('COUNT(*) as total'))
            ->join('simpeg_pegawai', 'simpeg_data_penghargaan.pegawai_id', '=', 'simpeg_pegawai.id')
            ->join('simpeg_unit_kerja', 'simpeg_pegawai.unit_kerja_id', '=', 'simpeg_unit_kerja.id')
            ->groupBy('simpeg_unit_kerja.nama_unit')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $perJenis = SimpegDataPenghargaanAdm::select('jenis_penghargaan', DB::raw('COUNT(*) as total'))
            ->whereNotNull('jenis_penghargaan')
            ->where('jenis_penghargaan', '!=', '')
            ->groupBy('jenis_penghargaan')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $perTahun = SimpegDataPenghargaanAdm::select(DB::raw('EXTRACT(YEAR FROM tanggal_penghargaan) as tahun'), DB::raw('COUNT(*) as total'))
            ->whereNotNull('tanggal_penghargaan')
            ->groupBy(DB::raw('EXTRACT(YEAR FROM tanggal_penghargaan)'))
            ->orderByDesc('tahun')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'statistics' => [
                'total_penghargaan' => $totalPenghargaan,
                'per_unit_kerja' => $perUnitKerja,
                'per_jenis' => $perJenis,
                'per_tahun' => $perTahun
            ]
        ]);
    }

    /**
     * Export data penghargaan.
     */
    public function export(Request $request)
    {
        $search = $request->search;
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $jenisPenghargaan = $request->jenis_penghargaan;

        $query = SimpegDataPenghargaanAdm::with([
            'pegawai' => function ($q) {
                $q->select('id', 'nip', 'nama', 'unit_kerja_id', 'jabatan_akademik_id')
                    ->with([
                        'unitKerja:id,nama_unit',
                        'jabatanAkademik:id,jabatan_akademik'
                    ]);
            }
        ]);

        // Apply filters using local scopes
        $query->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanFungsional($jabatanFungsionalId)
              ->filterByJenisPenghargaan($jenisPenghargaan)
              ->globalSearch($search);

        $dataPenghargaan = $query->orderBy('tanggal_penghargaan', 'desc')->get();

        // Format data for export
        $exportData = $dataPenghargaan->map(function ($item) {
            return [
                'NIP' => $item->pegawai->nip ?? '-',
                'Nama Pegawai' => $item->pegawai->nama ?? '-',
                'Unit Kerja' => $item->pegawai->unitKerja->nama_unit ?? '-',
                'Jabatan Akademik' => $item->pegawai->jabatanAkademik->jabatan_akademik ?? '-',
                'Jenis Penghargaan' => $item->jenis_penghargaan ?? '-',
                'Nama Penghargaan' => $item->nama_penghargaan ?? '-',
                'No SK' => $item->no_sk ?? '-',
                'Tanggal SK' => $item->tanggal_sk ? Carbon::parse($item->tanggal_sk)->format('d-m-Y') : '-',
                'Tanggal Penghargaan' => $item->tanggal_penghargaan ? Carbon::parse($item->tanggal_penghargaan)->format('d-m-Y') : '-',
                'Keterangan' => $item->keterangan ?? '-',
                'File Penghargaan' => $item->file_penghargaan ? $item->file_penghargaan : '-',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $exportData,
            'filename' => 'data_penghargaan_' . date('Y-m-d_H-i-s') . '.xlsx'
        ]);
    }

    /**
     * Validate duplicate penghargaan.
     */
    public function validateDuplicate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id',
            'jenis_penghargaan_id' => 'required|integer',
            'nama_penghargaan' => 'required|string',
            'tanggal_penghargaan' => 'nullable|date',
            'exclude_id' => 'nullable|integer' // untuk update
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = SimpegDataPenghargaanAdm::where('pegawai_id', $request->pegawai_id)
            ->where('jenis_penghargaan_id', $request->jenis_penghargaan_id)
            ->where('nama_penghargaan', $request->nama_penghargaan);

        if ($request->tanggal_penghargaan) {
            $query->whereDate('tanggal_penghargaan', $request->tanggal_penghargaan);
        }

        if ($request->exclude_id) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $exists = $query->exists();

        return response()->json([
            'success' => true,
            'is_duplicate' => $exists,
            'message' => $exists ? 'Data penghargaan serupa sudah ada' : 'Data penghargaan valid'
        ]);
    }

    /**
     * Helper: Format pegawai info.
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
     * Helper: Format data penghargaan response.
     */
    protected function formatDataPenghargaan($dataPenghargaan, $includeActions = true)
    {
        $pegawai = $dataPenghargaan->pegawai; // Pastikan relasi pegawai dimuat
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

        // Add action URLs if requested
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datapenghargaan/{$dataPenghargaan->id}"),
                'update_url' => url("/api/admin/datapenghargaan/{$dataPenghargaan->id}"),
                'delete_url' => url("/api/admin/datapenghargaan/{$dataPenghargaan->id}"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data penghargaan "' . $dataPenghargaan->nama_penghargaan . '"?'
                ]
            ];
        }

        return $data;
    }

    /**
     * Helper: Get status info.
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
}