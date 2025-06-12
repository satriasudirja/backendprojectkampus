<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPenghargaanAdm;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJabatanFungsional;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataPenghargaanAdmController extends Controller
{
    // Get all data penghargaan untuk admin (semua pegawai)
    public function index(Request $request) 
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $jenisPenghargaan = $request->jenis_penghargaan;

        // Query dengan eager loading untuk optimasi
        $query = SimpegDataPenghargaanAdm::with([
            'pegawai' => function($q) {
                $q->select('id', 'nip', 'nama', 'unit_kerja_id', 'jabatan_akademik_id')
                  ->with([
                      'unitKerja:kode_unit,nama_unit',
                      'jabatanAkademik:id,jabatan_akademik'
                  ]);
            }
        ]);

        // Apply filters menggunakan scope
        $query->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanFungsional($jabatanFungsionalId)
              ->filterByJenisPenghargaan($jenisPenghargaan)
              ->globalSearch($search);

        // Execute query dengan pagination
        $dataPenghargaan = $query->orderBy('tanggal_penghargaan', 'desc')
                                ->orderBy('created_at', 'desc')
                                ->paginate($perPage);

        // Transform the collection
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
                ['field' => 'jenis_penghargaan', 'label' => 'Jenis Penghargaan', 'sortable' => true, 'sortable_field' => 'jenis_penghargaan'],
                ['field' => 'nama_penghargaan', 'label' => 'Nama Penghargaan', 'sortable' => true, 'sortable_field' => 'nama_penghargaan'],
                ['field' => 'tanggal_penghargaan', 'label' => 'Tanggal Penghargaan', 'sortable' => true, 'sortable_field' => 'tanggal_penghargaan'],
                ['field' => 'no_sk', 'label' => 'No SK', 'sortable' => true, 'sortable_field' => 'no_sk'],
                ['field' => 'unit_kerja', 'label' => 'Unit Kerja', 'sortable' => true, 'sortable_field' => 'pegawai.unitKerja.nama_unit'],
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

    // Get detail data penghargaan
    public function show($id)
    {
        $dataPenghargaan = SimpegDataPenghargaanAdm::with([
            'pegawai' => function($q) {
                $q->with([
                    'unitKerja',
                    'statusAktif',
                    'jabatanAkademik',
                    'dataJabatanFungsional' => function($query) {
                        $query->with('jabatanFungsional')
                              ->orderBy('tmt_jabatan', 'desc')
                              ->limit(1);
                    },
                    'dataJabatanStruktural' => function($query) {
                        $query->with('jabatanStruktural.jenisJabatanStruktural')
                              ->orderBy('tgl_mulai', 'desc')
                              ->limit(1);
                    },
                    'dataPendidikanFormal' => function($query) {
                        $query->with('jenjangPendidikan')
                              ->orderBy('jenjang_pendidikan_id', 'desc')
                              ->limit(1);
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
            'data' => $this->formatDataPenghargaan($dataPenghargaan, false)
        ]);
    }

    // Store new data penghargaan
   public function store(Request $request)
    {
        // Validasi input dari request, termasuk file
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id',
            'jenis_penghargaan' => 'required|string|max:100',
            'nama_penghargaan' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:100',
            'tanggal_sk' => 'nullable|date',
            'tanggal_penghargaan' => 'nullable|date',
            'keterangan' => 'nullable|string',
            // Aturan validasi diubah dari 'date' menjadi 'file'
            'file_penghargaan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // Maksimal 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Ambil semua data yang sudah lolos validasi
            $validatedData = $validator->validated();
            
            // Cek apakah ada file yang diunggah
            if ($request->hasFile('file_penghargaan')) {
                // Simpan file ke storage/app/public/penghargaan
                // Nama file akan dibuat unik secara otomatis oleh Laravel
                $filePath = $request->file('file_penghargaan')->store('penghargaan', 'public');
                
                // Simpan path (lokasi) file ke dalam array data
                $validatedData['file_penghargaan'] = $filePath;
            }

            // Buat data baru di database
            $dataPenghargaan = SimpegDataPenghargaanAdm::create($validatedData);

            // (Opsional) Load relasi untuk data yang dikembalikan di response
            $dataPenghargaan->load(['pegawai.unitKerja', 'pegawai.jabatanAkademik']);

            // (Opsional) Catat aktivitas
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('create', $dataPenghargaan, $dataPenghargaan->toArray());
            }

            return response()->json([
                'success' => true,
                'message' => 'Data penghargaan berhasil ditambahkan',
                'data' => $dataPenghargaan // Anda bisa memanggil helper formatDataPenghargaan di sini jika ada
            ], 201);

        } catch (\Exception $e) {
            // Tangani jika ada error saat proses penyimpanan
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data penghargaan: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update data penghargaan
    public function update(Request $request, $id)
    {
        // Cari data yang akan diupdate
        $dataPenghargaan = SimpegDataPenghargaanAdm::find($id);
        if (!$dataPenghargaan) {
            return response()->json(['success' => false, 'message' => 'Data penghargaan tidak ditemukan'], 404);
        }

        // Validasi input dari request
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|integer|exists:simpeg_pegawai,id',
            'jenis_penghargaan' => 'sometimes|string|max:100',
            'nama_penghargaan' => 'sometimes|string|max:255',
            'no_sk' => 'nullable|string|max:100',
            'tanggal_sk' => 'nullable|date',
            'tanggal_penghargaan' => 'nullable|date',
            'keterangan' => 'nullable|string',
            'file_penghargaan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048', // Maksimal 2MB
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        try {
            $validatedData = $validator->validated();
            
            // Logika untuk memperbarui file
            if ($request->hasFile('file_penghargaan')) {
                // 1. Hapus file lama dari storage jika ada
                if ($dataPenghargaan->file_penghargaan) {
                    Storage::disk('public')->delete($dataPenghargaan->file_penghargaan);
                }

                // 2. Simpan file yang baru diunggah
                $filePath = $request->file('file_penghargaan')->store('penghargaan', 'public');
                $validatedData['file_penghargaan'] = $filePath;
            }

            $oldData = $dataPenghargaan->getOriginal();
            
            // Update data di database
            $dataPenghargaan->update($validatedData);

            // (Opsional) Load relasi untuk data yang dikembalikan di response
            $dataPenghargaan->load(['pegawai.unitKerja', 'pegawai.jabatanAkademik']);

            // (Opsional) Catat aktivitas
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('update', $dataPenghargaan, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data penghargaan berhasil diperbarui',
                'data' => $dataPenghargaan // Anda bisa memanggil helper formatDataPenghargaan di sini
            ]);

        } catch (\Exception $e) {
            // Tangani jika ada error saat proses pembaruan
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data penghargaan: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete data penghargaan
    public function destroy($id)
    {
        $dataPenghargaan = SimpegDataPenghargaanAdm::find($id);

        if (!$dataPenghargaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data penghargaan tidak ditemukan'
            ], 404);
        }

        $oldData = $dataPenghargaan->toArray();
        $dataPenghargaan->delete();

        // Log activity jika service tersedia
        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('delete', $dataPenghargaan, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data penghargaan berhasil dihapus'
        ]);
    }

    // Batch delete data penghargaan
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
                'message' => 'Data penghargaan tidak ditemukan'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataPenghargaanList as $dataPenghargaan) {
            try {
                $oldData = $dataPenghargaan->toArray();
                $dataPenghargaan->delete();
                
                // Log activity jika service tersedia
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('delete', $dataPenghargaan, $oldData);
                }
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataPenghargaan->id,
                    'nama_penghargaan' => $dataPenghargaan->nama_penghargaan,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
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

    // Get pegawai options untuk dropdown create form dengan search nama dan NIP
    public function getPegawaiOptions(Request $request)
    {
        $search = $request->search;
        $unitKerjaId = $request->unit_kerja_id;

        $query = SimpegPegawai::select('id', 'nip', 'nama', 'unit_kerja_id')
            ->with('unitKerja:kode_unit,nama_unit');

        // Filter by search (NIP dan Nama)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nip', 'like', '%'.$search.'%')
                  ->orWhere('nama', 'like', '%'.$search.'%');
            });
        }

        // Filter by unit kerja
        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $query->where('unit_kerja_id', $unitKerjaId);
        }

        $pegawai = $query->orderBy('nama')
                        ->limit(50) // Limit untuk performance
                        ->get()
                        ->map(function($item) {
                            return [
                                'id' => $item->id,
                                'nip' => $item->nip,
                                'nama' => $item->nama,
                                'unit_kerja' => $item->unitKerja->nama_unit ?? '-',
                                'label' => $item->nip . ' - ' . $item->nama . ' (' . ($item->unitKerja->nama_unit ?? 'No Unit') . ')',
                                'search_text' => $item->nip . ' ' . $item->nama // untuk search yang lebih baik
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

    // Get filter options untuk dropdown
    public function getFilterOptions()
    {
        // Unit kerja options
        $unitKerja = SimpegUnitKerja::select('kode_unit as id', 'nama_unit as nama')
            ->orderBy('nama_unit')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Unit Kerja']);

        // Jabatan fungsional options melalui jabatan akademik
        $jabatanFungsional = SimpegJabatanFungsional::select('id', 'nama_jabatan_fungsional as nama')
            ->orderBy('nama_jabatan_fungsional')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jabatan Fungsional']);

        // Jenis penghargaan options dari data yang ada
        $jenisPenghargaan = SimpegDataPenghargaanAdm::select('jenis_penghargaan')
            ->distinct()
            ->whereNotNull('jenis_penghargaan')
            ->where('jenis_penghargaan', '!=', '')
            ->orderBy('jenis_penghargaan')
            ->pluck('jenis_penghargaan')
            ->map(function($item) {
                return ['id' => $item, 'nama' => $item];
            })
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jenis Penghargaan']);

        return [
            'unit_kerja' => $unitKerja,
            'jabatan_fungsional' => $jabatanFungsional,
            'jenis_penghargaan' => $jenisPenghargaan
        ];
    }

    // Get form options untuk create/update forms
    public function getFormOptions()
    {
        // Unit kerja options
        $unitKerja = SimpegUnitKerja::select('kode_unit as id', 'nama_unit as nama')
            ->orderBy('nama_unit')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Unit Kerja']);

        return response()->json([
            'success' => true,
            'form_options' => [
                'unit_kerja' => $unitKerja
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|integer',
                'jenis_penghargaan' => 'required|string|max:100',
                'nama_penghargaan' => 'required|string|max:255',
                'no_sk' => 'nullable|string|max:100',
                'tanggal_sk' => 'nullable|date',
                'tanggal_penghargaan' => 'nullable|date',
                'keterangan' => 'nullable|string|max:255',
                'file_penghargaan' => 'nullable|date'
            ],
            'field_notes' => [
                'jenis_penghargaan' => 'Input manual. Ketik jenis penghargaan sesuai kebutuhan.',
                'nama_penghargaan' => 'Nama lengkap penghargaan yang diterima',
                'no_sk' => 'Nomor Surat Keputusan penghargaan (jika ada)',
                'tanggal_sk' => 'Tanggal Surat Keputusan diterbitkan',
                'tanggal_penghargaan' => 'Tanggal penghargaan diberikan/diterima',
                'keterangan' => 'Keterangan tambahan mengenai penghargaan',
                'file_penghargaan' => 'Tanggal file penghargaan (format date)',
                'pegawai_search' => 'Cari pegawai berdasarkan NIP atau nama'
            ]
        ]);
    }

    // Get statistics for dashboard
    public function getStatistics()
    {
        $totalPenghargaan = SimpegDataPenghargaanAdm::count();
        
        $perUnitKerja = SimpegDataPenghargaanAdm::select('simpeg_unit_kerja.nama_unit', DB::raw('COUNT(*) as total'))
            ->join('simpeg_pegawai', 'simpeg_data_penghargaan.pegawai_id', '=', 'simpeg_pegawai.id')
            ->join('simpeg_unit_kerja', 'simpeg_pegawai.unit_kerja_id', '=', 'simpeg_unit_kerja.kode_unit')
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

        $perTahun = SimpegDataPenghargaanAdm::select(DB::raw('YEAR(tanggal_penghargaan) as tahun'), DB::raw('COUNT(*) as total'))
            ->whereNotNull('tanggal_penghargaan')
            ->groupBy(DB::raw('YEAR(tanggal_penghargaan)'))
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

    // Export data penghargaan
    public function export(Request $request)
    {
        $search = $request->search;
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $jenisPenghargaan = $request->jenis_penghargaan;

        $query = SimpegDataPenghargaanAdm::with([
            'pegawai' => function($q) {
                $q->select('id', 'nip', 'nama', 'unit_kerja_id', 'jabatan_akademik_id')
                  ->with([
                      'unitKerja:kode_unit,nama_unit',
                      'jabatanAkademik:id,jabatan_akademik'
                  ]);
            }
        ]);

        // Apply filters
        $query->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanFungsional($jabatanFungsionalId)
              ->filterByJenisPenghargaan($jenisPenghargaan)
              ->globalSearch($search);

        $dataPenghargaan = $query->orderBy('tanggal_penghargaan', 'desc')->get();

        // Format data untuk export
        $exportData = $dataPenghargaan->map(function ($item) {
            return [
                'NIP' => $item->pegawai->nip ?? '-',
                'Nama Pegawai' => $item->pegawai->nama ?? '-',
                'Unit Kerja' => $item->pegawai->unitKerja->nama_unit ?? '-',
                'Jabatan Akademik' => $item->pegawai->jabatanAkademik->jabatan_akademik ?? '-',
                'Jenis Penghargaan' => $item->jenis_penghargaan ?? '-',
                'Nama Penghargaan' => $item->nama_penghargaan ?? '-',
                'No SK' => $item->no_sk ?? '-',
                'Tanggal SK' => $item->tanggal_sk ? $item->tanggal_sk->format('d-m-Y') : '-',
                'Tanggal Penghargaan' => $item->tanggal_penghargaan ? $item->tanggal_penghargaan->format('d-m-Y') : '-',
                'Keterangan' => $item->keterangan ?? '-',
                'File Penghargaan' => $item->file_penghargaan ? $item->file_penghargaan->format('d-m-Y') : '-',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $exportData,
            'filename' => 'data_penghargaan_' . date('Y-m-d_H-i-s') . '.xlsx'
        ]);
    }

    // Validate duplicate penghargaan
    public function validateDuplicate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id',
            'jenis_penghargaan' => 'required|string',
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
            ->where('jenis_penghargaan', $request->jenis_penghargaan)
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

    // Helper: Format pegawai info
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
            $jabatanFungsional = $pegawai->dataJabatanFungsional->first()->jabatanFungsional;
            if ($jabatanFungsional) {
                if (isset($jabatanFungsional->nama_jabatan_fungsional)) {
                    $jabatanFungsionalNama = $jabatanFungsional->nama_jabatan_fungsional;
                } elseif (isset($jabatanFungsional->nama)) {
                    $jabatanFungsionalNama = $jabatanFungsional->nama;
                }
            }
        }

        $jabatanStrukturalNama = '-';
        if ($pegawai->dataJabatanStruktural && $pegawai->dataJabatanStruktural->isNotEmpty()) {
            $jabatanStruktural = $pegawai->dataJabatanStruktural->first();
            
            if ($jabatanStruktural->jabatanStruktural && $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural;
            }
            elseif (isset($jabatanStruktural->jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->nama_jabatan;
            }
        }

        $jenjangPendidikanNama = '-';
        if ($pegawai->dataPendidikanFormal && $pegawai->dataPendidikanFormal->isNotEmpty()) {
            $highestEducation = $pegawai->dataPendidikanFormal->first();
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

    // Helper: Format data penghargaan response
    protected function formatDataPenghargaan($dataPenghargaan, $includeActions = true)
    {
        $data = [
            'id' => $dataPenghargaan->id,
            'pegawai_id' => $dataPenghargaan->pegawai_id,
            'nip' => $dataPenghargaan->pegawai->nip ?? '-',
            'nama_pegawai' => $dataPenghargaan->pegawai->nama ?? '-',
            'unit_kerja' => $dataPenghargaan->pegawai->unitKerja->nama_unit ?? '-',
            'jabatan_akademik' => $dataPenghargaan->pegawai->jabatanAkademik->jabatan_akademik ?? '-',
            'jenis_penghargaan' => $dataPenghargaan->jenis_penghargaan ?? '-',
            'nama_penghargaan' => $dataPenghargaan->nama_penghargaan ?? '-',
            'no_sk' => $dataPenghargaan->no_sk ?? '-',
            'tanggal_sk' => $dataPenghargaan->tanggal_sk,
            'tanggal_sk_formatted' => $dataPenghargaan->tanggal_sk ? $dataPenghargaan->tanggal_sk->format('d M Y') : '-',
            'tanggal_penghargaan' => $dataPenghargaan->tanggal_penghargaan,
            'tanggal_penghargaan_formatted' => $dataPenghargaan->tanggal_penghargaan ? $dataPenghargaan->tanggal_penghargaan->format('d M Y') : '-',
            'keterangan' => $dataPenghargaan->keterangan ?? '-',
            'file_penghargaan' => $dataPenghargaan->file_penghargaan,
            'file_penghargaan_formatted' => $dataPenghargaan->file_penghargaan ? $dataPenghargaan->file_penghargaan->format('d M Y') : '-',
            'created_at' => $dataPenghargaan->created_at,
            'updated_at' => $dataPenghargaan->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datapenghargaan/{$dataPenghargaan->id}"),
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
}