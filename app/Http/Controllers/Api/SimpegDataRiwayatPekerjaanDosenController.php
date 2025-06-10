<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataRiwayatPekerjaan;
use App\Models\SimpegDataPendukung;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataRiwayatPekerjaanDosenController extends Controller
{
    // Get all data riwayat pekerjaan for logged in dosen
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

        // Query HANYA untuk pegawai yang sedang login
        $query = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id);

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('bidang_usaha', 'like', '%'.$search.'%')
                  ->orWhere('jenis_pekerjaan', 'like', '%'.$search.'%')
                  ->orWhere('jabatan', 'like', '%'.$search.'%')
                  ->orWhere('instansi', 'like', '%'.$search.'%');
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('bidang_usaha')) {
            $query->where('bidang_usaha', 'like', '%'.$request->bidang_usaha.'%');
        }
        if ($request->filled('jenis_pekerjaan')) {
            $query->where('jenis_pekerjaan', 'like', '%'.$request->jenis_pekerjaan.'%');
        }
        if ($request->filled('instansi')) {
            $query->where('instansi', 'like', '%'.$request->instansi.'%');
        }
        if ($request->filled('mulai_bekerja')) {
            $query->whereDate('mulai_bekerja', $request->mulai_bekerja);
        }
        if ($request->filled('area_pekerjaan')) {
            $query->where('area_pekerjaan', $request->area_pekerjaan);
        }

        // Execute query dengan pagination
        $dataPekerjaan = $query->orderBy('mulai_bekerja', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataPekerjaan->getCollection()->transform(function ($item) {
            return $this->formatDataPekerjaan($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataPekerjaan,
            'empty_data' => $dataPekerjaan->isEmpty(),
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
                ['field' => 'bidang_usaha', 'label' => 'Bidang Usaha', 'sortable' => true, 'sortable_field' => 'bidang_usaha'],
                ['field' => 'jenis_pekerjaan', 'label' => 'Jenis Pekerjaan', 'sortable' => true, 'sortable_field' => 'jenis_pekerjaan'],
                ['field' => 'jabatan', 'label' => 'Jabatan', 'sortable' => true, 'sortable_field' => 'jabatan'],
                ['field' => 'instansi', 'label' => 'Instansi', 'sortable' => true, 'sortable_field' => 'instansi'],
                ['field' => 'mulai_bekerja', 'label' => 'Mulai Bekerja', 'sortable' => true, 'sortable_field' => 'mulai_bekerja'],
                ['field' => 'selesai_bekerja', 'label' => 'Selesai Bekerja', 'sortable' => true, 'sortable_field' => 'selesai_bekerja'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_url' => url("/api/dosen/data-riwayat-pekerjaan-dosen"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/data-riwayat-pekerjaan-dosen/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/data-riwayat-pekerjaan-dosen/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/data-riwayat-pekerjaan-dosen/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataPekerjaan->currentPage(),
                'per_page' => $dataPekerjaan->perPage(),
                'total' => $dataPekerjaan->total(),
                'last_page' => $dataPekerjaan->lastPage(),
                'from' => $dataPekerjaan->firstItem(),
                'to' => $dataPekerjaan->lastItem()
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
        $updatedCount = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data riwayat pekerjaan",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data riwayat pekerjaan
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPekerjaan = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
            ->with('dataPendukung')
            ->find($id);

        if (!$dataPekerjaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan tidak ditemukan'
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
            'data' => $this->formatDataPekerjaan($dataPekerjaan)
        ]);
    }

    // Store new data riwayat pekerjaan dengan draft/submit mode
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
            'bidang_usaha' => 'required|string|max:200',
            'jenis_pekerjaan' => 'required|string|max:50',
            'jabatan' => 'required|string|max:50',
            'instansi' => 'required|string|max:100',
            'divisi' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string',
            'mulai_bekerja' => 'required|date',
            'selesai_bekerja' => 'nullable|date|after_or_equal:mulai_bekerja',
            'area_pekerjaan' => 'required|boolean',
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.tipe_dokumen' => 'required|string|in:file',
            'dokumen_pendukung.*.file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'dokumen_pendukung.*.nama_dokumen' => 'required|string|max:100',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|string',
            'dokumen_pendukung.*.keterangan' => 'nullable|string',
            'submit_type' => 'sometimes|in:draft,submit', // Optional, default to draft
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Start database transaction
        DB::beginTransaction();

        try {
            $data = $request->except(['dokumen_pendukung', 'submit_type']);
            $data['pegawai_id'] = $pegawai->id;
            $data['tgl_input'] = now()->toDateString();

            // Set status berdasarkan submit_type (default: draft)
            $submitType = $request->input('submit_type', 'draft');
            if ($submitType === 'submit') {
                $data['status_pengajuan'] = 'diajukan';
                $data['tgl_diajukan'] = now();
                $message = 'Data riwayat pekerjaan berhasil diajukan untuk persetujuan';
            } else {
                $data['status_pengajuan'] = 'draft';
                $message = 'Data riwayat pekerjaan berhasil disimpan sebagai draft';
            }

            $dataPekerjaan = SimpegDataRiwayatPekerjaan::create($data);

            // Handle dokumen pendukung
            if ($request->hasFile('dokumen_pendukung')) {
                $this->saveDokumenPendukung($request->file('dokumen_pendukung'), $request->dokumen_pendukung, $dataPekerjaan);
            }

            DB::commit();
            
            ActivityLogger::log('create', $dataPekerjaan, $dataPekerjaan->toArray());

            return response()->json([
                'success' => true,
                'data' => $this->formatDataPekerjaan($dataPekerjaan->fresh(['dataPendukung'])),
                'message' => $message
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update data riwayat pekerjaan dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPekerjaan = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
            ->with('dataPendukung')
            ->find($id);

        if (!$dataPekerjaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataPekerjaan->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'bidang_usaha' => 'sometimes|string|max:200',
            'jenis_pekerjaan' => 'sometimes|string|max:50',
            'jabatan' => 'sometimes|string|max:50',
            'instansi' => 'sometimes|string|max:100',
            'divisi' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string',
            'mulai_bekerja' => 'sometimes|date',
            'selesai_bekerja' => 'nullable|date|after_or_equal:mulai_bekerja',
            'area_pekerjaan' => 'sometimes|boolean',
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.tipe_dokumen' => 'required|string|in:file',
            'dokumen_pendukung.*.file' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'dokumen_pendukung.*.nama_dokumen' => 'required|string|max:100',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|string',
            'dokumen_pendukung.*.keterangan' => 'nullable|string',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string',
            'dokumen_to_delete' => 'nullable|array',
            'dokumen_to_delete.*' => 'integer|exists:simpeg_data_pendukung,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Start database transaction
        DB::beginTransaction();

        try {
            $oldData = $dataPekerjaan->getOriginal();
            $data = $request->except(['dokumen_pendukung', 'submit_type', 'dokumen_to_delete']);

            // Reset status jika dari ditolak
            if ($dataPekerjaan->status_pengajuan === 'ditolak') {
                $data['status_pengajuan'] = 'draft';
                $data['tgl_ditolak'] = null;
                $data['keterangan'] = $request->keterangan ?? null;
            }

            // Handle submit_type
            if ($request->submit_type === 'submit') {
                $data['status_pengajuan'] = 'diajukan';
                $data['tgl_diajukan'] = now();
                $message = 'Data riwayat pekerjaan berhasil diperbarui dan diajukan untuk persetujuan';
            } else {
                $message = 'Data riwayat pekerjaan berhasil diperbarui';
            }

            $dataPekerjaan->update($data);

            // Handle dokumen yang akan dihapus
            if ($request->has('dokumen_to_delete') && is_array($request->dokumen_to_delete)) {
                foreach ($request->dokumen_to_delete as $dokumenId) {
                    $dokumen = SimpegDataPendukung::where('id', $dokumenId)
                        ->where('pendukungable_type', 'App\Models\SimpegDataRiwayatPekerjaan')
                        ->where('pendukungable_id', $dataPekerjaan->id)
                        ->first();
                    
                    if ($dokumen) {
                        // Hapus file dari storage
                        if ($dokumen->file_path) {
                            Storage::delete('public/pegawai/riwayat-pekerjaan/' . $dokumen->file_path);
                        }
                        $dokumen->delete();
                    }
                }
            }

            // Handle dokumen pendukung baru
            if ($request->hasFile('dokumen_pendukung')) {
                $this->saveDokumenPendukung($request->file('dokumen_pendukung'), $request->dokumen_pendukung, $dataPekerjaan);
            }

            DB::commit();
            
            ActivityLogger::log('update', $dataPekerjaan, $oldData);

            return response()->json([
                'success' => true,
                'data' => $this->formatDataPekerjaan($dataPekerjaan->fresh(['dataPendukung'])),
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete data riwayat pekerjaan
    public function destroy($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataPekerjaan = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
            ->with('dataPendukung')
            ->find($id);

        if (!$dataPekerjaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan tidak ditemukan'
            ], 404);
        }

        // Start database transaction
        DB::beginTransaction();

        try {
            // Delete all dokumen pendukung
            foreach ($dataPekerjaan->dataPendukung as $dokumen) {
                if ($dokumen->file_path) {
                    Storage::delete('public/pegawai/riwayat-pekerjaan/' . $dokumen->file_path);
                }
                $dokumen->delete();
            }

            $oldData = $dataPekerjaan->toArray();
            $dataPekerjaan->delete();

            DB::commit();
            
            ActivityLogger::log('delete', $dataPekerjaan, $oldData);

            return response()->json([
                'success' => true,
                'message' => 'Data riwayat pekerjaan berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data: ' . $e->getMessage()
            ], 500);
        }
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

        $dataPekerjaan = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataPekerjaan) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataPekerjaan->getOriginal();
        
        $dataPekerjaan->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataPekerjaan, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat pekerjaan berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete data riwayat pekerjaan
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_riwayat_pekerjaan,id'
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

        $dataList = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->with('dataPendukung')
            ->get();

        if ($dataList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat pekerjaan tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($dataList as $dataPekerjaan) {
                try {
                    // Delete all dokumen pendukung
                    foreach ($dataPekerjaan->dataPendukung as $dokumen) {
                        if ($dokumen->file_path) {
                            Storage::delete('public/pegawai/riwayat-pekerjaan/' . $dokumen->file_path);
                        }
                        $dokumen->delete();
                    }

                    $oldData = $dataPekerjaan->toArray();
                    $dataPekerjaan->delete();
                    
                    ActivityLogger::log('delete', $dataPekerjaan, $oldData);
                    $deletedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $dataPekerjaan->id,
                        'instansi' => $dataPekerjaan->instansi,
                        'error' => 'Gagal menghapus: ' . $e->getMessage()
                    ];
                }
            }

            DB::commit();

            if ($deletedCount == count($request->ids)) {
                return response()->json([
                    'success' => true,
                    'message' => "Berhasil menghapus {$deletedCount} data riwayat pekerjaan",
                    'deleted_count' => $deletedCount
                ]);
            } else {
                return response()->json([
                    'success' => $deletedCount > 0,
                    'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data riwayat pekerjaan",
                    'deleted_count' => $deletedCount,
                    'errors' => $errors
                ], $deletedCount > 0 ? 207 : 422);
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data: ' . $e->getMessage()
            ], 500);
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

        $updatedCount = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data riwayat pekerjaan untuk persetujuan",
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

        $updatedCount = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
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

        $statistics = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
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
            'require_document_upload' => env('REQUIRE_DOCUMENT_UPLOAD', false),
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

        $bidangUsaha = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('bidang_usaha')
            ->filter()
            ->values();

        $jenisPekerjaan = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('jenis_pekerjaan')
            ->filter()
            ->values();

        $instansi = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('instansi')
            ->filter()
            ->values();

        $jabatan = SimpegDataRiwayatPekerjaan::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('jabatan')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'bidang_usaha' => $bidangUsaha,
                'jenis_pekerjaan' => $jenisPekerjaan,
                'instansi' => $instansi,
                'jabatan' => $jabatan,
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus data riwayat pekerjaan ini?',
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua data riwayat pekerjaan yang dipilih?'
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

    // Helper: Menyimpan dokumen pendukung
    private function saveDokumenPendukung($files, $dokumenData, $dataPekerjaan)
    {
        foreach ($files as $index => $file) {
            if (!$file) continue;
            
            $fileName = 'riwayat_pekerjaan_' . time() . '_' . $index . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/riwayat-pekerjaan', $fileName);
            
            // FIXED: Gunakan polymorphic relationship yang benar
            SimpegDataPendukung::create([
                'tipe_dokumen' => $dokumenData[$index]['tipe_dokumen'] ?? 'file',
                'file_path' => $fileName,
                'nama_dokumen' => $dokumenData[$index]['nama_dokumen'] ?? 'Dokumen Riwayat Pekerjaan',
                'jenis_dokumen_id' => $dokumenData[$index]['jenis_dokumen_id'] ?? null,
                'keterangan' => $dokumenData[$index]['keterangan'] ?? null,
                // FIXED: Gunakan polymorphic columns yang benar
                'pendukungable_type' => 'App\Models\SimpegDataRiwayatPekerjaan',
                'pendukungable_id' => $dataPekerjaan->id
            ]);
        }
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

    // Helper: Format data pekerjaan response
    protected function formatDataPekerjaan($dataPekerjaan, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataPekerjaan->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        $data = [
            'id' => $dataPekerjaan->id,
            'bidang_usaha' => $dataPekerjaan->bidang_usaha,
            'jenis_pekerjaan' => $dataPekerjaan->jenis_pekerjaan,
            'jabatan' => $dataPekerjaan->jabatan,
            'instansi' => $dataPekerjaan->instansi,
            'divisi' => $dataPekerjaan->divisi,
            'deskripsi' => $dataPekerjaan->deskripsi,
            'mulai_bekerja' => $dataPekerjaan->mulai_bekerja,
            'selesai_bekerja' => $dataPekerjaan->selesai_bekerja,
            'area_pekerjaan' => $dataPekerjaan->area_pekerjaan,
            'area_pekerjaan_text' => $dataPekerjaan->area_pekerjaan ? 'Dalam Negeri' : 'Luar Negeri',
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataPekerjaan->keterangan,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataPekerjaan->tgl_input,
                'tgl_diajukan' => $dataPekerjaan->tgl_diajukan,
                'tgl_disetujui' => $dataPekerjaan->tgl_disetujui,
                'tgl_ditolak' => $dataPekerjaan->tgl_ditolak
            ],
            'created_at' => $dataPekerjaan->created_at,
            'updated_at' => $dataPekerjaan->updated_at
        ];

        // Add dokumen pendukung if available
        if ($dataPekerjaan->relationLoaded('dataPendukung')) {
            $data['dokumen_pendukung'] = $dataPekerjaan->dataPendukung->map(function($dokumen) {
                return [
                    'id' => $dokumen->id,
                    'tipe_dokumen' => $dokumen->tipe_dokumen,
                    'nama_dokumen' => $dokumen->nama_dokumen,
                    'jenis_dokumen_id' => $dokumen->jenis_dokumen_id,
                    'keterangan' => $dokumen->keterangan,
                    'file' => [
                        'nama_file' => $dokumen->file_path,
                        'url' => url('storage/pegawai/riwayat-pekerjaan/'.$dokumen->file_path)
                    ]
                ];
            });
        }

        // Add action URLs if requested
        if ($includeActions) {
            // Get URL prefix from request
            $request = request();
            $prefix = $request->segment(2) ?? 'dosen'; // fallback to 'dosen'
            
            $data['aksi'] = [
                'detail_url' => url("/api/{$prefix}/data-riwayat-pekerjaan-dosen/{$dataPekerjaan->id}"),
                'update_url' => url("/api/{$prefix}/data-riwayat-pekerjaan-dosen/{$dataPekerjaan->id}"),
                'delete_url' => url("/api/{$prefix}/data-riwayat-pekerjaan-dosen/{$dataPekerjaan->id}"),
                'submit_url' => url("/api/{$prefix}/data-riwayat-pekerjaan-dosen/{$dataPekerjaan->id}/submit"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data riwayat pekerjaan di "' . $dataPekerjaan->instansi . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data riwayat pekerjaan di "' . $dataPekerjaan->instansi . '" untuk persetujuan?'
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