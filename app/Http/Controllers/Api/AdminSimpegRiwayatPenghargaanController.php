<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPenghargaanAdm as SimpegDataPenghargaan;
use App\Models\SimpegPegawai;
use App\Models\SimpegDataPendukung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatPenghargaanController extends Controller
{
    /**
     * Menampilkan daftar riwayat penghargaan untuk pegawai tertentu.
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

        $query = SimpegDataPenghargaan::where('pegawai_id', $pegawai->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_penghargaan', 'like', '%' . $search . '%')
                  ->orWhere('instansi_pemberi', 'like', '%' . $search . '%');
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataPenghargaan = $query->orderBy('tanggal_penghargaan', 'desc')->paginate($perPage);

        $dataPenghargaan->getCollection()->transform(function ($item) use ($pegawai_id) {
            return $this->formatDataPenghargaan($item, $pegawai_id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataPenghargaan,
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
     * Menyimpan riwayat penghargaan baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'kategori_kegiatan' => 'required|string|max:255',
            'tingkat_penghargaan' => 'required|string|max:255',
            'jenis_penghargaan' => 'required|string|max:255',
            'nama_penghargaan' => 'required|string|max:255',
            'tanggal_penghargaan' => 'required|date',
            'instansi_pemberi' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:100', // Disesuaikan dari form, SK Penugasan
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
            $penghargaanData = collect($data)->except('dokumen_pendukung')->toArray();
            $penghargaanData['pegawai_id'] = $pegawai->id;
            $penghargaanData['tgl_input'] = now();

            $riwayatPenghargaan = SimpegDataPenghargaan::create($penghargaanData);

            if ($request->has('dokumen_pendukung')) {
                foreach ($request->dokumen_pendukung as $index => $dokumen) {
                    if ($request->hasFile("dokumen_pendukung.{$index}.file")) {
                        $file = $request->file("dokumen_pendukung.{$index}.file");
                        $fileName = 'penghargaan_' . $pegawai->id . '_' . time() . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs('penghargaan_files', $fileName, 'public');
                        
                        SimpegDataPendukung::create([
                            'pendukungable_id' => $riwayatPenghargaan->id,
                            'pendukungable_type' => SimpegDataPenghargaan::class,
                            'nama_dokumen' => $dokumen['nama_dokumen'],
                            'jenis_dokumen_id' => $dokumen['jenis_dokumen_id'],
                            'keterangan' => $dokumen['keterangan'] ?? null,
                            'file_path' => $filePath,
                        ]);
                    }
                }
            }
            
            ActivityLogger::log('create', $riwayatPenghargaan, $riwayatPenghargaan->toArray());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Penghargaan berhasil ditambahkan.',
                'data' => $this->formatDataPenghargaan($riwayatPenghargaan->load('dokumenPendukung'), $pegawai->id, false),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    public function show($pegawai_id, $riwayat_id)
    {
        $penghargaan = SimpegDataPenghargaan::where('pegawai_id', $pegawai_id)
            ->with('dokumenPendukung')
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPenghargaan($penghargaan, $pegawai_id, false),
        ]);
    }

    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $penghargaan = SimpegDataPenghargaan::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'kategori_kegiatan' => 'required|string|max:255',
            'tingkat_penghargaan' => 'required|string|max:255',
            'jenis_penghargaan' => 'required|string|max:255',
            'nama_penghargaan' => 'required|string|max:255',
            'tanggal_penghargaan' => 'required|date',
            'instansi_pemberi' => 'required|string|max:255',
            'no_sk' => 'nullable|string|max:100',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $penghargaan->getOriginal();
        $data = $validator->validated();

        $penghargaan->update($data);
        ActivityLogger::log('update', $penghargaan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Penghargaan berhasil diperbarui.',
            'data' => $this->formatDataPenghargaan($penghargaan, $pegawai_id, false),
        ]);
    }

    public function destroy($pegawai_id, $riwayat_id)
    {
        $penghargaan = SimpegDataPenghargaan::where('pegawai_id', $pegawai_id)->with('dokumenPendukung')->findOrFail($riwayat_id);
        
        DB::beginTransaction();
        try {
            foreach($penghargaan->dokumenPendukung as $dokumen) {
                Storage::disk('public')->delete($dokumen->file_path);
                $dokumen->delete();
            }

            $oldData = $penghargaan->toArray();
            $penghargaan->delete();
            ActivityLogger::log('delete', $penghargaan, $oldData);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Riwayat Penghargaan berhasil dihapus.']);
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

    protected function formatDataPenghargaan($penghargaan, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $penghargaan->id,
            'nama_penghargaan' => $penghargaan->nama_penghargaan,
            'instansi' => $penghargaan->instansi_pemberi,
            'tanggal' => Carbon::parse($penghargaan->tanggal_penghargaan)->isoFormat('D MMMM YYYY'),
            'status_pengajuan' => $penghargaan->status_pengajuan ?? 'draft',
            'dokumen_pendukung' => $penghargaan->dokumenPendukung->map(fn($dok) => ['id' => $dok->id, 'url' => Storage::url($dok->file_path)]),
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-penghargaan/{$penghargaan->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-penghargaan/{$penghargaan->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-penghargaan/{$penghargaan->id}"),
            ];
        }
        return $data;
    }
}
