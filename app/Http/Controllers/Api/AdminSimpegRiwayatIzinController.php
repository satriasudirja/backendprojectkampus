<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPengajuanIzinDosen as SimpegIzinRecord;
use App\Models\SimpegPegawai;
use App\Models\SimpegJenisIzin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatIzinController extends Controller
{
    /**
     * Menampilkan daftar riwayat izin untuk pegawai tertentu.
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

        $query = SimpegIzinRecord::where('pegawai_id', $pegawai->id)->with(['jenisIzin']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('alasan_izin', 'like', '%' . $search . '%')
                  ->orWhere('no_izin', 'like', '%' . $search . '%')
                  ->orWhereHas('jenisIzin', function ($subq) use ($search) {
                      $subq->where('jenis_izin', 'like', '%' . $search . '%');
                  });
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataIzin = $query->orderBy('tgl_mulai', 'desc')->paginate($perPage);

        $dataIzin->getCollection()->transform(function ($item) use ($pegawai_id) {
            return $this->formatDataIzin($item, $pegawai_id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataIzin,
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
     * Menyimpan riwayat izin baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'jenis_izin_id' => 'required|uuid|exists:simpeg_jenis_izin,id',
            'alasan_izin' => 'required|string|max:500',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $data['pegawai_id'] = $pegawai->id;
            $data['jumlah_izin'] = Carbon::parse($data['tgl_mulai'])->diffInDays(Carbon::parse($data['tgl_selesai'])) + 1;
            
            // Meng-handle tanggal pengajuan berdasarkan status
            if ($data['status_pengajuan'] === 'diajukan') {
                $data['tgl_diajukan'] = now();
            }

            if ($request->hasFile('file_pendukung')) {
                $file = $request->file('file_pendukung');
                $fileName = 'izin_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $data['file_pendukung'] = $file->storeAs('izin_files', $fileName, 'public');
            }

            $izin = SimpegIzinRecord::create($data);
            ActivityLogger::log('create', $izin, $izin->toArray());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Izin berhasil ditambahkan.',
                'data' => $this->formatDataIzin($izin, $pegawai->id, false),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    public function show($pegawai_id, $riwayat_id)
    {
        $izin = SimpegIzinRecord::where('pegawai_id', $pegawai_id)
            ->with(['jenisIzin'])
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataIzin($izin, $pegawai_id, false),
        ]);
    }

    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $izin = SimpegIzinRecord::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'jenis_izin_id' => 'required|uuid|exists:simpeg_jenis_izin,id',
            'alasan_izin' => 'required|string|max:500',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $izin->getOriginal();
        $data = $validator->validated();
        $data['jumlah_izin'] = Carbon::parse($data['tgl_mulai'])->diffInDays(Carbon::parse($data['tgl_selesai'])) + 1;
        
        // Handle tanggal pengajuan saat update
        if($data['status_pengajuan'] === 'diajukan' && $izin->status_pengajuan !== 'diajukan'){
            $data['tgl_diajukan'] = now();
        }

        if ($request->hasFile('file_pendukung')) {
            if($izin->file_pendukung) Storage::disk('public')->delete($izin->file_pendukung);
            $file = $request->file('file_pendukung');
            $fileName = 'izin_' . $pegawai_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_pendukung'] = $file->storeAs('izin_files', $fileName, 'public');
        }

        $izin->update($data);
        ActivityLogger::log('update', $izin, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Izin berhasil diperbarui.',
            'data' => $this->formatDataIzin($izin, $pegawai_id, false),
        ]);
    }

    public function destroy($pegawai_id, $riwayat_id)
    {
        $izin = SimpegIzinRecord::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
        
        DB::beginTransaction();
        try {
            if($izin->file_pendukung) Storage::disk('public')->delete($izin->file_pendukung);
            
            $oldData = $izin->toArray();
            $izin->delete();
            ActivityLogger::log('delete', $izin, $oldData);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Riwayat Izin berhasil dihapus.']);
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

    protected function formatDataIzin($izin, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $izin->id,
            'tgl_input' => Carbon::parse($izin->created_at)->isoFormat('D MMMM Y'),
            'jenis_izin' => optional($izin->jenisIzin)->jenis_izin,
            'keperluan' => $izin->alasan_izin,
            'lama' => $izin->jumlah_izin . ' hari',
            'status' => $izin->status_pengajuan ?? 'draft',
            'file_url' => $izin->file_pendukung ? Storage::url($izin->file_pendukung) : null,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-izin/{$izin->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-izin/{$izin->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-izin/{$izin->id}"),
            ];
        }
        return $data;
    }
}
