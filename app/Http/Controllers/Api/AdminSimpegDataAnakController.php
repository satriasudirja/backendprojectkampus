<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataKeluargaPegawai;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger; // Asumsi Anda punya service ini
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminSimpegDataAnakController extends Controller
{
    /**
     * Cari pegawai berdasarkan NIP atau Nama.
     * Endpoint ini digunakan untuk mengisi dropdown/autocomplete di frontend.
     */
    public function searchPegawai(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $search = $request->search;

        $pegawai = SimpegPegawai::where('nama', 'like', "%{$search}%")
            ->orWhere('nip', 'like', "%{$search}%")
            ->select('id', 'nip', 'nama')
            ->limit(15)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pegawai,
        ]);
    }

    /**
     * Menampilkan data anak dari seorang pegawai tertentu (untuk Admin).
     * Menerima pegawai_id dari URL.
     */
    public function index(Request $request, $pegawai_id)
    {
        // Eager load relasi untuk info pegawai
        // findOrFail akan otomatis memberikan response 404 jika pegawai tidak ditemukan.
        $pegawai = SimpegPegawai::with([
            'unitKerja', 'statusAktif', 'jabatanAkademik',
            'dataJabatanFungsional' => fn($q) => $q->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1),
            'dataJabatanStruktural' => fn($q) => $q->with('jabatanStruktural.jenisJabatanStruktural')->orderBy('tgl_mulai', 'desc')->limit(1),
            'dataPendidikanFormal' => fn($q) => $q->with('jenjangPendidikan')->orderBy('jenjang_pendidikan_id', 'desc')->limit(1)
        ])->findOrFail($pegawai_id);

        $perPage = $request->per_page ?? 10;
        $searchAnak = $request->search;
        $statusPengajuan = $request->status_pengajuan;

        // Query data anak untuk pegawai yang dipilih
        $query = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke');

        // Filter pencarian anak
        if ($searchAnak) {
            $query->where('nama', 'like', '%' . $searchAnak . '%');
        }

        // Filter status pengajuan
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataAnak = $query->orderBy('anak_ke', 'asc')->paginate($perPage);

        // Transform data untuk menyertakan URL aksi
        $dataAnak->getCollection()->transform(function ($item) use ($pegawai) {
            return $this->formatDataAnak($item, $pegawai->id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataAnak,
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
     * Menyimpan data anak baru untuk pegawai tertentu.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'tempat_lahir' => 'required|string|max:50',
            'tgl_lahir' => 'required|date|before:today',
            'anak_ke' => 'required|integer|min:1',
            'file_akte' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Cek duplikasi anak_ke
        if (SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)->where('anak_ke', $request->anak_ke)->exists()) {
             return response()->json(['success' => false, 'message' => 'Data anak ke-' . $request->anak_ke . ' sudah ada.'], 422);
        }

        $data = $request->except('file_akte');
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now();

        if ($request->hasFile('file_akte')) {
            $file = $request->file('file_akte');
            $fileName = 'akte_' . time() . '_' . $pegawai->id . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/keluarga/akte', $fileName);
            $data['file_akte'] = $fileName;
        }

        $dataAnak = SimpegDataKeluargaPegawai::create($data);

        // ActivityLogger::log('create', $dataAnak, $dataAnak->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Data anak berhasil ditambahkan.',
            'data' => $this->formatDataAnak($dataAnak, $pegawai->id, false),
        ], 201);
    }

    /**
     * Menampilkan detail spesifik data anak.
     */
    public function show($pegawai_id, $anak_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);
        $dataAnak = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->findOrFail($anak_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataAnak($dataAnak, $pegawai->id, false),
        ]);
    }

    /**
     * Update data anak yang ada. Menggunakan POST untuk menghandle file upload.
     */
    public function update(Request $request, $pegawai_id, $anak_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);
        $dataAnak = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->findOrFail($anak_id);
            
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'tempat_lahir' => 'required|string|max:50',
            'tgl_lahir' => 'required|date|before:today',
            'anak_ke' => 'required|integer|min:1',
            'file_akte' => 'nullable|file|mimes:pdf,jpg,jpeg|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Cek duplikasi anak_ke saat update
        if (SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)->where('anak_ke', $request->anak_ke)->where('id', '!=', $anak_id)->exists()) {
             return response()->json(['success' => false, 'message' => 'Data anak ke-' . $request->anak_ke . ' sudah ada.'], 422);
        }

        $oldData = $dataAnak->getOriginal();
        $data = $request->except('file_akte');

        if ($request->hasFile('file_akte')) {
            // Hapus file lama jika ada
            if ($dataAnak->file_akte) {
                Storage::delete('public/pegawai/keluarga/akte/' . $dataAnak->file_akte);
            }
            $file = $request->file('file_akte');
            $fileName = 'akte_' . time() . '_' . $pegawai->id . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/keluarga/akte', $fileName);
            $data['file_akte'] = $fileName;
        }

        $dataAnak->update($data);

        // ActivityLogger::log('update', $dataAnak, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data anak berhasil diperbarui.',
            'data' => $this->formatDataAnak($dataAnak, $pegawai->id, false),
        ]);
    }


    /**
     * Menghapus data anak.
     */
    public function destroy($pegawai_id, $anak_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);
        $dataAnak = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('anak_ke')
            ->findOrFail($anak_id);

        // Hapus file dari storage
        if ($dataAnak->file_akte) {
            Storage::delete('public/pegawai/keluarga/akte/' . $dataAnak->file_akte);
        }
        
        $oldData = $dataAnak->toArray();
        $dataAnak->delete();

        // ActivityLogger::log('delete', $dataAnak, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data anak berhasil dihapus.'
        ]);
    }

    /**
     * Helper: Format informasi pegawai. Diambil dari controller referensi.
     */
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

    /**
     * Helper: Format data anak untuk response JSON.
     */
    protected function formatDataAnak($dataAnak, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $dataAnak->id,
            'nama' => $dataAnak->nama,
            'jenis_kelamin' => $dataAnak->jenis_kelamin,
            'tempat_lahir' => $dataAnak->tempat_lahir,
            'tgl_lahir' => $dataAnak->tgl_lahir ? Carbon::parse($dataAnak->tgl_lahir)->isoFormat('D MMMM Y') : '-',
            'umur' => $dataAnak->tgl_lahir ? Carbon::parse($dataAnak->tgl_lahir)->age . ' tahun' : ($dataAnak->umur ? $dataAnak->umur . ' tahun' : '-'),
            'anak_ke' => $dataAnak->anak_ke,
            'status_pengajuan' => $dataAnak->status_pengajuan ?? 'draft',
            'file_akte_url' => $dataAnak->file_akte ? url('storage/pegawai/keluarga/akte/' . $dataAnak->file_akte) : null,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-data-anak/{$dataAnak->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-data-anak/{$dataAnak->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-data-anak/{$dataAnak->id}"),
            ];
        }

        return $data;
    }
}
