<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataOrganisasi;
use App\Models\SimpegPegawai;
use App\Models\SimpegDataPendukung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;
use Illuminate\Support\Str;

class AdminSimpegRiwayatOrganisasiController extends Controller
{
    /**
     * Menampilkan daftar riwayat organisasi untuk pegawai tertentu.
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

        $query = SimpegDataOrganisasi::where('pegawai_id', $pegawai->id);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_organisasi', 'like', '%' . $search . '%')
                  ->orWhere('jabatan_dalam_organisasi', 'like', '%' . $search . '%');
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataOrganisasi = $query->orderBy('periode_mulai', 'desc')->paginate($perPage);

        $dataOrganisasi->getCollection()->transform(function ($item) use ($pegawai_id) {
            return $this->formatDataOrganisasi($item, $pegawai_id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataOrganisasi,
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
     * Menyimpan riwayat organisasi baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'required|string|max:255',
            'jabatan_dalam_organisasi' => 'required|string|max:255',
            'periode_mulai' => 'required|date',
            'periode_selesai' => 'nullable|date|after_or_equal:periode_mulai',
            'lingkup' => 'required|in:Lokal,Nasional,Internasional,Lainnya',
            'alamat_organisasi' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'refleksi' => 'nullable|string',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'file_organisasi' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $data['pegawai_id'] = $pegawai->id;
            $data['tgl_input'] = now();
            
            // PERBAIKAN: Konversi lingkup ke format database (lowercase)
            $data['jenis_organisasi'] = Str::lower($data['lingkup']);
            $data['tempat_organisasi'] = $data['alamat_organisasi'] ?? null;
            $data['keterangan'] = $data['refleksi'] ?? null;
            unset($data['lingkup'], $data['alamat_organisasi'], $data['refleksi']);

            if ($request->hasFile('file_organisasi')) {
                $file = $request->file('file_organisasi');
                $fileName = 'organisasi_' . $pegawai->id . '_' . time() . '_' . $file->getClientOriginalName();
                $data['file_dokumen'] = $file->storeAs('organisasi_files', $fileName, 'public');
            }

            $riwayatOrganisasi = SimpegDataOrganisasi::create($data);
            
            ActivityLogger::log('create', $riwayatOrganisasi, $riwayatOrganisasi->toArray());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Organisasi berhasil ditambahkan.',
                'data' => $this->formatDataOrganisasi($riwayatOrganisasi, $pegawai->id, false),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    public function show($pegawai_id, $riwayat_id)
    {
        $organisasi = SimpegDataOrganisasi::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
        return response()->json(['success' => true, 'data' => $this->formatDataOrganisasi($organisasi, $pegawai_id, false)]);
    }

    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $riwayatOrganisasi = SimpegDataOrganisasi::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'required|string|max:255',
            'jabatan_dalam_organisasi' => 'required|string|max:255',
            'periode_mulai' => 'required|date',
            'periode_selesai' => 'nullable|date|after_or_equal:periode_mulai',
            'lingkup' => 'required|in:Lokal,Nasional,Internasional,Lainnya',
            'alamat_organisasi' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'refleksi' => 'nullable|string',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'file_organisasi' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $riwayatOrganisasi->getOriginal();
        $data = $validator->validated();
        
        // PERBAIKAN: Konversi lingkup ke format database (lowercase)
        $data['jenis_organisasi'] = Str::lower($data['lingkup']);
        $data['tempat_organisasi'] = $data['alamat_organisasi'] ?? null;
        $data['keterangan'] = $data['refleksi'] ?? null;
        unset($data['lingkup'], $data['alamat_organisasi'], $data['refleksi']);

        if ($request->hasFile('file_organisasi')) {
            if($riwayatOrganisasi->file_dokumen) Storage::disk('public')->delete($riwayatOrganisasi->file_dokumen);
            $file = $request->file('file_organisasi');
            $fileName = 'organisasi_' . $pegawai_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_dokumen'] = $file->storeAs('organisasi_files', $fileName, 'public');
        }

        $riwayatOrganisasi->update($data);
        ActivityLogger::log('update', $riwayatOrganisasi, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Organisasi berhasil diperbarui.',
            'data' => $this->formatDataOrganisasi($riwayatOrganisasi, $pegawai_id, false),
        ]);
    }

    public function destroy($pegawai_id, $riwayat_id)
    {
        $riwayatOrganisasi = SimpegDataOrganisasi::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
        
        DB::beginTransaction();
        try {
            if($riwayatOrganisasi->file_dokumen) Storage::disk('public')->delete($riwayatOrganisasi->file_dokumen);
            
            $oldData = $riwayatOrganisasi->toArray();
            $riwayatOrganisasi->delete();
            ActivityLogger::log('delete', $riwayatOrganisasi, $oldData);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Riwayat Organisasi berhasil dihapus.']);
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

    protected function formatDataOrganisasi($organisasi, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $organisasi->id,
            'tgl_mulai' => Carbon::parse($organisasi->periode_mulai)->isoFormat('D MMMM Y'),
            'tgl_selesai' => $organisasi->periode_selesai ? Carbon::parse($organisasi->periode_selesai)->isoFormat('D MMMM Y') : 'Sekarang',
            'nama_organisasi' => $organisasi->nama_organisasi,
            'jabatan' => $organisasi->jabatan_dalam_organisasi,
            'lingkup' => ucfirst($organisasi->jenis_organisasi), // Tampilkan dengan huruf besar di awal
            'status_pengajuan' => $organisasi->status_pengajuan ?? 'draft',
            'file_url' => $organisasi->file_dokumen ? Storage::url($organisasi->file_dokumen) : null,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-organisasi/{$organisasi->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-organisasi/{$organisasi->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-organisasi/{$organisasi->id}"),
            ];
        }
        return $data;
    }
}
