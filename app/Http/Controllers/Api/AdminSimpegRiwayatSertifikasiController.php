<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataSertifikasi;
use App\Models\SimpegPegawai;
use App\Models\SimpegDataPendukung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatSertifikasiController extends Controller
{
    /**
     * Menampilkan daftar riwayat sertifikasi untuk pegawai tertentu.
     */
    public function index(Request $request, $pegawai_id)
    {
        // Eager load semua relasi yang diperlukan untuk info pegawai yang lengkap
        $pegawai = SimpegPegawai::with([
            'unitKerja', 'statusAktif', 'jabatanAkademik',
            'dataJabatanFungsional.jabatanFungsional',
            'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
            'dataPendidikanFormal.jenjangPendidikan'
        ])->findOrFail($pegawai_id);

        $perPage = $request->per_page ?? 10;
        $search = $request->search; // Cari berdasarkan No. Sertifikasi, Jenis, atau Bidang Studi
        $statusPengajuan = $request->status_pengajuan;

        $query = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
            ->with(['jenisSertifikasi', 'bidangIlmu']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_sertifikasi', 'like', '%' . $search . '%')
                  ->orWhere('no_registrasi', 'like', '%' . $search . '%')
                  ->orWhereHas('jenisSertifikasi', function ($subq) use ($search) {
                      $subq->where('nama_sertifikasi', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('bidangIlmu', function ($subq) use ($search) {
                      $subq->where('nama_bidang', 'like', '%' . $search . '%');
                  });
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataSertifikasi = $query->orderBy('tgl_sertifikasi', 'desc')->paginate($perPage);

        $dataSertifikasi->getCollection()->transform(function ($item) use ($pegawai_id) {
            return $this->formatDataSertifikasi($item, $pegawai_id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataSertifikasi,
            'filters' => [
                 'status_pengajuan' => [
                     ['id' => 'semua', 'nama' => 'Semua'],
                     ['id' => 'draft', 'nama' => 'Draft'],
                     ['id' => 'diajukan', 'nama' => 'Diajukan'],
                     ['id' => 'disetujui', 'nama' => 'Disetujui'],
                     ['id' => 'ditolak', 'nama' => 'Ditolak']
                 ]
            ],
        ]);
    }

    /**
     * Menyimpan riwayat sertifikasi baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'jenis_sertifikasi_id' => 'required|uuid|exists:simpeg_master_jenis_sertifikasi,id',
            'bidang_ilmu_id' => 'required|uuid|exists:simpeg_rumpun_bidang_ilmu,id',
            'no_sertifikasi' => 'required|string|max:255',
            'tgl_sertifikasi' => 'required|date',
            'no_registrasi' => 'nullable|string|max:255',
            'no_peserta' => 'nullable|string|max:255',
            'peran' => 'nullable|string|max:100',
            'penyelenggara' => 'nullable|string|max:255',
            'tempat' => 'nullable|string|max:255',
            'lingkup' => 'nullable|string|max:100',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string',
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string|max:255',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'required_with:dokumen_pendukung|uuid',
            'dokumen_pendukung.*.file' => 'required_with:dokumen_pendukung|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'dokumen_pendukung.*.keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $sertifikasiData = collect($data)->except('dokumen_pendukung')->toArray();

            $sertifikasiData['pegawai_id'] = $pegawai->id;
            $sertifikasiData['tgl_input'] = now();

            $sertifikasi = SimpegDataSertifikasi::create($sertifikasiData);

            if ($request->has('dokumen_pendukung')) {
                foreach ($request->dokumen_pendukung as $index => $dokumen) {
                    if ($request->hasFile("dokumen_pendukung.{$index}.file")) {
                        $file = $request->file("dokumen_pendukung.{$index}.file");
                        $fileName = 'sertifikasi_' . $pegawai->id . '_' . time() . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs('sertifikasi_files', $fileName, 'public');
                        
                        SimpegDataPendukung::create([
                            'pendukungable_id' => $sertifikasi->id,
                            'pendukungable_type' => SimpegDataSertifikasi::class,
                            'nama_dokumen' => $dokumen['nama_dokumen'],
                            'jenis_dokumen_id' => $dokumen['jenis_dokumen_id'],
                            'keterangan' => $dokumen['keterangan'] ?? null,
                            'file_path' => $filePath,
                        ]);
                    }
                }
            }
            
            ActivityLogger::log('create', $sertifikasi, $sertifikasi->toArray());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Sertifikasi berhasil ditambahkan.',
                'data' => $this->formatDataSertifikasi($sertifikasi->load('dokumenPendukung'), $pegawai->id, false),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    public function show($pegawai_id, $riwayat_id)
    {
        $sertifikasi = SimpegDataSertifikasi::where('pegawai_id', $pegawai_id)
            ->with(['jenisSertifikasi', 'bidangIlmu', 'dokumenPendukung'])
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataSertifikasi($sertifikasi, $pegawai_id, false),
        ]);
    }

    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $sertifikasi = SimpegDataSertifikasi::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'jenis_sertifikasi_id' => 'required|uuid|exists:simpeg_master_jenis_sertifikasi,id',
            'bidang_ilmu_id' => 'required|uuid|exists:simpeg_rumpun_bidang_ilmu,id',
            'no_sertifikasi' => 'required|string|max:255',
            'tgl_sertifikasi' => 'required|date',
            'no_registrasi' => 'nullable|string|max:255',
            'no_peserta' => 'nullable|string|max:255',
            'peran' => 'nullable|string|max:100',
            'penyelenggara' => 'nullable|string|max:255',
            'tempat' => 'nullable|string|max:255',
            'lingkup' => 'nullable|string|max:100',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $sertifikasi->getOriginal();
        $data = $validator->validated();

        $sertifikasi->update($data);
        ActivityLogger::log('update', $sertifikasi, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Sertifikasi berhasil diperbarui.',
            'data' => $this->formatDataSertifikasi($sertifikasi, $pegawai_id, false),
        ]);
    }

    public function destroy($pegawai_id, $riwayat_id)
    {
        $sertifikasi = SimpegDataSertifikasi::where('pegawai_id', $pegawai_id)->with('dokumenPendukung')->findOrFail($riwayat_id);
        
        DB::beginTransaction();
        try {
            foreach($sertifikasi->dokumenPendukung as $dokumen) {
                Storage::disk('public')->delete($dokumen->file_path);
                $dokumen->delete();
            }

            $oldData = $sertifikasi->toArray();
            $sertifikasi->delete();
            ActivityLogger::log('delete', $sertifikasi, $oldData);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Riwayat Sertifikasi berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data: ' . $e->getMessage()], 500);
        }
    }

    private function formatPegawaiInfo($pegawai)
    {
        if (!$pegawai) return null;
        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama' => trim(($pegawai->gelar_depan ? $pegawai->gelar_depan . ' ' : '') . $pegawai->nama . ($pegawai->gelar_belakang ? ', ' . $pegawai->gelar_belakang : '')),
            'unit_kerja' => optional($pegawai->unitKerja)->nama_unit ?? 'Tidak Ada',
            'status' => optional($pegawai->statusAktif)->nama_status_aktif ?? '-',
            'jab_akademik' => optional($pegawai->jabatanAkademik)->jabatan_akademik ?? '-',
            'jab_fungsional' => optional(optional($pegawai->dataJabatanFungsional->first())->jabatanFungsional)->nama_jabatan_fungsional ?? '-',
            'jab_struktural' => optional(optional(optional($pegawai->dataJabatanStruktural->first())->jabatanStruktural)->jenisJabatanStruktural)->jenis_jabatan_struktural ?? '-',
            'pendidikan' => optional(optional($pegawai->dataPendidikanFormal->first())->jenjangPendidikan)->jenjang_pendidikan ?? '-',
        ];
    }

    protected function formatDataSertifikasi($sertifikasi, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $sertifikasi->id,
            'jenis_sertifikasi' => optional($sertifikasi->jenisSertifikasi)->nama_sertifikasi,
            'bidang_studi' => optional($sertifikasi->bidangIlmu)->nama_bidang,
            'no_registrasi' => $sertifikasi->no_registrasi,
            'no_sertifikasi' => $sertifikasi->no_sertifikasi,
            'tahun_sertifikasi' => Carbon::parse($sertifikasi->tgl_sertifikasi)->year,
            'status_pengajuan' => $sertifikasi->status_pengajuan ?? 'draft',
            'dokumen_pendukung' => $sertifikasi->dokumenPendukung->map(fn($dok) => ['id' => $dok->id, 'url' => Storage::url($dok->file_path)]),
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-sertifikasi/{$sertifikasi->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-sertifikasi/{$sertifikasi->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-sertifikasi/{$sertifikasi->id}"),
            ];
        }
        return $data;
    }
}
