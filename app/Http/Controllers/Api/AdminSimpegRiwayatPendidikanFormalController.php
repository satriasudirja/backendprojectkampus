<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPendidikanFormal;
use App\Models\SimpegPegawai;
use App\Models\SimpegJenjangPendidikan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatPendidikanFormalController extends Controller
{
    /**
     * Menampilkan daftar riwayat pendidikan formal untuk pegawai tertentu.
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
        $search = $request->search;
        $statusPengajuan = $request->status_pengajuan;

        $query = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->with(['jenjangPendidikan', 'perguruanTinggi', 'gelarAkademik']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('bidang_studi', 'like', '%' . $search . '%')
                  ->orWhere('konsentrasi', 'like', '%' . $search . '%')
                  ->orWhereHas('perguruanTinggi', function ($pt) use ($search) {
                      $pt->where('nama_universitas', 'like', '%' . $search . '%');
                  });
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataPendidikan = $query->orderBy('tahun_lulus', 'desc')->paginate($perPage);

        $dataPendidikan->getCollection()->transform(function ($item) use ($pegawai_id) {
            return $this->formatDataPendidikan($item, $pegawai_id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataPendidikan,
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
     * Menyimpan riwayat pendidikan formal baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'jenjang_pendidikan_id' => 'required|uuid|exists:simpeg_jenjang_pendidikan,id',
            'perguruan_tinggi_id' => 'required|uuid|exists:simpeg_master_perguruan_tinggi,id',
            'lokasi_studi' => 'required|in:Dalam Negeri,Luar Negeri',
            'tahun_masuk' => 'required|integer|digits:4',
            'tanggal_kelulusan' => 'required|date',
            'tahun_lulus' => 'required|integer|digits:4|gte:tahun_masuk',
            'nomor_ijazah' => 'required|string|max:100',
            'tanggal_ijazah' => 'required|date',
            'bidang_studi' => 'nullable|string|max:255',
            'konsentrasi' => 'nullable|string|max:255',
            'judul_tugas' => 'nullable|string|max:500',
            'ipk_kelulusan' => 'nullable|numeric|between:0,4.00',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'file_ijazah' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'file_transkrip' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $data['pegawai_id'] = $pegawai->id;
            $data['tgl_input'] = now();

            if ($request->hasFile('file_ijazah')) {
                $file = $request->file('file_ijazah');
                $fileName = 'ijazah_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $data['file_ijazah'] = $file->storeAs('pendidikan_formal', $fileName, 'public');
            }
            if ($request->hasFile('file_transkrip')) {
                $file = $request->file('file_transkrip');
                $fileName = 'transkrip_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $data['file_transkrip'] = $file->storeAs('pendidikan_formal', $fileName, 'public');
            }

            $pendidikan = SimpegDataPendidikanFormal::create($data);
            ActivityLogger::log('create', $pendidikan, $pendidikan->toArray());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Pendidikan Formal berhasil ditambahkan.',
                'data' => $this->formatDataPendidikan($pendidikan, $pegawai->id, false),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menampilkan detail spesifik riwayat pendidikan.
     */
    public function show($pegawai_id, $riwayat_id)
    {
        $pendidikan = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai_id)
            ->with(['jenjangPendidikan', 'perguruanTinggi', 'gelarAkademik'])
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPendidikan($pendidikan, $pegawai_id, false),
        ]);
    }

    /**
     * Update riwayat pendidikan yang ada.
     */
    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $pendidikan = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'jenjang_pendidikan_id' => 'required|uuid|exists:simpeg_jenjang_pendidikan,id',
            'perguruan_tinggi_id' => 'required|uuid|exists:simpeg_master_perguruan_tinggi,id',
            'lokasi_studi' => 'required|in:Dalam Negeri,Luar Negeri',
            'tahun_masuk' => 'required|integer|digits:4',
            'tanggal_kelulusan' => 'required|date',
            'tahun_lulus' => 'required|integer|digits:4|gte:tahun_masuk',
            'nomor_ijazah' => 'required|string|max:100',
            'tanggal_ijazah' => 'required|date',
            'bidang_studi' => 'nullable|string|max:255',
            'konsentrasi' => 'nullable|string|max:255',
            'judul_tugas' => 'nullable|string|max:500',
            'ipk_kelulusan' => 'nullable|numeric|between:0,4.00',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'file_ijazah' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'file_transkrip' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        try {
            $oldData = $pendidikan->getOriginal();
            $data = $validator->validated();

            if ($request->hasFile('file_ijazah')) {
                if($pendidikan->file_ijazah) Storage::disk('public')->delete($pendidikan->file_ijazah);
                $file = $request->file('file_ijazah');
                $fileName = 'ijazah_' . $pegawai_id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $data['file_ijazah'] = $file->storeAs('pendidikan_formal', $fileName, 'public');
            }
            if ($request->hasFile('file_transkrip')) {
                if($pendidikan->file_transkrip) Storage::disk('public')->delete($pendidikan->file_transkrip);
                $file = $request->file('file_transkrip');
                $fileName = 'transkrip_' . $pegawai_id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $data['file_transkrip'] = $file->storeAs('pendidikan_formal', $fileName, 'public');
            }

            $pendidikan->update($data);
            ActivityLogger::log('update', $pendidikan, $oldData);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Pendidikan Formal berhasil diperbarui.',
                'data' => $this->formatDataPendidikan($pendidikan, $pegawai_id, false),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menghapus riwayat pendidikan (Soft Delete).
     */
    public function destroy($pegawai_id, $riwayat_id)
    {
        $pendidikan = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);

        DB::beginTransaction();
        try {
            if($pendidikan->file_ijazah) Storage::disk('public')->delete($pendidikan->file_ijazah);
            if($pendidikan->file_transkrip) Storage::disk('public')->delete($pendidikan->file_transkrip);

            $oldData = $pendidikan->toArray();
            $pendidikan->delete();
            ActivityLogger::log('delete', $pendidikan, $oldData);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Riwayat Pendidikan Formal berhasil dihapus.']);
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

    protected function formatDataPendidikan($pendidikan, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $pendidikan->id,
            'jenjang' => optional($pendidikan->jenjangPendidikan)->jenjang_pendidikan,
            'gelar' => optional($pendidikan->gelarAkademik)->gelar,
            'nama_institusi' => optional($pendidikan->perguruanTinggi)->nama_universitas,
            'tahun_lulus' => $pendidikan->tahun_lulus,
            'status_pengajuan' => $pendidikan->status_pengajuan ?? 'draft',
            'file_ijazah_url' => $pendidikan->file_ijazah ? Storage::url($pendidikan->file_ijazah) : null,
            'file_transkrip_url' => $pendidikan->file_transkrip ? Storage::url($pendidikan->file_transkrip) : null,
            // Detail untuk form
            'jenjang_pendidikan_id' => $pendidikan->jenjang_pendidikan_id,
            'perguruan_tinggi_id' => $pendidikan->perguruan_tinggi_id,
            'lokasi_studi' => $pendidikan->lokasi_studi,
            'tahun_masuk' => $pendidikan->tahun_masuk,
            'tanggal_kelulusan' => $pendidikan->tanggal_kelulusan,
            'nomor_ijazah' => $pendidikan->nomor_ijazah,
            'tanggal_ijazah' => $pendidikan->tanggal_ijazah,
            'bidang_studi' => $pendidikan->bidang_studi,
            'konsentrasi' => $pendidikan->konsentrasi,
            'judul_tugas' => $pendidikan->judul_tugas,
            'ipk_kelulusan' => $pendidikan->ipk_kelulusan,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pendidikan-formal/{$pendidikan->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pendidikan-formal/{$pendidikan->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pendidikan-formal/{$pendidikan->id}"),
            ];
        }
        return $data;
    }
}
