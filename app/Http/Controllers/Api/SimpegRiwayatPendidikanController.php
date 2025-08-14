<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPendidikanFormal;
use App\Models\SimpegPegawai;
use App\Models\SimpegJenjangPendidikan;
use App\Models\SimpegGelarAkademik;
use App\Models\SimpegUnitKerja;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class SimpegRiwayatPendidikanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Build query with eager loading
        $query = SimpegDataPendidikanFormal::with([
            'jenjangPendidikan',
            'perguruanTinggi',
            'prodiPerguruanTinggi',
            'gelarAkademik',
            'pegawai'
        ]);

        // Filter status pengajuan
        if ($request->has('status_pengajuan') && $request->status_pengajuan !== 'semua') {
            $query->where('status_pengajuan', $request->status_pengajuan);
        }

        // Filter jenjang pendidikan
        if ($request->has('jenjang_pendidikan_id') && !empty($request->jenjang_pendidikan_id)) {
            $query->where('jenjang_pendidikan_id', $request->jenjang_pendidikan_id);
        }

        // Filter gelar akademik
        if ($request->has('gelar_akademik_id') && !empty($request->gelar_akademik_id)) {
            $query->where('gelar_akademik_id', $request->gelar_akademik_id);
        }

        // Filter perguruan tinggi
        if ($request->has('perguruan_tinggi_id') && !empty($request->perguruan_tinggi_id)) {
            $query->where('perguruan_tinggi_id', $request->perguruan_tinggi_id);
        }

        // Filter tahun lulus
        if ($request->has('tahun_lulus') && !empty($request->tahun_lulus)) {
            $query->where('tahun_lulus', $request->tahun_lulus);
        }

        // Pencarian
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->whereHas('jenjangPendidikan', function ($q) use ($searchTerm) {
                    $q->where('jenjang_pendidikan', 'LIKE', '%' . $searchTerm . '%');
                })
                ->orWhereHas('perguruanTinggi', function ($q) use ($searchTerm) {
                    $q->where('nama_universitas', 'LIKE', '%' . $searchTerm . '%');
                })
                ->orWhereHas('gelarAkademik', function ($q) use ($searchTerm) {
                    $q->where('nama_gelar', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('singkatan', 'LIKE', '%' . $searchTerm . '%');
                })
                ->orWhereHas('pegawai', function ($q) use ($searchTerm) {
                    $q->where('nama', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('nip', 'LIKE', '%' . $searchTerm . '%');
                });

                // Cek jika kolom nama_institusi ada di tabel
                if (Schema::hasColumn('simpeg_data_pendidikan_formal', 'nama_institusi')) {
                    $q->orWhere('nama_institusi', 'LIKE', '%' . $searchTerm . '%');
                }
            });
        }

        // Pagination and sorting
        $perPage = $request->input('per_page', 10);
        $sortField = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        // Validasi field sorting
        $allowedSortFields = ['tahun_lulus', 'created_at', 'status_pengajuan'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }

        $pendidikan = $query->orderBy($sortField, $sortOrder)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $pendidikan,
            'message' => 'Data riwayat pendidikan berhasil diambil'
        ]);
    }

    /**
     * Get pendidikan by pegawai ID.
     */
    public function getByPegawai(Request $request, $pegawaiId)
    {
        // Eager load all necessary relationships
        $pegawai = SimpegPegawai::with([
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
            }
        ])->find($pegawaiId);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        // Build the query for pendidikan formal with relationships
        $query = $pegawai->dataPendidikanFormal()->with([
            'jenjangPendidikan', 
            'perguruanTinggi', 
            'prodiPerguruanTinggi', 
            'gelarAkademik'
        ]);

        // Filter status pengajuan
        if ($request->has('status_pengajuan') && $request->status_pengajuan !== 'semua') {
            $query->where('status_pengajuan', $request->status_pengajuan);
        }

        // Filter jenjang pendidikan
        if ($request->has('jenjang_pendidikan_id') && !empty($request->jenjang_pendidikan_id)) {
            $query->where('jenjang_pendidikan_id', $request->jenjang_pendidikan_id);
        }

        // Filter gelar akademik
        if ($request->has('gelar_akademik_id') && !empty($request->gelar_akademik_id)) {
            $query->where('gelar_akademik_id', $request->gelar_akademik_id);
        }

        // Filter perguruan tinggi
        if ($request->has('perguruan_tinggi_id') && !empty($request->perguruan_tinggi_id)) {
            $query->where('perguruan_tinggi_id', $request->perguruan_tinggi_id);
        }

        // Filter berdasarkan data dari PT
        if ($request->has('nama_institusi') && !empty($request->nama_institusi)) {
            $query->whereHas('perguruanTinggi', function($q) use ($request) {
                $q->where('nama_universitas', 'LIKE', '%' . $request->nama_institusi . '%');
            });
        }

        // Filter tahun lulus
        if ($request->has('tahun_lulus') && !empty($request->tahun_lulus)) {
            $query->where('tahun_lulus', $request->tahun_lulus);
        }

        // Pencarian
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->whereHas('jenjangPendidikan', function ($q) use ($searchTerm) {
                    $q->where('jenjang_pendidikan', 'LIKE', '%' . $searchTerm . '%');
                })
                ->orWhereHas('perguruanTinggi', function ($q) use ($searchTerm) {
                    $q->where('nama_universitas', 'LIKE', '%' . $searchTerm . '%');
                })
                ->orWhereHas('gelarAkademik', function ($q) use ($searchTerm) {
                    $q->where('nama_gelar', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('singkatan', 'LIKE', '%' . $searchTerm . '%');
                });
            });
        }

        // Pagination and Ordering
        $perPage = $request->input('per_page', 10);
        
        $pendidikan = $query->orderBy($request->input('sort_by', 'tahun_lulus'), $request->input('sort_order', 'desc'))
                            ->paginate($perPage);
        
        // Get URL prefix
        $prefix = $request->segment(2);
        
        // Transform the collection to include only the columns we need
        $pendidikan->getCollection()->transform(function ($item, $key) use ($prefix) {
            return [
                'no' => $key + 1, // Provide row number
                'id' => $item->id,
                'jenjang' => $item->jenjangPendidikan ? $item->jenjangPendidikan->jenjang_pendidikan : '-',
                'jenjang_singkatan' => $item->jenjangPendidikan ? $item->jenjangPendidikan->jenjang_singkatan : '-',
                'gelar' => $item->gelarAkademik ? $item->gelarAkademik->singkatan : '-', // Menggunakan singkatan
                'nama_gelar' => $item->gelarAkademik ? $item->gelarAkademik->nama_gelar : '-',
                'nama_institusi' => $item->perguruanTinggi ? $item->perguruanTinggi->nama_universitas : ($item->nama_institusi ?? '-'),
                'tahun_lulus' => $item->tahun_lulus ?? '-',
                'status_pengajuan' => $item->status_pengajuan,
                'aksi' => [
                    'detail_url' => url("/api/{$prefix}/pegawai/riwayat-pendidikan/detail/{$item->id}"),
                    'update_url' => url("/api/{$prefix}/pegawai/riwayat-pendidikan/{$item->id}"),
                    'delete_url' => url("/api/{$prefix}/pegawai/riwayat-pendidikan/{$item->id}"),
                    'update_status_url' => url("/api/{$prefix}/pegawai/riwayat-pendidikan/{$item->id}/status")
                ]
            ];
        });
        
        // Prepare pegawai info
        $jabatanAkademikNama = '-';
        if ($pegawai->jabatanAkademik) {
            $jabatanAkademikNama = $pegawai->jabatanAkademik->jabatan_akademik ?? '-';
        }

        $jabatanFungsionalNama = '-';
        if ($pegawai->dataJabatanFungsional && $pegawai->dataJabatanFungsional->isNotEmpty()) {
            $jabatanFungsional = $pegawai->dataJabatanFungsional->first()->jabatanFungsional;
            if ($jabatanFungsional) {
                if (isset($jabatanFungsional->nama_jabatan_fungsional)) {
                    $jabatanFungsionalNama = $jabatanFungsional->nama_jabatan_fungsional;
                } elseif (isset($jabatanFungsional->nama)) {
                    $jabatanFungsionalNama = $jabatanFungsional->nama;
                }
            }
        }

        $jabatanStrukturalNama = '-';
        if ($pegawai->dataJabatanStruktural && $pegawai->dataJabatanStruktural->isNotEmpty()) {
            $jabatanStruktural = $pegawai->dataJabatanStruktural->first();
            
            if ($jabatanStruktural->jabatanStruktural && $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural;
            }
            elseif (isset($jabatanStruktural->jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->nama_jabatan;
            }
            elseif (isset($jabatanStruktural->jabatanStruktural->singkatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->singkatan;
            }
            elseif (isset($jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->nama_jabatan;
            }
        }

        $jenjangPendidikanNama = '-';
        $highestEducation = $pegawai->dataPendidikanFormal()
            ->with('jenjangPendidikan')
            ->orderBy('jenjang_pendidikan_id', 'desc')
            ->first();
        
        if ($highestEducation && $highestEducation->jenjangPendidikan) {
            $jenjangPendidikanNama = $highestEducation->jenjangPendidikan->jenjang_pendidikan ?? '-';
        }

        $unitKerjaNama = 'Tidak Ada';
        if ($pegawai->unitKerja) {
            $unitKerjaNama = $pegawai->unitKerja->nama_unit;
        } elseif ($pegawai->unit_kerja_id) {
            $unitKerja = SimpegUnitKerja::find($pegawai->unit_kerja_id);
            $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $pegawai->unit_kerja_id;
        }
        
        $fotoUrl = null;
        if (isset($pegawai->foto) && !empty($pegawai->foto)) {
            $fotoUrl = asset($pegawai->foto);
        } elseif (isset($pegawai->photo) && !empty($pegawai->photo)) {
            $fotoUrl = asset($pegawai->photo);
        } elseif (isset($pegawai->file_foto) && !empty($pegawai->file_foto)) {
            $fotoUrl = asset($pegawai->file_foto);
        } elseif (isset($pegawai->avatar) && !empty($pegawai->avatar)) {
            $fotoUrl = asset($pegawai->avatar);
        } else {
            $fotoUrl = asset('assets/images/default-user.png');
        }

        // Get jenjang pendidikan and gelar for filter options
        $jenjangPendidikan = SimpegJenjangPendidikan::select('id', 'jenjang_pendidikan as nama')
            ->orderBy('urutan_jenjang_pendidikan', 'asc')->get();
            
        // Cek jika tabel master_perguruan_tinggi ada
        try {
            $perguruanTinggi = DB::table('master_perguruan_tinggi')
                ->select('id', 'nama_universitas as nama')
                ->where('is_aktif', true)
                ->orderBy('nama_universitas', 'asc')
                ->get();
        } catch (\Exception $e) {
            // Jika tabel tidak ada, buat array kosong
            $perguruanTinggi = collect([]);
        }
            
        // Get gelar akademik for filter
       $gelarAkademik = DB::table('simpeg_master_gelar_akademik')
    ->select('id', 'nama_gelar as nama')
    ->orderBy('nama_gelar', 'asc')
    ->get();

        return response()->json([
            'success' => true,
            'data' => $pendidikan,
            'empty_data' => $pendidikan->isEmpty(), // Flag for empty data
            'pegawai_info' => [
                'id' => $pegawai->id,
                'nip' => $pegawai->nip ?? '-',
                'nama' => $pegawai->nama ?? '-',
                'foto_url' => $fotoUrl,
                'unit_kerja' => $unitKerjaNama,
                'status' => $pegawai->statusAktif ? $pegawai->statusAktif->nama_status_aktif : '-',
                'jabatan_akademik' => $jabatanAkademikNama,
                'jabatan_fungsional' => $jabatanFungsionalNama,
                'jabatan_struktural' => $jabatanStrukturalNama,
                'pendidikan' => $jenjangPendidikanNama
            ],
            'filters' => [
                'jenjang_pendidikan' => $jenjangPendidikan,
                'perguruan_tinggi' => $perguruanTinggi,
                'gelar_akademik' => $gelarAkademik,
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                    ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan'],
                ]
            ],
            'table_columns' => [
                ['field' => 'no', 'label' => 'No', 'sortable' => false],
                ['field' => 'jenjang', 'label' => 'Jenjang', 'sortable' => true, 'sortable_field' => 'jenjang_pendidikan_id'],
                ['field' => 'gelar', 'label' => 'Gelar', 'sortable' => true, 'sortable_field' => 'gelar_akademik_id'],
                ['field' => 'nama_institusi', 'label' => 'Nama Institusi', 'sortable' => true, 'sortable_field' => 'nama_institusi'],
                ['field' => 'tahun_lulus', 'label' => 'Tahun Lulus', 'sortable' => true, 'sortable_field' => 'tahun_lulus'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_pendidikan_url' => url("/api/{$prefix}/pegawai/riwayat-pendidikan/create")
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $pendidikan = SimpegDataPendidikanFormal::with([
            'jenjangPendidikan', 
            'perguruanTinggi', 
            'prodiPerguruanTinggi', 
            'gelarAkademik',
            'pegawai'
        ])->find($id);

        if (!$pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data pendidikan formal tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        // Format data to include more readable information
        $response = [
            'success' => true,
            'data' => [
                'id' => $pendidikan->id,
                'pegawai' => [
                    'id' => $pendidikan->pegawai->id ?? null,
                    'nip' => $pendidikan->pegawai->nip ?? null,
                    'nama' => $pendidikan->pegawai->nama ?? null,
                ],
                'jenjang_pendidikan' => [
                    'id' => $pendidikan->jenjangPendidikan->id ?? null,
                    'jenjang' => $pendidikan->jenjangPendidikan->jenjang_pendidikan ?? null,
                    'singkatan' => $pendidikan->jenjangPendidikan->jenjang_singkatan ?? null,
                ],
                'perguruan_tinggi' => [
                    'id' => $pendidikan->perguruanTinggi->id ?? null,
                    'nama' => $pendidikan->perguruanTinggi->nama_universitas ?? null,
                ],
                'prodi' => [
                    'id' => $pendidikan->prodiPerguruanTinggi->id ?? null,
                    'nama' => $pendidikan->prodiPerguruanTinggi->nama_prodi ?? null,
                ],
                'gelar_akademik' => [
                    'id' => $pendidikan->gelarAkademik->id ?? null,
                    'nama' => $pendidikan->gelarAkademik->nama_gelar ?? null,
                    'singkatan' => $pendidikan->gelarAkademik->singkatan ?? null,
                ],
                'lokasi_studi' => $pendidikan->lokasi_studi,
                'nama_institusi' => $pendidikan->nama_institusi ?? ($pendidikan->perguruanTinggi->nama_universitas ?? null),
                'bidang_studi' => $pendidikan->bidang_studi,
                'nisn' => $pendidikan->nisn,
                'konsentrasi' => $pendidikan->konsentrasi,
                'tahun_masuk' => $pendidikan->tahun_masuk,
                'tanggal_kelulusan' => $pendidikan->tanggal_kelulusan,
                'tahun_lulus' => $pendidikan->tahun_lulus,
                'nomor_ijazah' => $pendidikan->nomor_ijazah,
                'tanggal_ijazah' => $pendidikan->tanggal_ijazah,
                'nomor_ijazah_negara' => $pendidikan->nomor_ijazah_negara,
                'gelar_ijazah_negara' => $pendidikan->gelar_ijazah_negara,
                'tanggal_ijazah_negara' => $pendidikan->tanggal_ijazah_negara,
                'nomor_induk' => $pendidikan->nomor_induk,
                'judul_tugas' => $pendidikan->judul_tugas,
                'letak_gelar' => $pendidikan->letak_gelar,
                'jumlah_semster_ditempuh' => $pendidikan->jumlah_semster_ditempuh,
                'jumlah_sks_kelulusan' => $pendidikan->jumlah_sks_kelulusan,
                'ipk_kelulusan' => $pendidikan->ipk_kelulusan,
                'file_ijazah' => $pendidikan->file_ijazah ? asset('storage/' . $pendidikan->file_ijazah) : null,
                'file_transkrip' => $pendidikan->file_transkrip ? asset('storage/' . $pendidikan->file_transkrip) : null,
                'status_pengajuan' => $pendidikan->status_pengajuan,
                'tanggal_diajukan' => $pendidikan->tanggal_diajukan,
                'tanggal_disetujui' => $pendidikan->tanggal_disetujui,
                'tgl_input' => $pendidikan->tgl_input,
                'dibuat_oleh' => $pendidikan->dibuat_oleh,
                'created_at' => $pendidikan->created_at,
                'updated_at' => $pendidikan->updated_at,
            ],
            'update_url' => url("/api/{$prefix}/pegawai/riwayat-pendidikan/" . $pendidikan->id),
            'delete_url' => url("/api/{$prefix}/pegawai/riwayat-pendidikan/" . $pendidikan->id),
            'update_status_url' => url("/api/{$prefix}/pegawai/riwayat-pendidikan/" . $pendidikan->id . "/status"),
        ];

        return response()->json($response);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Cek jika tabel master_perguruan_tinggi ada
        $perguruanTinggiExists = Schema::hasTable('master_perguruan_tinggi');
        $prodiPerguruanTinggiExists = Schema::hasTable('master_prodi_perguruan_tinggi');
        
        // Siapkan aturan validasi
        $rules = [
            'pegawai_id' => 'required|uuid|exists:simpeg_pegawai,id',
            'jenjang_pendidikan_id' => 'nullable|uuid|exists:simpeg_jenjang_pendidikan,id',
            'gelar_akademik_id' => 'nullable|uuid|exists:simpeg_master_gelar_akademik,id',
            'lokasi_studi' => 'nullable|string|max:100',
            'bidang_studi' => 'nullable|string|max:100',
            'nisn' => 'nullable|string|max:30',
            'konsentrasi' => 'nullable|string|max:100',
            'tahun_masuk' => 'nullable|string|max:4',
            'tanggal_kelulusan' => 'nullable|date',
            'tahun_lulus' => 'nullable|string|max:4',
            'nomor_ijazah' => 'nullable|string|max:50',
            'tanggal_ijazah' => 'nullable|date',
            'nomor_ijazah_negara' => 'nullable|string|max:50',
            'gelar_ijazah_negara' => 'nullable|string|max:30',
            'tanggal_ijazah_negara' => 'nullable|date',
            'nomor_induk' => 'nullable|string|max:30',
            'judul_tugas' => 'nullable|string',
            'letak_gelar' => 'nullable|string|max:10',
            'jumlah_semster_ditempuh' => 'nullable|integer',
            'jumlah_sks_kelulusan' => 'nullable|integer',
            'ipk_kelulusan' => 'nullable|numeric',
            'file_ijazah' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'file_transkrip' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'nullable|string|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
            'tanggal_diajukan' => 'nullable|date',
            'tanggal_disetujui' => 'nullable|date',
        ];
        
        // Tambahkan validasi untuk perguruan_tinggi_id dan prodi_perguruan_tinggi_id jika tabel ada
        if ($perguruanTinggiExists) {
            $rules['perguruan_tinggi_id'] = 'nullable|uuid|exists:master_perguruan_tinggi,id';
        }
        
        if ($prodiPerguruanTinggiExists) {
            $rules['prodi_perguruan_tinggi_id'] = 'nullable|uuid|exists:master_prodi_perguruan_tinggi,id';
        }
        
        // Tambahkan validasi untuk nama_institusi jika kolom ada
        if (Schema::hasColumn('simpeg_data_pendidikan_formal', 'nama_institusi')) {
            $rules['nama_institusi'] = 'nullable|string|max:100';
        }

        $request->validate($rules);

        DB::beginTransaction();
        try {
            $data = $request->all();
            
            // Set tgl_input to today
            $data['tgl_input'] = Carbon::now()->format('Y-m-d');
            
            // Handle file uploads
            if ($request->hasFile('file_ijazah')) {
                $file = $request->file('file_ijazah');
                $fileName = 'ijazah_' . time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads/ijazah', $fileName, 'public');
                $data['file_ijazah'] = $filePath;
            }
            
            if ($request->hasFile('file_transkrip')) {
                $file = $request->file('file_transkrip');
                $fileName = 'transkrip_' . time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads/transkrip', $fileName, 'public');
                $data['file_transkrip'] = $filePath;
            }
            
            // Set default status if not provided
            if (!isset($data['status_pengajuan'])) {
                $data['status_pengajuan'] = 'draft';
            }
            
            // Set tanggal_diajukan if status is 'diajukan'
            if ($data['status_pengajuan'] === 'diajukan' && !isset($data['tanggal_diajukan'])) {
                $data['tanggal_diajukan'] = Carbon::now()->format('Y-m-d');
            }
            
            // Set tanggal_disetujui if status is 'disetujui'
            if ($data['status_pengajuan'] === 'disetujui' && !isset($data['tanggal_disetujui'])) {
                $data['tanggal_disetujui'] = Carbon::now()->format('Y-m-d');
            }
            
            // Set dibuat_oleh dengan nama pegawai yang login
         if (Auth::check()) {
    // Karena auth()->user() langsung mengacu ke model SimpegPegawai (bukan User), 
    // kita bisa langsung mengambil nama pegawai
    $data['dibuat_oleh'] = $user = Auth::user()->nama ?? 'Admin';
} else {
    $data['dibuat_oleh'] = 'Sistem';
}
            // Debug: tambahkan logging untuk melihat data sebelum insert
            Log::info('Data akan diinsert:', $data);
            
            $pendidikan = SimpegDataPendidikanFormal::create($data);

            ActivityLogger::log('create', $pendidikan, $pendidikan->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pendidikan,
                'message' => 'Data pendidikan formal berhasil ditambahkan'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saat insert data pendidikan: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data pendidikan formal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $pendidikan = SimpegDataPendidikanFormal::find($id);

        if (!$pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data pendidikan formal tidak ditemukan'], 404);
        }

        // Cek jika tabel master_perguruan_tinggi ada
        $perguruanTinggiExists = Schema::hasTable('master_perguruan_tinggi');
        $prodiPerguruanTinggiExists = Schema::hasTable('master_prodi_perguruan_tinggi');
        
        // Siapkan aturan validasi
        $rules = [
            'jenjang_pendidikan_id' => 'nullable|uuid|exists:simpeg_jenjang_pendidikan,id',
            'gelar_akademik_id' => 'nullable|uuid|exists:simpeg_master_gelar_akademik,id',
            'lokasi_studi' => 'nullable|string|max:100',
            'bidang_studi' => 'nullable|string|max:100',
            'nisn' => 'nullable|string|max:30',
            'konsentrasi' => 'nullable|string|max:100',
            'tahun_masuk' => 'nullable|string|max:4',
            'tanggal_kelulusan' => 'nullable|date',
            'tahun_lulus' => 'nullable|string|max:4',
            'nomor_ijazah' => 'nullable|string|max:50',
            'tanggal_ijazah' => 'nullable|date',
            'nomor_ijazah_negara' => 'nullable|string|max:50',
            'gelar_ijazah_negara' => 'nullable|string|max:30',
            'tanggal_ijazah_negara' => 'nullable|date',
            'nomor_induk' => 'nullable|string|max:30',
            'judul_tugas' => 'nullable|string',
            'letak_gelar' => 'nullable|string|max:10',
            'jumlah_semster_ditempuh' => 'nullable|integer',
            'jumlah_sks_kelulusan' => 'nullable|integer',
            'ipk_kelulusan' => 'nullable|numeric',
            'file_ijazah' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'file_transkrip' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'nullable|string|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
            'tanggal_diajukan' => 'nullable|date',
            'tanggal_disetujui' => 'nullable|date',
        ];
        
        // Tambahkan validasi untuk perguruan_tinggi_id dan prodi_perguruan_tinggi_id jika tabel ada
        if ($perguruanTinggiExists) {
            $rules['perguruan_tinggi_id'] = 'nullable|uuid|exists:master_perguruan_tinggi,id';
        }
        
        if ($prodiPerguruanTinggiExists) {
            $rules['prodi_perguruan_tinggi_id'] = 'nullable|uuid|exists:master_prodi_perguruan_tinggi,id';
        }
        
        // Tambahkan validasi untuk nama_institusi jika kolom ada
        if (Schema::hasColumn('simpeg_data_pendidikan_formal', 'nama_institusi')) {
            $rules['nama_institusi'] = 'nullable|string|max:100';
        }

        $request->validate($rules);

        $old = $pendidikan->getOriginal();
        $data = $request->except(['file_ijazah', 'file_transkrip', 'dibuat_oleh']); // Exclude dibuat_oleh dari update

        DB::beginTransaction();
        try {
            // Handle file uploads
            if ($request->hasFile('file_ijazah')) {
                // Save old file path for potential cleanup
                $oldIjazahPath = $pendidikan->file_ijazah;
                
                $file = $request->file('file_ijazah');
                $fileName = 'ijazah_' . time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads/ijazah', $fileName, 'public');
                $data['file_ijazah'] = $filePath;
                
                // Mark old file for delayed deletion if it exists
                if ($oldIjazahPath) {
                    $this->markFileForDeletion($oldIjazahPath);
                }
            }
            
            if ($request->hasFile('file_transkrip')) {
                // Save old file path for potential cleanup
                $oldTranskripPath = $pendidikan->file_transkrip;
                
                $file = $request->file('file_transkrip');
                $fileName = 'transkrip_' . time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads/transkrip', $fileName, 'public');
                $data['file_transkrip'] = $filePath;
                
                // Mark old file for delayed deletion if it exists
                if ($oldTranskripPath) {
                    $this->markFileForDeletion($oldTranskripPath);
                }
            }
            
            // Update tanggal_diajukan if status berubah menjadi 'diajukan'
            if (isset($data['status_pengajuan']) && $data['status_pengajuan'] === 'diajukan' && 
                ($pendidikan->status_pengajuan !== 'diajukan' && !isset($data['tanggal_diajukan']))) {
                $data['tanggal_diajukan'] = Carbon::now()->format('Y-m-d');
            }
            
            // Update tanggal_disetujui if status berubah menjadi 'disetujui'
            if (isset($data['status_pengajuan']) && $data['status_pengajuan'] === 'disetujui' && 
                ($pendidikan->status_pengajuan !== 'disetujui' && !isset($data['tanggal_disetujui']))) {
                $data['tanggal_disetujui'] = Carbon::now()->format('Y-m-d');
            }

            $pendidikan->update($data);

            $changes = array_diff_assoc($pendidikan->toArray(), $old);
            ActivityLogger::log('update', $pendidikan, $changes);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pendidikan,
                'message' => 'Data pendidikan formal berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data pendidikan formal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update status pengajuan for the specified resource.
     */
    public function updateStatusPengajuan(Request $request, $id)
    {
        $pendidikan = SimpegDataPendidikanFormal::find($id);

        if (!$pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data pendidikan formal tidak ditemukan'], 404);
        }

        $request->validate([
            'status_pengajuan' => 'required|string|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
        ]);

        $old = $pendidikan->getOriginal();
        $statusPengajuan = $request->status_pengajuan;

        DB::beginTransaction();
        try {
            $updateData = [
                'status_pengajuan' => $statusPengajuan,
            ];
            
            // Set tanggal_diajukan jika status berubah menjadi 'diajukan'
            if ($statusPengajuan === 'diajukan' && $pendidikan->status_pengajuan !== 'diajukan') {
                $updateData['tanggal_diajukan'] = Carbon::now()->format('Y-m-d');
            }
            
            // Set tanggal_disetujui jika status berubah menjadi 'disetujui'
            if ($statusPengajuan === 'disetujui' && $pendidikan->status_pengajuan !== 'disetujui') {
                $updateData['tanggal_disetujui'] = Carbon::now()->format('Y-m-d');
            }

            $pendidikan->update($updateData);

            $changes = array_diff_assoc($pendidikan->toArray(), $old);
            ActivityLogger::log('update_status', $pendidikan, $changes);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pendidikan,
                'message' => 'Status pengajuan berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status pengajuan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $pendidikan = SimpegDataPendidikanFormal::find($id);
        
        if (!$pendidikan) {
            return response()->json(['success' => false, 'message' => 'Data pendidikan formal tidak ditemukan'], 404);
        }
        
        $pendidikanData = $pendidikan->toArray(); // Simpan data sebelum dihapus
        
        DB::beginTransaction();
        try {
            // Mark files for delayed deletion
            if ($pendidikan->file_ijazah) {
                $this->markFileForDeletion($pendidikan->file_ijazah);
            }
            
            if ($pendidikan->file_transkrip) {
                $this->markFileForDeletion($pendidikan->file_transkrip);
            }
            
            $pendidikan->delete(); // Soft delete
            
            ActivityLogger::log('delete', $pendidikan, $pendidikanData);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Data pendidikan formal berhasil dihapus (soft delete)'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data pendidikan formal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch update status for multiple resources.
     */
    public function batchUpdateStatus(Request $request)
    {
        $request->validate([
            'pendidikan_ids' => 'required|array',
            'pendidikan_ids.*' => 'exists:simpeg_data_pendidikan_formal,id',
            'status_pengajuan' => 'required|string|in:draft,diajukan,disetujui,ditolak,ditangguhkan'
        ]);

        $statusPengajuan = $request->status_pengajuan;
        $now = Carbon::now()->format('Y-m-d');

        DB::beginTransaction();
        try {
            foreach ($request->pendidikan_ids as $id) {
                $pendidikan = SimpegDataPendidikanFormal::find($id);
                if (!$pendidikan) continue;
                
                $old = $pendidikan->getOriginal();
                $updateData = [
                    'status_pengajuan' => $statusPengajuan
                ];
                
                // Set tanggal_diajukan jika status berubah menjadi 'diajukan'
                if ($statusPengajuan === 'diajukan' && $pendidikan->status_pengajuan !== 'diajukan') {
                    $updateData['tanggal_diajukan'] = $now;
                }
                
                // Set tanggal_disetujui jika status berubah menjadi 'disetujui'
                if ($statusPengajuan === 'disetujui' && $pendidikan->status_pengajuan !== 'disetujui') {
                    $updateData['tanggal_disetujui'] = $now;
                }
                
                $pendidikan->update($updateData);
                
                $changes = array_diff_assoc($pendidikan->toArray(), $old);
                ActivityLogger::log('update_status', $pendidikan, $changes);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Status pendidikan formal berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status pendidikan formal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch delete resources from storage.
     */
    public function batchDelete(Request $request)
    {
        $request->validate([
            'pendidikan_ids' => 'required|array',
            'pendidikan_ids.*' => 'exists:simpeg_data_pendidikan_formal,id',
        ]);

        DB::beginTransaction();
        try {
            $deleted = 0;
            $failed = 0;
            
            foreach ($request->pendidikan_ids as $id) {
                $pendidikan = SimpegDataPendidikanFormal::find($id);
                if (!$pendidikan) {
                    $failed++;
                    continue;
                }
                
                // Mark files for delayed deletion
                if ($pendidikan->file_ijazah) {
                    $this->markFileForDeletion($pendidikan->file_ijazah);
                }
                
                if ($pendidikan->file_transkrip) {
                    $this->markFileForDeletion($pendidikan->file_transkrip);
                }
                
                $pendidikanData = $pendidikan->toArray();
                $pendidikan->delete(); // Using SoftDeletes
                ActivityLogger::log('delete', $pendidikan, $pendidikanData);
                $deleted++;
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Data pendidikan formal berhasil dihapus ($deleted item" . ($deleted > 1 ? 's' : '') . ")" . 
                            ($failed > 0 ? ", $failed item gagal dihapus" : "")
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data pendidikan formal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search for pegawai
     */
    public function searchPegawai(Request $request)
    {
        $query = SimpegPegawai::query();
        
        // Search by name or NIP
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'LIKE', "%{$search}%")
                  ->orWhere('nip', 'LIKE', "%{$search}%")
                  ->orWhere('nidn', 'LIKE', "%{$search}%");
            });
        }
        
        // Filter by unit kerja
        if ($request->has('unit_kerja_id') && !empty($request->unit_kerja_id)) {
            $query->where('unit_kerja_id', $request->unit_kerja_id);
        }
        
        // Filter by status aktif
        if ($request->has('status_aktif_id') && !empty($request->status_aktif_id)) {
            $query->where('status_aktif_id', $request->status_aktif_id);
        }
        
        $pegawai = $query->with(['unitKerja', 'statusAktif'])->limit(10)->get();
        
        return response()->json([
            'success' => true,
            'data' => $pegawai->map(function($item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'nip' => $item->nip,
                    'nidn' => $item->nidn,
                    'unit_kerja' => $item->unitKerja ? $item->unitKerja->nama_unit : null,
                    'status_aktif' => $item->statusAktif ? $item->statusAktif->nama_status_aktif : null,
                ];
            })
        ]);
    }

    /**
     * Get pegawai with pendidikan info
     */
    public function getPegawaiWithPendidikan($pegawaiId)
    {
        $pegawai = SimpegPegawai::with([
            'unitKerja',
            'statusAktif',
            'jabatanAkademik',
            'dataPendidikanFormal.jenjangPendidikan',
            'dataPendidikanFormal.gelarAkademik',
            'dataPendidikanFormal.perguruanTinggi',
        ])->find($pegawaiId);
        
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }
        
        $pendidikanList = $pegawai->dataPendidikanFormal->map(function($item) {
            return [
                'id' => $item->id,
                'jenjang' => $item->jenjangPendidikan ? $item->jenjangPendidikan->jenjang_pendidikan : null,
                'gelar' => $item->gelarAkademik ? $item->gelarAkademik->singkatan : null,
                'nama_gelar' => $item->gelarAkademik ? $item->gelarAkademik->nama_gelar : null,
                'institusi' => $item->perguruanTinggi ? $item->perguruanTinggi->nama_universitas : ($item->nama_institusi ?? null),
                'tahun_lulus' => $item->tahun_lulus,
                'ipk' => $item->ipk_kelulusan,
                'status' => $item->status_pengajuan,
            ];
        });
        
        $pendidikanTertinggi = $pegawai->dataPendidikanFormal()
            ->with('jenjangPendidikan')
            ->orderBy('jenjang_pendidikan_id', 'desc')
            ->first();
            
        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => [
                    'id' => $pegawai->id,
                    'nama' => $pegawai->nama,
                    'nip' => $pegawai->nip,
                    'nidn' => $pegawai->nidn,
                    'unit_kerja' => $pegawai->unitKerja ? $pegawai->unitKerja->nama_unit : null,
                    'status_aktif' => $pegawai->statusAktif ? $pegawai->statusAktif->nama_status_aktif : null,
                    'jabatan_akademik' => $pegawai->jabatanAkademik ? $pegawai->jabatanAkademik->jabatan_akademik : null,
                    'pendidikan_tertinggi' => $pendidikanTertinggi ? [
                        'jenjang' => $pendidikanTertinggi->jenjangPendidikan ? $pendidikanTertinggi->jenjangPendidikan->jenjang_pendidikan : null,
                        'gelar' => $pendidikanTertinggi->gelarAkademik ? $pendidikanTertinggi->gelarAkademik->singkatan : null,
                        'institusi' => $pendidikanTertinggi->perguruanTinggi ? $pendidikanTertinggi->perguruanTinggi->nama_universitas : ($pendidikanTertinggi->nama_institusi ?? null),
                    ] : null,
                ],
                'pendidikan' => $pendidikanList,
            ]
        ]);
    }

    /**
     * Mark file for delayed deletion
     */
    private function markFileForDeletion($filePath)
    {
        // Implementasi sesuai dengan kebutuhan aplikasi
        // Misalnya, menyimpan path file di tabel "deleted_files" untuk dibersihkan nanti
        // Atau menggunakan Job Queue untuk menghapus file setelah transaksi berhasil
        try {
            if (file_exists(public_path('storage/' . $filePath))) {
                // Contoh implementasi: hanya mencatat untuk dihapus kemudian
                // Log::info('File marked for deletion: ' . $filePath);
            }
        } catch (\Exception $e) {
            // Log error but don't stop the process
            // Log::error('Error marking file for deletion: ' . $e->getMessage());
        }
    }
}