<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataKemampuanBahasa;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatKemampuanBahasaController extends Controller
{
    /**
     * Menampilkan daftar riwayat kemampuan bahasa untuk pegawai tertentu.
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
        $statusPengajuan = $request->status_pengajuan;

        $query = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai->id)->with(['bahasa']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lembaga', 'like', '%' . $search . '%')
                  ->orWhereHas('bahasa', function ($subq) use ($search) {
                      $subq->where('nama_bahasa', 'like', '%' . $search . '%');
                  });
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataBahasa = $query->orderBy('tahun', 'desc')->paginate($perPage);

        $dataBahasa->getCollection()->transform(function ($item) use ($pegawai_id) {
            return $this->formatDataBahasa($item, $pegawai_id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataBahasa,
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
     * Menyimpan riwayat kemampuan bahasa baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'tahun' => 'required|integer|digits:4',
            'bahasa_id' => 'required|uuid|exists:bahasa,id',
            'nama_lembaga' => 'nullable|string|max:255',
            'kemampuan_mendengar' => 'required|integer|digits_between:1,3',
            'kemampuan_bicara' => 'required|integer|digits_between:1,3',
            'kemampuan_menulis' => 'required|integer|digits_between:1,3',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string'
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
                $fileName = 'bahasa_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $data['file_pendukung'] = $file->storeAs('kemampuan_bahasa', $fileName, 'public');
            }

            $kemampuanBahasa = SimpegDataKemampuanBahasa::create($data);
            ActivityLogger::log('create', $kemampuanBahasa, $kemampuanBahasa->toArray());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Kemampuan Bahasa berhasil ditambahkan.',
                'data' => $this->formatDataBahasa($kemampuanBahasa, $pegawai->id, false),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    public function show($pegawai_id, $riwayat_id)
    {
        $kemampuanBahasa = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai_id)
            ->with(['bahasa'])
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataBahasa($kemampuanBahasa, $pegawai_id, false),
        ]);
    }

    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $kemampuanBahasa = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'tahun' => 'required|integer|digits:4',
            'bahasa_id' => 'required|uuid|exists:bahasa,id',
            'nama_lembaga' => 'nullable|string|max:255',
            'kemampuan_mendengar' => 'required|integer|digits_between:1,3',
            'kemampuan_bicara' => 'required|integer|digits_between:1,3',
            'kemampuan_menulis' => 'required|integer|digits_between:1,3',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $kemampuanBahasa->getOriginal();
        $data = $validator->validated();

        if ($request->hasFile('file_pendukung')) {
            if($kemampuanBahasa->file_pendukung) Storage::disk('public')->delete($kemampuanBahasa->file_pendukung);
            $file = $request->file('file_pendukung');
            $fileName = 'bahasa_' . $pegawai_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_pendukung'] = $file->storeAs('kemampuan_bahasa', $fileName, 'public');
        }

        $kemampuanBahasa->update($data);
        ActivityLogger::log('update', $kemampuanBahasa, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Kemampuan Bahasa berhasil diperbarui.',
            'data' => $this->formatDataBahasa($kemampuanBahasa, $pegawai_id, false),
        ]);
    }

    public function destroy($pegawai_id, $riwayat_id)
    {
        $kemampuanBahasa = SimpegDataKemampuanBahasa::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
        
        DB::beginTransaction();
        try {
            if($kemampuanBahasa->file_pendukung) Storage::disk('public')->delete($kemampuanBahasa->file_pendukung);
            
            $oldData = $kemampuanBahasa->toArray();
            $kemampuanBahasa->delete();
            ActivityLogger::log('delete', $kemampuanBahasa, $oldData);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Riwayat Kemampuan Bahasa berhasil dihapus.']);
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

    protected function formatDataBahasa($bahasa, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $bahasa->id,
            'tahun' => $bahasa->tahun,
            'bahasa' => optional($bahasa->bahasa)->nama_bahasa,
            'kemampuan_mendengar' => $bahasa->kemampuan_mendengar,
            'kemampuan_bicara' => $bahasa->kemampuan_bicara,
            'kemampuan_menulis' => $bahasa->kemampuan_menulis,
            'status_pengajuan' => $bahasa->status_pengajuan ?? 'draft',
            'file_pendukung_url' => $bahasa->file_pendukung ? Storage::url($bahasa->file_pendukung) : null,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-kemampuan-bahasa/{$bahasa->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-kemampuan-bahasa/{$bahasa->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-kemampuan-bahasa/{$bahasa->id}"),
            ];
        }
        return $data;
    }
}
