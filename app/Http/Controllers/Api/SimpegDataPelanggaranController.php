<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPelanggaran;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJabatanFungsional;
use App\Models\SimpegJenisPelanggaran;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataPelanggaranController extends Controller
{
    // Get all data pelanggaran untuk admin (semua pegawai)
    public function index(Request $request) 
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $jenisPelanggaranId = $request->jenis_pelanggaran_id;

        // Query dengan eager loading untuk optimasi
        $query = SimpegDataPelanggaran::with([
            'pegawai' => function($q) {
                $q->select('id', 'nip', 'nama', 'unit_kerja_id', 'jabatan_akademik_id')
                  ->with([
                      'unitKerja:kode_unit,nama_unit',
                      'jabatanAkademik:id,jabatan_akademik'
                  ]);
            },
            'jenisPelanggaran:id,nama_pelanggaran'
        ]);

        // Apply filters menggunakan scope
        $query->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanFungsional($jabatanFungsionalId)
              ->filterByJenisPelanggaran($jenisPelanggaranId)
              ->globalSearch($search);

        // Execute query dengan pagination
        $dataPelanggaran = $query->orderBy('tgl_pelanggaran', 'desc')
                                ->orderBy('created_at', 'desc')
                                ->paginate($perPage);

        // Transform the collection
        $dataPelanggaran->getCollection()->transform(function ($item) {
            return $this->formatDataPelanggaran($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataPelanggaran,
            'empty_data' => $dataPelanggaran->isEmpty(),
            'filters' => $this->getFilterOptions(),
            'table_columns' => [
                ['field' => 'nip', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'pegawai.nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'pegawai.nama'],
                ['field' => 'tgl_pelanggaran', 'label' => 'Tgl. Pelanggaran', 'sortable' => true, 'sortable_field' => 'tgl_pelanggaran'],
                ['field' => 'jenis_pelanggaran', 'label' => 'Jenis Pelanggaran', 'sortable' => true, 'sortable_field' => 'jenisPelanggaran.nama_pelanggaran'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/datapelanggaran/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ]
            ],
            'pagination' => [
                'current_page' => $dataPelanggaran->currentPage(),
                'per_page' => $dataPelanggaran->perPage(),
                'total' => $dataPelanggaran->total(),
                'last_page' => $dataPelanggaran->lastPage(),
                'from' => $dataPelanggaran->firstItem(),
                'to' => $dataPelanggaran->lastItem()
            ]
        ]);
    }

    // Get detail data pelanggaran
    public function show($id)
    {
        $dataPelanggaran = SimpegDataPelanggaran::with([
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
            },
            'jenisPelanggaran'
        ])->find($id);

        if (!$dataPelanggaran) {
            return response()->json([
                'success' => false,
                'message' => 'Data pelanggaran tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($dataPelanggaran->pegawai),
            'data' => $this->formatDataPelanggaran($dataPelanggaran, false)
        ]);
    }

    // Store new data pelanggaran
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|uuid|exists:simpeg_pegawai,id',
            'jenis_pelanggaran_id' => 'required|uuid|exists:simpeg_jenis_pelanggaran,id',
            'tgl_pelanggaran' => 'nullable|date',
            'no_sk' => 'nullable|string|max:100',
            'tgl_sk' => 'nullable|date',
            'keterangan' => 'nullable|string|max:255',
            'file_foto' => 'required|image|mimes:jpeg,jpg,png|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $dataToCreate = $validator->validated();
            // Handle file upload
            if ($request->hasFile('file_foto')) {
                $file = $request->file('file_foto');
                $fileName = 'pelanggaran_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('pelanggaran', $fileName, 'public');
                $dataToCreate['file_foto'] = $filePath;
            }

            // Create data
            $dataPelanggaran = SimpegDataPelanggaran::create($dataToCreate);

            // Load relasi untuk response
            $dataPelanggaran->load([
                'pegawai' => function($q) {
                    $q->select('id', 'nip', 'nama', 'unit_kerja_id', 'jabatan_fungsional_id')
                      ->with([
                          'unitKerja:kode_unit,nama_unit',
                          'jabatanFungsional:id,jabatan_fungsional'
                      ]);
                },
                'jenisPelanggaran:id,nama_pelanggaran'
            ]);

            // Log activity jika service tersedia
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('create', $dataPelanggaran, $dataPelanggaran->toArray());
            }

            return response()->json([
                'success' => true,
                'message' => 'Data pelanggaran berhasil ditambahkan',
                'data' => $this->formatDataPelanggaran($dataPelanggaran, false)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data pelanggaran: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update data pelanggaran
    public function update(Request $request, $id)
    {
        $dataPelanggaran = SimpegDataPelanggaran::find($id);

        if (!$dataPelanggaran) {
            return response()->json([
                'success' => false,
                'message' => 'Data pelanggaran tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|uuid|exists:simpeg_pegawai,id',
            'jenis_pelanggaran_id' => 'sometimes|uuid|exists:simpeg_jenis_pelanggaran,id',
            'tgl_pelanggaran' => 'nullable|date',
            'no_sk' => 'nullable|string|max:100',
            'tgl_sk' => 'nullable|date',
            'keterangan' => 'nullable|string|max:255',
            'file_foto' => 'nullable|image|mimes:jpeg,jpg,png|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $oldData = $dataPelanggaran->getOriginal();
            $dataToUpdate = $request->except(['file_foto']);
            
            // Handle file upload
            if ($request->hasFile('file_foto')) {
                // Delete old file if exists
                if ($dataPelanggaran->file_foto && Storage::disk('public')->exists($dataPelanggaran->file_foto)) {
                    Storage::disk('public')->delete($dataPelanggaran->file_foto);
                }
                
                // Upload new file
                $file = $request->file('file_foto');
                $fileName = 'pelanggaran_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('pelanggaran', $fileName, 'public');
                $dataToUpdate['file_foto'] = $filePath;
            }
            
            // Update data
            $dataPelanggaran->update($dataToUpdate);

            // Load relasi untuk response
            $dataPelanggaran->load([
                'pegawai' => function($q) {
                    $q->select('id', 'nip', 'nama', 'unit_kerja_id', 'jabatan_fungsional_id')
                      ->with([
                          'unitKerja:kode_unit,nama_unit',
                          'jabatanFungsional:id,jabatan_fungsional'
                      ]);
                },
                'jenisPelanggaran:id,nama_pelanggaran'
            ]);

            // Log activity jika service tersedia
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('update', $dataPelanggaran, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data pelanggaran berhasil diperbarui',
                'data' => $this->formatDataPelanggaran($dataPelanggaran, false)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data pelanggaran: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete data pelanggaran
    public function destroy($id)
    {
        $dataPelanggaran = SimpegDataPelanggaran::find($id);

        if (!$dataPelanggaran) {
            return response()->json([
                'success' => false,
                'message' => 'Data pelanggaran tidak ditemukan'
            ], 404);
        }

        $oldData = $dataPelanggaran->toArray();
        
        // Delete file if exists
        if ($dataPelanggaran->file_foto && Storage::disk('public')->exists($dataPelanggaran->file_foto)) {
            Storage::disk('public')->delete($dataPelanggaran->file_foto);
        }
        
        $dataPelanggaran->delete();

        // Log activity jika service tersedia
        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('delete', $dataPelanggaran, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data pelanggaran berhasil dihapus'
        ]);
    }

    // Batch delete data pelanggaran
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_pelanggaran,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataPelanggaranList = SimpegDataPelanggaran::whereIn('id', $request->ids)->get();

        if ($dataPelanggaranList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data pelanggaran tidak ditemukan'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataPelanggaranList as $dataPelanggaran) {
            try {
                $oldData = $dataPelanggaran->toArray();
                
                // Delete file if exists
                if ($dataPelanggaran->file_foto && Storage::disk('public')->exists($dataPelanggaran->file_foto)) {
                    Storage::disk('public')->delete($dataPelanggaran->file_foto);
                }
                
                $dataPelanggaran->delete();
                
                // Log activity jika service tersedia
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('delete', $dataPelanggaran, $oldData);
                }
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataPelanggaran->id,
                    'jenis_pelanggaran' => $dataPelanggaran->jenisPelanggaran->nama_pelanggaran ?? 'Unknown',
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data pelanggaran",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data pelanggaran",
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

        // Jabatan fungsional options
        $jabatanFungsional = SimpegJabatanFungsional::select('id', 'nama_jabatan_fungsional as nama')
            ->orderBy('nama_jabatan_fungsional')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jabatan Fungsional']);

        // Jenis pelanggaran options
        $jenisPelanggaran = SimpegJenisPelanggaran::select('id', 'nama_pelanggaran as nama')
            ->orderBy('nama_pelanggaran')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jenis Pelanggaran']);

        return [
            'unit_kerja' => $unitKerja,
            'jabatan_fungsional' => $jabatanFungsional,
            'jenis_pelanggaran' => $jenisPelanggaran
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

        // Jenis pelanggaran options
        $jenisPelanggaran = SimpegJenisPelanggaran::select('id', 'nama_pelanggaran as nama')
            ->orderBy('nama_pelanggaran')
            ->get();

        return response()->json([
            'success' => true,
            'form_options' => [
                'unit_kerja' => $unitKerja,
                'jenis_pelanggaran' => $jenisPelanggaran
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|uuid',
                'jenis_pelanggaran_id' => 'required|uuid',
                'tgl_pelanggaran' => 'nullable|date',
                'no_sk' => 'nullable|string|max:100',
                'tgl_sk' => 'nullable|date',
                'keterangan' => 'nullable|string|max:255',
                'file_foto' => 'nullable|image|mimes:jpeg,jpg,png|max:2048'
            ],
            'field_notes' => [
                'jenis_pelanggaran_id' => 'Pilih jenis pelanggaran dari dropdown',
                'tgl_pelanggaran' => 'Tanggal pelanggaran terjadi',
                'no_sk' => 'Nomor Surat Keputusan pelanggaran (jika ada)',
                'tgl_sk' => 'Tanggal Surat Keputusan diterbitkan',
                'keterangan' => 'Keterangan tambahan mengenai pelanggaran',
                'file_foto' => 'Upload file foto bukti pelanggaran (JPEG, JPG, PNG, max 2MB)',
                'pegawai_search' => 'Cari pegawai berdasarkan NIP atau nama'
            ]
        ]);
    }

    // Get statistics for dashboard
    public function getStatistics()
    {
        $totalPelanggaran = SimpegDataPelanggaran::count();
        
        $perUnitKerja = SimpegDataPelanggaran::select('simpeg_unit_kerja.nama_unit', DB::raw('COUNT(*) as total'))
            ->join('simpeg_pegawai', 'simpeg_data_pelanggaran.pegawai_id', '=', 'simpeg_pegawai.id')
            ->join('simpeg_unit_kerja', function($join) {
                $join->on(DB::raw('CAST(simpeg_pegawai.unit_kerja_id AS VARCHAR)'), '=', 'simpeg_unit_kerja.kode_unit');
        })
            ->groupBy('simpeg_unit_kerja.nama_unit')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $perJenis = SimpegDataPelanggaran::select('simpeg_jenis_pelanggaran.nama_pelanggaran', DB::raw('COUNT(*) as total'))
            ->join('simpeg_jenis_pelanggaran', 'simpeg_data_pelanggaran.jenis_pelanggaran_id', '=', 'simpeg_jenis_pelanggaran.id')
            ->groupBy('simpeg_jenis_pelanggaran.nama_pelanggaran')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $perTahun = SimpegDataPelanggaran::select(
            DB::raw('EXTRACT(YEAR FROM tgl_pelanggaran) as tahun'), 
            DB::raw('COUNT(*) as total')
        )
            ->whereNotNull('tgl_pelanggaran')
            ->groupBy(DB::raw('EXTRACT(YEAR FROM tgl_pelanggaran)'))
            ->orderByDesc('tahun')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'statistics' => [
                'total_pelanggaran' => $totalPelanggaran,
                'per_unit_kerja' => $perUnitKerja,
                'per_jenis' => $perJenis,
                'per_tahun' => $perTahun
            ]
        ]);
    }

    // Export data pelanggaran
    public function export(Request $request)
    {
        $search = $request->search;
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $jenisPelanggaranId = $request->jenis_pelanggaran_id;

        $query = SimpegDataPelanggaran::with([
            'pegawai' => function($q) {
                $q->select('id', 'nip', 'nama', 'unit_kerja_id', 'jabatan_akademik_id')
                  ->with([
                      'unitKerja:kode_unit,nama_unit',
                      'jabatanAkademik:id,jabatan_akademik'
                  ]);
            },
            'jenisPelanggaran:id,nama_pelanggaran'
        ]);

        // Apply filters
        $query->filterByUnitKerja($unitKerjaId)
              ->filterByJabatanFungsional($jabatanFungsionalId)
              ->filterByJenisPelanggaran($jenisPelanggaranId)
              ->globalSearch($search);

        $dataPelanggaran = $query->orderBy('tgl_pelanggaran', 'desc')->get();

        // Format data untuk export
        $exportData = $dataPelanggaran->map(function ($item) {
            return [
                'NIP' => $item->pegawai->nip ?? '-',
                'Nama Pegawai' => $item->pegawai->nama ?? '-',
                'Unit Kerja' => $item->pegawai->unitKerja->nama_unit ?? '-',
                'Jabatan Akademik' => $item->pegawai->jabatanAkademik->jabatan_akademik ?? '-',
                'Jenis Pelanggaran' => $item->jenisPelanggaran->nama_pelanggaran ?? '-',
                'Tgl Pelanggaran' => $item->tgl_pelanggaran ? $item->tgl_pelanggaran->format('d-m-Y') : '-',
                'No SK' => $item->no_sk ?? '-',
                'Tgl SK' => $item->tgl_sk ? $item->tgl_sk->format('d-m-Y') : '-',
                'Keterangan' => $item->keterangan ?? '-',
                'File Foto' => $item->file_foto ? basename($item->file_foto) : '-',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $exportData,
            'filename' => 'data_pelanggaran_' . date('Y-m-d_H-i-s') . '.xlsx'
        ]);
    }

    // Validate duplicate pelanggaran
    public function validateDuplicate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|uuid|exists:simpeg_pegawai,id',
            'jenis_pelanggaran_id' => 'required|uuid|exists:simpeg_jenis_pelanggaran,id',
            'tgl_pelanggaran' => 'nullable|date',
            'exclude_id' => 'nullable|uuid' // untuk update
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = SimpegDataPelanggaran::where('pegawai_id', $request->pegawai_id)
            ->where('jenis_pelanggaran_id', $request->jenis_pelanggaran_id);

        if ($request->tgl_pelanggaran) {
            $query->whereDate('tgl_pelanggaran', $request->tgl_pelanggaran);
        }

        if ($request->exclude_id) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $exists = $query->exists();

        return response()->json([
            'success' => true,
            'is_duplicate' => $exists,
            'message' => $exists ? 'Data pelanggaran serupa sudah ada' : 'Data pelanggaran valid'
        ]);
    }

    // Download/Show file foto pelanggaran
    public function showFile($id)
    {
        $dataPelanggaran = SimpegDataPelanggaran::find($id);

        if (!$dataPelanggaran) {
            return response()->json([
                'success' => false,
                'message' => 'Data pelanggaran tidak ditemukan'
            ], 404);
        }

        if (!$dataPelanggaran->file_foto || !Storage::disk('public')->exists($dataPelanggaran->file_foto)) {
            return response()->json([
                'success' => false,
                'message' => 'File foto tidak ditemukan'
            ], 404);
        }

        $filePath = storage_path('app/public/' . $dataPelanggaran->file_foto);
        $fileName = basename($dataPelanggaran->file_foto);
        $mimeType = Storage::disk('public')->mimeType($dataPelanggaran->file_foto);

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"'
        ]);
    }

    // Download file foto pelanggaran
    public function downloadFile($id)
    {
        $dataPelanggaran = SimpegDataPelanggaran::find($id);

        if (!$dataPelanggaran) {
            return response()->json([
                'success' => false,
                'message' => 'Data pelanggaran tidak ditemukan'
            ], 404);
        }

        if (!$dataPelanggaran->file_foto || !Storage::disk('public')->exists($dataPelanggaran->file_foto)) {
            return response()->json([
                'success' => false,
                'message' => 'File foto tidak ditemukan'
            ], 404);
        }

        $filePath = storage_path('app/public/' . $dataPelanggaran->file_foto);
        $fileName = 'pelanggaran_' . $dataPelanggaran->id . '_' . basename($dataPelanggaran->file_foto);

        return response()->download($filePath, $fileName);
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

    // Helper: Format data pelanggaran response
    protected function formatDataPelanggaran($dataPelanggaran, $includeActions = true)
    {
        $data = [
            'id' => $dataPelanggaran->id,
            'pegawai_id' => $dataPelanggaran->pegawai_id,
            'nip' => $dataPelanggaran->pegawai->nip ?? '-',
            'nama_pegawai' => $dataPelanggaran->pegawai->nama ?? '-',
            'unit_kerja' => $dataPelanggaran->pegawai->unitKerja->nama_unit ?? '-',
            'jabatan_akademik' => $dataPelanggaran->pegawai->jabatanAkademik->jabatan_akademik ?? '-',
            'jenis_pelanggaran_id' => $dataPelanggaran->jenis_pelanggaran_id,
            'jenis_pelanggaran' => $dataPelanggaran->jenisPelanggaran->nama_pelanggaran ?? '-',
            'tgl_pelanggaran' => $dataPelanggaran->tgl_pelanggaran,
            'tgl_pelanggaran_formatted' => $dataPelanggaran->tgl_pelanggaran ? $dataPelanggaran->tgl_pelanggaran->format('d M Y') : '-',
            'no_sk' => $dataPelanggaran->no_sk ?? '-',
            'tgl_sk' => $dataPelanggaran->tgl_sk,
            'tgl_sk_formatted' => $dataPelanggaran->tgl_sk ? $dataPelanggaran->tgl_sk->format('d M Y') : '-',
            'keterangan' => $dataPelanggaran->keterangan ?? '-',
            'file_foto' => $dataPelanggaran->file_foto ? Storage::url($dataPelanggaran->file_foto) : null,
            'file_foto_path' => $dataPelanggaran->file_foto ?? null,
            'file_foto_name' => $dataPelanggaran->file_foto ? basename($dataPelanggaran->file_foto) : null,
            'created_at' => $dataPelanggaran->created_at,
            'updated_at' => $dataPelanggaran->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/dosen/datapelanggaran/{$dataPelanggaran->id}"),
                'delete_url' => url("/api/dosen/datapelanggaran/{$dataPelanggaran->id}"),
                'file_view_url' => $dataPelanggaran->file_foto ? url("/api/dosen/datapelanggaran/{$dataPelanggaran->id}/file/view") : null,
                'file_download_url' => $dataPelanggaran->file_foto ? url("/api/dosen/datapelanggaran/{$dataPelanggaran->id}/file/download") : null,
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data pelanggaran "' . ($dataPelanggaran->jenisPelanggaran->nama_pelanggaran ?? 'Unknown') . '"?'
                ]
            ];

            // Add file actions if file exists
            if ($dataPelanggaran->file_foto) {
                $data['actions']['view_file'] = [
                    'url' => $data['aksi']['file_view_url'],
                    'method' => 'GET',
                    'label' => 'Lihat File',
                    'icon' => 'image',
                    'color' => 'primary',
                    'target' => '_blank'
                ];
                
                $data['actions']['download_file'] = [
                    'url' => $data['aksi']['file_download_url'],
                    'method' => 'GET',
                    'label' => 'Download File',
                    'icon' => 'download',
                    'color' => 'success'
                ];
            }
        }

        return $data;
    }
}