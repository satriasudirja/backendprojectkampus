<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJamKerja;
use App\Models\SimpegJenisKehadiran;
use App\Models\SimpegSettingKehadiran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class InputPresensiController extends Controller
{
    /**
     * Display a listing of presensi with filters
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->per_page ?? 10;
            $search = $request->search;
            
            // Filter parameters - default hari ini
            $tanggal = $request->tanggal ?? date('Y-m-d');
            $unitKerjaFilter = $request->unit_kerja;
            $jenisKehadiranFilter = $request->jenis_kehadiran;
            $statusPresensiFilter = $request->status_presensi;

            // REMOVED: Auto-generate daily attendance records
            // Hanya tampilkan data yang sudah ada input

            // Base query dengan relasi - hanya tampilkan yang sudah ada record
            $query = SimpegAbsensiRecord::with([
                'pegawai.unitKerja',
                'jamKerja',
                'jenisKehadiran',
                'settingKehadiran',
                'cutiRecord',
                'izinRecord'
            ])->whereDate('tanggal_absensi', $tanggal);

            // Filter by unit kerja
            if ($unitKerjaFilter) {
                $query->whereHas('pegawai', function($q) use ($unitKerjaFilter) {
                    if (is_numeric($unitKerjaFilter)) {
                        $unitKerja = SimpegUnitKerja::find($unitKerjaFilter);
                        if ($unitKerja) {
                            $q->whereRaw("unit_kerja_id::text = ?", [$unitKerja->kode_unit]);
                        }
                    } else {
                        $q->whereRaw("unit_kerja_id::text = ?", [$unitKerjaFilter]);
                    }
                });
            }

            // Filter by jenis kehadiran
            if ($jenisKehadiranFilter) {
                $query->where('jenis_kehadiran_id', $jenisKehadiranFilter);
            }

            // Filter by status presensi
            if ($statusPresensiFilter) {
                $query->where(function($q) use ($statusPresensiFilter) {
                    switch ($statusPresensiFilter) {
                        case 'alpha':
                            $q->whereNull('jam_masuk')
                              ->whereNull('jam_keluar')
                              ->whereNull('cuti_record_id')
                              ->whereNull('izin_record_id');
                            break;
                        case 'hadir':
                            $q->whereNotNull('jam_masuk');
                            break;
                        case 'hadir_lengkap':
                            $q->whereNotNull('jam_masuk')
                              ->whereNotNull('jam_keluar');
                            break;
                        case 'hadir_masuk':
                            $q->whereNotNull('jam_masuk')
                              ->whereNull('jam_keluar');
                            break;
                        case 'cuti':
                            $q->whereNotNull('cuti_record_id');
                            break;
                        case 'izin':
                            $q->whereNotNull('izin_record_id');
                            break;
                        case 'sakit':
                            $q->where('keterangan', 'like', '%sakit%');
                            break;
                        case 'dinas':
                            $q->where('keterangan', 'like', '%dinas%');
                            break;
                        case 'terlambat':
                            $q->where('terlambat', true);
                            break;
                        case 'pulang_awal':
                            $q->where('pulang_awal', true);
                            break;
                        case 'libur':
                            $q->where('keterangan', 'like', '%libur%');
                            break;
                    }
                });
            }

            // Search functionality
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->whereHas('pegawai', function($subQ) use ($search) {
                        $subQ->where('nip', 'like', '%'.$search.'%')
                             ->orWhere('nama', 'like', '%'.$search.'%');
                    })
                    ->orWhere('keterangan', 'like', '%'.$search.'%')
                    ->orWhereHas('pegawai.unitKerja', function($subQ) use ($search) {
                        $subQ->where('nama_unit', 'like', '%'.$search.'%');
                    });
                });
            }

            // Order by NIP
            $query->join('simpeg_pegawai', 'simpeg_absensi_record.pegawai_id', '=', 'simpeg_pegawai.id')
                  ->orderBy('simpeg_pegawai.nip', 'asc')
                  ->select('simpeg_absensi_record.*');

            $presensiData = $query->paginate($perPage);

            // Get filter options
            $filterOptions = $this->getFilterOptions();

            return response()->json([
                'success' => true,
                'tanggal_input' => $tanggal,
                'hari' => Carbon::parse($tanggal)->locale('id')->isoFormat('dddd, DD MMMM YYYY'),
                'filter_options' => $filterOptions,
                'data' => $presensiData->map(function ($item) {
                    return $this->formatPresensiData($item);
                }),
                'pagination' => [
                    'current_page' => $presensiData->currentPage(),
                    'per_page' => $presensiData->perPage(),
                    'total' => $presensiData->total(),
                    'last_page' => $presensiData->lastPage(),
                    'from' => $presensiData->firstItem(),
                    'to' => $presensiData->lastItem()
                ],
                'filters_applied' => [
                    'tanggal' => $tanggal,
                    'unit_kerja' => $unitKerjaFilter,
                    'jenis_kehadiran' => $jenisKehadiranFilter,
                    'status_presensi' => $statusPresensiFilter,
                    'search' => $search
                ],
                'table_columns' => [
                    ['field' => 'nip', 'label' => 'NIP', 'sortable' => true],
                    ['field' => 'nama_pegawai', 'label' => 'Pegawai', 'sortable' => true],
                    ['field' => 'status', 'label' => 'Status', 'sortable' => true],
                    ['field' => 'jam_datang', 'label' => 'Jam Datang', 'sortable' => true],
                    ['field' => 'jam_pulang', 'label' => 'Jam Pulang', 'sortable' => true],
                    ['field' => 'keterangan', 'label' => 'Keterangan', 'sortable' => false],
                    ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new presensi record - Simplified
     */
    public function store(Request $request)
    {
        try {
            // Simplified validation - hanya field yang diperlukan
            $validator = Validator::make($request->all(), [
                'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id',
                'tanggal_absensi' => 'required|date',
                'jam_masuk' => 'nullable|date_format:H:i',
                'jam_keluar' => 'nullable|date_format:H:i',
                'jenis_kehadiran_id' => 'required|integer|exists:simpeg_jenis_kehadiran,id',
                'keterangan' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();

            // Cek apakah sudah ada record untuk pegawai dan tanggal tersebut
            $existingRecord = SimpegAbsensiRecord::where('pegawai_id', $data['pegawai_id'])
                                                 ->whereDate('tanggal_absensi', $data['tanggal_absensi'])
                                                 ->first();

            if ($existingRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data presensi untuk pegawai ini pada tanggal tersebut sudah ada'
                ], 422);
            }
            
            // Set default values untuk field yang tidak wajib
            $data['status_verifikasi'] = 'verified';
            $data['terlambat'] = false;
            $data['pulang_awal'] = false;

            // Auto-assign default jam kerja jika tidak ada
            if (!isset($data['jam_kerja_id'])) {
                $defaultJamKerja = SimpegJamKerja::where('is_default', true)
                                                ->orWhere('is_active', true)
                                                ->first();
                if ($defaultJamKerja) {
                    $data['jam_kerja_id'] = $defaultJamKerja->id;
                }
            }

            // Generate checksum
            $data['check_sum_absensi'] = $this->generateChecksum($data);

            $presensi = SimpegAbsensiRecord::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Data presensi berhasil ditambahkan',
                'data' => $this->formatPresensiData($presensi->load(['pegawai.unitKerja', 'jamKerja', 'jenisKehadiran']))
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    // Rest of the methods remain the same...
    // (show, update, destroy, batchDestroy, import, etc.)

    /**
     * REMOVED: generateDailyAttendanceRecords method
     * Karena sekarang hanya menampilkan data yang sudah diinput
     */

    /**
     * Get filter options - Updated status_presensi
     */
    private function getFilterOptions()
    {
        return [
            'unit_kerja' => SimpegUnitKerja::select('id', 'kode_unit', 'nama_unit')
                                          ->orderBy('nama_unit')
                                          ->get()
                                          ->map(function($unit) {
                                              return [
                                                  'id' => $unit->id,
                                                  'kode_unit' => $unit->kode_unit,
                                                  'nama_unit' => $unit->nama_unit,
                                                  'value' => $unit->kode_unit,
                                                  'label' => "{$unit->kode_unit} - {$unit->nama_unit}"
                                              ];
                                          }),
            'jenis_kehadiran' => SimpegJenisKehadiran::select('id', 'kode_jenis', 'nama_jenis', 'warna')
                                                    ->orderBy('nama_jenis')
                                                    ->get()
                                                    ->map(function($jenis) {
                                                        return [
                                                            'id' => $jenis->id,
                                                            'kode_jenis' => $jenis->kode_jenis,
                                                            'nama_jenis' => $jenis->nama_jenis,
                                                            'warna' => $jenis->warna,
                                                            'value' => $jenis->id,
                                                            'label' => $jenis->nama_jenis
                                                        ];
                                                    }),
            'status_presensi' => [
                ['value' => '', 'label' => '-- Semua Status Presensi --'],
                ['value' => 'hadir_lengkap', 'label' => 'Hadir Lengkap (Masuk + Keluar)'],
                ['value' => 'hadir_masuk', 'label' => 'Hadir Masuk Saja'],
                ['value' => 'hadir', 'label' => 'Hadir (Ada Jam Masuk)'],
                ['value' => 'terlambat', 'label' => 'Terlambat'],
                ['value' => 'pulang_awal', 'label' => 'Pulang Awal'],
                ['value' => 'alpha', 'label' => 'Alpha'],
                ['value' => 'cuti', 'label' => 'Cuti'],
                ['value' => 'izin', 'label' => 'Izin'],
                ['value' => 'sakit', 'label' => 'Sakit'],
                ['value' => 'dinas', 'label' => 'Dinas'],
                ['value' => 'libur', 'label' => 'Libur']
            ],
            'jam_kerja' => SimpegJamKerja::select('id', 'jenis_jam_kerja')
                                        ->orderBy('jenis_jam_kerja')
                                        ->get()
                                        ->map(function($jam) {
                                            return [
                                                'id' => $jam->id,
                                                'value' => $jam->id,
                                                'label' => $jam->jenis_jam_kerja ?? 'Jam Kerja ' . $jam->id
                                            ];
                                        }),
            'setting_kehadiran' => SimpegSettingKehadiran::select('id', 'nama_gedung')
                                                         ->orderBy('nama_gedung')
                                                         ->get()
                                                         ->map(function($setting) {
                                                             return [
                                                                 'id' => $setting->id,
                                                                 'value' => $setting->id,
                                                                 'label' => $setting->nama_gedung
                                                             ];
                                                         })
        ];
    }

    /**
     * Display the specified presensi
     */
    public function show($id)
    {
        try {
            $presensi = SimpegAbsensiRecord::with([
                'pegawai.unitKerja',
                'pegawai.jabatanAkademik',
                'jamKerja',
                'jenisKehadiran',
                'cutiRecord',
                'izinRecord'
            ])->find($id);

            if (!$presensi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data presensi tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'presensi_detail' => $this->formatDetailPresensi($presensi),
                    'pegawai_info' => $this->formatPegawaiInfo($presensi->pegawai)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified presensi
     */
    public function update(Request $request, $id)
    {
        try {
            $presensi = SimpegAbsensiRecord::find($id);

            if (!$presensi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data presensi tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'jam_masuk' => 'nullable|date_format:H:i',
                'jam_keluar' => 'nullable|date_format:H:i',
                'jenis_kehadiran_id' => 'sometimes|integer|exists:simpeg_jenis_kehadiran,id',
                'keterangan' => 'nullable|string|max:500',
                'jam_kerja_id' => 'nullable|integer|exists:simpeg_jam_kerja,id',
                'setting_kehadiran_id' => 'nullable|integer|exists:simpeg_setting_kehadiran,id',
                'lokasi_masuk' => 'nullable|string|max:255',
                'lokasi_keluar' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();

            // Set additional fields based on status
            $this->setAdditionalFields($data);

            // Update checksum
            $data['check_sum_absensi'] = $this->generateChecksum(array_merge($presensi->toArray(), $data));

            $presensi->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Data presensi berhasil diperbarui',
                'data' => $this->formatPresensiData($presensi->load(['pegawai.unitKerja', 'jamKerja', 'jenisKehadiran']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified presensi
     */
    public function destroy($id)
    {
        try {
            $presensi = SimpegAbsensiRecord::find($id);

            if (!$presensi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data presensi tidak ditemukan'
                ], 404);
            }

            $presensi->delete();

            return response()->json([
                'success' => true,
                'message' => 'Data presensi berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch delete presensi records
     */
    public function batchDestroy(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:simpeg_absensi_record,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $deletedCount = SimpegAbsensiRecord::whereIn('id', $request->ids)->delete();

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data presensi"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import presensi data from Excel
     */
    public function import(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv|max:5120', // 5MB max
                'tanggal_absensi' => 'required|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $tanggal = $request->tanggal_absensi;

            // Process import
            $importResult = $this->processImportFile($file, $tanggal);

            return response()->json([
                'success' => true,
                'message' => 'Import berhasil',
                'result' => $importResult
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat import: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Generate checksum for record
     */
    private function generateChecksum($data)
    {
        $key = ($data['pegawai_id'] ?? '') . '|' . 
               ($data['tanggal_absensi'] ?? '') . '|' . 
               ($data['jam_masuk'] ?? '') . '|' . 
               ($data['jam_keluar'] ?? '');
        return md5($key);
    }

    /**
     * Set additional fields based on status
     */
    private function setAdditionalFields(&$data)
    {
        // Set status verifikasi default
        $data['status_verifikasi'] = $data['status_verifikasi'] ?? 'verified';
        
        // Set terlambat dan pulang_awal jika ada jam masuk/keluar
        if (isset($data['jam_masuk']) && $data['jam_masuk']) {
            // Logic untuk menentukan terlambat berdasarkan jam kerja
            // Untuk sementara set false, bisa disesuaikan dengan business logic
            $data['terlambat'] = $data['terlambat'] ?? false;
        }
        
        if (isset($data['jam_keluar']) && $data['jam_keluar']) {
            // Logic untuk menentukan pulang awal berdasarkan jam kerja
            $data['pulang_awal'] = $data['pulang_awal'] ?? false;
        }
    }

    /**
     * Format presensi data untuk tabel
     */
    private function formatPresensiData($presensi)
    {
        // Get status dari jenis kehadiran atau kondisi
        $status = $this->getStatusFromRecord($presensi);
        
        return [
            'id' => $presensi->id,
            'nip' => $presensi->pegawai->nip ?? '-',
            'nama_pegawai' => $presensi->pegawai->nama ?? '-',
            'unit_kerja' => $presensi->pegawai->unitKerja->nama_unit ?? '-',
            'status' => $status['label'],
            'status_color' => $status['color'],
            'jam_datang' => $presensi->jam_masuk ? Carbon::parse($presensi->jam_masuk)->format('H:i') : '',
            'jam_pulang' => $presensi->jam_keluar ? Carbon::parse($presensi->jam_keluar)->format('H:i') : '',
            'keterangan' => $presensi->keterangan ?? '',
            'terlambat' => $presensi->terlambat,
            'pulang_awal' => $presensi->pulang_awal,
            'jenis_kehadiran' => $presensi->jenisKehadiran ? [
                'id' => $presensi->jenisKehadiran->id,
                'kode' => $presensi->jenisKehadiran->kode_jenis,
                'nama' => $presensi->jenisKehadiran->nama_jenis,
                'warna' => $presensi->jenisKehadiran->warna
            ] : null,
            'actions' => $this->generateActionLinks($presensi)
        ];
    }

    /**
     * Get status from record
     */
    private function getStatusFromRecord($presensi)
    {
        // Prioritas pertama: gunakan jenis kehadiran jika ada
        if ($presensi->jenisKehadiran) {
            return [
                'label' => $presensi->jenisKehadiran->nama_jenis,
                'color' => $presensi->jenisKehadiran->warna ?? 'secondary'
            ];
        }
        
        // Jika ada cuti record
        if ($presensi->cutiRecord) {
            return ['label' => 'Cuti', 'color' => 'info'];
        }
        
        // Jika ada izin record
        if ($presensi->izinRecord) {
            return ['label' => 'Izin', 'color' => 'warning'];
        }
        
        // Berdasarkan kondisi jam masuk/keluar
        if ($presensi->jam_masuk && $presensi->jam_keluar) {
            if ($presensi->terlambat && $presensi->pulang_awal) {
                return ['label' => 'Hadir (Terlambat & Pulang Awal)', 'color' => 'danger'];
            } elseif ($presensi->terlambat) {
                return ['label' => 'Terlambat', 'color' => 'warning'];
            } elseif ($presensi->pulang_awal) {
                return ['label' => 'Pulang Awal', 'color' => 'warning'];
            } else {
                return ['label' => 'Hadir', 'color' => 'success'];
            }
        } elseif ($presensi->jam_masuk) {
            return ['label' => 'Hadir Masuk', 'color' => 'info'];
        } else {
            return ['label' => 'Alpha', 'color' => 'danger'];
        }
    }

    /**
     * Format detail presensi
     */
    private function formatDetailPresensi($presensi)
    {
        return [
            'id' => $presensi->id,
            'tanggal_absensi' => $presensi->tanggal_absensi->format('Y-m-d'),
            'hari' => $presensi->tanggal_absensi->locale('id')->isoFormat('dddd'),
            'jam_masuk' => $presensi->jam_masuk ? Carbon::parse($presensi->jam_masuk)->format('H:i:s') : null,
            'jam_keluar' => $presensi->jam_keluar ? Carbon::parse($presensi->jam_keluar)->format('H:i:s') : null,
            'durasi_kerja' => method_exists($presensi, 'getFormattedWorkingDuration') ? 
                             $presensi->getFormattedWorkingDuration() : '-',
            'lokasi_masuk' => $presensi->lokasi_masuk,
            'lokasi_keluar' => $presensi->lokasi_keluar,
            'terlambat' => $presensi->terlambat,
            'pulang_awal' => $presensi->pulang_awal,
            'status_verifikasi' => $presensi->status_verifikasi,
            'keterangan' => $presensi->keterangan,
            'jenis_kehadiran' => $presensi->jenisKehadiran ? [
                'id' => $presensi->jenisKehadiran->id,
                'kode_jenis' => $presensi->jenisKehadiran->kode_jenis,
                'nama_jenis' => $presensi->jenisKehadiran->nama_jenis,
                'warna' => $presensi->jenisKehadiran->warna
            ] : null,
            'jam_kerja' => $presensi->jamKerja ? [
                'id' => $presensi->jamKerja->id,
                'nama' => $presensi->jamKerja->jenis_jam_kerja ?? 'Jam Kerja ' . $presensi->jamKerja->id
            ] : null,
            'setting_kehadiran' => $presensi->settingKehadiran ? [
                'id' => $presensi->settingKehadiran->id,
                'nama_gedung' => $presensi->settingKehadiran->nama_gedung,
                'latitude' => $presensi->settingKehadiran->latitude,
                'longitude' => $presensi->settingKehadiran->longitude,
                'radius' => $presensi->settingKehadiran->radius
            ] : null
        ];
    }

    /**
     * Format pegawai info
     */
    private function formatPegawaiInfo($pegawai)
    {
        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'nama' => $pegawai->nama,
            'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
            'jabatan_akademik' => isset($pegawai->jabatanAkademik) ? 
                                 $pegawai->jabatanAkademik->jabatan_akademik ?? '-' : '-',
            'email' => $pegawai->email_pegawai ?? $pegawai->email_pribadi ?? '-',
            'no_handphone' => $pegawai->no_handphone ?? '-'
        ];
    }

    /**
     * Generate action links
     */
    private function generateActionLinks($presensi)
    {
        $baseUrl = request()->getSchemeAndHttpHost();
        
        return [
            'edit' => [
                'url' => "{$baseUrl}/api/admin/input-presensi/{$presensi->id}",
                'method' => 'PUT',
                'label' => 'Edit'
            ],
            'delete' => [
                'url' => "{$baseUrl}/api/admin/input-presensi/{$presensi->id}",
                'method' => 'DELETE',
                'label' => 'Hapus',
                'confirm' => true
            ],
            'detail' => [
                'url' => "{$baseUrl}/api/admin/input-presensi/{$presensi->id}",
                'method' => 'GET',
                'label' => 'Detail'
            ]
        ];
    }

    /**
     * Process import file
     */
    private function processImportFile($file, $tanggal)
    {
        // Implementation for Excel/CSV import
        // This would depend on your specific import format requirements
        
        return [
            'total_processed' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'errors' => []
        ];
    }
}