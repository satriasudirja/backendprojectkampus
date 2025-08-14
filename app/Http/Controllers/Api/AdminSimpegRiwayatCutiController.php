<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegCutiRecord;
use App\Models\SimpegPegawai;
use App\Models\SimpegDaftarCuti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class AdminSimpegRiwayatCutiController extends Controller
{
    /**
     * Menampilkan daftar riwayat cuti untuk pegawai tertentu.
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

        $query = SimpegCutiRecord::where('pegawai_id', $pegawai->id)->with(['jenisCuti']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('alasan_cuti', 'like', '%' . $search . '%')
                  ->orWhere('no_urut_cuti', 'like', '%' . $search . '%')
                  ->orWhereHas('jenisCuti', function ($subq) use ($search) {
                      $subq->where('nama_jenis_cuti', 'like', '%' . $search . '%');
                  });
            });
        }
        
        if ($statusPengajuan && $statusPengajuan !== 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        $dataCuti = $query->orderBy('tgl_mulai', 'desc')->paginate($perPage);

        $dataCuti->getCollection()->transform(function ($item) use ($pegawai_id) {
            return $this->formatDataCuti($item, $pegawai_id, true);
        });
        
        return response()->json([
            'success' => true,
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'data' => $dataCuti,
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
     * Menyimpan riwayat cuti baru untuk pegawai.
     */
    public function store(Request $request, $pegawai_id)
    {
        $pegawai = SimpegPegawai::findOrFail($pegawai_id);

        $validator = Validator::make($request->all(), [
            'jenis_cuti_id' => 'required|uuid|exists:simpeg_daftar_cuti,id',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'alasan_cuti' => 'required|string|max:500',
            'alamat' => 'required|string|max:500',
            'no_telp' => 'required|string|max:20',
            'file_cuti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $data['pegawai_id'] = $pegawai->id;
            $data['no_urut_cuti'] = $this->generateNoUrutCuti($request->jenis_cuti_id);
            $data['jumlah_cuti'] = Carbon::parse($data['tgl_mulai'])->diffInDays(Carbon::parse($data['tgl_selesai'])) + 1;
            
            // PERBAIKAN: Set tgl_diajukan untuk memenuhi constraint NOT NULL di database
            // Jika status 'diajukan', set tanggal sekarang. Jika 'draft', set juga tanggal sekarang sebagai placeholder.
            // Solusi jangka panjang terbaik adalah membuat kolom tgl_diajukan nullable di database.
            $data['tgl_diajukan'] = now();
            if($request->status_pengajuan !== 'diajukan') {
                // Untuk status selain 'diajukan', idealnya ini null.
                // Tapi karena constraint DB, kita isi dengan tanggal input.
                $data['tgl_diajukan'] = now(); 
            }


            if ($request->hasFile('file_cuti')) {
                $file = $request->file('file_cuti');
                $fileName = 'cuti_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                $data['file_cuti'] = $file->storeAs('cuti_files', $fileName, 'public');
            }

            $cuti = SimpegCutiRecord::create($data);
            ActivityLogger::log('create', $cuti, $cuti->toArray());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Riwayat Cuti berhasil ditambahkan.',
                'data' => $this->formatDataCuti($cuti, $pegawai->id, false),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    public function show($pegawai_id, $riwayat_id)
    {
        $cuti = SimpegCutiRecord::where('pegawai_id', $pegawai_id)
            ->with(['jenisCuti'])
            ->findOrFail($riwayat_id);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataCuti($cuti, $pegawai_id, false),
        ]);
    }

    public function update(Request $request, $pegawai_id, $riwayat_id)
    {
        $cuti = SimpegCutiRecord::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
            
        $validator = Validator::make($request->all(), [
            'jenis_cuti_id' => 'required|uuid|exists:simpeg_daftar_cuti,id',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'alasan_cuti' => 'required|string|max:500',
            'alamat' => 'required|string|max:500',
            'no_telp' => 'required|string|max:20',
            'file_cuti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $oldData = $cuti->getOriginal();
        $data = $validator->validated();
        $data['jumlah_cuti'] = Carbon::parse($data['tgl_mulai'])->diffInDays(Carbon::parse($data['tgl_selesai'])) + 1;
        
        // PERBAIKAN: Handle timestamp saat update
        if($data['status_pengajuan'] === 'diajukan' && $cuti->status_pengajuan !== 'diajukan'){
            $data['tgl_diajukan'] = now();
        }

        if ($request->hasFile('file_cuti')) {
            if($cuti->file_cuti) Storage::disk('public')->delete($cuti->file_cuti);
            $file = $request->file('file_cuti');
            $fileName = 'cuti_' . $pegawai_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $data['file_cuti'] = $file->storeAs('cuti_files', $fileName, 'public');
        }

        $cuti->update($data);
        ActivityLogger::log('update', $cuti, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat Cuti berhasil diperbarui.',
            'data' => $this->formatDataCuti($cuti, $pegawai_id, false),
        ]);
    }

    public function destroy($pegawai_id, $riwayat_id)
    {
        $cuti = SimpegCutiRecord::where('pegawai_id', $pegawai_id)->findOrFail($riwayat_id);
        
        DB::beginTransaction();
        try {
            if($cuti->file_cuti) Storage::disk('public')->delete($cuti->file_cuti);
            
            $oldData = $cuti->toArray();
            $cuti->delete();
            ActivityLogger::log('delete', $cuti, $oldData);
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Riwayat Cuti berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data: ' . $e->getMessage()], 500);
        }
    }

    private function generateNoUrutCuti($jenisCutiId)
    {
        $jenisCuti = SimpegDaftarCuti::find($jenisCutiId);
        $kode = optional($jenisCuti)->kode ?? 'CT';
        $year = date('Y');
        $month = date('m');
        $count = SimpegCutiRecord::whereYear('created_at', $year)->count() + 1;
        $sequence = str_pad($count, 4, '0', STR_PAD_LEFT);

        return "{$kode}/{$sequence}/{$month}/{$year}";
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

    protected function formatDataCuti($cuti, $pegawaiId, $includeActions = true)
    {
        $data = [
            'id' => $cuti->id,
            'tgl_input' => Carbon::parse($cuti->created_at)->isoFormat('D MMMM Y'),
            'jenis_cuti' => optional($cuti->jenisCuti)->nama_jenis_cuti,
            'keperluan' => $cuti->alasan_cuti,
            'lama' => $cuti->jumlah_cuti . ' hari',
            'status' => $cuti->status_pengajuan ?? 'draft',
            'file_url' => $cuti->file_cuti ? Storage::url($cuti->file_cuti) : null,
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-cuti/{$cuti->id}"),
                'update_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-cuti/{$cuti->id}"),
                'delete_url' => url("/api/admin/pegawai/{$pegawaiId}/riwayat-cuti/{$cuti->id}"),
            ];
        }
        return $data;
    }
}
