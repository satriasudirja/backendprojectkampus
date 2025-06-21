<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataTes;
use App\Models\SimpegPegawai;
use App\Models\SimpegDataPendukung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatTesController extends Controller
{
    /**
     * Menampilkan daftar riwayat tes untuk pegawai tertentu.
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

        $query = SimpegDataTes::where('pegawai_id', $pegawai->id)->with(['jenisTes']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_tes', 'like', '%' . $search . '%')
                  ->orWhere('penyelenggara', 'like', '%' . $search . '%')
                  ->orWhereHas('jenisTes', function ($subq) use ($search) {
                      $subq->where('jenis_tes', 'like', '%' . $search . '%');
                  });
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataTes = $query->orderBy('tgl_tes', 'desc')->paginate($perPage);

        $dataTes->getCollection()->transform(function ($item) use ($pegawai_id) {
            return $this->formatDataTes($item, $pegawai_id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataTes,
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
     * Menyimpan riwayat tes baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'jenis_tes_id' => 'required|integer|exists:simpeg_daftar_jenis_test,id',
            'nama_tes' => 'required|string|max:255',
            'penyelenggara' => 'required|string|max:255',
            'tgl_tes' => 'required|date',
            'skor' => 'required|numeric',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $data['pegawai_id'] = $pegawai->id;
            $data['tgl_input'] = now();

            if ($request->hasFile('file_pendukung')) {
                $file = $request->file('file_pendukung');
                $fileName = 'tes_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $data['file_pendukung'] = $file->storeAs('tes_files', $fileName, 'public');
            }

            $riwayatTes = SimpegDataTes::create($data);
            ActivityLogger::log('create', $riwayatTes, $riwayatTes->toArray());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Tes berhasil ditambahkan.',
                'data' => $this->formatDataTes($riwayatTes, $pegawai->id, false),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    public function show($pegawai_id, $riwayat_id)
    {
        $riwayatTes = SimpegDataTes::where('pegawai_id', $pegawai_id)
            ->with(['jenisTes'])
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataTes($riwayatTes, $pegawai_id, false),
        ]);
    }

    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $riwayatTes = SimpegDataTes::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'jenis_tes_id' => 'required|integer|exists:simpeg_daftar_jenis_test,id',
            'nama_tes' => 'required|string|max:255',
            'penyelenggara' => 'required|string|max:255',
            'tgl_tes' => 'required|date',
            'skor' => 'required|numeric',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $riwayatTes->getOriginal();
        $data = $validator->validated();

        if ($request->hasFile('file_pendukung')) {
            if($riwayatTes->file_pendukung) Storage::disk('public')->delete($riwayatTes->file_pendukung);
            $file = $request->file('file_pendukung');
            $fileName = 'tes_' . $pegawai_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_pendukung'] = $file->storeAs('tes_files', $fileName, 'public');
        }

        $riwayatTes->update($data);
        ActivityLogger::log('update', $riwayatTes, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Tes berhasil diperbarui.',
            'data' => $this->formatDataTes($riwayatTes, $pegawai_id, false),
        ]);
    }

    public function destroy($pegawai_id, $riwayat_id)
    {
        $riwayatTes = SimpegDataTes::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
        
        DB::beginTransaction();
        try {
            if($riwayatTes->file_pendukung) Storage::disk('public')->delete($riwayatTes->file_pendukung);
            
            $oldData = $riwayatTes->toArray();
            $riwayatTes->delete();
            ActivityLogger::log('delete', $riwayatTes, $oldData);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Riwayat Tes berhasil dihapus.']);
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

    protected function formatDataTes($tes, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $tes->id,
            'nama_tes' => $tes->nama_tes,
            'skor_tes' => $tes->skor,
            'jenis_tes' => optional($tes->jenisTes)->jenis_tes,
            'penyelenggara' => $tes->penyelenggara,
            'tahun_tes' => Carbon::parse($tes->tgl_tes)->year,
            'status_pengajuan' => $tes->status_pengajuan ?? 'draft',
            'file_pendukung_url' => $tes->file_pendukung ? Storage::url($tes->file_pendukung) : null,
            // Detail untuk form
            'jenis_tes_id' => $tes->jenis_tes_id,
            'tgl_tes' => $tes->tgl_tes,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-tes/{$tes->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-tes/{$tes->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-tes/{$tes->id}"),
            ];
        }
        return $data;
    }
}
