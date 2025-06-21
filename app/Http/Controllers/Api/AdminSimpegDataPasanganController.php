<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataKeluargaPegawai;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\ActivityLogger; // Pastikan namespace ini benar

class AdminSimpegDataPasanganController extends Controller
{
    /**
     * Menampilkan data pasangan dari seorang pegawai tertentu (untuk Admin).
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

        // Query data pasangan untuk pegawai yang dipilih
        $query = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan');

        if ($search) {
            $query->where('nama_pasangan', 'like', '%' . $search . '%');
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataPasangan = $query->orderBy('tgl_input', 'desc')->paginate($perPage);

        $dataPasangan->getCollection()->transform(function ($item) use ($pegawai) {
            return $this->formatDataPasangan($item, $pegawai->id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataPasangan,
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
     * Menyimpan data pasangan baru untuk pegawai tertentu.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $existingSpouse = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->first();

        if ($existingSpouse) {
            return response()->json([
                'success' => false, 
                'message' => 'Pegawai ini sudah memiliki data pasangan. Harap edit data yang sudah ada.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'nama_pasangan' => 'required|string|max:100',
            'tempat_lahir' => 'nullable|string|max:50',
            'tgl_lahir' => 'nullable|date|before:today',
            'pekerjaan' => 'nullable|string|max:100',
            'kartu_nikah' => 'nullable|string|max:50',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'pasangan_kerja_dalam_satu_instansi' => 'nullable|in:Ya,Tidak',
            'karpeg_pasangan' => 'nullable|string|max:50',
            'status_kepegawaian' => 'nullable|string|max:100',
            'keterangan' => 'nullable|string|max:255',
            'file_karpeg_pasangan' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->except(['file_karpeg_pasangan']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now();
        $data['nama'] = $request->nama_pasangan;
        $data['pasangan_berkerja_dalam_satu_instansi'] = ($request->pasangan_kerja_dalam_satu_instansi === 'Ya');

        if ($request->hasFile('file_karpeg_pasangan')) {
            $file = $request->file('file_karpeg_pasangan');
            $fileName = 'karpeg_pasangan_' . time() . '_' . $pegawai->id . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/keluarga/karpeg_pasangan', $fileName);
            $data['file_karpeg_pasangan'] = $fileName;
        }

        $dataPasangan = SimpegDataKeluargaPegawai::create($data);

        // Mencatat aktivitas pembuatan data baru
        ActivityLogger::log('create', $dataPasangan, $dataPasangan->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Data pasangan berhasil ditambahkan.',
            'data' => $this->formatDataPasangan($dataPasangan, $pegawai->id, false),
        ], 201);
    }

    /**
     * Menampilkan detail spesifik data pasangan.
     */
    public function show($pegawai_id, $pasangan_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);
        $dataPasangan = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->findOrFail($pasangan_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPasangan($dataPasangan, $pegawai->id, false),
        ]);
    }

    /**
     * Update data pasangan yang ada.
     */
    public function update(Request $request, $pegawai_id, $pasangan_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);
        $dataPasangan = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->findOrFail($pasangan_id);
            
        $validator = Validator::make($request->all(), [
            'nama_pasangan' => 'required|string|max:100',
            'tempat_lahir' => 'nullable|string|max:50',
            'tgl_lahir' => 'nullable|date|before:today',
            'pekerjaan' => 'nullable|string|max:100',
            'kartu_nikah' => 'nullable|string|max:50',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'pasangan_kerja_dalam_satu_instansi' => 'nullable|in:Ya,Tidak',
            'karpeg_pasangan' => 'nullable|string|max:50',
            'status_kepegawaian' => 'nullable|string|max:100',
            'keterangan' => 'nullable|string|max:255',
            'file_karpeg_pasangan' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        // Simpan data lama sebelum diupdate untuk perbandingan log
        $oldData = $dataPasangan->getOriginal();

        $data = $request->except('file_karpeg_pasangan');
        $data['nama'] = $request->nama_pasangan;
        $data['pasangan_berkerja_dalam_satu_instansi'] = ($request->pasangan_kerja_dalam_satu_instansi === 'Ya');

        if ($request->hasFile('file_karpeg_pasangan')) {
            if ($dataPasangan->file_karpeg_pasangan) {
                Storage::delete('public/pegawai/keluarga/karpeg_pasangan/' . $dataPasangan->file_karpeg_pasangan);
            }
            $file = $request->file('file_karpeg_pasangan');
            $fileName = 'karpeg_pasangan_' . time() . '_' . $pegawai->id . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/keluarga/karpeg_pasangan', $fileName);
            $data['file_karpeg_pasangan'] = $fileName;
        }

        $dataPasangan->update($data);

        // Mencatat aktivitas pembaruan data
        ActivityLogger::log('update', $dataPasangan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data pasangan berhasil diperbarui.',
            'data' => $this->formatDataPasangan($dataPasangan, $pegawai->id, false),
        ]);
    }

    /**
     * Menghapus data pasangan (Soft Delete).
     */
    public function destroy($pegawai_id, $pasangan_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);
        $dataPasangan = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('nama_pasangan')
            ->findOrFail($pasangan_id);

        // Simpan data lama sebelum dihapus untuk log
        $oldData = $dataPasangan->toArray();
        
        // Model sudah menggunakan trait SoftDeletes, jadi ini akan melakukan soft delete
        $dataPasangan->delete();
        
        // Mencatat aktivitas penghapusan data
        ActivityLogger::log('delete', $dataPasangan, $oldData);

        return response()->json(['success' => true, 'message' => 'Data pasangan berhasil dihapus.']);
    }

    /**
     * Helper: Format informasi pegawai.
     */
    private function formatPegawaiInfo($pegawai)
    {
        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama' => $pegawai->nama ?? '-',
            'unit_kerja' => optional($pegawai->unitKerja)->nama_unit ?? 'Tidak Ada',
            'status' => optional($pegawai->statusAktif)->nama_status_aktif ?? '-',
            'jab_akademik' => optional($pegawai->jabatanAkademik)->jabatan_akademik ?? '-',
            'jab_fungsional' => optional(optional($pegawai->dataJabatanFungsional->first())->jabatanFungsional)->nama_jabatan_fungsional ?? '-',
            'jab_struktural' => optional(optional(optional($pegawai->dataJabatanStruktural->first())->jabatanStruktural)->jenisJabatanStruktural)->jenis_jabatan_struktural ?? '-',
            'pendidikan' => optional(optional($pegawai->dataPendidikanFormal->first())->jenjangPendidikan)->jenjang_pendidikan ?? '-',
        ];
    }

    /**
     * Helper: Format data pasangan untuk response JSON.
     */
    protected function formatDataPasangan($dataPasangan, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $dataPasangan->id,
            'nama_pasangan' => $dataPasangan->nama_pasangan,
            'tempat_lahir' => $dataPasangan->tempat_lahir,
            'tgl_lahir' => $dataPasangan->tgl_lahir ? Carbon::parse($dataPasangan->tgl_lahir)->isoFormat('D MMMM Y') : '-',
            'pekerjaan' => $dataPasangan->pekerjaan,
            'kartu_nikah' => $dataPasangan->kartu_nikah,
            'status_pengajuan' => $dataPasangan->status_pengajuan ?? 'draft',
            'pasangan_kerja_dalam_satu_instansi' => $dataPasangan->pasangan_berkerja_dalam_satu_instansi ? 'Ya' : 'Tidak',
            'karpeg_pasangan' => $dataPasangan->karpeg_pasangan,
            'status_kepegawaian' => $dataPasangan->status_kepegawaian,
            'keterangan' => $dataPasangan->keterangan,
            'file_karpeg_url' => $dataPasangan->file_karpeg_pasangan ? url('storage/pegawai/keluarga/karpeg_pasangan/' . $dataPasangan->file_karpeg_pasangan) : null,
            'timestamps' => [
                'tgl_input' => $dataPasangan->tgl_input,
                'tgl_diajukan' => $dataPasangan->tgl_diajukan,
                'tgl_disetujui' => $dataPasangan->tgl_disetujui,
                'tgl_ditolak' => $dataPasangan->tgl_ditolak,
            ]
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-data-pasangan/{$dataPasangan->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-data-pasangan/{$dataPasangan->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-data-pasangan/{$dataPasangan->id}"),
            ];
        }

        return $data;
    }
}
