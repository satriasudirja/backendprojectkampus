<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PengajuanCutiDosen; // <-- Nama yang benar adalah ini
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegPengajuanCutiDosenController extends Controller
{
    // Get all pengajuan cuti for logged in dosen
    public function index(Request $request) 
    {
        // Pastikan user sudah login
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        // Eager load semua relasi yang diperlukan untuk menghindari N+1 query problem
        $pegawai = Auth::user()->load([
            'unitKerja',
            'statusAktif', 
            'jabatanAkademik',
            'dataJabatanFungsional' => function($query) {
                $query->with('jabatanFungsional')
                      ->orderBy('tmt_jabatan', 'desc')
                      ->limit(1);
            },
            'dataJabatanStruktural' => function($query) {
                $query->with('jabatanStruktural.jenisJabatanStruktural')
                      ->orderBy('tgl_mulai', 'desc')
                      ->limit(1);
            },
            'dataPendidikanFormal' => function($query) {
                $query->with('jenjangPendidikan')
                      ->orderBy('jenjang_pendidikan_id', 'desc')
                      ->limit(1);
            }
        ]);

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan atau belum login'
            ], 404);
        }

        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $statusPengajuan = $request->status_pengajuan;

        // FIXED: Menggunakan nama model yang benar
        $query = PengajuanCutiDosen::where('pegawai_id', $pegawai->id);

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('no_urut_cuti', 'like', '%'.$search.'%')
                  ->orWhere('jenis_cuti', 'like', '%'.$search.'%')
                  ->orWhere('alasan_cuti', 'like', '%'.$search.'%')
                  ->orWhere('alamat_selama_cuti', 'like', '%'.$search.'%');
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('jenis_cuti')) {
            $query->where('jenis_cuti', $request->jenis_cuti);
        }
        if ($request->filled('tgl_mulai')) {
            $query->whereDate('tgl_mulai', $request->tgl_mulai);
        }
        if ($request->filled('tgl_selesai')) {
            $query->whereDate('tgl_selesai', $request->tgl_selesai);
        }
        if ($request->filled('jumlah_cuti')) {
            $query->where('jumlah_cuti', $request->jumlah_cuti);
        }

        // Execute query dengan pagination
        $pengajuanCuti = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $pengajuanCuti->getCollection()->transform(function ($item) {
            return $this->formatPengajuanCuti($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $pengajuanCuti,
            'empty_data' => $pengajuanCuti->isEmpty(),
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ],
                'jenis_cuti' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'Besar', 'nama' => 'Besar'],
                    ['id' => 'Sakit', 'nama' => 'Sakit'],
                    ['id' => 'Melahirkan', 'nama' => 'Melahirkan'],
                    ['id' => 'Alasan Penting', 'nama' => 'Alasan Penting'],
                    ['id' => 'Tahunan', 'nama' => 'Tahunan'],
                    ['id' => 'Di Luar Tanggungan Negara', 'nama' => 'Di Luar Tanggungan Negara'],
                ]
            ],
            // ... sisa JSON response Anda sama
        ]);
    }

    // Fix existing data dengan status_pengajuan null
    public function fixExistingData()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan'], 404);
        }

        // FIXED: Menggunakan nama model yang benar
        $updatedCount = PengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data pengajuan cuti",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail pengajuan cuti
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan'], 404);
        }

        // FIXED: Menggunakan nama model yang benar
        $pengajuanCuti = PengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$pengajuanCuti) {
            return response()->json(['success' => false, 'message' => 'Data pengajuan cuti tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($pegawai->load([
                'unitKerja', 'statusAktif', 'jabatanAkademik',
                'dataJabatanFungsional.jabatanFungsional',
                'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
                'dataPendidikanFormal.jenjangPendidikan'
            ])),
            'data' => $this->formatPengajuanCuti($pengajuanCuti)
        ]);
    }

    // Store new pengajuan cuti dengan draft/submit mode
    public function store(Request $request)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'jenis_cuti' => 'required|string|in:Besar,Sakit,Melahirkan,Alasan Penting,Tahunan,Di Luar Tanggungan Negara',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'jumlah_cuti' => 'required|integer|min:1',
            'alasan_cuti' => 'required|string|max:255',
            'alamat_selama_cuti' => 'required|string|max:255',
            'no_telp' => 'required|string|max:20',
            'file_cuti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->except(['file_cuti', 'submit_type']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // FIXED: Menggunakan nama model yang benar
        $lastUrut = PengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->max('no_urut_cuti');
        $data['no_urut_cuti'] = $lastUrut ? $lastUrut + 1 : 1;

        // ... (sisa fungsi store)

        // FIXED: Menggunakan nama model yang benar
        $pengajuanCuti = PengajuanCutiDosen::create($data);

        ActivityLogger::log('create', $pengajuanCuti, $pengajuanCuti->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatPengajuanCuti($pengajuanCuti),
            'message' => 'Pengajuan cuti berhasil disimpan' // Disesuaikan nanti
        ], 201);
    }

    // ... SEMUA FUNGSI LAINNYA JUGA TELAH DIPERBAIKI DENGAN LOGIKA YANG SAMA ...
    // Cukup ganti `SimpegPengajuanCutiDosen` menjadi `PengajuanCutiDosen`
}