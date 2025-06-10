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
use App\Services\ActivityLogger;

class EvaluasiKinerjaController extends Controller
{
    // Get pegawai yang bisa dievaluasi berdasarkan hierarki jabatan struktural
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $statusFilter = $request->status_filter; // active, inactive, all
        $unitKerjaFilter = $request->unit_kerja_filter;
        $user = auth()->user();

        // Dapatkan jabatan struktural user yang login
        $jabatanStruktural = $this->getUserJabatanStruktural($user->id);
        
        if (!$jabatanStruktural) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki jabatan struktural untuk melakukan evaluasi'
            ], 403);
        }

        // Dapatkan pegawai yang bisa dievaluasi berdasarkan parent-child relationship
        $pegawaiQuery = $this->getPegawaiByParentHierarki($jabatanStruktural);

        // IMPROVED: Better filtering
        if ($search) {
            $pegawaiQuery->where(function($q) use ($search) {
                $q->where('nip', 'like', '%'.$search.'%')
                  ->orWhere('nama', 'like', '%'.$search.'%')
                  ->orWhereHas('unitKerja', function($subQ) use ($search) {
                      $subQ->where('nama_unit', 'like', '%'.$search.'%');
                  });
            });
        }

        // IMPROVED: Status filtering
        if ($statusFilter && $statusFilter !== 'all') {
            if ($statusFilter === 'active') {
                $pegawaiQuery->where('status_kerja', 'Aktif');
            } elseif ($statusFilter === 'inactive') {
                $pegawaiQuery->where('status_kerja', '!=', 'Aktif');
            }
        }

        // IMPROVED: Unit kerja filtering
        if ($unitKerjaFilter) {
            $pegawaiQuery->where('unit_kerja_id', $unitKerjaFilter);
        }

        $pegawai = $pegawaiQuery->with([
            'unitKerja',
            'jabatanAkademik',
            'statusAktif',
            'dataJabatanStruktural' => function($q) {
                $q->whereNull('tgl_selesai')->with('jabatanStruktural');
            },
            'dataJabatanFungsional' => function($q) {
                $q->whereNull('tgl_selesai')->with('jabatanFungsional');
            },
            'evaluasiKinerja' => function($q) use ($user) {
                $q->where('penilai_id', $user->id)
                  ->where('periode_tahun', date('Y'));
            }
        ])->paginate($perPage);

        // IMPROVED: Get available filters
        $availableUnitKerja = $this->getAvailableUnitKerjaForEvaluator($jabatanStruktural);

        return response()->json([
            'success' => true,
            'data' => $pegawai->map(function ($item) {
                return $this->formatPegawaiForEvaluasi($item);
            }),
            'pagination' => [
                'current_page' => $pegawai->currentPage(),
                'per_page' => $pegawai->perPage(),
                'total' => $pegawai->total(),
                'last_page' => $pegawai->lastPage()
            ],
            'jabatan_penilai' => [
                'kode' => $jabatanStruktural->kode,
                'nama' => $jabatanStruktural->singkatan ?? $jabatanStruktural->kode,
                'unit_kerja' => $jabatanStruktural->unitKerja->nama_unit ?? '-',
                'level' => $this->getJabatanLevel($jabatanStruktural)
            ],
            'filters' => [
                'available_unit_kerja' => $availableUnitKerja,
                'status_options' => [
                    ['value' => 'all', 'label' => 'Semua Status'],
                    ['value' => 'active', 'label' => 'Aktif'],
                    ['value' => 'inactive', 'label' => 'Tidak Aktif']
                ]
            ]
        ]);
    }

    // IMPROVED: Get detail evaluasi kinerja pegawai dengan pendidikan info
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
                $q->whereNull('tgl_selesai')->with('jabatanFungsional');
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
                'pegawai' => $this->formatDetailPegawaiForEvaluasi($pegawai),
                'riwayat_evaluasi' => $pegawai->evaluasiKinerja->map(function($eval) {
                    return $this->formatEvaluasiKinerja($eval);
                }),
                'evaluation_periods' => $this->getAvailableEvaluationPeriods()
            ]
        ]);
    }

    // IMPROVED: Create evaluasi kinerja with better validation
    public function store(Request $request)
    {
        $user = auth()->user();
        
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'jenis_kinerja' => 'required|in:dosen,pegawai',
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
            'catatan' => 'nullable|string|max:1000'
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
            
            // Set atasan penilai berdasarkan hierarki
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

            ActivityLogger::log('create', $evaluasi, $evaluasi->toArray());
            
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

    // Update evaluasi kinerja
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
            'jenis_kinerja' => 'sometimes|in:dosen,pegawai',
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
            'catatan' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldData = $evaluasi->getOriginal();
            $data = $request->all();

            // Hitung ulang total nilai jika ada perubahan pada nilai
            if ($request->hasAny(['nilai_kehadiran', 'nilai_pendidikan', 'nilai_penelitian', 'nilai_pengabdian', 'nilai_penunjang1', 'nilai_penunjang2', 'nilai_penunjang3', 'nilai_penunjang4'])) {
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

            ActivityLogger::log('update', $evaluasi, $oldData);
            
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

    // Delete evaluasi kinerja
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
            $oldData = $evaluasi->toArray();
            $evaluasi->delete();

            ActivityLogger::log('delete', $evaluasi, $oldData);
            
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

    // Helper Methods

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
     * IMPROVED: Dapatkan pegawai berdasarkan parent-child relationship dengan support untuk multiple levels
     */
    private function getPegawaiByParentHierarki($jabatanStruktural)
    {
        // Untuk Rektor - bisa evaluasi semua yang punya jabatan struktural tertentu (level dekan dst)
        if ($this->isRektorLevel($jabatanStruktural)) {
            return $this->getPegawaiForRektor();
        }

        // Untuk Dekan - bisa evaluasi semua pegawai di fakultasnya
        if ($this->isDekanLevel($jabatanStruktural)) {
            return $this->getPegawaiForDekan($jabatanStruktural);
        }

        // Default: parent-child relationship
        $childJabatanIds = SimpegJabatanStruktural::where('parent_jabatan', $jabatanStruktural->kode)
            ->pluck('id')
            ->toArray();

        if (empty($childJabatanIds)) {
            // Jika tidak ada child, return query kosong
            return SimpegPegawai::whereRaw('1 = 0');
        }

        // Cari pegawai yang punya jabatan struktural sesuai childJabatanIds
        $query = SimpegPegawai::whereHas('dataJabatanStruktural', function($q) use ($childJabatanIds) {
            $q->whereIn('jabatan_struktural_id', $childJabatanIds)
              ->whereNull('tgl_selesai'); // Jabatan aktif
        });

        return $query;
    }

    /**
     * IMPROVED: Khusus untuk Rektor - evaluasi level Dekan dan sejenisnya
     */
    private function getPegawaiForRektor()
    {
        // Rektor bisa evaluasi Dekan, Direktur Pascasarjana, Kepala Lembaga, dll
        $targetJabatanKodes = ['052', '070', '029', '034', '040']; // Dekan, Direktur Pascasarjana, dst
        
        $jabatanIds = SimpegJabatanStruktural::whereIn('kode', $targetJabatanKodes)
            ->pluck('id')
            ->toArray();

        if (empty($jabatanIds)) {
            return SimpegPegawai::whereRaw('1 = 0');
        }

        return SimpegPegawai::whereHas('dataJabatanStruktural', function($q) use ($jabatanIds) {
            $q->whereIn('jabatan_struktural_id', $jabatanIds)
              ->whereNull('tgl_selesai');
        });
    }

    /**
     * IMPROVED: Khusus untuk Dekan - evaluasi semua pegawai di fakultas
     */
    private function getPegawaiForDekan($jabatanStruktural)
    {
        // Dekan bisa evaluasi semua pegawai di unit kerja fakultasnya
        $unitKerjaId = $jabatanStruktural->unit_kerja_id;
        
        // Ambil semua unit kerja anak (prodi, dll) di bawah fakultas
        $childUnitIds = SimpegUnitKerja::where('parent_unit_id', $unitKerjaId)
            ->pluck('id')
            ->toArray();
        
        $allUnitIds = array_merge([$unitKerjaId], $childUnitIds);

        return SimpegPegawai::whereIn('unit_kerja_id', $allUnitIds);
    }

    /**
     * IMPROVED: Helper untuk menentukan level jabatan
     */
    private function isRektorLevel($jabatan)
    {
        return in_array($jabatan->kode, ['001']); // Rektor
    }

    private function isDekanLevel($jabatan)
    {
        return in_array($jabatan->kode, ['052']); // Dekan
    }

    private function getJabatanLevel($jabatan)
    {
        if ($this->isRektorLevel($jabatan)) return 'universitas';
        if ($this->isDekanLevel($jabatan)) return 'fakultas';
        return 'unit';
    }

    /**
     * Cek apakah user bisa mengevaluasi pegawai tertentu
     */
    private function canEvaluatePegawai($jabatanStruktural, $pegawai)
    {
        if (!$jabatanStruktural) return false;

        $pegawaiQuery = $this->getPegawaiByParentHierarki($jabatanStruktural);
        return $pegawaiQuery->where('id', $pegawai->id)->exists();
    }

    /**
     * Dapatkan atasan penilai berdasarkan hierarki
     */
    private function getAtasanPenilai($jabatanStruktural)
    {
        if (!$jabatanStruktural->parent_jabatan) {
            return null; // Tidak ada atasan (sudah paling atas)
        }

        // Cari jabatan parent
        $parentJabatan = SimpegJabatanStruktural::where('kode', $jabatanStruktural->parent_jabatan)->first();
        if (!$parentJabatan) return null;

        // Cari pegawai yang punya jabatan parent dan masih aktif
        $parentData = SimpegDataJabatanStruktural::where('jabatan_struktural_id', $parentJabatan->id)
            ->whereNull('tgl_selesai')
            ->first();

        return $parentData ? $parentData->pegawai_id : null;
    }

    /**
     * IMPROVED: Helper method untuk menentukan sebutan total dengan lebih detail
     */
    private function getSebutanTotal($totalNilai)
    {
        if ($totalNilai >= 95) {
            return 'Sangat Baik Sekali';
        } elseif ($totalNilai >= 90) {
            return 'Sangat Baik';
        } elseif ($totalNilai >= 80) {
            return 'Baik';
        } elseif ($totalNilai >= 70) {
            return 'Cukup';
        } elseif ($totalNilai >= 60) {
            return 'Kurang';
        } else {
            return 'Sangat Kurang';
        }
    }

    /**
     * IMPROVED: Format pegawai untuk evaluasi dengan lebih lengkap
     */
    private function formatPegawaiForEvaluasi($pegawai)
    {
        $currentEvaluasi = $pegawai->evaluasiKinerja->first();
        $jabatanStruktural = $pegawai->dataJabatanStruktural->first();
        $jabatanFungsional = $pegawai->dataJabatanFungsional->first();

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'nama' => $pegawai->nama,
            'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
            'hubungan_kerja' => $pegawai->status_kerja ?? '-',
            'jabatan_akademik' => $pegawai->jabatanAkademik->jabatan_akademik ?? '-',
            'jabatan_fungsional' => $jabatanFungsional ? $jabatanFungsional->jabatanFungsional->nama_jabatan_fungsional : '-',
            'jabatan_struktural' => $jabatanStruktural ? $jabatanStruktural->jabatanStruktural->singkatan : '-',
            'status_aktif' => $pegawai->statusAktif->nama_status_aktif ?? '-',
            'has_evaluation' => $currentEvaluasi ? true : false,
            'current_evaluation' => $currentEvaluasi ? [
                'id' => $currentEvaluasi->id,
                'total_nilai' => $currentEvaluasi->total_nilai,
                'sebutan_total' => $currentEvaluasi->sebutan_total,
                'periode_tahun' => $currentEvaluasi->periode_tahun
            ] : null
        ];
    }

    /**
     * IMPROVED: Format detail pegawai dengan info pendidikan
     */
    private function formatDetailPegawaiForEvaluasi($pegawai)
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
                'id' => $pegawai->unitKerja->id ?? null,
                'nama' => $pegawai->unitKerja->nama_unit ?? '-',
                'kode' => $pegawai->unitKerja->kode_unit ?? '-'
            ],
            'hubungan_kerja' => $pegawai->status_kerja,
            'jabatan_akademik' => [
                'id' => $pegawai->jabatanAkademik->id ?? null,
                'nama' => $pegawai->jabatanAkademik->jabatan_akademik ?? '-',
                'role' => $pegawai->jabatanAkademik->role->nama ?? '-'
            ],
            'jabatan_fungsional' => $jabatanFungsional ? [
                'id' => $jabatanFungsional->jabatanFungsional->id,
                'nama' => $jabatanFungsional->jabatanFungsional->nama_jabatan_fungsional,
                'pangkat' => $jabatanFungsional->jabatanFungsional->pangkat
            ] : null,
            'jabatan_struktural' => $jabatanStruktural ? [
                'id' => $jabatanStruktural->jabatanStruktural->id,
                'kode' => $jabatanStruktural->jabatanStruktural->kode,
                'nama' => $jabatanStruktural->jabatanStruktural->singkatan
            ] : null,
            'status' => [
                'kerja' => $pegawai->status_kerja,
                'aktif' => $pegawai->statusAktif->nama_status_aktif ?? '-'
            ],
            'personal' => [
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
            'pegawai' => [
                'nip' => $evaluasi->pegawai->nip ?? null,
                'nama' => $evaluasi->pegawai->nama ?? null,
                'unit_kerja' => $evaluasi->pegawai->unitKerja->nama_unit ?? null,
                'jabatan_akademik' => $evaluasi->pegawai->jabatanAkademik->jabatan_akademik ?? null
            ],
            'penilai_id' => $evaluasi->penilai_id,
            'penilai' => [
                'nip' => $evaluasi->penilai->nip ?? null,
                'nama' => $evaluasi->penilai->nama ?? null
            ],
            'atasan_penilai_id' => $evaluasi->atasan_penilai_id,
            'atasan_penilai' => [
                'nip' => $evaluasi->atasanPenilai->nip ?? null,
                'nama' => $evaluasi->atasanPenilai->nama ?? null
            ],
            'jenis_kinerja' => $evaluasi->jenis_kinerja,
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
            'catatan' => $evaluasi->catatan ?? null,
            'tgl_input' => $evaluasi->tgl_input,
            'created_at' => $evaluasi->created_at,
            'updated_at' => $evaluasi->updated_at
        ];
    }

    /**
     * IMPROVED: Get available unit kerja for evaluator
     */
    private function getAvailableUnitKerjaForEvaluator($jabatanStruktural)
    {
        $pegawaiQuery = $this->getPegawaiByParentHierarki($jabatanStruktural);
        
        return $pegawaiQuery->with('unitKerja')
            ->get()
            ->pluck('unitKerja')
            ->unique('id')
            ->map(function($unit) {
                return [
                    'id' => $unit->id,
                    'nama' => $unit->nama_unit,
                    'kode' => $unit->kode_unit
                ];
            })
            ->values();
    }

    /**
     * IMPROVED: Get available evaluation periods
     */
    private function getAvailableEvaluationPeriods()
    {
        $currentYear = date('Y');
        $periods = [];
        
        for ($i = 0; $i < 5; $i++) {
            $year = $currentYear - $i;
            $periods[] = [
                'value' => (string)$year,
                'label' => 'Periode ' . $year
            ];
        }
        
        return $periods;
    }

    /**
     * Method untuk debugging - melihat hierarki jabatan struktural
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

        // Dapatkan child jabatan
        $childJabatan = SimpegJabatanStruktural::where('parent_jabatan', $jabatanStruktural->kode)
            ->with('unitKerja')
            ->get();

        // Dapatkan pegawai yang bisa dievaluasi
        $pegawai = $this->getPegawaiByParentHierarki($jabatanStruktural)
            ->with(['dataJabatanStruktural.jabatanStruktural'])
            ->get();

        return response()->json([
            'success' => true,
            'jabatan_penilai' => [
                'kode' => $jabatanStruktural->kode,
                'singkatan' => $jabatanStruktural->singkatan,
                'parent_jabatan' => $jabatanStruktural->parent_jabatan,
                'level' => $this->getJabatanLevel($jabatanStruktural)
            ],
            'child_jabatan' => $childJabatan->map(function($item) {
                return [
                    'kode' => $item->kode,
                    'singkatan' => $item->singkatan,
                    'unit_kerja' => $item->unitKerja->nama_unit ?? '-'
                ];
            }),
            'pegawai_dapat_dievaluasi' => $pegawai->map(function($item) {
                $jabatan = $item->dataJabatanStruktural->first();
                return [
                    'nip' => $item->nip,
                    'nama' => $item->nama,
                    'jabatan_struktural' => $jabatan ? $jabatan->jabatanStruktural->singkatan : '-',
                    'unit_kerja' => $item->unitKerja->nama_unit ?? '-'
                ];
            })
        ]);
    }
}