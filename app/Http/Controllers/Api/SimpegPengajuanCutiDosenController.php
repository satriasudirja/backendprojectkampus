<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PengajuanCutiDosen;
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

        // Query HANYA untuk dosen yang sedang login
        $query = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id);

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
            'table_columns' => [
                ['field' => 'no_urut_cuti', 'label' => 'No. Urut Cuti', 'sortable' => true, 'sortable_field' => 'no_urut_cuti'],
                ['field' => 'jenis_cuti', 'label' => 'Jenis Cuti', 'sortable' => true, 'sortable_field' => 'jenis_cuti'],
                ['field' => 'tgl_mulai', 'label' => 'Tanggal Mulai', 'sortable' => true, 'sortable_field' => 'tgl_mulai'],
                ['field' => 'tgl_selesai', 'label' => 'Tanggal Selesai', 'sortable' => true, 'sortable_field' => 'tgl_selesai'],
                ['field' => 'jumlah_cuti', 'label' => 'Jumlah Hari', 'sortable' => true, 'sortable_field' => 'jumlah_cuti'],
                ['field' => 'alasan_cuti', 'label' => 'Alasan', 'sortable' => true, 'sortable_field' => 'alasan_cuti'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_cuti_url' => url("/api/dosen/pengajuan-cuti-dosen"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/pengajuan-cuti-dosen/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/pengajuan-cuti-dosen/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/pengajuan-cuti-dosen/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $pengajuanCuti->currentPage(),
                'per_page' => $pengajuanCuti->perPage(),
                'total' => $pengajuanCuti->total(),
                'last_page' => $pengajuanCuti->lastPage(),
                'from' => $pengajuanCuti->firstItem(),
                'to' => $pengajuanCuti->lastItem()
            ]
        ]);
    }

    // Fix existing data dengan status_pengajuan null
    public function fixExistingData()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        // Update data yang status_pengajuan-nya null menjadi draft
        $updatedCount = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
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
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $pengajuanCuti = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$pengajuanCuti) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan cuti tidak ditemukan'
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
            'data' => $this->formatPengajuanCuti($pengajuanCuti)
        ]);
    }

    // Store new pengajuan cuti dengan draft/submit mode
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
            'jenis_cuti' => 'required|string|in:Besar,Sakit,Melahirkan,Alasan Penting,Tahunan,Di Luar Tanggungan Negara',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'jumlah_cuti' => 'required|integer|min:1',
            'alasan_cuti' => 'required|string|max:255',
            'alamat_selama_cuti' => 'required|string|max:255',
            'no_telp' => 'required|string|max:20',
            'file_cuti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'sometimes|in:draft,submit', // Optional, default to draft
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['file_cuti', 'submit_type']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Generate no_urut_cuti
        $lastUrut = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->max('no_urut_cuti');
        $data['no_urut_cuti'] = $lastUrut ? $lastUrut + 1 : 1;

        // Hitung jumlah hari cuti jika tidak diinput
        if (!$request->jumlah_cuti) {
            $tglMulai = new \DateTime($request->tgl_mulai);
            $tglSelesai = new \DateTime($request->tgl_selesai);
            $selisih = $tglMulai->diff($tglSelesai);
            $data['jumlah_cuti'] = $selisih->days + 1; // inklusive
        }

        // Set status berdasarkan submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Pengajuan cuti berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Pengajuan cuti berhasil disimpan sebagai draft';
        }

        // Handle file upload
        if ($request->hasFile('file_cuti')) {
            $file = $request->file('file_cuti');
            $fileName = 'cuti_dosen_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/cuti', $fileName);
            $data['file_cuti'] = $fileName;
        }

        $pengajuanCuti = SimpegPengajuanCutiDosen::create($data);

        ActivityLogger::log('create', $pengajuanCuti, $pengajuanCuti->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatPengajuanCuti($pengajuanCuti),
            'message' => $message
        ], 201);
    }

    // Update pengajuan cuti dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $pengajuanCuti = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$pengajuanCuti) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan cuti tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($pengajuanCuti->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'jenis_cuti' => 'sometimes|string|in:Besar,Sakit,Melahirkan,Alasan Penting,Tahunan,Di Luar Tanggungan Negara',
            'tgl_mulai' => 'sometimes|date',
            'tgl_selesai' => 'sometimes|date|after_or_equal:tgl_mulai',
            'jumlah_cuti' => 'sometimes|integer|min:1',
            'alasan_cuti' => 'sometimes|string|max:255',
            'alamat_selama_cuti' => 'sometimes|string|max:255',
            'no_telp' => 'sometimes|string|max:20',
            'file_cuti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $pengajuanCuti->getOriginal();
        $data = $request->except(['file_cuti', 'submit_type']);

        // Reset status jika dari ditolak
        if ($pengajuanCuti->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null;
            $data['keterangan'] = $request->keterangan ?? null;
        }

        // Handle submit_type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Pengajuan cuti berhasil diperbarui dan diajukan untuk persetujuan';
        } else {
            $message = 'Pengajuan cuti berhasil diperbarui';
        }

        // Hitung jumlah hari cuti jika tgl_mulai atau tgl_selesai diubah
        if (($request->has('tgl_mulai') || $request->has('tgl_selesai')) && !$request->has('jumlah_cuti')) {
            $tglMulai = new \DateTime($request->tgl_mulai ?? $pengajuanCuti->tgl_mulai);
            $tglSelesai = new \DateTime($request->tgl_selesai ?? $pengajuanCuti->tgl_selesai);
            $selisih = $tglMulai->diff($tglSelesai);
            $data['jumlah_cuti'] = $selisih->days + 1; // inklusive
        }

        // Handle file upload
        if ($request->hasFile('file_cuti')) {
            if ($pengajuanCuti->file_cuti) {
                Storage::delete('public/pegawai/cuti/'.$pengajuanCuti->file_cuti);
            }

            $file = $request->file('file_cuti');
            $fileName = 'cuti_dosen_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/cuti', $fileName);
            $data['file_cuti'] = $fileName;
        }

        $pengajuanCuti->update($data);

        ActivityLogger::log('update', $pengajuanCuti, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatPengajuanCuti($pengajuanCuti),
            'message' => $message
        ]);
    }

    // Delete pengajuan cuti
    public function destroy($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $pengajuanCuti = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$pengajuanCuti) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan cuti tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($pengajuanCuti->file_cuti) {
            Storage::delete('public/pegawai/cuti/'.$pengajuanCuti->file_cuti);
        }

        $oldData = $pengajuanCuti->toArray();
        $pengajuanCuti->delete();

        ActivityLogger::log('delete', $pengajuanCuti, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti berhasil dihapus'
        ]);
    }

    // Submit draft ke diajukan
    public function submitDraft($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $pengajuanCuti = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$pengajuanCuti) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan cuti draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $pengajuanCuti->getOriginal();
        
        $pengajuanCuti->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $pengajuanCuti, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete pengajuan cuti
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_pengajuan_cuti_dosen,id'
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

        $pengajuanCutiList = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->get();

        if ($pengajuanCutiList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan cuti tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($pengajuanCutiList as $pengajuanCuti) {
            try {
                // Delete file if exists
                if ($pengajuanCuti->file_cuti) {
                    Storage::delete('public/pegawai/cuti/'.$pengajuanCuti->file_cuti);
                }

                $oldData = $pengajuanCuti->toArray();
                $pengajuanCuti->delete();
                
                ActivityLogger::log('delete', $pengajuanCuti, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $pengajuanCuti->id,
                    'no_urut_cuti' => $pengajuanCuti->no_urut_cuti,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data pengajuan cuti",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data pengajuan cuti",
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
            'ids.*' => 'required|integer'
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

        $updatedCount = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data pengajuan cuti untuk persetujuan",
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
                $updateData['tgl_diajukan'] = now();
                break;
            case 'disetujui':
                $updateData['tgl_disetujui'] = now();
                break;
            case 'ditolak':
                $updateData['tgl_ditolak'] = now();
                break;
        }

        $updatedCount = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui',
            'updated_count' => $updatedCount
        ]);
    }

    // Get status statistics untuk dashboard
    public function getStatusStatistics()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $statistics = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
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

        // Get statistics for jenis cuti
        $jenisCutiStats = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->selectRaw('jenis_cuti, COUNT(*) as total')
            ->groupBy('jenis_cuti')
            ->get()
            ->pluck('total', 'jenis_cuti')
            ->toArray();

        // Get total days for each jenis_cuti
        $jenisCutiDaysStats = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'disetujui')
            ->selectRaw('jenis_cuti, SUM(jumlah_cuti) as total_hari')
            ->groupBy('jenis_cuti')
            ->get()
            ->pluck('total_hari', 'jenis_cuti')
            ->toArray();

        return response()->json([
            'success' => true,
            'statistics' => [
                'status' => $statistics,
                'jenis_cuti' => $jenisCutiStats,
                'hari_cuti' => $jenisCutiDaysStats
            ]
        ]);
    }

    // Get system configuration
    public function getSystemConfig()
    {
        $config = [
            'submission_mode' => env('SUBMISSION_MODE', 'draft'),
            'allow_edit_after_submit' => env('ALLOW_EDIT_AFTER_SUBMIT', false),
            'require_document_upload' => env('REQUIRE_DOCUMENT_UPLOAD', false),
            'max_draft_days' => env('MAX_DRAFT_DAYS', 30),
            'auto_submit_reminder_days' => env('AUTO_SUBMIT_REMINDER_DAYS', 7),
            'jenis_cuti_options' => [
                'Besar', 
                'Sakit',
                'Melahirkan',
                'Alasan Penting',
                'Tahunan',
                'Di Luar Tanggungan Negara'
            ],
            'max_days' => [
                'Besar' => 90,
                'Sakit' => 365,
                'Melahirkan' => 90,
                'Alasan Penting' => 60,
                'Tahunan' => 12,
                'Di Luar Tanggungan Negara' => 730
            ]
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
                    'actions' => ['view', 'print']
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

        $jenisCuti = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('jenis_cuti')
            ->filter()
            ->values();

        $tglMulai = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('tgl_mulai')
            ->filter()
            ->sort()
            ->values();

        $jumlahCuti = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('jumlah_cuti')
            ->filter()
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'jenis_cuti' => $jenisCuti,
                'tgl_mulai' => $tglMulai,
                'jumlah_cuti' => $jumlahCuti,
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus pengajuan cuti ini?',
                        'condition' => 'can_delete'
                    ],
                    [
                        'key' => 'submit',
                        'label' => 'Ajukan',
                        'icon' => 'paper-plane',
                        'color' => 'primary',
                        'condition' => 'can_submit'
                    ],
                    [
                        'key' => 'print',
                        'label' => 'Cetak',
                        'icon' => 'printer',
                        'color' => 'success',
                        'condition' => 'can_print'
                    ]
                ],
                'batch' => [
                    [
                        'key' => 'batch_delete',
                        'label' => 'Hapus Terpilih',
                        'icon' => 'trash',
                        'color' => 'danger',
                        'confirm' => true,
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua pengajuan cuti yang dipilih?'
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

    // Helper: Calculate remaining cuti based on jenis_cuti
    public function getRemainingCuti(Request $request)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $jenisCuti = $request->jenis_cuti;
        if (!$jenisCuti) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter jenis_cuti diperlukan'
            ], 422);
        }

        // Map jenis cuti to max days
        $maxCutiMap = [
            'Besar' => 90,
            'Sakit' => 365,
            'Melahirkan' => 90,
            'Alasan Penting' => 60,
            'Tahunan' => 12,
            'Di Luar Tanggungan Negara' => 730
        ];

        if (!isset($maxCutiMap[$jenisCuti])) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis cuti tidak valid'
            ], 422);
        }

        // Get used days for this jenis_cuti in the current year
        $currentYear = date('Y');
        $usedDays = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->where('jenis_cuti', $jenisCuti)
            ->where('status_pengajuan', 'disetujui')
            ->whereYear('tgl_mulai', $currentYear)
            ->sum('jumlah_cuti');

        $maxDays = $maxCutiMap[$jenisCuti];
        $remainingDays = $maxDays - $usedDays;

        return response()->json([
            'success' => true,
            'data' => [
                'jenis_cuti' => $jenisCuti,
                'max_days' => $maxDays,
                'used_days' => $usedDays,
                'remaining_days' => max(0, $remainingDays)
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

    // Helper: Format pengajuan cuti response
    protected function formatPengajuanCuti($pengajuanCuti, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $pengajuanCuti->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        $canPrint = $status === 'disetujui';
        
        $data = [
            'id' => $pengajuanCuti->id,
            'no_urut_cuti' => $pengajuanCuti->no_urut_cuti,
            'jenis_cuti' => $pengajuanCuti->jenis_cuti,
            'tgl_mulai' => $pengajuanCuti->tgl_mulai,
            'tgl_selesai' => $pengajuanCuti->tgl_selesai,
            'jumlah_cuti' => $pengajuanCuti->jumlah_cuti,
            'alasan_cuti' => $pengajuanCuti->alasan_cuti,
            'alamat_selama_cuti' => $pengajuanCuti->alamat_selama_cuti,
            'no_telp' => $pengajuanCuti->no_telp,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $pengajuanCuti->keterangan,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'can_print' => $canPrint,
            'timestamps' => [
                'tgl_input' => $pengajuanCuti->tgl_input,
                'tgl_diajukan' => $pengajuanCuti->tgl_diajukan,
                'tgl_disetujui' => $pengajuanCuti->tgl_disetujui,
                'tgl_ditolak' => $pengajuanCuti->tgl_ditolak
            ],
            'dokumen' => $pengajuanCuti->file_cuti ? [
                'nama_file' => $pengajuanCuti->file_cuti,
                'url' => url('storage/pegawai/cuti/'.$pengajuanCuti->file_cuti)
            ] : null,
            'created_at' => $pengajuanCuti->created_at,
            'updated_at' => $pengajuanCuti->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            // Get URL prefix from request
            $request = request();
            $prefix = $request->segment(2) ?? 'dosen'; // fallback to 'dosen'
            
            $data['aksi'] = [
                'detail_url' => url("/api/{$prefix}/pengajuan-cuti-dosen/{$pengajuanCuti->id}"),
                'update_url' => url("/api/{$prefix}/pengajuan-cuti-dosen/{$pengajuanCuti->id}"),
                'delete_url' => url("/api/{$prefix}/pengajuan-cuti-dosen/{$pengajuanCuti->id}"),
                'submit_url' => url("/api/{$prefix}/pengajuan-cuti-dosen/{$pengajuanCuti->id}/submit"),
                'print_url' => url("/api/{$prefix}/pengajuan-cuti-dosen/{$pengajuanCuti->id}/print"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus pengajuan cuti ini?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan cuti ini untuk persetujuan?'
                ];
            }
            
            if ($canPrint) {
                $data['actions']['print'] = [
                    'url' => $data['aksi']['print_url'],
                    'method' => 'GET',
                    'label' => 'Cetak',
                    'icon' => 'printer',
                    'color' => 'success'
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

    // Print SK Cuti (hanya untuk status disetujui)
    public function printCuti($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $pengajuanCuti = SimpegPengajuanCutiDosen::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'disetujui')
            ->find($id);

        if (!$pengajuanCuti) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan cuti tidak ditemukan atau belum disetujui'
            ], 404);
        }

        // Format data untuk cetak
        $data = $this->formatPengajuanCuti($pengajuanCuti, false);
        $data['pegawai'] = $this->formatPegawaiInfo($pegawai->load([
            'unitKerja', 'statusAktif', 'jabatanAkademik',
            'dataJabatanFungsional.jabatanFungsional',
            'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
            'dataPendidikanFormal.jenjangPendidikan'
        ]));

        // Tambahkan URL untuk cetak PDF
        $data['print_url'] = url("/api/dosen/pengajuan-cuti-dosen/{$id}/print-pdf");

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data cetak SK cuti berhasil disiapkan'
        ]);
    }
}