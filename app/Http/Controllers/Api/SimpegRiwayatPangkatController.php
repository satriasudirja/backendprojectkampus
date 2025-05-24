<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPangkat;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class SimpegRiwayatPangkatController extends Controller
{
    // Get all riwayat pangkat (for admin)
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $status = $request->status;

        $query = SimpegDataPangkat::with([
            'pegawai',
            'pangkat',
            'jenisKenaikanPangkat',
            'jenisSk'
        ]);

        // Filter by search
        if ($search) {
            $query->whereHas('pegawai', function($q) use ($search) {
                $q->where('nip', 'like', '%'.$search.'%')
                  ->orWhere('nama', 'like', '%'.$search.'%');
            });
        }

        // Filter by status pengajuan
        if ($status && $status != 'Semua') {
            $query->where('status_pengajuan', $status);
        }

        $riwayat = $query->orderBy('tmt_pangkat', 'desc')
                        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $riwayat->map(function ($item) {
                return $this->formatRiwayatPangkat($item);
            }),
            'pagination' => [
                'current_page' => $riwayat->currentPage(),
                'per_page' => $riwayat->perPage(),
                'total' => $riwayat->total(),
                'last_page' => $riwayat->lastPage()
            ]
        ]);
    }

    // Get riwayat pangkat by pegawai ID
    public function getByPegawai($pegawaiId)
    {
        $pegawai = SimpegPegawai::find($pegawaiId);

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan'
            ], 404);
        }

        $riwayat = $pegawai->dataPangkat()
            ->with(['pangkat', 'jenisKenaikanPangkat', 'jenisSk'])
            ->orderBy('tmt_pangkat', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'pegawai' => [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'jabatan_akademik' => $pegawai->jabatanAkademik->nama_jabatan ?? '-',
                'jabatan_fungsional' => $pegawai->dataJabatanFungsional->first()->jabatanFungsional->nama_jabatan ?? '-',
                'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
                'status' => $pegawai->statusAktif->nama_status_aktif ?? '-'
            ],
            'data' => $riwayat->map(function ($item) {
                return $this->formatRiwayatPangkat($item);
            })
        ]);
    }

    // Get detail riwayat pangkat
    public function show($id)
    {
        $riwayat = SimpegDataPangkat::with([
            'pegawai',
            'pangkat',
            'jenisKenaikanPangkat',
            'jenisSk'
        ])->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pangkat tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatPangkat($riwayat)
        ]);
    }

    // Store new riwayat pangkat
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'pangkat_id' => 'required|exists:simpeg_master_pangkat,id',
            'jenis_kenaikan_pangkat_id' => 'required|exists:simpeg_jenis_kenaikan_pangkat,id',
            'jenis_sk_id' => 'required|exists:simpeg_daftar_jenis_sk,id',
            'tmt_pangkat' => 'required|date',
            'no_sk' => 'required|string|max:50',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'required|string|max:100',
            'masa_kerja_tahun' => 'required|string|max:2',
            'masa_kerja_bulan' => 'required|string|max:2',
            'file_pangkat' => 'nullable|file|mimes:pdf|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'is_aktif' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except('file_pangkat');

        // Handle file upload
        if ($request->hasFile('file_pangkat')) {
            $file = $request->file('file_pangkat');
            $fileName = 'pangkat_'.time().'_'.$request->pegawai_id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/pangkat', $fileName);
            $data['file_pangkat'] = $fileName;
        }

        // Jika diaktifkan, nonaktifkan yang lain
        if ($request->is_aktif) {
            SimpegDataPangkat::where('pegawai_id', $request->pegawai_id)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false]);
        }

        $riwayat = SimpegDataPangkat::create($data);

        ActivityLogger::log('create', $riwayat, $riwayat->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatPangkat($riwayat),
            'message' => 'Riwayat pangkat berhasil ditambahkan'
        ], 201);
    }

    // Update riwayat pangkat
    public function update(Request $request, $id)
    {
        $riwayat = SimpegDataPangkat::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pangkat tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pangkat_id' => 'sometimes|exists:simpeg_master_pangkat,id',
            'jenis_kenaikan_pangkat_id' => 'sometimes|exists:simpeg_jenis_kenaikan_pangkat,id',
            'jenis_sk_id' => 'sometimes|exists:simpeg_daftar_jenis_sk,id',
            'tmt_pangkat' => 'sometimes|date',
            'no_sk' => 'sometimes|string|max:50',
            'tgl_sk' => 'sometimes|date',
            'pejabat_penetap' => 'sometimes|string|max:100',
            'masa_kerja_tahun' => 'sometimes|string|max:2',
            'masa_kerja_bulan' => 'sometimes|string|max:2',
            'file_pangkat' => 'nullable|file|mimes:pdf|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            'is_aktif' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $riwayat->getOriginal();
        $data = $request->except('file_pangkat');

        // Handle file upload
        if ($request->hasFile('file_pangkat')) {
            // Hapus file lama jika ada
            if ($riwayat->file_pangkat) {
                Storage::delete('public/pegawai/pangkat/'.$riwayat->file_pangkat);
            }

            $file = $request->file('file_pangkat');
            $fileName = 'pangkat_'.time().'_'.$riwayat->pegawai_id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/pangkat', $fileName);
            $data['file_pangkat'] = $fileName;
        }

        // Jika diaktifkan, nonaktifkan yang lain
        if ($request->has('is_aktif') && $request->is_aktif) {
            SimpegDataPangkat::where('pegawai_id', $riwayat->pegawai_id)
                ->where('id', '!=', $id)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false]);
        }

        $riwayat->update($data);

        ActivityLogger::log('update', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatPangkat($riwayat),
            'message' => 'Riwayat pangkat berhasil diperbarui'
        ]);
    }

    // Delete riwayat pangkat
    public function destroy($id)
    {
        $riwayat = SimpegDataPangkat::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pangkat tidak ditemukan'
            ], 404);
        }

        // Hapus file jika ada
        if ($riwayat->file_pangkat) {
            Storage::delete('public/pegawai/pangkat/'.$riwayat->file_pangkat);
        }

        $oldData = $riwayat->toArray();
        $riwayat->delete();

        ActivityLogger::log('delete', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pangkat berhasil dihapus'
        ]);
    }

    // Batch update status
    public function batchUpdateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        SimpegDataPangkat::whereIn('id', $request->ids)
            ->update(['status_pengajuan' => $request->status_pengajuan]);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui'
        ]);
    }

    // Batch delete
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $riwayat = SimpegDataPangkat::whereIn('id', $request->ids)->get();

        // Hapus file terkait
        foreach ($riwayat as $item) {
            if ($item->file_pangkat) {
                Storage::delete('public/pegawai/pangkat/'.$item->file_pangkat);
            }
            ActivityLogger::log('delete', $item, $item->toArray());
        }

        SimpegDataPangkat::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat pangkat berhasil dihapus'
        ]);
    }

    // Update status pengajuan
    public function updateStatusPengajuan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $riwayat = SimpegDataPangkat::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pangkat tidak ditemukan'
            ], 404);
        }

        $oldData = $riwayat->getOriginal();
        $riwayat->update(['status_pengajuan' => $request->status_pengajuan]);

        ActivityLogger::log('update', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui'
        ]);
    }

    // Format response
    protected function formatRiwayatPangkat($riwayat)
    {
        return [
            'id' => $riwayat->id,
            'pegawai_id' => $riwayat->pegawai_id,
            'pegawai' => [
                'nip' => $riwayat->pegawai->nip ?? null,
                'nama' => $riwayat->pegawai->nama ?? null
            ],
            'tmt_pangkat' => $riwayat->tmt_pangkat,
            'no_sk' => $riwayat->no_sk,
            'tgl_sk' => $riwayat->tgl_sk,
            'pejabat_penetap' => $riwayat->pejabat_penetap,
            'pangkat' => $riwayat->pangkat ? [
                'id' => $riwayat->pangkat->id,
                'nama' => $riwayat->pangkat->nama_golongan,
                'golongan' => $riwayat->pangkat->pangkat
            ] : null,
            'jenis_kenaikan' => $riwayat->jenisKenaikanPangkat ? [
                'id' => $riwayat->jenisKenaikanPangkat->id,
                'nama' => $riwayat->jenisKenaikanPangkat->nama_jenis_kenaikan_pangkat
            ] : null,
            'jenis_sk' => $riwayat->jenisSk ? [
                'id' => $riwayat->jenisSk->id,
                'nama' => $riwayat->jenisSk->nama_jenis_sk
            ] : null,
            'masa_kerja' => [
                'tahun' => $riwayat->masa_kerja_tahun,
                'bulan' => $riwayat->masa_kerja_bulan
            ],
            'status' => [
                'is_aktif' => $riwayat->is_aktif,
                'pengajuan' => $riwayat->status_pengajuan
            ],
            'dokumen' => $riwayat->file_pangkat ? [
                'nama_file' => $riwayat->file_pangkat,
                'url' => url('storage/pegawai/pangkat/'.$riwayat->file_pangkat)
            ] : null,
            'created_at' => $riwayat->created_at,
            'updated_at' => $riwayat->updated_at
        ];
    }
}