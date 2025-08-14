<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataHubunganKerja;
use App\Models\SimpegPegawai;
use App\Models\HubunganKerja; // Master Hubungan Kerja
use App\Models\SimpegStatusAktif; // Master Status Aktif
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatHubunganKerjaController extends Controller
{
    /**
     * Menampilkan daftar riwayat hubungan kerja untuk pegawai tertentu.
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
        $search = $request->search; // Cari berdasarkan No SK atau jenis hubungan kerja
        $statusPengajuan = $request->status_pengajuan;

        $query = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->with(['hubunganKerja', 'statusAktif']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_sk', 'like', '%' . $search . '%')
                  ->orWhereHas('hubunganKerja', function ($q_hub) use ($search) {
                      $q_hub->where('nama_hub_kerja', 'like', '%' . $search . '%');
                  });
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataHubunganKerja = $query->orderBy('tgl_awal', 'desc')->paginate($perPage);

        $dataHubunganKerja->getCollection()->transform(function ($item) use ($pegawai) {
            return $this->formatDataHubunganKerja($item, $pegawai->id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataHubunganKerja,
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
     * Menyimpan riwayat hubungan kerja baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'hubungan_kerja_id' => 'required|uuid|exists:simpeg_hubungan_kerja,id',
            'status_aktif_id' => 'required|uuid|exists:simpeg_status_aktif,id',
            'tgl_awal' => 'required|date',
            'tgl_akhir' => 'nullable|date|after_or_equal:tgl_awal',
            'pejabat_penetap' => 'nullable|string|max:255',
            'file_hubungan_kerja' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'keterangan' => 'nullable|string|max:1000',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now();

        if ($request->hasFile('file_hubungan_kerja')) {
            $file = $request->file('file_hubungan_kerja');
            $fileName = 'hub_kerja_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_hubungan_kerja'] = $file->storeAs('hubungan_kerja_files', $fileName, 'public');
        }

        $hubunganKerja = SimpegDataHubunganKerja::create($data);
        ActivityLogger::log('create', $hubunganKerja, $hubunganKerja->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Hubungan Kerja berhasil ditambahkan.',
            'data' => $this->formatDataHubunganKerja($hubunganKerja, $pegawai->id, false),
        ], 201);
    }

    /**
     * Menampilkan detail spesifik riwayat hubungan kerja.
     */
    public function show($pegawai_id, $riwayat_id)
    {
        $hubunganKerja = SimpegDataHubunganKerja::where('pegawai_id', $pegawai_id)
            ->with(['hubunganKerja', 'statusAktif'])
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataHubunganKerja($hubunganKerja, $pegawai_id, false),
        ]);
    }

    /**
     * Update riwayat hubungan kerja yang ada.
     */
    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $hubunganKerja = SimpegDataHubunganKerja::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'hubungan_kerja_id' => 'required|uuid|exists:simpeg_hubungan_kerja,id',
            'status_aktif_id' => 'required|uuid|exists:simpeg_status_aktif,id',
            'tgl_awal' => 'required|date',
            'tgl_akhir' => 'nullable|date|after_or_equal:tgl_awal',
            'pejabat_penetap' => 'nullable|string|max:255',
            'file_hubungan_kerja' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'keterangan' => 'nullable|string|max:1000',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $hubunganKerja->getOriginal();
        $data = $validator->validated();

        if ($request->hasFile('file_hubungan_kerja')) {
            if ($hubunganKerja->file_hubungan_kerja) {
                Storage::disk('public')->delete($hubunganKerja->file_hubungan_kerja);
            }
            $file = $request->file('file_hubungan_kerja');
            $fileName = 'hub_kerja_' . $pegawai_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_hubungan_kerja'] = $file->storeAs('hubungan_kerja_files', $fileName, 'public');
        }

        $hubunganKerja->update($data);
        ActivityLogger::log('update', $hubunganKerja, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Hubungan Kerja berhasil diperbarui.',
            'data' => $this->formatDataHubunganKerja($hubunganKerja, $pegawai_id, false),
        ]);
    }

    /**
     * Menghapus riwayat hubungan kerja (Soft Delete).
     */
    public function destroy($pegawai_id, $riwayat_id)
    {
        $hubunganKerja = SimpegDataHubunganKerja::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);

        $oldData = $hubunganKerja->toArray();
        $hubunganKerja->delete();
        ActivityLogger::log('delete', $hubunganKerja, $oldData);

        return response()->json(['success' => true, 'message' => 'Riwayat Hubungan Kerja berhasil dihapus.']);
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

    protected function formatDataHubunganKerja($dataHubungan, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $dataHubungan->id,
            'hubungan_kerja_id' => $dataHubungan->hubungan_kerja_id,
            'hubungan_kerja' => optional($dataHubungan->hubunganKerja)->nama_hub_kerja,
            'status_aktif_id' => $dataHubungan->status_aktif_id,
            'status_aktif' => optional($dataHubungan->statusAktif)->nama_status_aktif,
            'tgl_awal' => $dataHubungan->tgl_awal,
            'tgl_awal_formatted' => Carbon::parse($dataHubungan->tgl_awal)->isoFormat('D MMMM Y'),
            'tgl_akhir' => $dataHubungan->tgl_akhir,
            'tgl_akhir_formatted' => $dataHubungan->tgl_akhir ? Carbon::parse($dataHubungan->tgl_akhir)->isoFormat('D MMMM Y') : '-',
            'no_sk' => $dataHubungan->no_sk,
            'tgl_sk' => $dataHubungan->tgl_sk,
            'pejabat_penetap' => $dataHubungan->pejabat_penetap,
            'keterangan' => $dataHubungan->keterangan,
            'status_pengajuan' => $dataHubungan->status_pengajuan ?? 'draft',
            'file_hubungan_kerja_url' => $dataHubungan->file_hubungan_kerja ? Storage::url($dataHubungan->file_hubungan_kerja) : null,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-hubungan-kerja/{$dataHubungan->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-hubungan-kerja/{$dataHubungan->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-hubungan-kerja/{$dataHubungan->id}"),
            ];
        }
        return $data;
    }
}
