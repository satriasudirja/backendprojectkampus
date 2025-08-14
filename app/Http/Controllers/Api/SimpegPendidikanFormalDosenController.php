<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataPendidikanFormal;
use App\Models\SimpegPegawai;
use App\Models\SimpegJenjangPendidikan;
use App\Models\MasterPerguruanTinggi;
use App\Models\SimpegUnitKerja;
use App\Models\MasterProdiPerguruanTinggi;
use App\Models\MasterGelarAkademik;
use App\Models\SimpegDataPendukung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegPendidikanFormalDosenController extends Controller
{
    // Get all pendidikan formal for logged in pegawai
    public function index(Request $request) 
    {
        // Ensure user is logged in
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        // Eager load pegawai relations
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
                $query->with(['jenjangPendidikan', 'perguruanTinggi', 'prodiPerguruanTinggi', 'gelarAkademik'])
                      ->orderBy('jenjang_pendidikan_id', 'desc');
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

        // Query for logged in pegawai
        $query = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->with(['jenjangPendidikan', 'perguruanTinggi', 'prodiPerguruanTinggi', 'gelarAkademik']);

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('bidang_studi', 'like', '%'.$search.'%')
                  ->orWhere('konsentrasi', 'like', '%'.$search.'%')
                  ->orWhere('nomor_ijazah', 'like', '%'.$search.'%')
                  ->orWhere('tahun_masuk', 'like', '%'.$search.'%')
                  ->orWhere('tahun_lulus', 'like', '%'.$search.'%')
                  ->orWhereHas('jenjangPendidikan', function($jq) use ($search) {
                      $jq->where('jenjang_pendidikan', 'like', '%'.$search.'%');
                  })
                  ->orWhereHas('perguruanTinggi', function($pq) use ($search) {
                      $pq->where('nama_universitas', 'like', '%'.$search.'%');
                  })
                  ->orWhereHas('prodiPerguruanTinggi', function($prq) use ($search) {
                      $prq->where('nama_prodi', 'like', '%'.$search.'%');
                  });
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('jenjang_pendidikan_id')) {
            $query->where('jenjang_pendidikan_id', $request->jenjang_pendidikan_id);
        }
        if ($request->filled('lokasi_studi')) {
            $query->where('lokasi_studi', $request->lokasi_studi);
        }
        if ($request->filled('tahun_masuk')) {
            $query->where('tahun_masuk', $request->tahun_masuk);
        }
        if ($request->filled('tahun_lulus')) {
            $query->where('tahun_lulus', $request->tahun_lulus);
        }

        // Execute query with pagination
        $pendidikanFormal = $query->orderBy('jenjang_pendidikan_id', 'desc')
                                 ->orderBy('tahun_lulus', 'desc')
                                 ->paginate($perPage);

        // Transform the collection
        $pendidikanFormal->getCollection()->transform(function ($item) {
            return $this->formatPendidikanFormal($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $pendidikanFormal,
            'empty_data' => $pendidikanFormal->isEmpty(),
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ],
                'jenjang_pendidikan' => SimpegJenjangPendidikan::select('id', 'jenjang_pendidikan as nama')
                    ->orderBy('jenjang_pendidikan')
                    ->get()
                    ->toArray(),
                'lokasi_studi' => [
                    ['id' => 'dalam_negeri', 'nama' => 'Dalam Negeri'],
                    ['id' => 'luar_negeri', 'nama' => 'Luar Negeri']
                ]
            ],
            'table_columns' => [
                ['field' => 'jenjang_pendidikan', 'label' => 'Jenjang', 'sortable' => true, 'sortable_field' => 'jenjang_pendidikan_id'],
                ['field' => 'perguruan_tinggi', 'label' => 'Perguruan Tinggi', 'sortable' => false],
                ['field' => 'prodi', 'label' => 'Program Studi', 'sortable' => false],
                ['field' => 'tahun_masuk', 'label' => 'Tahun Masuk', 'sortable' => true],
                ['field' => 'tahun_lulus', 'label' => 'Tahun Lulus', 'sortable' => true],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_url' => url("/api/dosen/pendidikanformaldosen"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/pendidikanformaldosen/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/pendidikanformaldosen/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/pendidikanformaldosen/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $pendidikanFormal->currentPage(),
                'per_page' => $pendidikanFormal->perPage(),
                'total' => $pendidikanFormal->total(),
                'last_page' => $pendidikanFormal->lastPage(),
                'from' => $pendidikanFormal->firstItem(),
                'to' => $pendidikanFormal->lastItem()
            ]
        ]);
    }

    // Fix existing data with null status_pengajuan
    public function fixExistingData()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        // Update data with null status_pengajuan to draft
        $updatedCount = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data pendidikan formal",
            'updated_count' => $updatedCount
        ]);
    }

    // Bulk fix all existing data (admin only)
    public function bulkFixExistingData()
    {
        $updatedCount = SimpegDataPendidikanFormal::whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data pendidikan formal dari semua pegawai",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail pendidikan formal
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $pendidikanFormal = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->with(['jenjangPendidikan', 'perguruanTinggi', 'prodiPerguruanTinggi', 'gelarAkademik'])
            ->find($id);

        if (!$pendidikanFormal) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($pegawai->load([
                'unitKerja', 'statusAktif', 'jabatanAkademik',
                'dataJabatanFungsional.jabatanFungsional',
                'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
                'dataPendidikanFormal.jenjangPendidikan'
            ])),
            'data' => $this->formatPendidikanFormal($pendidikanFormal)
        ]);
    }

    // Store new pendidikan formal with draft/submit mode
    public function store(Request $request)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'lokasi_studi' => 'required|in:dalam_negeri,luar_negeri',
            'jenjang_pendidikan_id' => 'required|uuid|exists:simpeg_jenjang_pendidikan,id',
            'perguruan_tinggi_id' => 'nullable|uuid|exists:simpeg_master_perguruan_tinggi,id',
            'prodi_perguruan_tinggi_id' => 'nullable|uuid|exists:simpeg_master_prodi_perguruan_tinggi,id',
            'gelar_akademik_id' => 'nullable|uuid|exists:simpeg_master_gelar_akademik,id',
            'bidang_studi' => 'required|string|max:100',
            'nisn' => 'nullable|string|max:30',
            'konsentrasi' => 'nullable|string|max:100',
            'tahun_masuk' => 'required|digits:4|integer|min:1900|max:'.(date('Y')+1),
            'tanggal_kelulusan' => 'nullable|date|before_or_equal:today',
            'tahun_lulus' => 'required|digits:4|integer|min:1900|max:'.(date('Y')+1),
            'nomor_ijazah' => 'required|string|max:50',
            'tanggal_ijazah' => 'required|date|before_or_equal:today',
            'file_ijazah' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'file_transkrip' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'nomor_ijazah_negara' => 'nullable|string|max:50',
            'gelar_ijazah_negara' => 'nullable|string|max:30',
            'tanggal_ijazah_negara' => 'nullable|date|before_or_equal:today',
            'nomor_induk' => 'nullable|string|max:30',
            'judul_tugas' => 'nullable|string|max:255',
            'letak_gelar' => 'nullable|in:depan,belakang',
            'jumlah_semester_ditempuh' => 'nullable|integer|min:1',
            'jumlah_sks_kelulusan' => 'nullable|integer|min:1',
            'ipk_kelulusan' => 'nullable|numeric|min:0|max:4',
            'submit_type' => 'sometimes|in:draft,submit',
            // 'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['file_ijazah', 'file_transkrip', 'submit_type']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Set status based on submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tanggal_diajukan'] = now();
            $message = 'Data pendidikan formal berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data pendidikan formal berhasil disimpan sebagai draft';
        }

        // Handle file uploads
        if ($request->hasFile('file_ijazah')) {
            $file = $request->file('file_ijazah');
            $fileName = 'ijazah_'.time().'_'.$pegawai->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/pendidikan/ijazah', $fileName);
            $data['file_ijazah'] = $fileName;
        }

        if ($request->hasFile('file_transkrip')) {
            $file = $request->file('file_transkrip');
            $fileName = 'transkrip_'.time().'_'.$pegawai->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/pendidikan/transkrip', $fileName);
            $data['file_transkrip'] = $fileName;
        }

        $pendidikanFormal = SimpegDataPendidikanFormal::create($data);

        ActivityLogger::log('create', $pendidikanFormal, $pendidikanFormal->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatPendidikanFormal($pendidikanFormal->load(['jenjangPendidikan', 'perguruanTinggi', 'prodiPerguruanTinggi', 'gelarAkademik'])),
            'message' => $message
        ], 201);
    }

    // Update pendidikan formal with status validation
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $pendidikanFormal = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$pendidikanFormal) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal tidak ditemukan'
            ], 404);
        }

        // Validate if can be edited based on status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($pendidikanFormal->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'lokasi_studi' => 'sometimes|in:dalam_negeri,luar_negeri',
            'jenjang_pendidikan_id' => 'sometimes|uuid|exists:simpeg_jenjang_pendidikan,id',
            'perguruan_tinggi_id' => 'nullable|uuid|exists:simpeg_master_perguruan_tinggi,id',
            'prodi_perguruan_tinggi_id' => 'nullable|uuid|exists:simpeg_master_prodi_perguruan_tinggi,id',
            'gelar_akademik_id' => 'nullable|uuid|exists:simpeg_master_gelar_akademik,id',
            'bidang_studi' => 'sometimes|string|max:100',
            'nisn' => 'nullable|string|max:30',
            'konsentrasi' => 'nullable|string|max:100',
            'tahun_masuk' => 'sometimes|digits:4|integer|min:1900|max:'.(date('Y')+1),
            'tanggal_kelulusan' => 'nullable|date|before_or_equal:today',
            'tahun_lulus' => 'sometimes|digits:4|integer|min:1900|max:'.(date('Y')+1),
            'nomor_ijazah' => 'sometimes|string|max:50',
            'tanggal_ijazah' => 'sometimes|date|before_or_equal:today',
            'file_ijazah' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'file_transkrip' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'nomor_ijazah_negara' => 'nullable|string|max:50',
            'gelar_ijazah_negara' => 'nullable|string|max:30',
            'tanggal_ijazah_negara' => 'nullable|date|before_or_equal:today',
            'nomor_induk' => 'nullable|string|max:30',
            'judul_tugas' => 'nullable|string|max:255',
            'letak_gelar' => 'nullable|in:depan,belakang',
            'jumlah_semester_ditempuh' => 'nullable|integer|min:1',
            'jumlah_sks_kelulusan' => 'nullable|integer|min:1',
            'ipk_kelulusan' => 'nullable|numeric|min:0|max:4',
            'submit_type' => 'sometimes|in:draft,submit',
            // 'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $pendidikanFormal->getOriginal();
        $data = $request->except(['file_ijazah', 'file_transkrip', 'submit_type']);

        // Reset status if from ditolak
        if ($pendidikanFormal->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tanggal_ditolak'] = null;
            // $data['keterangan'] = $request->keterangan ?? null;
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tanggal_diajukan'] = now();
            $message = 'Data pendidikan formal berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Data pendidikan formal berhasil diperbarui';
        }

        // Handle file uploads
        if ($request->hasFile('file_ijazah')) {
            if ($pendidikanFormal->file_ijazah) {
                Storage::delete('public/pegawai/pendidikan/ijazah/'.$pendidikanFormal->file_ijazah);
            }

            $file = $request->file('file_ijazah');
            $fileName = 'ijazah_'.time().'_'.$pegawai->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/pendidikan/ijazah', $fileName);
            $data['file_ijazah'] = $fileName;
        }

        if ($request->hasFile('file_transkrip')) {
            if ($pendidikanFormal->file_transkrip) {
                Storage::delete('public/pegawai/pendidikan/transkrip/'.$pendidikanFormal->file_transkrip);
            }

            $file = $request->file('file_transkrip');
            $fileName = 'transkrip_'.time().'_'.$pegawai->id.'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/pendidikan/transkrip', $fileName);
            $data['file_transkrip'] = $fileName;
        }

        $pendidikanFormal->update($data);

        ActivityLogger::log('update', $pendidikanFormal, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatPendidikanFormal($pendidikanFormal->load(['jenjangPendidikan', 'perguruanTinggi', 'prodiPerguruanTinggi', 'gelarAkademik'])),
            'message' => $message
        ]);
    }

    // Delete pendidikan formal
    public function destroy($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $pendidikanFormal = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$pendidikanFormal) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal tidak ditemukan'
            ], 404);
        }

        // Delete files if exists
        if ($pendidikanFormal->file_ijazah) {
            Storage::delete('public/pegawai/pendidikan/ijazah/'.$pendidikanFormal->file_ijazah);
        }
        if ($pendidikanFormal->file_transkrip) {
            Storage::delete('public/pegawai/pendidikan/transkrip/'.$pendidikanFormal->file_transkrip);
        }

        $oldData = $pendidikanFormal->toArray();
        $pendidikanFormal->delete();

        ActivityLogger::log('delete', $pendidikanFormal, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data pendidikan formal berhasil dihapus'
        ]);
    }

    // Submit draft to diajukan
    public function submitDraft($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $pendidikanFormal = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$pendidikanFormal) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $pendidikanFormal->getOriginal();
        
        $pendidikanFormal->update([
            'status_pengajuan' => 'diajukan',
            'tanggal_diajukan' => now()
        ]);

        ActivityLogger::log('update', $pendidikanFormal, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data pendidikan formal berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete pendidikan formal
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_pendidikan_formal,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $pendidikanFormalList = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->get();

        if ($pendidikanFormalList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data pendidikan formal tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($pendidikanFormalList as $pendidikanFormal) {
            try {
                // Delete files if exists
                if ($pendidikanFormal->file_ijazah) {
                    Storage::delete('public/pegawai/pendidikan/ijazah/'.$pendidikanFormal->file_ijazah);
                }
                if ($pendidikanFormal->file_transkrip) {
                    Storage::delete('public/pegawai/pendidikan/transkrip/'.$pendidikanFormal->file_transkrip);
                }

                $oldData = $pendidikanFormal->toArray();
                $pendidikanFormal->delete();
                
                ActivityLogger::log('delete', $pendidikanFormal, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $pendidikanFormal->id,
                    'bidang_studi' => $pendidikanFormal->bidang_studi,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data pendidikan formal",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data pendidikan formal",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }

    // Batch submit drafts
    public function batchSubmitDrafts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $updatedCount = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tanggal_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data pendidikan formal untuk persetujuan",
            'updated_count' => $updatedCount
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

        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $updateData = ['status_pengajuan' => $request->status_pengajuan];

        // Set timestamp based on status
        switch ($request->status_pengajuan) {
            case 'diajukan':
                $updateData['tanggal_diajukan'] = now();
                break;
            case 'disetujui':
                $updateData['tanggal_disetujui'] = now();
                break;
            case 'ditolak':
                $updateData['tanggal_ditolak'] = now();
                break;
        }

        $updatedCount = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui',
            'updated_count' => $updatedCount
        ]);
    }

    // Get status statistics for dashboard
    public function getStatusStatistics()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $statistics = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->selectRaw('status_pengajuan, COUNT(*) as total')
            ->groupBy('status_pengajuan')
            ->get()
            ->pluck('total', 'status_pengajuan')
            ->toArray();

        $defaultStats = [
            'draft' => 0,
            'diajukan' => 0,
            'disetujui' => 0,
            'ditolak' => 0
        ];

        $statistics = array_merge($defaultStats, $statistics);
        $statistics['total'] = array_sum($statistics);

        return response()->json([
            'success' => true,
            'statistics' => $statistics
        ]);
    }

    // Get system configuration
    public function getSystemConfig()
    {
        $config = [
            'submission_mode' => env('SUBMISSION_MODE', 'draft'),
            'allow_edit_after_submit' => env('ALLOW_EDIT_AFTER_SUBMIT', false),
            'require_document_upload' => env('REQUIRE_DOCUMENT_UPLOAD', true),
            'max_draft_days' => env('MAX_DRAFT_DAYS', 30),
            'auto_submit_reminder_days' => env('AUTO_SUBMIT_REMINDER_DAYS', 7)
        ];

        return response()->json([
            'success' => true,
            'config' => $config,
            'status_flow' => [
                [
                    'status' => 'draft',
                    'label' => 'Draft',
                    'description' => 'Data tersimpan tapi belum diajukan',
                    'color' => 'secondary',
                    'icon' => 'edit',
                    'actions' => ['edit', 'delete', 'submit']
                ],
                [
                    'status' => 'diajukan',
                    'label' => 'Diajukan',
                    'description' => 'Menunggu persetujuan atasan',
                    'color' => 'info',
                    'icon' => 'clock',
                    'actions' => ['view']
                ],
                [
                    'status' => 'disetujui',
                    'label' => 'Disetujui',
                    'description' => 'Telah disetujui oleh atasan',
                    'color' => 'success',
                    'icon' => 'check-circle',
                    'actions' => ['view']
                ],
                [
                    'status' => 'ditolak',
                    'label' => 'Ditolak',
                    'description' => 'Ditolak oleh atasan',
                    'color' => 'danger',
                    'icon' => 'x-circle',
                    'actions' => ['view', 'edit', 'resubmit']
                ]
            ]
        ]);
    }

    // Get filter options
    public function getFilterOptions()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $jenjangPendidikanList = SimpegJenjangPendidikan::select('id', 'jenjang_pendidikan as nama')
            ->orderBy('jenjang_pendidikan')
            ->get()
            ->toArray();

        $bidangStudiList = SimpegDataPendidikanFormal::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('bidang_studi')
            ->filter()
            ->values();

        $perguruanTinggiList = MasterPerguruanTinggi::select('id', 'nama_universitas as nama')
            ->orderBy('nama_universitas')
            ->get()
            ->toArray();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'jenjang_pendidikan' => $jenjangPendidikanList,
                'bidang_studi' => $bidangStudiList,
                'perguruan_tinggi' => $perguruanTinggiList,
                'lokasi_studi' => [
                    ['id' => 'dalam_negeri', 'nama' => 'Dalam Negeri'],
                    ['id' => 'luar_negeri', 'nama' => 'Luar Negeri']
                ],
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ]
            ]
        ]);
    }

    // Get available actions
    public function getAvailableActions()
    {
        return response()->json([
            'success' => true,
            'actions' => [
                'single' => [
                    [
                        'key' => 'view',
                        'label' => 'Lihat Detail',
                        'icon' => 'eye',
                        'color' => 'info'
                    ],
                    [
                        'key' => 'edit',
                        'label' => 'Edit',
                        'icon' => 'edit',
                        'color' => 'warning',
                        'condition' => 'can_edit'
                    ],
                    [
                        'key' => 'delete',
                        'label' => 'Hapus',
                        'icon' => 'trash',
                        'color' => 'danger',
                        'confirm' => true,
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus data pendidikan formal ini?',
                        'condition' => 'can_delete'
                    ],
                    [
                        'key' => 'submit',
                        'label' => 'Ajukan',
                        'icon' => 'paper-plane',
                        'color' => 'primary',
                        'condition' => 'can_submit'
                    ]
                ],
                'batch' => [
                    [
                        'key' => 'batch_delete',
                        'label' => 'Hapus Terpilih',
                        'icon' => 'trash',
                        'color' => 'danger',
                        'confirm' => true,
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua data pendidikan formal yang dipilih?'
                    ],
                    [
                        'key' => 'batch_submit',
                        'label' => 'Ajukan Terpilih',
                        'icon' => 'paper-plane',
                        'color' => 'primary'
                    ],
                    [
                        'key' => 'batch_update_status',
                        'label' => 'Update Status Terpilih',
                        'icon' => 'check-circle',
                        'color' => 'info'
                    ]
                ]
            ],
            'status_options' => [
                ['value' => 'draft', 'label' => 'Draft', 'color' => 'secondary'],
                ['value' => 'diajukan', 'label' => 'Diajukan', 'color' => 'info'],
                ['value' => 'disetujui', 'label' => 'Disetujui', 'color' => 'success'],
                ['value' => 'ditolak', 'label' => 'Ditolak', 'color' => 'danger']
            ]
        ]);
    }

    // Helper: Format pegawai info
    private function formatPegawaiInfo($pegawai)
    {
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
        if ($pegawai->dataPendidikanFormal && $pegawai->dataPendidikanFormal->isNotEmpty()) {
            $highestEducation = $pegawai->dataPendidikanFormal->first();
            if ($highestEducation && $highestEducation->jenjangPendidikan) {
                $jenjangPendidikanNama = $highestEducation->jenjangPendidikan->jenjang_pendidikan ?? '-';
            }
        }

        $unitKerjaNama = 'Tidak Ada';
        if ($pegawai->unitKerja) {
            $unitKerjaNama = $pegawai->unitKerja->nama_unit;
        } elseif ($pegawai->unit_kerja_id) {
            $unitKerja = SimpegUnitKerja::find($pegawai->unit_kerja_id);
            $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $pegawai->unit_kerja_id;
        }

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

    // Helper: Format pendidikan formal response
    protected function formatPendidikanFormal($pendidikanFormal, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $pendidikanFormal->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        $data = [
            'id' => $pendidikanFormal->id,
            'lokasi_studi' => $pendidikanFormal->lokasi_studi,
            'jenjang_pendidikan_id' => $pendidikanFormal->jenjang_pendidikan_id,
            'jenjang_pendidikan' => $pendidikanFormal->jenjangPendidikan ? $pendidikanFormal->jenjangPendidikan->jenjang_pendidikan : '-',
            'perguruan_tinggi_id' => $pendidikanFormal->perguruan_tinggi_id,
            'perguruan_tinggi' => $pendidikanFormal->perguruanTinggi ? $pendidikanFormal->perguruanTinggi->nama_universitas : '-',
            'prodi_perguruan_tinggi_id' => $pendidikanFormal->prodi_perguruan_tinggi_id,
            'prodi' => $pendidikanFormal->prodiPerguruanTinggi ? $pendidikanFormal->prodiPerguruanTinggi->nama_prodi : '-',
            'gelar_akademik_id' => $pendidikanFormal->gelar_akademik_id,
            'gelar_akademik' => $pendidikanFormal->gelarAkademik ? $pendidikanFormal->gelarAkademik->nama_gelar : '-',
            'bidang_studi' => $pendidikanFormal->bidang_studi,
            'nisn' => $pendidikanFormal->nisn,
            'konsentrasi' => $pendidikanFormal->konsentrasi,
            'tahun_masuk' => $pendidikanFormal->tahun_masuk,
            'tanggal_kelulusan' => $pendidikanFormal->tanggal_kelulusan,
            'tahun_lulus' => $pendidikanFormal->tahun_lulus,
            'nomor_ijazah' => $pendidikanFormal->nomor_ijazah,
            'tanggal_ijazah' => $pendidikanFormal->tanggal_ijazah,
            'nomor_ijazah_negara' => $pendidikanFormal->nomor_ijazah_negara,
            'gelar_ijazah_negara' => $pendidikanFormal->gelar_ijazah_negara,
            'tanggal_ijazah_negara' => $pendidikanFormal->tanggal_ijazah_negara,
            'nomor_induk' => $pendidikanFormal->nomor_induk,
            'judul_tugas' => $pendidikanFormal->judul_tugas,
            'letak_gelar' => $pendidikanFormal->letak_gelar,
            'jumlah_semester_ditempuh' => $pendidikanFormal->jumlah_semester_ditempuh,
            'jumlah_sks_kelulusan' => $pendidikanFormal->jumlah_sks_kelulusan,
            'ipk_kelulusan' => $pendidikanFormal->ipk_kelulusan,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            // 'keterangan' => $pendidikanFormal->keterangan,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $pendidikanFormal->tgl_input,
                'tanggal_diajukan' => $pendidikanFormal->tanggal_diajukan ?? null,
                'tanggal_disetujui' => $pendidikanFormal->tanggal_disetujui ?? null,
                'tanggal_ditolak' => $pendidikanFormal->tanggal_ditolak ?? null
            ],
            'dokumen' => [
                'ijazah' => $pendidikanFormal->file_ijazah ? [
                    'nama_file' => $pendidikanFormal->file_ijazah,
                    'url' => url('storage/pegawai/pendidikan/ijazah/'.$pendidikanFormal->file_ijazah)
                ] : null,
                'transkrip' => $pendidikanFormal->file_transkrip ? [
                    'nama_file' => $pendidikanFormal->file_transkrip,
                    'url' => url('storage/pegawai/pendidikan/transkrip/'.$pendidikanFormal->file_transkrip)
                ] : null
            ],
            'created_at' => $pendidikanFormal->created_at,
            'updated_at' => $pendidikanFormal->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/dosen/pendidikanformaldosen/{$pendidikanFormal->id}"),
                'update_url' => url("/api/dosen/pendidikanformaldosen/{$pendidikanFormal->id}"),
                'delete_url' => url("/api/dosen/pendidikanformaldosen/{$pendidikanFormal->id}"),
                'submit_url' => url("/api/dosen/pendidikanformaldosen/{$pendidikanFormal->id}/submit"),
            ];

            // Conditional action URLs based on permissions
            $data['actions'] = [];
            
            if ($canEdit) {
                $data['actions']['edit'] = [
                    'url' => $data['aksi']['update_url'],
                    'method' => 'PUT',
                    'label' => 'Edit',
                    'icon' => 'edit',
                    'color' => 'warning'
                ];
            }
            
            if ($canDelete) {
                $data['actions']['delete'] = [
                    'url' => $data['aksi']['delete_url'],
                    'method' => 'DELETE',
                    'label' => 'Hapus',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data pendidikan formal "' . $pendidikanFormal->bidang_studi . '"?'
                ];
            }
            
            if ($canSubmit) {
                $data['actions']['submit'] = [
                    'url' => $data['aksi']['submit_url'],
                    'method' => 'PATCH',
                    'label' => 'Ajukan',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data pendidikan formal "' . $pendidikanFormal->bidang_studi . '" untuk persetujuan?'
                ];
            }
            
            // Always allow view/detail
            $data['actions']['view'] = [
                'url' => $data['aksi']['detail_url'],
                'method' => 'GET',
                'label' => 'Lihat Detail',
                'icon' => 'eye',
                'color' => 'info'
            ];
        }

        return $data;
    }

    // Helper: Get status info
    private function getStatusInfo($status)
    {
        $statusMap = [
            'draft' => [
                'label' => 'Draft',
                'color' => 'secondary',
                'icon' => 'edit',
                'description' => 'Belum diajukan'
            ],
            'diajukan' => [
                'label' => 'Diajukan',
                'color' => 'info',
                'icon' => 'clock',
                'description' => 'Menunggu persetujuan'
            ],
            'disetujui' => [
                'label' => 'Disetujui',
                'color' => 'success',
                'icon' => 'check-circle',
                'description' => 'Telah disetujui'
            ],
            'ditolak' => [
                'label' => 'Ditolak',
                'color' => 'danger',
                'icon' => 'x-circle',
                'description' => 'Ditolak, dapat diedit ulang'
            ]
        ];

        return $statusMap[$status] ?? [
            'label' => ucfirst($status),
            'color' => 'secondary',
            'icon' => 'circle',
            'description' => ''
        ];
    }
}