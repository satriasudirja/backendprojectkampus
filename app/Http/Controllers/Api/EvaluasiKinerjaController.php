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
use Illuminate\Support\Facades\Auth;

class EvaluasiKinerjaController extends Controller
{
    /**
     * Menampilkan daftar pegawai yang dapat dievaluasi oleh pengguna yang login.
     * Termasuk tombol aksi dinamis untuk setiap pegawai.
     */
    public function index(Request $request)
    {
        $user = Auth::user()->pegawai;
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $jabatanStruktural = $this->getUserJabatanStruktural($user->id);
        if (!$jabatanStruktural) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki jabatan struktural yang aktif untuk melakukan evaluasi'], 403);
        }

        $pegawaiQuery = $this->getPegawaiByHierarki($jabatanStruktural, $user->id);

        if ($request->search) {
            $pegawaiQuery->where(function ($q) use ($request) {
                $q->where('nip', 'like', '%' . $request->search . '%')
                  ->orWhere('nama', 'like', '%' . $request->search . '%');
            });
        }
        
        $pegawai = $pegawaiQuery->with([
            'unitKerja',
            'role', // Ubah dari jabatanAkademik.role menjadi role
            'dataJabatanStruktural' => fn($q) => $q->whereNull('tgl_selesai')->with('jabatanStruktural'),
            'evaluasiKinerja' => fn($q) => $q->where('penilai_id', $user->id)->where('periode_tahun', date('Y'))
        ])->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'evaluator' => $this->formatPegawaiInfo($user),
            'data' => $pegawai->map(fn($p) => $this->formatPegawaiForTable($p)),
            'pagination' => [
                'current_page' => $pegawai->currentPage(),
                'per_page' => $pegawai->perPage(),
                'total' => $pegawai->total(),
                'last_page' => $pegawai->lastPage(),
            ],
        ]);
    }

    /**
     * Menampilkan detail seorang pegawai dan data evaluasi yang ada untuk form.
     */
    public function show($pegawaiId)
    {
        $user = Auth::user()->pegawai;
        $pegawai = SimpegPegawai::with([
            'unitKerja',
            'role', // Ubah dari jabatanAkademik.role
            'statusAktif',
            'dataPendidikanFormal.jenjangPendidikan',
            'dataJabatanStruktural' => fn($q) => $q->whereNull('tgl_selesai')->with('jabatanStruktural'),
            'evaluasiKinerja' => fn($q) => $q->where('penilai_id', $user->id)->where('periode_tahun', date('Y'))->with('penilai')
        ])->find($pegawaiId);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $jabatanStrukturalPenilai = $this->getUserJabatanStruktural($user->id);
        if (!$this->canEvaluatePegawai($jabatanStrukturalPenilai, $pegawai)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak berhak mengevaluasi pegawai ini'], 403);
        }

        $evaluasiTerkini = $pegawai->evaluasiKinerja->first();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $this->formatPegawaiInfo($pegawai),
                'evaluasi' => $evaluasiTerkini ? $this->formatEvaluasi($evaluasiTerkini) : null,
                'jenis_form' => $this->determineJenisKinerja($pegawai) // 'dosen' atau 'tendik'
            ]
        ]);
    }

    /**
     * Menyimpan evaluasi kinerja baru.
     */
    public function store(Request $request)
    {
        return $this->saveOrUpdate($request);
    }

    /**
     * Memperbarui evaluasi kinerja yang sudah ada.
     */
    public function update(Request $request, $id)
    {
        return $this->saveOrUpdate($request, $id);
    }

    /**
     * Logika utama untuk menyimpan atau memperbarui evaluasi.
     */
    private function saveOrUpdate(Request $request, $id = null)
    {
        $user = Auth::user()->pegawai;
        $pegawai = SimpegPegawai::with('role')->find($request->pegawai_id); // Ubah dari jabatanAkademik.role

        if (!$pegawai) return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        
        $jenisKinerja = $this->determineJenisKinerja($pegawai);
        $validator = $this->validateEvaluationRequest($request, $jenisKinerja);
        if ($validator->fails()) return response()->json(['success' => false, 'errors' => $validator->errors()], 422);

        $jabatanStrukturalPenilai = $this->getUserJabatanStruktural($user->id);
        if (!$this->canEvaluatePegawai($jabatanStrukturalPenilai, $pegawai)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak berhak mengevaluasi pegawai ini'], 403);
        }

        $atasanPenilaiId = $this->getAtasanPegawaiId($jabatanStrukturalPenilai);
        if (!$atasanPenilaiId) {
            if (is_null($jabatanStrukturalPenilai->parent_jabatan)) {
                $atasanPenilaiId = $user->id;
            } else {
                return response()->json(['success' => false, 'message' => 'Data atasan penilai tidak dapat ditemukan di sistem.'], 404);
            }
        }

        $data = $request->all();
        $data['penilai_id'] = $user->id;
        $data['atasan_penilai_id'] = $atasanPenilaiId;
        $data['jenis_kinerja'] = $jenisKinerja;
        $data['tgl_input'] = now();
        $data['total_nilai'] = $this->calculateTotalNilai($data, $jenisKinerja);
        $data['sebutan_total'] = $this->getSebutanByNilai($data['total_nilai']);

        $evaluasi = SimpegEvaluasiKinerja::updateOrCreate(
            [
                'pegawai_id' => $request->pegawai_id,
                'periode_tahun' => $request->periode_tahun,
                'id' => $id,
            ],
            $data
        );

        $message = $id ? 'Evaluasi kinerja berhasil diperbarui' : 'Evaluasi kinerja berhasil disimpan';
        return response()->json(['success' => true, 'data' => $evaluasi, 'message' => $message], 200);
    }

    /**
     * Menghapus data evaluasi kinerja.
     */
    public function destroy($id)
    {
        $user = Auth::user()->pegawai;
        $evaluasi = SimpegEvaluasiKinerja::where('penilai_id', $user->id)->find($id);

        if (!$evaluasi) {
            return response()->json(['success' => false, 'message' => 'Data evaluasi tidak ditemukan atau Anda tidak berhak menghapusnya.'], 404);
        }

        $evaluasi->delete();
        
        return response()->json(['success' => true, 'message' => 'Evaluasi kinerja berhasil dihapus.']);
    }

    // ==================== HELPER METHODS ====================

    private function validateEvaluationRequest(Request $request, $jenisKinerja)
    {
        $rules = [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'periode_tahun' => 'required|string|max:10',
            'tanggal_penilaian' => 'required|date|before_or_equal:today',
            'nilai_kehadiran' => 'nullable|numeric|min:0|max:100',
            'nilai_penerapan_tridharma' => 'nullable|numeric|min:0|max:100',
            'nilai_komitmen_disiplin' => 'nullable|numeric|min:0|max:100',
            'nilai_kepemimpinan_kerjasama' => 'nullable|numeric|min:0|max:100',
            'nilai_inisiatif_integritas' => 'nullable|numeric|min:0|max:100',
        ];

        if ($jenisKinerja === 'dosen') {
            $rules['nilai_pendidikan'] = 'nullable|numeric|min:0|max:100';
            $rules['nilai_penelitian'] = 'nullable|numeric|min:0|max:100';
            $rules['nilai_pengabdian'] = 'nullable|numeric|min:0|max:100';
        }

        return Validator::make($request->all(), $rules);
    }

    private function calculateTotalNilai(array $data, $jenisKinerja)
    {
        // --- BOBOT BERDASARKAN FOTO (Total 100%) ---
        $bobotKehadiran = 0.10; // 10%
        $bobotPenunjang = 0.20; // 20%

        // Bobot Tugas Pokok untuk Dosen (Total 70%)
        $bobotPendidikan = 0.40; // 40%
        $bobotPenelitian = 0.20; // 20%
        $bobotPengabdian = 0.10; // 10%

        // 1. HITUNG NILAI KOMPONEN KEHADIRAN (10%)
        $nilaiKehadiran = (float)($data['nilai_kehadiran'] ?? 0);
        $skorKehadiran = $nilaiKehadiran * $bobotKehadiran;

        // 2. HITUNG NILAI KOMPONEN PENUNJANG (20%)
        $nilaiPenunjangItems = [
            (float)($data['nilai_penerapan_tridharma'] ?? 0),
            (float)($data['nilai_komitmen_disiplin'] ?? 0),
            (float)($data['nilai_kepemimpinan_kerjasama'] ?? 0),
            (float)($data['nilai_inisiatif_integritas'] ?? 0),
        ];
        $rataRataPenunjang = count($nilaiPenunjangItems) > 0 ? array_sum($nilaiPenunjangItems) / count($nilaiPenunjangItems) : 0;
        $skorPenunjang = $rataRataPenunjang * $bobotPenunjang;

        // 3. HITUNG NILAI KOMPONEN TUGAS POKOK (TOTAL 70%)
        $skorTugasPokok = 0;
        if ($jenisKinerja === 'dosen') {
            $skorPendidikan = (float)($data['nilai_pendidikan'] ?? 0) * $bobotPendidikan;
            $skorPenelitian = (float)($data['nilai_penelitian'] ?? 0) * $bobotPenelitian;
            $skorPengabdian = (float)($data['nilai_pengabdian'] ?? 0) * $bobotPengabdian;
            
            $skorTugasPokok = $skorPendidikan + $skorPenelitian + $skorPengabdian;
        }

        // 4. JUMLAHKAN SEMUA SKOR KOMPONEN
        $totalNilai = $skorKehadiran + $skorTugasPokok + $skorPenunjang;

        return round($totalNilai, 2);
    }

    private function determineJenisKinerja(SimpegPegawai $pegawai)
    {
        // Pastikan relasi role sudah di-load
        $pegawai->loadMissing('role');
        $role = optional($pegawai->role)->nama;
        return in_array($role, ['Dosen', 'Dosen LB']) ? 'dosen' : 'tendik';
    }

    private function getUserJabatanStruktural($userId)
    {
        // Perbaikan: Cek jabatan yang masih aktif (belum selesai atau tanggal selesai di masa depan)
        $dataJabatan = SimpegDataJabatanStruktural::where('pegawai_id', $userId)
            ->where(function($q) {
                $q->whereNull('tgl_selesai')
                  ->orWhere('tgl_selesai', '>=', now());
            })
            ->where('status_pengajuan', 'disetujui')
            ->with('jabatanStruktural')
            ->orderBy('tgl_mulai', 'desc')
            ->first();
        
        return optional($dataJabatan)->jabatanStruktural;
    }

    private function getPegawaiByHierarki($jabatanStruktural, $penilaiId)
    {
        $childJabatanIds = $jabatanStruktural->getAllDescendants()->pluck('id');
        $pegawaiTable = (new SimpegPegawai)->getTable();

        return SimpegPegawai::select($pegawaiTable . '.*')
            ->where($pegawaiTable . '.id', '!=', $penilaiId)
            ->whereHas('dataJabatanStruktural', fn($q) => 
                $q->whereIn('jabatan_struktural_id', $childJabatanIds)->whereNull('tgl_selesai')
        );
    }

    private function canEvaluatePegawai($jabatanPenilai, $pegawaiDievaluasi)
    {
        if (!$jabatanPenilai || !$pegawaiDievaluasi) {
            return false;
        }

        $jenisPegawai = $this->determineFungsional($pegawaiDievaluasi);

        // 1. Aturan untuk Dosen
        if ($jenisPegawai === 'Dosen') {
            $jabatanPegawaiDievaluasi = $this->getUserJabatanStruktural($pegawaiDievaluasi->id);
            if (!$jabatanPegawaiDievaluasi) {
                return false;
            }
            return $jabatanPegawaiDievaluasi->isChildOf($jabatanPenilai->kode);
        }
        // 2. Aturan untuk SEMUA role SELAIN Dosen
        else {
            return true;
        }
    }

    private function getAtasanPegawaiId($jabatanStruktural)
    {
        if (!$jabatanStruktural || !$jabatanStruktural->parent_jabatan) return null;
        $parentJabatan = SimpegJabatanStruktural::where('kode', $jabatanStruktural->parent_jabatan)->first();
        if (!$parentJabatan) return null;
        return optional(SimpegDataJabatanStruktural::where('jabatan_struktural_id', $parentJabatan->id)
            ->whereNull('tgl_selesai')->first())->pegawai_id;
    }

    private function getSebutanByNilai($nilai)
    {
        if ($nilai >= 91) return 'Sangat Baik';
        if ($nilai >= 76) return 'Baik';
        if ($nilai >= 61) return 'Cukup';
        if ($nilai >= 51) return 'Kurang';
        return 'Sangat Kurang';
    }

    private function formatPegawaiForTable($pegawai)
    {
        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'nama_pegawai' => $pegawai->nama,
            'unit_kerja' => $this->getUnitKerjaNama($pegawai),
            'fungsional' => $this->determineFungsional($pegawai),
            'aksi' => $this->generateActionButtons($pegawai, $pegawai->evaluasiKinerja->first())
        ];
    }
    
    private function formatPegawaiInfo($pegawai)
    {
        // Pastikan relasi sudah di-load
        $pegawai->loadMissing([
            'role',
            'unitKerja',
            'statusAktif',
            'dataJabatanStruktural.jabatanStruktural',
            'dataPendidikanFormal.jenjangPendidikan'
        ]);



        $jabatanStruktural = $this->getUserJabatanStruktural($pegawai->id);
        $pendidikanTerakhirData = $pegawai->dataPendidikanFormal
        ->sortByDesc('tahun_lulus')
        ->first();

        return [
            'id' => $pegawai->id,
            'nama_lengkap' => $pegawai->nama,
            'unit_kerja' => $this->getUnitKerjaNama($pegawai),
            'status' => optional($pegawai->statusAktif)->nama_status_aktif ?? 'Tidak Diketahui',
            'jab_fungsional' => optional($pegawai->jabatanFungsional)->nama_jabatan_fungsional ?? '-', // Sekarang dari role
            'jenis_pegawai' => $this->determineFungsional($pegawai),
            'jab_struktural' => optional($jabatanStruktural)->singkatan ?? '-',
            'pendidikan' => optional(optional($pendidikanTerakhirData)->jenjangPendidikan)->nama_jenjang ?? 'Tidak Diketahui',
        ];
    }

    private function getUnitKerjaNama($pegawai)
    {
        if (!$pegawai) {
            return '-';
        }
        if ($pegawai->relationLoaded('unitKerja') && $pegawai->unitKerja) {
            return $pegawai->unitKerja->nama_unit;
        }
        if ($pegawai->unit_kerja_id) {
            $unitKerja = SimpegUnitKerja::find($pegawai->unit_kerja_id);
            return $unitKerja ? $unitKerja->nama_unit : '-';
        }
        return '-';
    }

    private function formatEvaluasi($evaluasi)
    {
        return [
            'id' => $evaluasi->id,
            'periode_tahun' => $evaluasi->periode_tahun,
            'tanggal_penilaian' => optional($evaluasi->tanggal_penilaian)->format('Y-m-d'),
            'nilai_kehadiran' => $evaluasi->nilai_kehadiran,
            'nilai_pendidikan' => $evaluasi->nilai_pendidikan,
            'nilai_penelitian' => $evaluasi->nilai_penelitian,
            'nilai_pengabdian' => $evaluasi->nilai_pengabdian,
            'nilai_penerapan_tridharma' => $evaluasi->nilai_penerapan_tridharma,
            'nilai_komitmen_disiplin' => $evaluasi->nilai_komitmen_disiplin,
            'nilai_kepemimpinan_kerjasama' => $evaluasi->nilai_kepemimpinan_kerjasama,
            'nilai_inisiatif_integritas' => $evaluasi->nilai_inisiatif_integritas,
            'total_nilai' => round($evaluasi->total_nilai, 2),
            'sebutan_total' => $evaluasi->sebutan_total,
            'penilai' => optional($evaluasi->penilai)->nama ?? 'N/A',
        ];
    }

    private function determineFungsional($pegawai)
    {
        // Pastikan relasi role sudah di-load
        $pegawai->loadMissing('role');
        $role = optional($pegawai->role)->nama;
        
        // Fungsi ini mengembalikan 'Dosen' atau 'Staff'.
        return in_array($role, ['Dosen', 'Dosen LB']) ? 'Dosen' : 'Staff';
    }

    private function generateActionButtons($pegawai, $evaluasiTerkini)
    {
        $actions = ['add' => false, 'edit' => false, 'delete' => false, 'evaluation_id' => null];
        if ($evaluasiTerkini) {
            $actions['edit'] = true;
            $actions['delete'] = true;
            $actions['evaluation_id'] = $evaluasiTerkini->id;
        } else {
            $actions['add'] = true;
        }
        return $actions;
    }
}