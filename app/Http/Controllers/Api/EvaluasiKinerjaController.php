<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegEvaluasiKinerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegDataJabatanStruktural;
use App\Models\SimpegJabatanStruktural;
use App\Models\SimpegUnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EvaluasiKinerjaController extends Controller
{
    /**
     * Get pegawai yang bisa dievaluasi berdasarkan hierarki jabatan struktural
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $statusFilter = $request->status_filter; // active, inactive, all
        $user = auth()->user();

        // Dapatkan jabatan struktural user yang login
        $jabatanStruktural = $this->getUserJabatanStruktural($user->id);
        
        if (!$jabatanStruktural) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki jabatan struktural untuk melakukan evaluasi'
            ], 403);
        }

        // Dapatkan pegawai yang bisa dievaluasi berdasarkan hierarki
        $pegawaiQuery = $this->getPegawaiByHierarki($jabatanStruktural);

        // Filter by search (NIP atau Nama)
        if ($search) {
            $pegawaiQuery->where(function($q) use ($search) {
                $q->where('nip', 'like', '%'.$search.'%')
                  ->orWhere('nama', 'like', '%'.$search.'%');
            });
        }

        // Filter by status
        if ($statusFilter && $statusFilter !== 'all') {
            if ($statusFilter === 'active') {
                $pegawaiQuery->where('status_kerja', 'Aktif');
            } elseif ($statusFilter === 'inactive') {
                $pegawaiQuery->where('status_kerja', '!=', 'Aktif');
            }
        }

        // Load relasi yang diperlukan
        $pegawai = $pegawaiQuery->with([
            'unitKerja',
            'jabatanAkademik', 
            'statusAktif',
            'dataJabatanStruktural' => function($q) {
                $q->whereNull('tgl_selesai')->with('jabatanStruktural');
            },
            'dataJabatanFungsional' => function($q) {
                $q->with('jabatanFungsional')->latest('tmt_jabatan');
            },
            'evaluasiKinerja' => function($q) use ($user) {
                $q->where('penilai_id', $user->id)
                  ->where('periode_tahun', date('Y'));
            }
        ])->paginate($perPage);

        return response()->json([
            'success' => true,
            // Info evaluator (user yang login) - ditampilkan di atas tabel
            'evaluator' => $this->formatEvaluatorInfo($user, $jabatanStruktural),
            // Data pegawai untuk tabel
            'pegawai_list' => $pegawai->map(function ($item) {
                return $this->formatPegawaiForTable($item);
            }),
            'pagination' => [
                'current_page' => $pegawai->currentPage(),
                'per_page' => $pegawai->perPage(),
                'total' => $pegawai->total(),
                'last_page' => $pegawai->lastPage()
            ],
            'summary' => [
                'total_pegawai' => $pegawai->total(),
                'level_evaluasi' => $this->getEvaluationLevel($jabatanStruktural),
                'periode_aktif' => date('Y')
            ]
        ]);
    }

    /**
     * Get detail pegawai untuk evaluasi
     */
    public function show($pegawaiId)
    {
        $user = auth()->user();
        $pegawai = SimpegPegawai::with([
            'unitKerja',
            'jabatanAkademik.role',
            'statusAktif',
            'dataJabatanStruktural' => function($q) {
                $q->whereNull('tgl_selesai')->with('jabatanStruktural');
            },
            'dataJabatanFungsional' => function($q) {
                $q->with('jabatanFungsional')->latest('tmt_jabatan');
            },
            'evaluasiKinerja' => function($q) use ($user) {
                $q->where('penilai_id', $user->id)
                  ->orderBy('periode_tahun', 'desc');
            }
        ])->find($pegawaiId);

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan'
            ], 404);
        }

        // Cek apakah user berhak mengevaluasi pegawai ini
        $jabatanStruktural = $this->getUserJabatanStruktural($user->id);
        if (!$this->canEvaluatePegawai($jabatanStruktural, $pegawai)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak berhak mengevaluasi pegawai ini'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $this->formatDetailPegawai($pegawai),
                'riwayat_evaluasi' => $pegawai->evaluasiKinerja->map(function($eval) {
                    return $this->formatEvaluasiKinerja($eval);
                }),
                'available_actions' => $this->generateActionLinks($pegawai, $pegawai->evaluasiKinerja->first())
            ]
        ]);
    }

    /**
     * Create evaluasi kinerja baru
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'periode_tahun' => 'required|string|max:10',
            'tanggal_penilaian' => 'required|date|before_or_equal:today',
            'nilai_kehadiran' => 'nullable|numeric|min:0|max:100',
            'nilai_pendidikan' => 'nullable|numeric|min:0|max:100',
            'nilai_penelitian' => 'nullable|numeric|min:0|max:100',
            'nilai_pengabdian' => 'nullable|numeric|min:0|max:100',
            'nilai_penunjang1' => 'required|numeric|min:0|max:100',
            'nilai_penunjang2' => 'required|numeric|min:0|max:100',
            'nilai_penunjang3' => 'required|numeric|min:0|max:100',
            'nilai_penunjang4' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah user berhak mengevaluasi pegawai ini
        $pegawai = SimpegPegawai::find($request->pegawai_id);
        $jabatanStruktural = $this->getUserJabatanStruktural($user->id);
        
        if (!$this->canEvaluatePegawai($jabatanStruktural, $pegawai)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak berhak mengevaluasi pegawai ini'
            ], 403);
        }

        // Cek apakah sudah ada evaluasi untuk periode ini
        $existingEvaluasi = SimpegEvaluasiKinerja::where('pegawai_id', $request->pegawai_id)
            ->where('penilai_id', $user->id)
            ->where('periode_tahun', $request->periode_tahun)
            ->first();

        if ($existingEvaluasi) {
            return response()->json([
                'success' => false,
                'message' => 'Evaluasi untuk pegawai ini pada periode ' . $request->periode_tahun . ' sudah ada'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $request->all();
            $data['penilai_id'] = $user->id;
            $data['atasan_penilai_id'] = $this->getAtasanPenilai($jabatanStruktural);

            // Hitung total nilai
            $totalNilai = ($data['nilai_kehadiran'] ?? 0) + 
                         ($data['nilai_pendidikan'] ?? 0) + 
                         ($data['nilai_penelitian'] ?? 0) + 
                         ($data['nilai_pengabdian'] ?? 0) + 
                         $data['nilai_penunjang1'] + 
                         $data['nilai_penunjang2'] + 
                         $data['nilai_penunjang3'] + 
                         $data['nilai_penunjang4'];

            $data['total_nilai'] = $totalNilai;
            $data['sebutan_total'] = $this->getSebutanTotal($totalNilai);
            $data['tgl_input'] = now()->toDateString();

            $evaluasi = SimpegEvaluasiKinerja::create($data);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatEvaluasiKinerja($evaluasi),
                'message' => 'Evaluasi kinerja berhasil ditambahkan'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan evaluasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update evaluasi kinerja
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        
        $evaluasi = SimpegEvaluasiKinerja::where('penilai_id', $user->id)->find($id);

        if (!$evaluasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data evaluasi kinerja tidak ditemukan atau Anda tidak berhak mengubahnya'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'periode_tahun' => 'sometimes|string|max:10',
            'tanggal_penilaian' => 'sometimes|date|before_or_equal:today',
            'nilai_kehadiran' => 'nullable|numeric|min:0|max:100',
            'nilai_pendidikan' => 'nullable|numeric|min:0|max:100',
            'nilai_penelitian' => 'nullable|numeric|min:0|max:100',
            'nilai_pengabdian' => 'nullable|numeric|min:0|max:100',
            'nilai_penunjang1' => 'sometimes|numeric|min:0|max:100',
            'nilai_penunjang2' => 'sometimes|numeric|min:0|max:100',
            'nilai_penunjang3' => 'sometimes|numeric|min:0|max:100',
            'nilai_penunjang4' => 'sometimes|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $request->all();

            // Hitung ulang total nilai jika ada perubahan nilai
            $nilaiFields = ['nilai_kehadiran', 'nilai_pendidikan', 'nilai_penelitian', 'nilai_pengabdian', 'nilai_penunjang1', 'nilai_penunjang2', 'nilai_penunjang3', 'nilai_penunjang4'];
            
            if ($request->hasAny($nilaiFields)) {
                $totalNilai = ($data['nilai_kehadiran'] ?? $evaluasi->nilai_kehadiran ?? 0) + 
                             ($data['nilai_pendidikan'] ?? $evaluasi->nilai_pendidikan ?? 0) + 
                             ($data['nilai_penelitian'] ?? $evaluasi->nilai_penelitian ?? 0) + 
                             ($data['nilai_pengabdian'] ?? $evaluasi->nilai_pengabdian ?? 0) + 
                             ($data['nilai_penunjang1'] ?? $evaluasi->nilai_penunjang1) + 
                             ($data['nilai_penunjang2'] ?? $evaluasi->nilai_penunjang2) + 
                             ($data['nilai_penunjang3'] ?? $evaluasi->nilai_penunjang3) + 
                             ($data['nilai_penunjang4'] ?? $evaluasi->nilai_penunjang4);

                $data['total_nilai'] = $totalNilai;
                $data['sebutan_total'] = $this->getSebutanTotal($totalNilai);
            }

            $evaluasi->update($data);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatEvaluasiKinerja($evaluasi),
                'message' => 'Evaluasi kinerja berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui evaluasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete evaluasi kinerja
     */
    public function destroy($id)
    {
        $user = auth()->user();
        
        $evaluasi = SimpegEvaluasiKinerja::where('penilai_id', $user->id)->find($id);

        if (!$evaluasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data evaluasi kinerja tidak ditemukan atau Anda tidak berhak menghapusnya'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $evaluasi->delete();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Evaluasi kinerja berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus evaluasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get evaluasi kinerja yang sudah ada untuk periode tertentu
     */
    public function getEvaluasiByPeriode(Request $request)
    {
        $user = auth()->user();
        $periode = $request->periode ?? date('Y');
        
        $evaluasiList = SimpegEvaluasiKinerja::where('penilai_id', $user->id)
            ->where('periode_tahun', $periode)
            ->with(['pegawai.unitKerja', 'pegawai.jabatanAkademik'])
            ->get();

        return response()->json([
            'success' => true,
            'periode' => $periode,
            'total_evaluasi' => $evaluasiList->count(),
            'data' => $evaluasiList->map(function($eval) {
                return $this->formatEvaluasiKinerja($eval);
            })
        ]);
    }

    /**
     * Export data pegawai untuk evaluasi (untuk keperluan laporan)
     */
    public function exportPegawaiList(Request $request)
    {
        $user = auth()->user();
        $jabatanStruktural = $this->getUserJabatanStruktural($user->id);
        
        if (!$jabatanStruktural) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki jabatan struktural untuk melakukan evaluasi'
            ], 403);
        }

        $pegawai = $this->getPegawaiByHierarki($jabatanStruktural)
            ->with([
                'unitKerja',
                'jabatanAkademik',
                'statusAktif',
                'dataJabatanStruktural' => function($q) {
                    $q->whereNull('tgl_selesai')->with('jabatanStruktural');
                },
                'dataJabatanFungsional' => function($q) {
                    $q->with('jabatanFungsional')->latest('tmt_jabatan');
                },
                'evaluasiKinerja' => function($q) use ($user) {
                    $q->where('penilai_id', $user->id)
                      ->where('periode_tahun', date('Y'));
                }
            ])
            ->get();

        return response()->json([
            'success' => true,
            'evaluator' => $this->formatEvaluatorInfo($user, $jabatanStruktural),
            'export_data' => [
                'periode' => date('Y'),
                'tanggal_export' => now()->format('Y-m-d H:i:s'),
                'total_pegawai' => $pegawai->count(),
                'pegawai_list' => $pegawai->map(function($item) {
                    return $this->formatPegawaiForTable($item);
                })
            ]
        ]);
    }

    /**
     * Debug method untuk melihat hierarki dan testing
     */
    public function debugHierarki(Request $request)
    {
        $user = auth()->user();
        $jabatanStruktural = $this->getUserJabatanStruktural($user->id);
        
        if (!$jabatanStruktural) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak memiliki jabatan struktural'
            ]);
        }

        $pegawai = $this->getPegawaiByHierarki($jabatanStruktural)->get();

        return response()->json([
            'success' => true,
            'evaluator_info' => $this->formatEvaluatorInfo($user, $jabatanStruktural),
            'hierarki_info' => [
                'kode_jabatan' => $jabatanStruktural->kode,
                'nama_jabatan' => $jabatanStruktural->singkatan,
                'level_evaluasi' => $this->getEvaluationLevel($jabatanStruktural),
                'unit_kerja' => $jabatanStruktural->unitKerja->nama_unit ?? '-'
            ],
            'statistik' => [
                'total_pegawai_dapat_dievaluasi' => $pegawai->count(),
                'total_dosen' => $pegawai->filter(function($p) {
                    return in_array($p->jabatanAkademik->jabatan_akademik ?? '', ['Guru Besar', 'Lektor Kepala', 'Lektor', 'Asisten Ahli', 'Tenaga Pengajar']);
                })->count(),
                'total_tendik' => $pegawai->filter(function($p) {
                    return in_array($p->jabatanAkademik->jabatan_akademik ?? '', ['Laboran', 'Administrasi', 'Pustakawan', 'Teknisi']);
                })->count()
            ],
            'sample_pegawai' => $pegawai->take(10)->map(function($item) {
                $jabatanFungsional = $item->dataJabatanFungsional()->latest('tmt_jabatan')->first();
                return [
                    'nip' => $item->nip,
                    'nama' => $item->nama,
                    'unit_kerja' => $item->unitKerja->nama_unit ?? '-',
                    'jabatan_akademik' => $item->jabatanAkademik->jabatan_akademik ?? '-',
                    'fungsional' => $this->determineFungsional($item, $jabatanFungsional)
                ];
            })
        ]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Dapatkan jabatan struktural user yang login
     */
    private function getUserJabatanStruktural($userId)
    {
        $dataJabatan = SimpegDataJabatanStruktural::where('pegawai_id', $userId)
            ->whereNull('tgl_selesai') // Jabatan aktif
            ->with('jabatanStruktural.unitKerja')
            ->first();

        return $dataJabatan ? $dataJabatan->jabatanStruktural : null;
    }

    /**
     * Dapatkan pegawai berdasarkan hierarki jabatan struktural
     */
    private function getPegawaiByHierarki($jabatanStruktural)
    {
        $kodeJabatan = $jabatanStruktural->kode;

        // REKTOR (001) - evaluasi pegawai se universitas yang punya jabatan struktural level Dekan
        if ($kodeJabatan === '001') {
            return $this->getPegawaiForRektor();
        }

        // DEKAN (052) - evaluasi semua pegawai di fakultas
        if ($kodeJabatan === '052') {
            return $this->getPegawaiForDekan($jabatanStruktural);
        }

        // KAPRODI (056) - evaluasi pegawai di prodi
        if ($kodeJabatan === '056') {
            return $this->getPegawaiForKaprodi($jabatanStruktural);
        }

        // Default: parent-child relationship untuk jabatan lainnya
        return $this->getPegawaiByParentChild($jabatanStruktural);
    }

    /**
     * Pegawai yang bisa dievaluasi Rektor - se universitas dengan jabatan struktural level Dekan
     */
    private function getPegawaiForRektor()
    {
        // Rektor mengevaluasi pegawai se universitas yang punya jabatan struktural level Dekan dan sejenisnya
        $targetJabatanKodes = [
            '052', // Dekan
            '070', // Direktur Pascasarjana  
            '029', // Kepala Lembaga Penelitian
            '034', // Kepala UPT Perpustakaan
            '040', // Ketua Kantor Penjaminan Mutu
            '053', // Wakil Dekan Bidang Akademik
            '054', // Wakil Dekan Bidang Pengelolaan Sumberdaya
            '055', // Wakil Dekan Bidang Kemahasiswaan
        ];
        
        $jabatanIds = SimpegJabatanStruktural::whereIn('kode', $targetJabatanKodes)->pluck('id');

        return SimpegPegawai::whereHas('dataJabatanStruktural', function($q) use ($jabatanIds) {
            $q->whereIn('jabatan_struktural_id', $jabatanIds)
              ->whereNull('tgl_selesai');
        });
    }

    /**
     * Pegawai yang bisa dievaluasi Dekan - semua pegawai di fakultas
     */
    private function getPegawaiForDekan($jabatanStruktural)
    {
        // Dekan mengevaluasi semua pegawai di fakultasnya (termasuk prodi-prodi di bawahnya)
        $unitKerjaId = $jabatanStruktural->unit_kerja_id;
        
        // Ambil semua unit kerja anak (prodi) di bawah fakultas
        $childUnitIds = SimpegUnitKerja::where('parent_unit_id', $unitKerjaId)->pluck('id');
        $allUnitIds = $childUnitIds->prepend($unitKerjaId); // Gabung dengan fakultas induk

        return SimpegPegawai::whereIn('unit_kerja_id', $allUnitIds);
    }

    /**
     * Pegawai yang bisa dievaluasi Kaprodi - pegawai di prodi
     */
    private function getPegawaiForKaprodi($jabatanStruktural)
    {
        // Kaprodi mengevaluasi pegawai di prodinya
        $unitKerjaId = $jabatanStruktural->unit_kerja_id;
        
        return SimpegPegawai::where('unit_kerja_id', $unitKerjaId);
    }

    /**
     * Default parent-child relationship untuk jabatan lainnya
     */
    private function getPegawaiByParentChild($jabatanStruktural)
    {
        $childJabatanIds = SimpegJabatanStruktural::where('parent_jabatan', $jabatanStruktural->kode)
            ->pluck('id');

        if ($childJabatanIds->isEmpty()) {
            return SimpegPegawai::whereRaw('1 = 0'); // Query kosong
        }

        return SimpegPegawai::whereHas('dataJabatanStruktural', function($q) use ($childJabatanIds) {
            $q->whereIn('jabatan_struktural_id', $childJabatanIds)
              ->whereNull('tgl_selesai');
        });
    }

    /**
     * Cek apakah user bisa mengevaluasi pegawai tertentu
     */
    private function canEvaluatePegawai($jabatanStruktural, $pegawai)
    {
        if (!$jabatanStruktural) return false;

        $pegawaiQuery = $this->getPegawaiByHierarki($jabatanStruktural);
        return $pegawaiQuery->where('id', $pegawai->id)->exists();
    }

    /**
     * Dapatkan atasan penilai berdasarkan hierarki
     */
    private function getAtasanPenilai($jabatanStruktural)
    {
        if (!$jabatanStruktural->parent_jabatan) {
            return null; // Tidak ada atasan (Rektor)
        }

        $parentJabatan = SimpegJabatanStruktural::where('kode', $jabatanStruktural->parent_jabatan)->first();
        if (!$parentJabatan) return null;

        $parentData = SimpegDataJabatanStruktural::where('jabatan_struktural_id', $parentJabatan->id)
            ->whereNull('tgl_selesai')
            ->first();

        return $parentData ? $parentData->pegawai_id : null;
    }

    /**
     * Tentukan level evaluasi berdasarkan kode jabatan
     */
    private function getEvaluationLevel($jabatan)
    {
        switch ($jabatan->kode) {
            case '001': return 'Universitas'; // Rektor
            case '052': return 'Fakultas';    // Dekan
            case '056': return 'Program Studi'; // Kaprodi
            default: return 'Unit Kerja';
        }
    }

    /**
     * Tentukan sebutan berdasarkan total nilai
     */
    private function getSebutanTotal($totalNilai)
    {
        if ($totalNilai >= 95) return 'Sangat Baik Sekali';
        if ($totalNilai >= 90) return 'Sangat Baik';
        if ($totalNilai >= 80) return 'Baik';
        if ($totalNilai >= 70) return 'Cukup';
        if ($totalNilai >= 60) return 'Kurang';
        return 'Sangat Kurang';
    }

    /**
     * Format info evaluator (user yang login) - ditampilkan di bagian atas
     */
    private function formatEvaluatorInfo($user, $jabatanStruktural)
    {
        $jabatanStrukturalAktif = $user->dataJabatanStruktural()
            ->whereNull('tgl_selesai')
            ->with('jabatanStruktural')
            ->first();

        $jabatanFungsionalAktif = $user->dataJabatanFungsional()
            ->with('jabatanFungsional')
            ->latest('tmt_jabatan')
            ->first();

        return [
            'nip' => $user->nip,
            'nama_lengkap' => $user->nama,
            'unit_kerja' => $user->unitKerja->nama_unit ?? '-',
            'status' => $user->statusAktif->nama_status_aktif ?? $user->status_kerja,
            'jabatan_akademik' => $user->jabatanAkademik->jabatan_akademik ?? '-',
            'jabatan_fungsional' => $jabatanFungsionalAktif ? $jabatanFungsionalAktif->jabatanFungsional->nama_jabatan_fungsional : '-',
            'jabatan_struktural' => $jabatanStruktural->singkatan ?? '-',
            'pendidikan' => $this->getPendidikanTerakhir($user),
            'level_evaluasi' => $this->getEvaluationLevel($jabatanStruktural),
            'periode_evaluasi' => date('Y')
        ];
    }

    /**
     * Format pegawai untuk tabel (sesuai requirement)
     */
    private function formatPegawaiForTable($pegawai)
    {
        $currentEvaluasi = $pegawai->evaluasiKinerja->first();
        $jabatanStruktural = $pegawai->dataJabatanStruktural->first();
        $jabatanFungsional = $pegawai->dataJabatanFungsional->first();

        // Tentukan fungsional berdasarkan jabatan akademik
        $fungsional = $this->determineFungsional($pegawai, $jabatanFungsional);

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'nama_pegawai' => $pegawai->nama,
            'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
            'hubungan_kerja' => $this->getHubunganKerja($pegawai), // Dari status kerja atau data hubungan kerja
            'fungsional' => $fungsional,
            'jabatan_akademik' => $pegawai->jabatanAkademik->jabatan_akademik ?? '-',
            'jabatan_struktural' => $jabatanStruktural ? $jabatanStruktural->jabatanStruktural->singkatan : '-',
            'status_aktif' => $pegawai->statusAktif->nama_status_aktif ?? $pegawai->status_kerja,
            'has_evaluation' => $currentEvaluasi ? true : false,
            'current_evaluation_id' => $currentEvaluasi->id ?? null,
            'actions' => $this->generateActionLinks($pegawai, $currentEvaluasi)
        ];
    }

    /**
     * Generate action links untuk setiap pegawai
     */
    private function generateActionLinks($pegawai, $currentEvaluasi = null)
    {
        $baseUrl = request()->getSchemeAndHttpHost();
        $actions = [];

        // Link untuk melihat detail pegawai
        $actions['detail'] = [
            'url' => "{$baseUrl}/api/dosen/evaluasi-kinerja/pegawai/{$pegawai->id}",
            'label' => 'Detail Pegawai',
            'method' => 'GET',
            'description' => 'Melihat detail informasi pegawai dan riwayat evaluasi'
        ];

        // Link untuk menambah evaluasi baru (jika belum ada evaluasi)
        if (!$currentEvaluasi) {
            $actions['add_evaluation'] = [
                'url' => "{$baseUrl}/api/dosen/evaluasi-kinerja",
                'label' => 'Tambah Evaluasi',
                'method' => 'POST',
                'description' => 'Menambahkan evaluasi kinerja baru untuk pegawai ini',
                'required_data' => [
                    'pegawai_id' => $pegawai->id,
                    'periode_tahun' => date('Y'),
                    'tanggal_penilaian' => date('Y-m-d'),
                    'nilai_kehadiran' => 'numeric (0-100)',
                    'nilai_pendidikan' => 'numeric (0-100)',
                    'nilai_penelitian' => 'numeric (0-100)', 
                    'nilai_pengabdian' => 'numeric (0-100)',
                    'nilai_penunjang1' => 'numeric (0-100)',
                    'nilai_penunjang2' => 'numeric (0-100)',
                    'nilai_penunjang3' => 'numeric (0-100)',
                    'nilai_penunjang4' => 'numeric (0-100)'
                ]
            ];
        }

        // Link untuk edit evaluasi (jika sudah ada evaluasi)
        if ($currentEvaluasi) {
            $actions['edit_evaluation'] = [
                'url' => "{$baseUrl}/api/dosen/evaluasi-kinerja/{$currentEvaluasi->id}",
                'label' => 'Edit Evaluasi',
                'method' => 'PUT',
                'description' => 'Mengedit evaluasi kinerja yang sudah ada'
            ];

            $actions['delete_evaluation'] = [
                'url' => "{$baseUrl}/api/dosen/evaluasi-kinerja/{$currentEvaluasi->id}",
                'label' => 'Hapus Evaluasi',
                'method' => 'DELETE',
                'description' => 'Menghapus evaluasi kinerja'
            ];
        }

        return $actions;
    }

    /**
     * Tentukan fungsional berdasarkan jabatan akademik dan jabatan fungsional
     */
    private function determineFungsional($pegawai, $jabatanFungsional)
    {
        // Jika ada jabatan fungsional, gunakan itu
        if ($jabatanFungsional) {
            return $jabatanFungsional->jabatanFungsional->nama_jabatan_fungsional;
        }

        // Jika tidak ada, gunakan jabatan akademik sebagai basis
        $jabatanAkademik = $pegawai->jabatanAkademik->jabatan_akademik ?? '';

        // Mapping jabatan akademik ke fungsional yang sesuai
        switch ($jabatanAkademik) {
            case 'Guru Besar':
            case 'Lektor Kepala':
            case 'Lektor':
            case 'Asisten Ahli':
            case 'Tenaga Pengajar':
                return 'Dosen';
            
            case 'Laboran':
                return 'Laboran';
            
            case 'Administrasi':
                return 'Staff';
            
            case 'Pustakawan':
                return 'Pustakawan';
            
            case 'Teknisi':
                return 'Teknisi';
            
            default:
                return 'Staff';
        }
    }

    /**
     * Dapatkan hubungan kerja pegawai
     */
    private function getHubunganKerja($pegawai)
    {
        // Cek apakah ada data hubungan kerja (ambil yang terbaru berdasarkan created_at)
        $hubunganKerjaAktif = $pegawai->dataHubunganKerja()
            ->with('hubunganKerja')
            ->latest('created_at')
            ->first();

        if ($hubunganKerjaAktif) {
            return $hubunganKerjaAktif->hubunganKerja->nama_hub_kerja;
        }

        // Jika tidak ada, gunakan default berdasarkan status
        if ($pegawai->status_kerja === 'Aktif') {
            // Asumsi default untuk pegawai aktif
            return 'Tetap Yayasan Karyawan';
        }

        return $pegawai->status_kerja ?? 'Tidak Diketahui';
    }

    /**
     * Dapatkan pendidikan terakhir pegawai
     */
    private function getPendidikanTerakhir($pegawai)
    {
        // Cek apakah ada data pendidikan formal
        $pendidikanTerakhir = $pegawai->dataPendidikanFormal()
            ->orderBy('tahun_lulus', 'desc')
            ->first();

        if ($pendidikanTerakhir) {
            return $pendidikanTerakhir->jenjang . ' ' . $pendidikanTerakhir->program_studi;
        }

        // Jika tidak ada, coba dari gelar
        $gelar = [];
        if ($pegawai->gelar_depan) $gelar[] = $pegawai->gelar_depan;
        if ($pegawai->gelar_belakang) $gelar[] = $pegawai->gelar_belakang;

        if (!empty($gelar)) {
            return implode(' ', $gelar);
        }

        return 'Tidak Diketahui';
    }

    /**
     * Format detail pegawai
     */
    private function formatDetailPegawai($pegawai)
    {
        $jabatanStruktural = $pegawai->dataJabatanStruktural->first();
        $jabatanFungsional = $pegawai->dataJabatanFungsional->first();

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'nama' => $pegawai->nama,
            'gelar_depan' => $pegawai->gelar_depan,
            'gelar_belakang' => $pegawai->gelar_belakang,
            'unit_kerja' => [
                'nama' => $pegawai->unitKerja->nama_unit ?? '-',
                'kode' => $pegawai->unitKerja->kode_unit ?? '-'
            ],
            'hubungan_kerja' => $pegawai->status_kerja,
            'jabatan_akademik' => [
                'nama' => $pegawai->jabatanAkademik->jabatan_akademik ?? '-',
                'role' => $pegawai->jabatanAkademik->role->nama ?? '-'
            ],
            'jabatan_fungsional' => $jabatanFungsional ? [
                'nama' => $jabatanFungsional->jabatanFungsional->nama_jabatan_fungsional,
                'pangkat' => $jabatanFungsional->jabatanFungsional->pangkat ?? '-'
            ] : null,
            'jabatan_struktural' => $jabatanStruktural ? [
                'kode' => $jabatanStruktural->jabatanStruktural->kode,
                'nama' => $jabatanStruktural->jabatanStruktural->singkatan
            ] : null,
            'pendidikan' => [
                'tempat_lahir' => $pegawai->tempat_lahir,
                'tanggal_lahir' => $pegawai->tanggal_lahir,
                'jenis_kelamin' => $pegawai->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan',
                'agama' => $pegawai->agama,
                'email' => $pegawai->email_pribadi
            ]
        ];
    }

    /**
     * Format evaluasi kinerja
     */
    private function formatEvaluasiKinerja($evaluasi)
    {
        return [
            'id' => $evaluasi->id,
            'pegawai_id' => $evaluasi->pegawai_id,
            'penilai_id' => $evaluasi->penilai_id,
            'atasan_penilai_id' => $evaluasi->atasan_penilai_id,
            'periode_tahun' => $evaluasi->periode_tahun,
            'tanggal_penilaian' => $evaluasi->tanggal_penilaian,
            'nilai' => [
                'kehadiran' => $evaluasi->nilai_kehadiran,
                'pendidikan' => $evaluasi->nilai_pendidikan,
                'penelitian' => $evaluasi->nilai_penelitian,
                'pengabdian' => $evaluasi->nilai_pengabdian,
                'penunjang1' => $evaluasi->nilai_penunjang1,
                'penunjang2' => $evaluasi->nilai_penunjang2,
                'penunjang3' => $evaluasi->nilai_penunjang3,
                'penunjang4' => $evaluasi->nilai_penunjang4
            ],
            'total_nilai' => $evaluasi->total_nilai,
            'sebutan_total' => $evaluasi->sebutan_total,
            'tgl_input' => $evaluasi->tgl_input,
            'created_at' => $evaluasi->created_at,
            'updated_at' => $evaluasi->updated_at
        ];
    }
}