<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataRiwayatPekerjaanDosen as SimpegDataRiwayatPekerjaan;
use App\Models\SimpegPegawai;
use App\Models\SimpegDataPendukung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatPekerjaanController extends Controller
{
    /**
     * Menampilkan daftar riwayat pekerjaan untuk pegawai tertentu.
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
        $search = $request->search; // Cari berdasarkan Instansi atau Jabatan
        $statusPengajuan = $request->status_pengajuan;

        $query = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('instansi', 'like', '%' . $search . '%')
                  ->orWhere('jabatan', 'like', '%' . $search . '%');
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataPekerjaan = $query->orderBy('mulai_bekerja', 'desc')->paginate($perPage);

        $dataPekerjaan->getCollection()->transform(function ($item) use ($pegawai_id) {
            return $this->formatDataPekerjaan($item, $pegawai_id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataPekerjaan,
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
     * Menyimpan riwayat pekerjaan baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'bidang_usaha' => 'required|string|max:255',
            'jenis_pekerjaan' => 'required|string|max:255',
            'jabatan' => 'required|string|max:255',
            'instansi' => 'required|string|max:255',
            'divisi' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'mulai_bekerja' => 'required|date',
            'selesai_bekerja' => 'required|date|after_or_equal:mulai_bekerja',
            'area_pekerjaan' => 'required|in:Dalam Negeri,Luar Negeri',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string|max:255',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'required_with:dokumen_pendukung|integer',
            'dokumen_pendukung.*.file' => 'required_with:dokumen_pendukung|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'dokumen_pendukung.*.keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $pekerjaanData = collect($data)->except('dokumen_pendukung')->toArray();

            $pekerjaanData['pegawai_id'] = $pegawai->id;
            $pekerjaanData['tgl_input'] = now();
            $pekerjaanData['area_pekerjaan'] = ($pekerjaanData['area_pekerjaan'] === 'Luar Negeri');

            $riwayatPekerjaan = SimpegDataRiwayatPekerjaan::create($pekerjaanData);

            if ($request->has('dokumen_pendukung')) {
                foreach ($request->dokumen_pendukung as $index => $dokumen) {
                    if ($request->hasFile("dokumen_pendukung.{$index}.file")) {
                        $file = $request->file("dokumen_pendukung.{$index}.file");
                        $fileName = 'pekerjaan_' . $pegawai->id . '_' . time() . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs('riwayat_pekerjaan', $fileName, 'public');
                        
                        SimpegDataPendukung::create([
                            'pendukungable_id' => $riwayatPekerjaan->id,
                            'pendukungable_type' => SimpegDataRiwayatPekerjaan::class,
                            'nama_dokumen' => $dokumen['nama_dokumen'],
                            'jenis_dokumen_id' => $dokumen['jenis_dokumen_id'],
                            'keterangan' => $dokumen['keterangan'] ?? null,
                            'file_path' => $filePath,
                        ]);
                    }
                }
            }
            
            ActivityLogger::log('create', $riwayatPekerjaan, $riwayatPekerjaan->toArray());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Pekerjaan berhasil ditambahkan.',
                'data' => $this->formatDataPekerjaan($riwayatPekerjaan->load('dataPendukung'), $pegawai->id, false),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menampilkan detail spesifik riwayat pekerjaan.
     */
    public function show($pegawai_id, $riwayat_id)
    {
        $riwayatPekerjaan = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai_id)
            ->with('dataPendukung')
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPekerjaan($riwayatPekerjaan, $pegawai_id, false),
        ]);
    }

    /**
     * Update riwayat pekerjaan yang ada.
     */
    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $riwayatPekerjaan = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'bidang_usaha' => 'required|string|max:255',
            'jenis_pekerjaan' => 'required|string|max:255',
            'jabatan' => 'required|string|max:255',
            'instansi' => 'required|string|max:255',
            'divisi' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'mulai_bekerja' => 'required|date',
            'selesai_bekerja' => 'required|date|after_or_equal:mulai_bekerja',
            'area_pekerjaan' => 'required|in:Dalam Negeri,Luar Negeri',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $riwayatPekerjaan->getOriginal();
        $data = $validator->validated();
        $data['area_pekerjaan'] = ($data['area_pekerjaan'] === 'Luar Negeri');

        $riwayatPekerjaan->update($data);
        ActivityLogger::log('update', $riwayatPekerjaan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Pekerjaan berhasil diperbarui.',
            'data' => $this->formatDataPekerjaan($riwayatPekerjaan, $pegawai_id, false),
        ]);
    }

    /**
     * Menghapus riwayat pekerjaan (Soft Delete).
     */
    public function destroy($pegawai_id, $riwayat_id)
    {
        $riwayatPekerjaan = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai_id)->with('dataPendukung')->findOrFail($riwayat_id);
        
        DB::beginTransaction();
        try {
            foreach($riwayatPekerjaan->dataPendukung as $dokumen) {
                Storage::disk('public')->delete($dokumen->file_path);
                $dokumen->delete();
            }

            $oldData = $riwayatPekerjaan->toArray();
            $riwayatPekerjaan->delete();
            ActivityLogger::log('delete', $riwayatPekerjaan, $oldData);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Riwayat Pekerjaan berhasil dihapus.']);
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

    protected function formatDataPekerjaan($pekerjaan, $pegawaiId, $includeActions = true)
    {
        $waktu = Carbon::parse($pekerjaan->mulai_bekerja)->isoFormat('MMMM YYYY') . ' - ' . Carbon::parse($pekerjaan->selesai_bekerja)->isoFormat('MMMM YYYY');
        
        $data = [
            'id' => $pekerjaan->id,
            'nama_instansi' => $pekerjaan->instansi,
            'jabatan' => $pekerjaan->jabatan,
            'waktu' => $waktu,
            'area_pekerjaan' => $pekerjaan->area_pekerjaan ? 'Luar Negeri' : 'Dalam Negeri',
            'status_pengajuan' => $pekerjaan->status_pengajuan ?? 'draft',
            'dokumen_pendukung' => $pekerjaan->dataPendukung->map(fn($dok) => ['id' => $dok->id, 'url' => Storage::url($dok->file_path)]),
            'bidang_usaha' => $pekerjaan->bidang_usaha,
            'jenis_pekerjaan' => $pekerjaan->jenis_pekerjaan,
            'divisi' => $pekerjaan->divisi,
            'deskripsi' => $pekerjaan->deskripsi,
            'mulai_bekerja' => $pekerjaan->mulai_bekerja,
            'selesai_bekerja' => $pekerjaan->selesai_bekerja,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pekerjaan/{$pekerjaan->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pekerjaan/{$pekerjaan->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pekerjaan/{$pekerjaan->id}"),
            ];
        }
        return $data;
    }
}
