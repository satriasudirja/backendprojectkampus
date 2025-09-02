<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataJabatanStruktural;
use App\Models\SimpegPegawai;
use App\Models\SimpegJabatanStruktural as MasterJabatanStruktural;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatJabatanStrukturalController extends Controller
{
    /**
     * Menampilkan daftar riwayat jabatan struktural untuk pegawai tertentu.
     */
    public function index(Request $request, $pegawai_id)
    {
        // Eager load semua relasi yang diperlukan untuk info pegawai yang lengkap
        $pegawai = SimpegPegawai::with([
            'unitKerja', 'statusAktif', 'jabatanFungsional',
            'jabatanStruktural.jenisJabatanStruktural',
            'dataPendidikanFormal.jenjangPendidikan'
        ])->findOrFail($pegawai_id);

        $perPage = $request->per_page ?? 10;
        $search = $request->search; // Cari berdasarkan No SK atau Nama Jabatan
        $statusPengajuan = $request->status_pengajuan;

        $query = SimpegDataJabatanStruktural::where('pegawai_id', $pegawai->id)
            ->with(['jabatanStruktural.jenisJabatanStruktural']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_sk', 'like', '%' . $search . '%')
                  ->orWhereHas('jabatanStruktural', function ($q_jabatan) use ($search) {
                      $q_jabatan->where('nama_jabatan', 'like', '%' . $search . '%')
                                ->orWhere('singkatan', 'like', '%' . $search . '%')
                                ->orWhereHas('jenisJabatanStruktural', function ($q_jenis) use ($search) {
                                    $q_jenis->where('jenis_jabatan_struktural', 'like', '%' . $search . '%');
                                });
                  });
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataJabatan = $query->orderBy('tgl_mulai', 'desc')->paginate($perPage);

        $dataJabatan->getCollection()->transform(function ($item) use ($pegawai) {
            return $this->formatDataJabatanStruktural($item, $pegawai->id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataJabatan,
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
     * Menyimpan riwayat jabatan struktural baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'jabatan_struktural_id' => 'required|uuid|exists:simpeg_jabatan_struktural,id',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'nullable|date|after_or_equal:tgl_mulai',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'file_jabatan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now();

        if ($request->hasFile('file_jabatan')) {
            $file = $request->file('file_jabatan');
            $fileName = 'jabatan_struktural_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_jabatan'] = $file->storeAs('jabatan_struktural_files', $fileName, 'public');
        }

        $dataJabatan = SimpegDataJabatanStruktural::create($data);
        ActivityLogger::log('create', $dataJabatan, $dataJabatan->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Jabatan Struktural berhasil ditambahkan.',
            'data' => $this->formatDataJabatanStruktural($dataJabatan, $pegawai->id, false),
        ], 201);
    }

    /**
     * Menampilkan detail spesifik riwayat jabatan struktural.
     */
    public function show($pegawai_id, $riwayat_id)
    {
        $dataJabatan = SimpegDataJabatanStruktural::where('pegawai_id', $pegawai_id)
            ->with(['jabatanStruktural.jenisJabatanStruktural'])
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataJabatanStruktural($dataJabatan, $pegawai_id, false),
        ]);
    }

    /**
     * Update riwayat jabatan struktural yang ada.
     */
    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $dataJabatan = SimpegDataJabatanStruktural::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'jabatan_struktural_id' => 'required|uuid|exists:simpeg_jabatan_struktural,id',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'nullable|date|after_or_equal:tgl_mulai',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'nullable|string|max:255',
            'file_jabatan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $dataJabatan->getOriginal();
        $data = $validator->validated();

        if ($request->hasFile('file_jabatan')) {
            if ($dataJabatan->file_jabatan) {
                Storage::disk('public')->delete($dataJabatan->file_jabatan);
            }
            $file = $request->file('file_jabatan');
            $fileName = 'jabatan_struktural_' . $pegawai_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_jabatan'] = $file->storeAs('jabatan_struktural_files', $fileName, 'public');
        }

        $dataJabatan->update($data);
        ActivityLogger::log('update', $dataJabatan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Jabatan Struktural berhasil diperbarui.',
            'data' => $this->formatDataJabatanStruktural($dataJabatan, $pegawai_id, false),
        ]);
    }

    /**
     * Menghapus riwayat jabatan struktural (Soft Delete).
     */
    public function destroy($pegawai_id, $riwayat_id)
    {
        $dataJabatan = SimpegDataJabatanStruktural::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);

        $oldData = $dataJabatan->toArray();
        $dataJabatan->delete(); // Assuming the model uses SoftDeletes trait
        ActivityLogger::log('delete', $dataJabatan, $oldData);

        return response()->json(['success' => true, 'message' => 'Riwayat Jabatan Struktural berhasil dihapus.']);
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

    protected function formatDataJabatanStruktural($dataJabatan, $pegawaiId, $includeActions = true)
    {
        $namaJabatan = '-';
        if ($jabatan = $dataJabatan->jabatanStruktural) {
             $namaJabatan = optional($jabatan->jenisJabatanStruktural)->jenis_jabatan_struktural ?? $jabatan->nama_jabatan ?? $jabatan->singkatan ?? '-';
        }

        $data = [
            'id' => $dataJabatan->id,
            'jabatan_struktural_id' => $dataJabatan->jabatan_struktural_id,
            'nama_jabatan' => $namaJabatan,
            'tgl_mulai' => $dataJabatan->tgl_mulai,
            'tgl_mulai_formatted' => Carbon::parse($dataJabatan->tgl_mulai)->isoFormat('D MMMM Y'),
            'tgl_selesai' => $dataJabatan->tgl_selesai,
            'tgl_selesai_formatted' => $dataJabatan->tgl_selesai ? Carbon::parse($dataJabatan->tgl_selesai)->isoFormat('D MMMM Y') : '-',
            'no_sk' => $dataJabatan->no_sk,
            'tgl_sk' => $dataJabatan->tgl_sk,
            'pejabat_penetap' => $dataJabatan->pejabat_penetap,
            'status_pengajuan' => $dataJabatan->status_pengajuan ?? 'draft',
            'file_jabatan_url' => $dataJabatan->file_jabatan ? Storage::url($dataJabatan->file_jabatan) : null,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-jabatan-struktural/{$dataJabatan->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-jabatan-struktural/{$dataJabatan->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-jabatan-struktural/{$dataJabatan->id}"),
            ];
        }
        return $data;
    }
}
