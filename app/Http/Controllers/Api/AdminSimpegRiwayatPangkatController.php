<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPangkat;
use App\Models\SimpegPegawai;
use App\Models\SimpegMasterPangkat;
use App\Models\SimpegDaftarJenisSk;
use App\Models\SimpegJenisKenaikanPangkat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatPangkatController extends Controller
{
    /**
     * Menampilkan daftar riwayat pangkat untuk pegawai tertentu.
     */
    public function index(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::with(['unitKerja', 'statusAktif', 'jabatanAkademik'])->findOrFail($pegawai_id);

        $perPage = $request->per_page ?? 10;
        $search = $request->search; // Cari berdasarkan No SK atau Nama Pangkat
        $statusPengajuan = $request->status_pengajuan;

        $query = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
            ->with(['pangkat', 'jenisSk']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_sk', 'like', '%' . $search . '%')
                  ->orWhereHas('pangkat', function ($q_pangkat) use ($search) {
                      $q_pangkat->where('pangkat', 'like', '%' . $search . '%')
                                ->orWhere('nama_golongan', 'like', '%' . $search . '%');
                  });
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataPangkat = $query->orderBy('tmt_pangkat', 'desc')->paginate($perPage);

        $dataPangkat->getCollection()->transform(function ($item) use ($pegawai) {
            return $this->formatDataPangkat($item, $pegawai->id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataPangkat,
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
     * Menyimpan riwayat pangkat baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'jenis_sk_id' => 'required|uuid|exists:simpeg_daftar_jenis_sk,id',
            'jenis_kenaikan_pangkat_id' => 'required|uuid|exists:simpeg_jenis_kenaikan_pangkat,id',
            'pangkat_id' => 'required|uuid|exists:simpeg_master_pangkat,id',
            'tmt_pangkat' => 'required|date',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'masa_kerja_tahun' => 'required|integer|min:0',
            'masa_kerja_bulan' => 'required|integer|min:0|max:11',
            'acuan_masa_kerja' => 'sometimes|boolean',
            'file_pangkat' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now();
        $data['acuan_masa_kerja'] = $request->input('acuan_masa_kerja', 0); // Default ke 0 atau false jika tidak ada

        if ($request->hasFile('file_pangkat')) {
            $file = $request->file('file_pangkat');
            $fileName = 'pangkat_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_pangkat'] = $file->storeAs('pangkat_files', $fileName, 'public');
        }

        $dataPangkat = SimpegDataPangkat::create($data);
        ActivityLogger::log('create', $dataPangkat, $dataPangkat->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pangkat berhasil ditambahkan.',
            'data' => $this->formatDataPangkat($dataPangkat, $pegawai->id, false),
        ], 201);
    }

    /**
     * Menampilkan detail spesifik riwayat pangkat.
     */
    public function show($pegawai_id, $pangkat_id)
    {
        $dataPangkat = SimpegDataPangkat::where('pegawai_id', $pegawai_id)->with(['pangkat', 'jenisSk', 'jenisKenaikanPangkat'])->findOrFail($pangkat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPangkat($dataPangkat, $pegawai_id, false),
        ]);
    }

    /**
     * Update riwayat pangkat yang ada.
     */
    public function update(Request $request, $pegawai_id, $pangkat_id)
    {
        $dataPangkat = SimpegDataPangkat::where('pegawai_id', $pegawai_id)->findOrFail($pangkat_id);
            
        $validator = Validator::make($request->all(), [
            'jenis_sk_id' => 'required|uuid|exists:simpeg_daftar_jenis_sk,id',
            'jenis_kenaikan_pangkat_id' => 'required|uuid|exists:simpeg_jenis_kenaikan_pangkat,id',
            'pangkat_id' => 'required|uuid|exists:simpeg_master_pangkat,id',
            'tmt_pangkat' => 'required|date',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'masa_kerja_tahun' => 'required|integer|min:0',
            'masa_kerja_bulan' => 'required|integer|min:0|max:11',
            'acuan_masa_kerja' => 'sometimes|boolean',
            'file_pangkat' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $dataPangkat->getOriginal();
        $data = $validator->validated();
        $data['acuan_masa_kerja'] = $request->input('acuan_masa_kerja', 0);

        if ($request->hasFile('file_pangkat')) {
            if ($dataPangkat->file_pangkat) {
                Storage::disk('public')->delete($dataPangkat->file_pangkat);
            }
            $file = $request->file('file_pangkat');
            $fileName = 'pangkat_' . $pegawai_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_pangkat'] = $file->storeAs('pangkat_files', $fileName, 'public');
        }

        $dataPangkat->update($data);
        ActivityLogger::log('update', $dataPangkat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pangkat berhasil diperbarui.',
            'data' => $this->formatDataPangkat($dataPangkat, $pegawai_id, false),
        ]);
    }

    /**
     * Menghapus riwayat pangkat (Soft Delete).
     */
    public function destroy($pegawai_id, $pangkat_id)
    {
        $dataPangkat = SimpegDataPangkat::where('pegawai_id', $pegawai_id)->findOrFail($pangkat_id);

        $oldData = $dataPangkat->toArray();
        $dataPangkat->delete();
        ActivityLogger::log('delete', $dataPangkat, $oldData);

        return response()->json(['success' => true, 'message' => 'Riwayat pangkat berhasil dihapus.']);
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

    protected function formatDataPangkat($dataPangkat, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $dataPangkat->id,
            'pangkat_id' => $dataPangkat->pangkat_id,
            'nama_pangkat' => optional($dataPangkat->pangkat)->pangkat . ' (' . optional($dataPangkat->pangkat)->nama_golongan . ')',
            'jenis_sk_id' => $dataPangkat->jenis_sk_id,
            'jenis_sk' => optional($dataPangkat->jenisSk)->jenis_sk,
            'jenis_kenaikan_pangkat_id' => $dataPangkat->jenis_kenaikan_pangkat_id,
            'jenis_kenaikan_pangkat' => optional($dataPangkat->jenisKenaikanPangkat)->jenis_pangkat,
            'tmt_pangkat' => $dataPangkat->tmt_pangkat,
            'tmt_pangkat_formatted' => Carbon::parse($dataPangkat->tmt_pangkat)->isoFormat('D MMMM Y'),
            'no_sk' => $dataPangkat->no_sk,
            'tgl_sk' => $dataPangkat->tgl_sk,
            'pejabat_penetap' => $dataPangkat->pejabat_penetap,
            'masa_kerja' => $dataPangkat->masa_kerja_tahun . ' tahun, ' . $dataPangkat->masa_kerja_bulan . ' bulan',
            'masa_kerja_tahun' => $dataPangkat->masa_kerja_tahun,
            'masa_kerja_bulan' => $dataPangkat->masa_kerja_bulan,
            'acuan_masa_kerja' => (bool)$dataPangkat->acuan_masa_kerja,
            'status_pengajuan' => $dataPangkat->status_pengajuan ?? 'draft',
            'file_pangkat_url' => $dataPangkat->file_pangkat ? Storage::url($dataPangkat->file_pangkat) : null,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pangkat/{$dataPangkat->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pangkat/{$dataPangkat->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pangkat/{$dataPangkat->id}"),
            ];
        }
        return $data;
    }
}
