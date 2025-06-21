<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataKeluargaPegawai;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\ActivityLogger; // Pastikan namespace ini benar

class AdminSimpegDataOrangTuaController extends Controller
{
    /**
     * Menampilkan data orang tua dari seorang pegawai tertentu.
     */
    public function index(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::with([
            'unitKerja', 'statusAktif', 'jabatanAkademik'
        ])->findOrFail($pegawai_id);

        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $statusPengajuan = $request->status_pengajuan;

        // Query data orang tua (Ayah/Ibu) untuk pegawai yang dipilih
        $query = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereNotNull('status_orangtua');

        if ($search) {
            $query->where('nama', 'like', '%' . $search . '%');
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Urutkan berdasarkan jenis orang tua (Ayah dulu, baru Ibu)
        $dataOrangTua = $query->orderByRaw("FIELD(status_orangtua, 'Ayah', 'Ibu')")->paginate($perPage);

        $dataOrangTua->getCollection()->transform(function ($item) use ($pegawai) {
            return $this->formatDataOrangTua($item, $pegawai->id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataOrangTua,
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
     * Menyimpan data orang tua baru untuk pegawai tertentu.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'status_orangtua' => 'nullable|string|max:255',
            'tempat_lahir' => 'required|string|max:50',
            'tgl_lahir' => 'required|date|before:today',
            'alamat' => 'nullable|string|max:255',
            'telepon' => 'nullable|string|max:20',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Validasi agar tidak ada duplikasi jenis orang tua (satu Ayah, satu Ibu)
        $existingParent = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->where('status_orangtua', $request->status_orangtua)
            ->first();

        if ($existingParent) {
            return response()->json([
                'success' => false, 
                'message' => 'Data ' . $request->status_orangtua . ' untuk pegawai ini sudah ada.'
            ], 422);
        }

        $data = $request->all();
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now();

        $dataOrangTua = SimpegDataKeluargaPegawai::create($data);

        ActivityLogger::log('create', $dataOrangTua, $dataOrangTua->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Data orang tua berhasil ditambahkan.',
            'data' => $this->formatDataOrangTua($dataOrangTua, $pegawai->id, false),
        ], 201);
    }

    /**
     * Menampilkan detail spesifik data orang tua.
     */
    public function show($pegawai_id, $orangtua_id)
    {
        $dataOrangTua = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai_id)
            ->whereNotNull('status_orangtua')
            ->findOrFail($orangtua_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataOrangTua($dataOrangTua, $pegawai_id, false),
        ]);
    }

    /**
     * Update data orang tua yang ada.
     */
    public function update(Request $request, $pegawai_id, $orangtua_id)
    {
        $dataOrangTua = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai_id)
            ->whereNotNull('status_orangtua')
            ->findOrFail($orangtua_id);
            
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:100',
            'status_orangtua' => 'nullable|string|max:255',
            'tempat_lahir' => 'required|string|max:50',
            'tgl_lahir' => 'required|date|before:today',
            'alamat' => 'nullable|string|max:255',
            'telepon' => 'nullable|string|max:20',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Validasi duplikasi jika jenis orang tua diubah
        $existingParent = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai_id)
            ->where('status_orangtua', $request->status_orangtua)
            ->where('id', '!=', $orangtua_id)
            ->first();

        if ($existingParent) {
            return response()->json([
                'success' => false, 
                'message' => 'Data ' . $request->status_orangtua . ' untuk pegawai ini sudah ada.'
            ], 422);
        }
        
        $oldData = $dataOrangTua->getOriginal();
        $dataOrangTua->update($request->all());
        ActivityLogger::log('update', $dataOrangTua, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data orang tua berhasil diperbarui.',
            'data' => $this->formatDataOrangTua($dataOrangTua, $pegawai_id, false),
        ]);
    }

    /**
     * Menghapus data orang tua (Soft Delete).
     */
    public function destroy($pegawai_id, $orangtua_id)
    {
        $dataOrangTua = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai_id)
            ->whereNotNull('status_orangtua')
            ->findOrFail($orangtua_id);

        $oldData = $dataOrangTua->toArray();
        $dataOrangTua->delete();
        ActivityLogger::log('delete', $dataOrangTua, $oldData);

        return response()->json(['success' => true, 'message' => 'Data orang tua berhasil dihapus.']);
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

    protected function formatDataOrangTua($dataOrangTua, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $dataOrangTua->id,
            'nama' => $dataOrangTua->nama,
            'jenis_orang_tua' => $dataOrangTua->status_orangtua,
            'tempat_lahir' => $dataOrangTua->tempat_lahir,
            'tgl_lahir' => $dataOrangTua->tgl_lahir ? Carbon::parse($dataOrangTua->tgl_lahir)->isoFormat('D MMMM Y') : '-',
            'alamat' => $dataOrangTua->alamat,
            'telepon' => $dataOrangTua->telepon,
            'status_pengajuan' => $dataOrangTua->status_pengajuan ?? 'draft',
            'keterangan' => $dataOrangTua->keterangan,
            'timestamps' => [
                'tgl_input' => $dataOrangTua->tgl_input,
            ]
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-data-orang-tua/{$dataOrangTua->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-data-orang-tua/{$dataOrangTua->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-data-orang-tua/{$dataOrangTua->id}"),
            ];
        }
        return $data;
    }
}
