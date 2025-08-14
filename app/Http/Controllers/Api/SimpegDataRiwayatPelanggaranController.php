<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPelanggaran;
use App\Models\SimpegJenisPelanggaran;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataRiwayatPelanggaranController extends Controller
{
    /**
     * Get all data riwayat pelanggaran for logged in pegawai.
     */
    public function index(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized - Silakan login terlebih dahulu'], 401);
        }

        $pegawai = Auth::user()->load([
            'unitKerja', 'statusAktif', 'jabatanAkademik',
            'dataJabatanFungsional' => fn($q) => $q->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1),
            'dataJabatanStruktural' => fn($q) => $q->with('jabatanStruktural.jenisJabatanStruktural')->orderBy('tgl_mulai', 'desc')->limit(1),
            'dataPendidikanFormal' => fn($q) => $q->with('jenjangPendidikan')->orderBy('jenjang_pendidikan_id', 'desc')->limit(1)
        ]);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan'], 404);
        }

        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $jenisPelanggaranId = $request->jenis_pelanggaran_id;

        $query = SimpegDataPelanggaran::where('pegawai_id', $pegawai->id)
            ->with(['jenisPelanggaran']);

        // Filter by search (using the global search scope from the model)
        if ($search) {
            $query->globalSearch($search);
        }

        // Filter by jenis pelanggaran
        if ($jenisPelanggaranId && $jenisPelanggaranId != 'semua') {
             $query->filterByJenisPelanggaran($jenisPelanggaranId);
        }
        
        $dataRiwayatPelanggaran = $query->orderBy('tgl_pelanggaran', 'desc')->paginate($perPage);

        $dataRiwayatPelanggaran->getCollection()->transform(function ($item) {
            return $this->formatDataPelanggaran($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataRiwayatPelanggaran,
            'empty_data' => $dataRiwayatPelanggaran->isEmpty(),
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'filters' => [
                'jenis_pelanggaran' => SimpegJenisPelanggaran::select('id', 'nama_pelanggaran as nama')->orderBy('nama_pelanggaran')->get()->toArray()
            ],
            'table_columns' => [
                ['field' => 'tgl_pelanggaran', 'label' => 'Tgl. Pelanggaran', 'sortable' => true],
                ['field' => 'jenis_pelanggaran', 'label' => 'Jenis Pelanggaran', 'sortable' => true],
                ['field' => 'no_sk', 'label' => 'No. SK', 'sortable' => true],
                ['field' => 'tgl_sk', 'label' => 'Tgl. SK', 'sortable' => true],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_url' => url("/api/dosen/riwayatpelanggarandosen"),
        ]);
    }

    /**
     * Get detail data riwayat pelanggaran.
     */
    public function show($id)
    {
        $pegawai = Auth::user();
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan'], 404);
        }

        $dataRiwayatPelanggaran = SimpegDataPelanggaran::where('pegawai_id', $pegawai->id)
            ->with(['jenisPelanggaran'])
            ->find($id);

        if (!$dataRiwayatPelanggaran) {
            return response()->json(['success' => false, 'message' => 'Data riwayat pelanggaran tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPelanggaran($dataRiwayatPelanggaran)
        ]);
    }

    /**
     * Store new data riwayat pelanggaran.
     */
    public function store(Request $request)
    {
        $pegawai = Auth::user();
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'jenis_pelanggaran_id' => 'required|uuid|exists:simpeg_jenis_pelanggaran,id',
            'tgl_pelanggaran' => 'required|date|before_or_equal:today',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date|before_or_equal:today',
            'keterangan' => 'nullable|string',
            'file_foto' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->except(['file_foto']);
        $data['pegawai_id'] = $pegawai->id;

        if ($request->hasFile('file_foto')) {
            $file = $request->file('file_foto');
            $fileName = 'pelanggaran_'.time().'_'.$pegawai->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/pelanggaran/foto', $fileName);
            $data['file_foto'] = $fileName;
        }

        $dataRiwayatPelanggaran = SimpegDataPelanggaran::create($data);
        ActivityLogger::log('create', $dataRiwayatPelanggaran, $dataRiwayatPelanggaran->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPelanggaran($dataRiwayatPelanggaran->load('jenisPelanggaran')),
            'message' => 'Data riwayat pelanggaran berhasil disimpan'
        ], 201);
    }

    /**
     * Update data riwayat pelanggaran.
     * Menggunakan POST request dengan _method: 'PUT' untuk mengakomodasi file upload.
     */
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan'], 404);
        }

        $dataRiwayatPelanggaran = SimpegDataPelanggaran::where('pegawai_id', $pegawai->id)->find($id);
        if (!$dataRiwayatPelanggaran) {
            return response()->json(['success' => false, 'message' => 'Data riwayat pelanggaran tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'jenis_pelanggaran_id' => 'sometimes|required|uuid|exists:simpeg_jenis_pelanggaran,id',
            'tgl_pelanggaran' => 'sometimes|required|date|before_or_equal:today',
            'no_sk' => 'sometimes|required|string|max:100',
            'tgl_sk' => 'sometimes|required|date|before_or_equal:today',
            'keterangan' => 'nullable|string',
            'file_foto' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $dataRiwayatPelanggaran->getOriginal();
        $data = $request->except(['file_foto', '_method']);

        if ($request->hasFile('file_foto')) {
            // Hapus file lama jika ada
            if ($dataRiwayatPelanggaran->file_foto) {
                Storage::delete('public/pegawai/pelanggaran/foto/'.$dataRiwayatPelanggaran->file_foto);
            }

            $file = $request->file('file_foto');
            $fileName = 'pelanggaran_'.time().'_'.$pegawai->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/pelanggaran/foto', $fileName);
            $data['file_foto'] = $fileName;
        }

        $dataRiwayatPelanggaran->update($data);
        ActivityLogger::log('update', $dataRiwayatPelanggaran, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPelanggaran($dataRiwayatPelanggaran->load('jenisPelanggaran')),
            'message' => 'Data riwayat pelanggaran berhasil diperbarui'
        ]);
    }

    /**
     * Delete data riwayat pelanggaran.
     */
    public function destroy($id)
    {
        $pegawai = Auth::user();
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan'], 404);
        }

        $dataRiwayatPelanggaran = SimpegDataPelanggaran::where('pegawai_id', $pegawai->id)->find($id);
        if (!$dataRiwayatPelanggaran) {
            return response()->json(['success' => false, 'message' => 'Data riwayat pelanggaran tidak ditemukan'], 404);
        }

        if ($dataRiwayatPelanggaran->file_foto) {
            Storage::delete('public/pegawai/pelanggaran/foto/'.$dataRiwayatPelanggaran->file_foto);
        }

        $oldData = $dataRiwayatPelanggaran->toArray();
        $dataRiwayatPelanggaran->delete();
        ActivityLogger::log('delete', $dataRiwayatPelanggaran, $oldData);

        return response()->json(['success' => true, 'message' => 'Data riwayat pelanggaran berhasil dihapus']);
    }
    
    /**
     * Batch delete data riwayat pelanggaran.
     */
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_pelanggaran,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $pegawai = Auth::user();
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan'], 404);
        }

        $pelanggaranList = SimpegDataPelanggaran::where('pegawai_id', $pegawai->id)->whereIn('id', $request->ids)->get();

        foreach ($pelanggaranList as $pelanggaran) {
            if ($pelanggaran->file_foto) {
                Storage::delete('public/pegawai/pelanggaran/foto/'.$pelanggaran->file_foto);
            }
            $pelanggaran->delete();
        }

        return response()->json([
            'success' => true, 
            'message' => 'Berhasil menghapus ' . $pelanggaranList->count() . ' data riwayat pelanggaran'
        ]);
    }
    
    /**
     * Get list jenis pelanggaran for dropdown.
     */
    public function getJenisPelanggaran()
    {
        $jenisList = SimpegJenisPelanggaran::select('id', 'nama_pelanggaran as nama')->orderBy('nama_pelanggaran')->get();
        return response()->json(['success' => true, 'data' => $jenisList]);
    }

    /**
     * Helper: Format pegawai info. (Copied from reference)
     */
    private function formatPegawaiInfo($pegawai)
    {
        $jabatanAkademikNama = $pegawai->jabatanAkademik->jabatan_akademik ?? '-';
        $jabatanFungsionalNama = $pegawai->dataJabatanFungsional->first()->jabatanFungsional->nama_jabatan_fungsional ?? '-';
        $jabatanStrukturalNama = $pegawai->dataJabatanStruktural->first()->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural ?? '-';
        $jenjangPendidikanNama = $pegawai->dataPendidikanFormal->first()->jenjangPendidikan->jenjang_pendidikan ?? '-';
        $unitKerjaNama = $pegawai->unitKerja->nama_unit ?? 'Tidak Ada';

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip ?? '-',
            'nama' => $pegawai->nama ?? '-',
            'unit_kerja' => $unitKerjaNama,
            'status' => $pegawai->statusAktif ? $pegawai->statusAktif->nama_status_aktif : '-',
            'jab_akademik' => $jabatanAkademikNama,
            'jab_fungsional' => $jabatanFungsionalNama,
            'jab_struktural' => $jabatanStrukturalNama,
            'pendidikan' => $jenjangPendidikanNama
        ];
    }

    /**
     * Helper: Format data riwayat pelanggaran response.
     */
    protected function formatDataPelanggaran($item, $includeActions = true)
    {
        $data = [
            'id' => $item->id,
            'pegawai_id' => $item->pegawai_id,
            'jenis_pelanggaran_id' => $item->jenis_pelanggaran_id,
            'jenis_pelanggaran' => $item->jenisPelanggaran ? $item->jenisPelanggaran->nama_pelanggaran : '-',
            'tgl_pelanggaran' => $item->tgl_pelanggaran->format('Y-m-d'),
            'no_sk' => $item->no_sk,
            'tgl_sk' => $item->tgl_sk->format('Y-m-d'),
            'keterangan' => $item->keterangan,
            'dokumen' => $item->file_foto ? [
                'nama_file' => $item->file_foto,
                'url' => url('storage/pegawai/pelanggaran/foto/'.$item->file_foto)
            ] : null,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/dosen/riwayatpelanggarandosen/{$item->id}"),
                'update_url' => url("/api/dosen/riwayatpelanggarandosen/{$item->id}"),
                'delete_url' => url("/api/dosen/riwayatpelanggarandosen/{$item->id}"),
            ];
        }

        return $data;
    }
}