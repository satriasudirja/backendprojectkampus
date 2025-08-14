<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataDiklat;
use App\Models\SimpegPegawai;
use App\Models\SimpegDataPendukung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatDiklatController extends Controller
{
    /**
     * Menampilkan daftar riwayat diklat untuk pegawai tertentu.
     */
    public function index(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::with([
            'unitKerja', 'statusAktif', 'jabatanAkademik',
            'dataJabatanFungsional.jabatanFungsional',
            'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
            'dataPendidikanFormal.jenjangPendidikan'
        ])->findOrFail($pegawai_id);

        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $statusPengajuan = $request->status_pengajuan;

        $query = SimpegDataDiklat::where('pegawai_id', $pegawai->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_diklat', 'like', '%' . $search . '%')
                  ->orWhere('penyelenggara', 'like', '%' . $search . '%')
                  ->orWhere('jenis_diklat', 'like', '%' . $search . '%');
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataDiklat = $query->orderBy('tahun_penyelenggaraan', 'desc')->paginate($perPage);

        $dataDiklat->getCollection()->transform(function ($item) use ($pegawai_id) {
            return $this->formatDataDiklat($item, $pegawai_id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataDiklat,
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
     * Menyimpan riwayat diklat baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'nama_diklat' => 'required|string|max:255',
            'jenis_diklat' => 'required|string|max:100',
            'kategori_diklat' => 'required|string|max:100',
            'tingkat_diklat' => 'required|string|max:100',
            'penyelenggara' => 'required|string|max:255',
            'peran' => 'required|string|max:100', // PERBAIKAN: Diubah dari nullable menjadi required
            'jumlah_jam' => 'nullable|integer|min:1',
            'no_sertifikat' => 'required|string|max:100',
            'tgl_sertifikat' => 'required|date',
            'tahun_penyelenggaraan' => 'required|integer|digits:4',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'tempat' => 'nullable|string|max:255',
            'sk_penugasan' => 'nullable|string|max:255',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'required_with:dokumen_pendukung|uuid', 
            'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string|max:255',
            'dokumen_pendukung.*.file' => 'required_with:dokumen_pendukung|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'dokumen_pendukung.*.keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $diklatData = collect($data)->except('dokumen_pendukung')->toArray();

            $diklatData['pegawai_id'] = $pegawai->id;
            $diklatData['tgl_input'] = now();

            $riwayatDiklat = SimpegDataDiklat::create($diklatData);

            if ($request->has('dokumen_pendukung')) {
                foreach ($request->dokumen_pendukung as $index => $dokumen) {
                    if ($request->hasFile("dokumen_pendukung.{$index}.file")) {
                        $file = $request->file("dokumen_pendukung.{$index}.file");
                        $fileName = 'diklat_' . $pegawai->id . '_' . time() . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs('diklat_dokumen', $fileName, 'public');
                        
                        SimpegDataPendukung::create([
                            'pendukungable_id' => $riwayatDiklat->id,
                            'pendukungable_type' => SimpegDataDiklat::class,
                            'nama_dokumen' => $dokumen['nama_dokumen'],
                            'jenis_dokumen_id' => $dokumen['jenis_dokumen_id'],
                            'keterangan' => $dokumen['keterangan'] ?? null,
                            'file_path' => $filePath,
                        ]);
                    }
                }
            }
            
            ActivityLogger::log('create', $riwayatDiklat, $riwayatDiklat->toArray());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Diklat berhasil ditambahkan.',
                'data' => $this->formatDataDiklat($riwayatDiklat->load('dataPendukung'), $pegawai->id, false),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }
    
    // Metode show, update, dan destroy juga diperbarui dengan validator yang sama
    public function show($pegawai_id, $riwayat_id)
    {
        $riwayatDiklat = SimpegDataDiklat::where('pegawai_id', $pegawai_id)
            ->with('dataPendukung')
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataDiklat($riwayatDiklat, $pegawai_id, false),
        ]);
    }

    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $riwayatDiklat = SimpegDataDiklat::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'nama_diklat' => 'required|string|max:255',
            'jenis_diklat' => 'required|string|max:100',
            'kategori_diklat' => 'required|string|max:100',
            'tingkat_diklat' => 'required|string|max:100',
            'penyelenggara' => 'required|string|max:255',
            'peran' => 'required|string|max:100', // PERBAIKAN: Diubah dari nullable menjadi required
            'jumlah_jam' => 'nullable|integer|min:1',
            'no_sertifikat' => 'required|string|max:100',
            'tgl_sertifikat' => 'required|date',
            'tahun_penyelenggaraan' => 'required|integer|digits:4',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'tempat' => 'nullable|string|max:255',
            'sk_penugasan' => 'nullable|string|max:255',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $riwayatDiklat->getOriginal();
        $data = $validator->validated();

        $riwayatDiklat->update($data);
        ActivityLogger::log('update', $riwayatDiklat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Diklat berhasil diperbarui.',
            'data' => $this->formatDataDiklat($riwayatDiklat, $pegawai_id, false),
        ]);
    }

    public function destroy($pegawai_id, $riwayat_id)
    {
        $riwayatDiklat = SimpegDataDiklat::where('pegawai_id', $pegawai_id)->with('dataPendukung')->findOrFail($riwayat_id);
        
        DB::beginTransaction();
        try {
            foreach($riwayatDiklat->dataPendukung as $dokumen) {
                Storage::disk('public')->delete($dokumen->file_path);
                $dokumen->delete();
            }

            $oldData = $riwayatDiklat->toArray();
            $riwayatDiklat->delete();
            ActivityLogger::log('delete', $riwayatDiklat, $oldData);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Riwayat Diklat berhasil dihapus.']);
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

    protected function formatDataDiklat($diklat, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $diklat->id,
            'nama_diklat' => $diklat->nama_diklat,
            'jenis_diklat' => $diklat->jenis_diklat,
            'kategori_diklat' => $diklat->kategori_diklat,
            'tingkat_diklat' => $diklat->tingkat_diklat,
            'penyelenggara' => $diklat->penyelenggara,
            'peran' => $diklat->peran,
            'jumlah_jam' => $diklat->jumlah_jam,
            'no_sertifikat' => $diklat->no_sertifikat,
            'tgl_sertifikat' => $diklat->tgl_sertifikat,
            'tahun_penyelenggaraan' => $diklat->tahun_penyelenggaraan,
            'tgl_mulai_formatted' => Carbon::parse($diklat->tgl_mulai)->isoFormat('D MMMM Y'),
            'tgl_selesai_formatted' => Carbon::parse($diklat->tgl_selesai)->isoFormat('D MMMM Y'),
            'tempat' => $diklat->tempat,
            'sk_penugasan' => $diklat->sk_penugasan,
            'status_pengajuan' => $diklat->status_pengajuan ?? 'draft',
            'dokumen_pendukung' => $diklat->dataPendukung->map(fn($dok) => ['id' => $dok->id, 'url' => Storage::url($dok->file_path)]),
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-diklat/{$diklat->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-diklat/{$diklat->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-diklat/{$diklat->id}"),
            ];
        }
        return $data;
    }
}
