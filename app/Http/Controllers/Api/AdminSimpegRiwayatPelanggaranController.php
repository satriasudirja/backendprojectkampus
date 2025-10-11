<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPelanggaran;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatPelanggaranController extends Controller
{
    /**
     * Menampilkan daftar riwayat pelanggaran untuk pegawai tertentu.
     */
    public function index(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::with([
            'unitKerja', 'statusAktif', 'jabatanFungsional',
            'jabatanStruktural.jenisJabatanStruktural',
            'dataPendidikanFormal.jenjangPendidikan'
        ])->findOrFail($pegawai_id);

        $perPage = $request->per_page ?? 10;
        $search = $request->search;

        $query = SimpegDataPelanggaran::where('pegawai_id', $pegawai->id)
            ->with(['jenisPelanggaran']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_sk', 'like', '%' . $search . '%')
                  ->orWhere('keterangan', 'like', '%' . $search . '%')
                  ->orWhereHas('jenisPelanggaran', function ($subq) use ($search) {
                      $subq->where('nama_pelanggaran', 'like', '%' . $search . '%');
                  });
            });
        }

        $dataPelanggaran = $query->orderBy('tgl_pelanggaran', 'desc')->paginate($perPage);

        $dataPelanggaran->getCollection()->transform(function ($item) use ($pegawai_id) {
            return $this->formatDataPelanggaran($item, $pegawai_id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataPelanggaran,
        ]);
    }

    /**
     * Menyimpan riwayat pelanggaran baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'jenis_pelanggaran_id' => 'required|uuid|exists:simpeg_jenis_pelanggaran,id',
            'tgl_pelanggaran' => 'required|date',
            'no_sk' => 'required|string|max:255',
            'tgl_sk' => 'required|date',
            'keterangan' => 'nullable|string',
            'file_foto' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $data['pegawai_id'] = $pegawai->id;

            if ($request->hasFile('file_foto')) {
                $file = $request->file('file_foto');
                $fileName = 'pelanggaran_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $data['file_foto'] = $file->storeAs('pelanggaran_files', $fileName, 'public');
            }

            $pelanggaran = SimpegDataPelanggaran::create($data);
            ActivityLogger::log('create', $pelanggaran, $pelanggaran->toArray());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Pelanggaran berhasil ditambahkan.',
                'data' => $this->formatDataPelanggaran($pelanggaran, $pegawai->id, false),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    public function show($pegawai_id, $riwayat_id)
    {
        $pelanggaran = SimpegDataPelanggaran::where('pegawai_id', $pegawai_id)
            ->with(['jenisPelanggaran'])
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataPelanggaran($pelanggaran, $pegawai_id, false),
        ]);
    }

    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $pelanggaran = SimpegDataPelanggaran::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'jenis_pelanggaran_id' => 'required|uuid|exists:simpeg_jenis_pelanggaran,id',
            'tgl_pelanggaran' => 'required|date',
            'no_sk' => 'required|string|max:255',
            'tgl_sk' => 'required|date',
            'keterangan' => 'nullable|string',
            'file_foto' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $pelanggaran->getOriginal();
        $data = $validator->validated();

        if ($request->hasFile('file_foto')) {
            if($pelanggaran->file_foto) Storage::disk('public')->delete($pelanggaran->file_foto);
            $file = $request->file('file_foto');
            $fileName = 'pelanggaran_' . $pegawai_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_foto'] = $file->storeAs('pelanggaran_files', $fileName, 'public');
        }

        $pelanggaran->update($data);
        ActivityLogger::log('update', $pelanggaran, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Pelanggaran berhasil diperbarui.',
            'data' => $this->formatDataPelanggaran($pelanggaran, $pegawai_id, false),
        ]);
    }

    public function destroy($pegawai_id, $riwayat_id)
    {
        $pelanggaran = SimpegDataPelanggaran::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
        
        DB::beginTransaction();
        try {
            if($pelanggaran->file_foto) Storage::disk('public')->delete($pelanggaran->file_foto);
            
            $oldData = $pelanggaran->toArray();
            $pelanggaran->delete();
            ActivityLogger::log('delete', $pelanggaran, $oldData);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Riwayat Pelanggaran berhasil dihapus.']);
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
            'jab_fungsional' => optional(optional($pegawai->dataJabatanFungsional->first())->jabatanFungsional)->nama_jabatan_fungsional ?? '-',
            'jab_struktural' => optional(optional(optional($pegawai->dataJabatanStruktural->first())->jabatanStruktural)->jenisJabatanStruktural)->jenis_jabatan_struktural ?? '-',
            'pendidikan' => optional(optional($pegawai->dataPendidikanFormal->first())->jenjangPendidikan)->jenjang_pendidikan ?? '-',
        ];
    }

    protected function formatDataPelanggaran($pelanggaran, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $pelanggaran->id,
            'tgl_pelanggaran' => Carbon::parse($pelanggaran->tgl_pelanggaran)->isoFormat('D MMMM Y'),
            'jenis_pelanggaran' => optional($pelanggaran->jenisPelanggaran)->nama_pelanggaran,
            'no_sk' => $pelanggaran->no_sk,
            'tgl_sk' => Carbon::parse($pelanggaran->tgl_sk)->isoFormat('D MMMM Y'),
            'keterangan' => $pelanggaran->keterangan,
            'file_foto_url' => $pelanggaran->file_foto ? Storage::url($pelanggaran->file_foto) : null,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pelanggaran/{$pelanggaran->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pelanggaran/{$pelanggaran->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-pelanggaran/{$pelanggaran->id}"),
            ];
        }
        return $data;
    }
}
