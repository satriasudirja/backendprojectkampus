<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataHubunganKerja;
use App\Models\HubunganKerja;
use App\Models\SimpegStatusAktif;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataHubunganKerjaController extends Controller
{
    // Get all data hubungan kerja for logged in pegawai
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

        // Query HANYA untuk pegawai yang sedang login (data hubungan kerja)
        $query = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->with(['hubunganKerja', 'statusAktif']);

        // Filter by search (no_sk, pejabat_penetap, tgl_awal, tgl_sk)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('no_sk', 'like', '%'.$search.'%')
                  ->orWhere('pejabat_penetap', 'like', '%'.$search.'%')
                  ->orWhere('tgl_awal', 'like', '%'.$search.'%')
                  ->orWhere('tgl_sk', 'like', '%'.$search.'%')
                  ->orWhereHas('hubunganKerja', function($query) use ($search) {
                      $query->where('nama_hub_kerja', 'like', '%'.$search.'%');
                  })
                  ->orWhereHas('statusAktif', function($query) use ($search) {
                      $query->where('nama_status_aktif', 'like', '%'.$search.'%');
                  });
            });
        }

        // Filter by status pengajuan (jika ada field status_pengajuan)
        if ($statusPengajuan && $statusPengajuan != 'semua' && \Schema::hasColumn('simpeg_data_hubungan_kerja', 'status_pengajuan')) {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('hubungan_kerja_id')) {
            $query->where('hubungan_kerja_id', $request->hubungan_kerja_id);
        }
        if ($request->filled('status_aktif_id')) {
            $query->where('status_aktif_id', $request->status_aktif_id);
        }
        if ($request->filled('tgl_awal')) {
            $query->whereDate('tgl_awal', $request->tgl_awal);
        }
        if ($request->filled('tgl_akhir')) {
            $query->whereDate('tgl_akhir', $request->tgl_akhir);
        }
        if ($request->filled('tgl_sk')) {
            $query->whereDate('tgl_sk', $request->tgl_sk);
        }

        // Execute query dengan pagination
        $dataHubunganKerja = $query->orderBy('tgl_awal', 'desc')
                                 ->orderBy('created_at', 'desc')
                                 ->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataHubunganKerja->getCollection()->transform(function ($item) {
            return $this->formatDataHubunganKerja($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataHubunganKerja,
            'empty_data' => $dataHubunganKerja->isEmpty(),
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ]
            ],
            'table_columns' => [
                ['field' => 'no_sk', 'label' => 'No. SK', 'sortable' => true, 'sortable_field' => 'no_sk'],
                ['field' => 'tgl_sk', 'label' => 'Tanggal SK', 'sortable' => true, 'sortable_field' => 'tgl_sk'],
                ['field' => 'tgl_awal', 'label' => 'Tanggal Awal', 'sortable' => true, 'sortable_field' => 'tgl_awal'],
                ['field' => 'tgl_akhir', 'label' => 'Tanggal Akhir', 'sortable' => true, 'sortable_field' => 'tgl_akhir'],
                ['field' => 'nama_hub_kerja', 'label' => 'Hubungan Kerja', 'sortable' => true, 'sortable_field' => 'nama_hub_kerja'],
                ['field' => 'nama_status_aktif', 'label' => 'Status Aktif', 'sortable' => true, 'sortable_field' => 'nama_status_aktif'],
                ['field' => 'pejabat_penetap', 'label' => 'Pejabat Penetap', 'sortable' => true, 'sortable_field' => 'pejabat_penetap'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'is_aktif', 'label' => 'Status', 'sortable' => true, 'sortable_field' => 'is_aktif'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_hubungan_kerja_url' => url("/api/dosen/hubungankerja"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/hubungankerja/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ]
            ],
            'pagination' => [
                'current_page' => $dataHubunganKerja->currentPage(),
                'per_page' => $dataHubunganKerja->perPage(),
                'total' => $dataHubunganKerja->total(),
                'last_page' => $dataHubunganKerja->lastPage(),
                'from' => $dataHubunganKerja->firstItem(),
                'to' => $dataHubunganKerja->lastItem()
            ]
        ]);
    }

    // Fix existing data dengan status_pengajuan null (jika ada field status_pengajuan)
    public function fixExistingData()
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        // Check jika ada column status_pengajuan
        if (!\Schema::hasColumn('simpeg_data_hubungan_kerja', 'status_pengajuan')) {
            return response()->json([
                'success' => false,
                'message' => 'Field status_pengajuan tidak tersedia di tabel ini'
            ], 400);
        }

        // Update data yang status_pengajuan-nya null menjadi draft
        $updatedCount = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data hubungan kerja",
            'updated_count' => $updatedCount
        ]);
    }

    // Bulk fix all existing data
    public function bulkFixExistingData()
    {
        // Check jika ada column status_pengajuan
        if (!\Schema::hasColumn('simpeg_data_hubungan_kerja', 'status_pengajuan')) {
            return response()->json([
                'success' => false,
                'message' => 'Field status_pengajuan tidak tersedia di tabel ini'
            ], 400);
        }

        $updatedCount = SimpegDataHubunganKerja::whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data hubungan kerja dari semua pegawai",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data hubungan kerja
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataHubunganKerja = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->with(['hubunganKerja', 'statusAktif'])
            ->find($id);

        if (!$dataHubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
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
            'data' => $this->formatDataHubunganKerja($dataHubunganKerja, false)
        ]);
    }

    // Store new data hubungan kerja
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
            'hubungan_kerja_id' => 'required|exists:simpeg_hubungan_kerja,id',
            'status_aktif_id' => 'required|exists:simpeg_status_aktif,id',
            'tgl_awal' => 'required|date',
            'tgl_akhir' => 'nullable|date|after:tgl_awal',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'required|string|max:100',
            'file_hubungan_kerja' => 'nullable|file|mimes:pdf|max:5120', // Max 5MB
            'status_pengajuan' => 'nullable|in:draft,diajukan,disetujui,ditolak',
            'is_aktif' => 'nullable|in:0,1,true,false'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['file_hubungan_kerja']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Set default status pengajuan if not provided
        if (!isset($data['status_pengajuan']) && \Schema::hasColumn('simpeg_data_hubungan_kerja', 'status_pengajuan')) {
            $data['status_pengajuan'] = 'draft';
        }

        // Convert is_aktif to boolean if column exists
        if (isset($data['is_aktif']) && \Schema::hasColumn('simpeg_data_hubungan_kerja', 'is_aktif')) {
            $data['is_aktif'] = in_array($data['is_aktif'], [1, '1', 'true', true], true);
        }

        // Handle file upload
        if ($request->hasFile('file_hubungan_kerja')) {
            $file = $request->file('file_hubungan_kerja');
            $fileName = 'hubungan_kerja_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('uploads/hubungan_kerja', $fileName, 'public');
            $data['file_hubungan_kerja'] = $filePath;
        }

        // Jika diaktifkan, nonaktifkan yang lain
        if (isset($data['is_aktif']) && $data['is_aktif'] && \Schema::hasColumn('simpeg_data_hubungan_kerja', 'is_aktif')) {
            SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false]);
        }

        $dataHubunganKerja = SimpegDataHubunganKerja::create($data);

        ActivityLogger::log('create', $dataHubunganKerja, $dataHubunganKerja->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataHubunganKerja($dataHubunganKerja->load(['hubunganKerja', 'statusAktif']), false),
            'message' => 'Data hubungan kerja berhasil disimpan'
        ], 201);
    }

    // Update data hubungan kerja
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataHubunganKerja = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataHubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'hubungan_kerja_id' => 'sometimes|exists:simpeg_hubungan_kerja,id',
            'status_aktif_id' => 'sometimes|exists:simpeg_status_aktif,id',
            'tgl_awal' => 'sometimes|date',
            'tgl_akhir' => 'nullable|date|after:tgl_awal',
            'no_sk' => 'sometimes|string|max:100',
            'tgl_sk' => 'sometimes|date',
            'pejabat_penetap' => 'sometimes|string|max:100',
            'file_hubungan_kerja' => 'nullable|file|mimes:pdf|max:5120', // Max 5MB
            'status_pengajuan' => 'nullable|in:draft,diajukan,disetujui,ditolak',
            'is_aktif' => 'nullable|in:0,1,true,false'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $dataHubunganKerja->getOriginal();
        $data = $request->except(['file_hubungan_kerja']);

        // Convert is_aktif to boolean if column exists
        if (isset($data['is_aktif']) && \Schema::hasColumn('simpeg_data_hubungan_kerja', 'is_aktif')) {
            $data['is_aktif'] = in_array($data['is_aktif'], [1, '1', 'true', true], true);
        }

        // Handle file upload
        if ($request->hasFile('file_hubungan_kerja')) {
            // Delete old file if exists
            if ($dataHubunganKerja->file_hubungan_kerja) {
                Storage::disk('public')->delete($dataHubunganKerja->file_hubungan_kerja);
            }
            
            $file = $request->file('file_hubungan_kerja');
            $fileName = 'hubungan_kerja_' . $pegawai->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('uploads/hubungan_kerja', $fileName, 'public');
            $data['file_hubungan_kerja'] = $filePath;
        }

        // Jika diaktifkan, nonaktifkan yang lain
        if (isset($data['is_aktif']) && $data['is_aktif'] && \Schema::hasColumn('simpeg_data_hubungan_kerja', 'is_aktif')) {
            SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
                ->where('id', '!=', $id)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false]);
        }

        $dataHubunganKerja->update($data);

        ActivityLogger::log('update', $dataHubunganKerja, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataHubunganKerja($dataHubunganKerja->load(['hubunganKerja', 'statusAktif']), false),
            'message' => 'Data hubungan kerja berhasil diperbarui'
        ]);
    }

    // Delete data hubungan kerja
    public function destroy($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataHubunganKerja = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataHubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($dataHubunganKerja->file_hubungan_kerja) {
            Storage::disk('public')->delete($dataHubunganKerja->file_hubungan_kerja);
        }

        $oldData = $dataHubunganKerja->toArray();
        $dataHubunganKerja->delete();

        ActivityLogger::log('delete', $dataHubunganKerja, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data hubungan kerja berhasil dihapus'
        ]);
    }

        public function batchUpdateStatus(Request $request)
    {
        // 1. Validasi Input
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array', // Memastikan 'ids' ada dan berupa array
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak' // Memastikan status yang dikirim valid
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Cek Pengguna yang Login
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        // 3. Siapkan Data untuk Diupdate
        $updateData = ['status_pengajuan' => $request->status_pengajuan];

        // Secara dinamis menambahkan timestamp berdasarkan status baru
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

        // 4. Jalankan Query Update Massal
        $updatedCount = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id) // Hanya update data milik user ini (Penting untuk keamanan)
            ->whereIn('id', $request->ids) // Hanya untuk ID yang dipilih
            ->update($updateData); // Lakukan update

        // 5. Kirim Respons Sukses
        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui',
            'updated_count' => $updatedCount
        ]);
    }

    // Batch delete data hubungan kerja
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_hubungan_kerja,id'
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

        $dataHubunganKerjaList = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataHubunganKerjaList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataHubunganKerjaList as $dataHubunganKerja) {
            try {
                // Delete file if exists
                if ($dataHubunganKerja->file_hubungan_kerja) {
                    Storage::disk('public')->delete($dataHubunganKerja->file_hubungan_kerja);
                }

                $oldData = $dataHubunganKerja->toArray();
                $dataHubunganKerja->delete();
                
                ActivityLogger::log('delete', $dataHubunganKerja, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataHubunganKerja->id,
                    'no_sk' => $dataHubunganKerja->no_sk,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data hubungan kerja",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data hubungan kerja",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }

    // NEW: Update status pengajuan
    public function updateStatusPengajuan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
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

        $dataHubunganKerja = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataHubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
            ], 404);
        }

        if (!\Schema::hasColumn('simpeg_data_hubungan_kerja', 'status_pengajuan')) {
            return response()->json([
                'success' => false,
                'message' => 'Field status_pengajuan tidak tersedia di tabel ini'
            ], 400);
        }

        $oldData = $dataHubunganKerja->getOriginal();
        $dataHubunganKerja->update(['status_pengajuan' => $request->status_pengajuan]);

        ActivityLogger::log('update', $dataHubunganKerja, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui'
        ]);
    }

    // NEW: Activate/Deactivate hubungan kerja
    public function toggleActive(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataHubunganKerja = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataHubunganKerja) {
            return response()->json([
                'success' => false,
                'message' => 'Data hubungan kerja tidak ditemukan'
            ], 404);
        }

        if (!\Schema::hasColumn('simpeg_data_hubungan_kerja', 'is_aktif')) {
            return response()->json([
                'success' => false,
                'message' => 'Field is_aktif tidak tersedia di tabel ini'
            ], 400);
        }

        $currentStatus = $dataHubunganKerja->is_aktif ?? false;
        $newStatus = !$currentStatus;

        // Jika mengaktifkan, nonaktifkan yang lain
        if ($newStatus) {
            SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
                ->where('id', '!=', $id)
                ->update(['is_aktif' => false]);
        }

        $oldData = $dataHubunganKerja->getOriginal();
        $dataHubunganKerja->update(['is_aktif' => $newStatus]);

        ActivityLogger::log('update', $dataHubunganKerja, $oldData);

        return response()->json([
            'success' => true,
            'message' => $newStatus ? 'Hubungan kerja berhasil diaktifkan' : 'Hubungan kerja berhasil dinonaktifkan',
            'is_aktif' => $newStatus
        ]);
    }

    // Get dropdown options for create/update forms
    public function getFormOptions()
    {
        $hubunganKerja = HubunganKerja::get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama_hub_kerja
                ];
            })
            ->sortBy('nama')
            ->values();

        $statusAktif = SimpegStatusAktif::get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama_status_aktif
                ];
            })
            ->sortBy('nama')
            ->values();

        return response()->json([
            'success' => true,
            'form_options' => [
                'hubungan_kerja' => $hubunganKerja,
                'status_aktif' => $statusAktif,
                'status_pengajuan' => [
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ]
            ]
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

        // Basic statistics berdasarkan status aktif dan hubungan kerja
        $totalData = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)->count();
        
        $statisticsByHubunganKerja = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->with('hubunganKerja')
            ->get()
            ->groupBy('hubungan_kerja_id')
            ->map(function($group) {
                return [
                    'nama' => $group->first()->hubunganKerja->nama_hub_kerja ?? 'Unknown',
                    'total' => $group->count()
                ];
            });

        $statisticsByStatusAktif = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->with('statusAktif')
            ->get()
            ->groupBy('status_aktif_id')
            ->map(function($group) {
                return [
                    'nama' => $group->first()->statusAktif->nama_status_aktif ?? 'Unknown',
                    'total' => $group->count()
                ];
            });

        // Statistics berdasarkan status pengajuan (jika ada)
        $statisticsByStatusPengajuan = [];
        if (\Schema::hasColumn('simpeg_data_hubungan_kerja', 'status_pengajuan')) {
            $statisticsByStatusPengajuan = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
                ->selectRaw('status_pengajuan, COUNT(*) as total')
                ->groupBy('status_pengajuan')
                ->pluck('total', 'status_pengajuan')
                ->map(function($total, $status) {
                    $labels = [
                        'draft' => 'Draft',
                        'diajukan' => 'Diajukan',
                        'disetujui' => 'Disetujui',
                        'ditolak' => 'Ditolak'
                    ];
                    return [
                        'nama' => $labels[$status] ?? $status,
                        'total' => $total
                    ];
                });
        }

        // Statistics berdasarkan is_aktif (jika ada)
        $statisticsByIsAktif = [];
        if (\Schema::hasColumn('simpeg_data_hubungan_kerja', 'is_aktif')) {
            $aktifCount = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
                ->where('is_aktif', true)->count();
            $nonAktifCount = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
                ->where('is_aktif', false)->count();
            
            $statisticsByIsAktif = [
                'aktif' => ['nama' => 'Aktif', 'total' => $aktifCount],
                'non_aktif' => ['nama' => 'Tidak Aktif', 'total' => $nonAktifCount]
            ];
        }

        return response()->json([
            'success' => true,
            'statistics' => [
                'total' => $totalData,
                'by_hubungan_kerja' => $statisticsByHubunganKerja,
                'by_status_aktif' => $statisticsByStatusAktif,
                'by_status_pengajuan' => $statisticsByStatusPengajuan,
                'by_is_aktif' => $statisticsByIsAktif
            ]
        ]);
    }

    // Get system configuration
    public function getSystemConfig()
    {
        $config = [
            'max_file_size' => 5120, // 5MB in KB
            'allowed_file_types' => ['pdf'],
            'has_status_pengajuan' => \Schema::hasColumn('simpeg_data_hubungan_kerja', 'status_pengajuan'),
            'has_is_aktif' => \Schema::hasColumn('simpeg_data_hubungan_kerja', 'is_aktif')
        ];

        return response()->json([
            'success' => true,
            'config' => $config
        ]);
    }

    // Download file hubungan kerja
    public function downloadFile($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataHubunganKerja = SimpegDataHubunganKerja::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataHubunganKerja || !$dataHubunganKerja->file_hubungan_kerja) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan'
            ], 404);
        }

        $filePath = storage_path('app/public/' . $dataHubunganKerja->file_hubungan_kerja);
        
        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan di storage'
            ], 404);
        }

        return response()->download($filePath);
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

    // Helper: Format data hubungan kerja response
    protected function formatDataHubunganKerja($dataHubunganKerja, $includeActions = true)
    {
        $data = [
            'id' => $dataHubunganKerja->id,
            'hubungan_kerja_id' => $dataHubunganKerja->hubungan_kerja_id,
            'nama_hub_kerja' => $dataHubunganKerja->hubunganKerja ? $dataHubunganKerja->hubunganKerja->nama_hub_kerja : '-',
            'status_aktif_id' => $dataHubunganKerja->status_aktif_id,
            'nama_status_aktif' => $dataHubunganKerja->statusAktif ? $dataHubunganKerja->statusAktif->nama_status_aktif : '-',
            'tgl_awal' => $dataHubunganKerja->tgl_awal,
            'tgl_awal_formatted' => $dataHubunganKerja->tgl_awal ? $dataHubunganKerja->tgl_awal->format('d-m-Y') : '-',
            'tgl_akhir' => $dataHubunganKerja->tgl_akhir,
            'tgl_akhir_formatted' => $dataHubunganKerja->tgl_akhir ? $dataHubunganKerja->tgl_akhir->format('d-m-Y') : '-',
            'no_sk' => $dataHubunganKerja->no_sk,
            'tgl_sk' => $dataHubunganKerja->tgl_sk,
            'tgl_sk_formatted' => $dataHubunganKerja->tgl_sk ? $dataHubunganKerja->tgl_sk->format('d-m-Y') : '-',
            'pejabat_penetap' => $dataHubunganKerja->pejabat_penetap,
            'file_hubungan_kerja' => $dataHubunganKerja->file_hubungan_kerja,
            'file_url' => $dataHubunganKerja->file_hubungan_kerja ? Storage::url($dataHubunganKerja->file_hubungan_kerja) : null,
            'tgl_input' => $dataHubunganKerja->tgl_input,
            'tgl_input_formatted' => $dataHubunganKerja->tgl_input ? $dataHubunganKerja->tgl_input->format('d-m-Y') : '-',
            'created_at' => $dataHubunganKerja->created_at,
            'updated_at' => $dataHubunganKerja->updated_at
        ];

        // Add status fields if columns exist
        if (\Schema::hasColumn('simpeg_data_hubungan_kerja', 'status_pengajuan')) {
            $data['status_pengajuan'] = $dataHubunganKerja->status_pengajuan ?? 'draft';
            $statusLabels = [
                'draft' => 'Draft',
                'diajukan' => 'Diajukan', 
                'disetujui' => 'Disetujui',
                'ditolak' => 'Ditolak'
            ];
            $data['status_pengajuan_label'] = $statusLabels[$data['status_pengajuan']] ?? $data['status_pengajuan'];
        }

        if (\Schema::hasColumn('simpeg_data_hubungan_kerja', 'is_aktif')) {
            $data['is_aktif'] = $dataHubunganKerja->is_aktif ?? false;
            $data['is_aktif_label'] = $data['is_aktif'] ? 'Aktif' : 'Tidak Aktif';
        }

        // Add action URLs if requested
        if ($includeActions) {
            // Get URL prefix from request
            $request = request();
            $prefix = $request->segment(2) ?? 'dosen'; // fallback to 'dosen'
            
            $data['aksi'] = [
                'detail_url' => url("/api/{$prefix}/hubungankerja/{$dataHubunganKerja->id}"),
                'update_url' => url("/api/{$prefix}/hubungankerja/{$dataHubunganKerja->id}"),
                'delete_url' => url("/api/{$prefix}/hubungankerja/{$dataHubunganKerja->id}"),
                'download_url' => $dataHubunganKerja->file_hubungan_kerja ? url("/api/{$prefix}/hubungankerja/{$dataHubunganKerja->id}/download") : null,
            ];

            // Add status action URLs if columns exist
            if (\Schema::hasColumn('simpeg_data_hubungan_kerja', 'status_pengajuan')) {
                $data['aksi']['status_url'] = url("/api/{$prefix}/hubungankerja/{$dataHubunganKerja->id}/status");
            }

            if (\Schema::hasColumn('simpeg_data_hubungan_kerja', 'is_aktif')) {
                $data['aksi']['toggle_active_url'] = url("/api/{$prefix}/hubungankerja/{$dataHubunganKerja->id}/toggle-active");
            }

            // Action URLs
            $data['actions'] = [];
            
            $data['actions']['edit'] = [
                'url' => $data['aksi']['update_url'],
                'method' => 'PUT',
                'label' => 'Edit',
                'icon' => 'edit',
                'color' => 'warning'
            ];
            
            $data['actions']['delete'] = [
                'url' => $data['aksi']['delete_url'],
                'method' => 'DELETE',
                'label' => 'Hapus',
                'icon' => 'trash',
                'color' => 'danger',
                'confirm' => true,
                'confirm_message' => 'Apakah Anda yakin ingin menghapus data hubungan kerja dengan No. SK "' . $dataHubunganKerja->no_sk . '"?'
            ];

            // Download action if file exists
            if ($dataHubunganKerja->file_hubungan_kerja) {
                $data['actions']['download'] = [
                    'url' => $data['aksi']['download_url'],
                    'method' => 'GET',
                    'label' => 'Download File',
                    'icon' => 'download',
                    'color' => 'success'
                ];
            }
            
            // Toggle active action if column exists
            if (\Schema::hasColumn('simpeg_data_hubungan_kerja', 'is_aktif')) {
                $isAktif = $dataHubunganKerja->is_aktif ?? false;
                $data['actions']['toggle_active'] = [
                    'url' => $data['aksi']['toggle_active_url'],
                    'method' => 'POST',
                    'label' => $isAktif ? 'Nonaktifkan' : 'Aktifkan',
                    'icon' => $isAktif ? 'pause-circle' : 'play-circle',
                    'color' => $isAktif ? 'secondary' : 'primary'
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
}