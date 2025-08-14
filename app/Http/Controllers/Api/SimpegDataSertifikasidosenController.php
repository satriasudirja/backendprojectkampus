<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataSertifikasi;
use App\Models\SimpegDataPendukung;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegMasterJenisSertifikasi;
use App\Models\RumpunBidangIlmu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataSertifikasidosenController extends Controller
{
    // Get all data sertifikasi for logged in pegawai
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
        $query = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
            ->with(['jenisSertifikasi', 'bidangIlmu']);

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('no_sertifikasi', 'like', '%'.$search.'%')
                  ->orWhere('no_registrasi', 'like', '%'.$search.'%')
                  ->orWhere('no_peserta', 'like', '%'.$search.'%')
                  ->orWhere('peran', 'like', '%'.$search.'%')
                  ->orWhere('penyelenggara', 'like', '%'.$search.'%')
                  ->orWhere('tempat', 'like', '%'.$search.'%')
                  ->orWhereHas('jenisSertifikasi', function($q) use ($search) {
                      $q->where('nama_jenis_sertifikasi', 'like', '%'.$search.'%');
                  })
                  ->orWhereHas('bidangIlmu', function($q) use ($search) {
                      $q->where('nama_bidang_ilmu', 'like', '%'.$search.'%');
                  });
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Additional filters
        if ($request->filled('jenis_sertifikasi_id')) {
            $query->where('jenis_sertifikasi_id', $request->jenis_sertifikasi_id);
        }
        if ($request->filled('bidang_ilmu_id')) {
            $query->where('bidang_ilmu_id', $request->bidang_ilmu_id);
        }
        if ($request->filled('tgl_sertifikasi')) {
            $query->whereDate('tgl_sertifikasi', $request->tgl_sertifikasi);
        }
        if ($request->filled('lingkup')) {
            $query->where('lingkup', $request->lingkup);
        }
        if ($request->filled('penyelenggara')) {
            $query->where('penyelenggara', 'like', '%'.$request->penyelenggara.'%');
        }

        // Execute query dengan pagination
        $dataSertifikasi = $query->orderBy('tgl_sertifikasi', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataSertifikasi->getCollection()->transform(function ($item) {
            return $this->formatDataSertifikasi($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataSertifikasi,
            'empty_data' => $dataSertifikasi->isEmpty(),
            'pegawai_info' => $this->formatPegawaiInfo($pegawai),
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ],
                'lingkup' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'Nasional', 'nama' => 'Nasional'],
                    ['id' => 'Internasional', 'nama' => 'Internasional'],
                    ['id' => 'Lokal', 'nama' => 'Lokal']
                ]
            ],
            'table_columns' => [
                ['field' => 'jenis_sertifikasi', 'label' => 'Jenis Sertifikasi', 'sortable' => true, 'sortable_field' => 'jenis_sertifikasi_id'],
                ['field' => 'no_sertifikasi', 'label' => 'Nomor Sertifikasi', 'sortable' => true, 'sortable_field' => 'no_sertifikasi'],
                ['field' => 'bidang_ilmu', 'label' => 'Bidang Ilmu', 'sortable' => true, 'sortable_field' => 'bidang_ilmu_id'],
                ['field' => 'tgl_sertifikasi', 'label' => 'Tanggal Sertifikasi', 'sortable' => true, 'sortable_field' => 'tgl_sertifikasi'],
                ['field' => 'penyelenggara', 'label' => 'Penyelenggara', 'sortable' => true, 'sortable_field' => 'penyelenggara'],
                ['field' => 'lingkup', 'label' => 'Lingkup', 'sortable' => true, 'sortable_field' => 'lingkup'],
                ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'tambah_data_sertifikasi_url' => url("/api/dosen/datasertifikasidosen"),
            'batch_actions' => [
                'delete' => [
                    'url' => url("/api/dosen/datasertifikasidosen/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true
                ],
                'submit' => [
                    'url' => url("/api/dosen/datasertifikasidosen/batch/submit"),
                    'method' => 'PATCH',
                    'label' => 'Ajukan Terpilih',
                    'icon' => 'paper-plane',
                    'color' => 'primary',
                    'confirm' => true
                ],
                'update_status' => [
                    'url' => url("/api/dosen/datasertifikasidosen/batch/status"),
                    'method' => 'PATCH',
                    'label' => 'Update Status Terpilih',
                    'icon' => 'check-circle',
                    'color' => 'info'
                ]
            ],
            'pagination' => [
                'current_page' => $dataSertifikasi->currentPage(),
                'per_page' => $dataSertifikasi->perPage(),
                'total' => $dataSertifikasi->total(),
                'last_page' => $dataSertifikasi->lastPage(),
                'from' => $dataSertifikasi->firstItem(),
                'to' => $dataSertifikasi->lastItem()
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
        $updatedCount = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
            ->whereNull('status_pengajuan')
            ->update([
                'status_pengajuan' => 'draft',
                'tgl_input' => DB::raw('COALESCE(tgl_input, created_at)')
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbaiki {$updatedCount} data sertifikasi",
            'updated_count' => $updatedCount
        ]);
    }

    // Get detail data sertifikasi
    public function show($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataSertifikasi = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
            ->with(['jenisSertifikasi', 'bidangIlmu'])
            ->find($id);

        if (!$dataSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data sertifikasi tidak ditemukan'
            ], 404);
        }

        // Load dokumen pendukung
        $dokumenPendukung = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataSertifikasi')
            ->where('pendukungable_id', $id)
            ->get();

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($pegawai->load([
                'unitKerja', 'statusAktif', 'jabatanAkademik',
                'dataJabatanFungsional.jabatanFungsional',
                'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
                'dataPendidikanFormal.jenjangPendidikan'
            ])),
            'data' => $this->formatDataSertifikasi($dataSertifikasi),
            'dokumen_pendukung' => $dokumenPendukung->map(function($dok) {
                return [
                    'id' => $dok->id,
                    'tipe_dokumen' => $dok->tipe_dokumen,
                    'nama_dokumen' => $dok->nama_dokumen,
                    'jenis_dokumen_id' => $dok->jenis_dokumen_id,
                    'keterangan' => $dok->keterangan,
                    'file_url' => $dok->file_url,
                    'file_exists' => $dok->file_exists,
                    'file_size_formatted' => $dok->file_size_formatted,
                    'file_extension' => $dok->file_extension
                ];
            })
        ]);
    }

    // Store new data sertifikasi dengan draft/submit mode
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
            'jenis_sertifikasi_id' => 'required|uuid|exists:simpeg_master_jenis_sertifikasi,id',
            'bidang_ilmu_id' => 'required|uuid|exists:simpeg_rumpun_bidang_ilmu,id',
            'no_sertifikasi' => 'required|string|max:50',
            'tgl_sertifikasi' => 'required|date|before_or_equal:today',
            'no_registrasi' => 'required|string|max:20',
            'no_peserta' => 'required|string|max:50',
            'peran' => 'required|string|max:100',
            'penyelenggara' => 'required|string|max:100',
            'tempat' => 'required|string|max:100',
            'lingkup' => 'required|in:Nasional,Internasional,Lokal',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string',
            // Dokumen pendukung
            'dokumen_pendukung' => 'nullable|array',
            'dokumen_pendukung.*.tipe_dokumen' => 'required_with:dokumen_pendukung|string',
            'dokumen_pendukung.*.nama_dokumen' => 'required_with:dokumen_pendukung|string',
            'dokumen_pendukung.*.jenis_dokumen_id' => 'nullable|uuid',
            'dokumen_pendukung.*.keterangan' => 'nullable|string',
            'dokumen_pendukung.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if no_sertifikasi already exists
        $existingSertifikasi = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
            ->where('no_sertifikasi', $request->no_sertifikasi)
            ->first();

        if ($existingSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor sertifikasi "'.$request->no_sertifikasi.'" sudah ada untuk pegawai ini'
            ], 422);
        }

        $data = $request->except(['submit_type', 'dokumen_pendukung']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now()->toDateString();

        // Set status berdasarkan submit_type (default: draft)
        $submitType = $request->input('submit_type', 'draft');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data sertifikasi berhasil diajukan untuk persetujuan';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data sertifikasi berhasil disimpan sebagai draft';
        }

        DB::beginTransaction();
        try {
            $dataSertifikasi = SimpegDataSertifikasi::create($data);

            // Handle dokumen pendukung
            if ($request->has('dokumen_pendukung') && is_array($request->dokumen_pendukung)) {
                $this->storeDokumenPendukung($request->dokumen_pendukung, $dataSertifikasi);
            }

            ActivityLogger::log('create', $dataSertifikasi, $dataSertifikasi->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatDataSertifikasi($dataSertifikasi->load(['jenisSertifikasi', 'bidangIlmu'])),
                'message' => $message
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update data sertifikasi dengan validasi status
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataSertifikasi = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)->find($id);

        if (!$dataSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data sertifikasi tidak ditemukan'
            ], 404);
        }

        // Validasi apakah bisa diedit berdasarkan status
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($dataSertifikasi->status_pengajuan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'jenis_sertifikasi_id' => 'sometimes|uuid|exists:simpeg_master_jenis_sertifikasi,id',
            'bidang_ilmu_id' => 'sometimes|uuid|exists:simpeg_rumpun_bidang_ilmu,id',
            'no_sertifikasi' => 'sometimes|string|max:50',
            'tgl_sertifikasi' => 'sometimes|date|before_or_equal:today',
            'no_registrasi' => 'sometimes|string|max:20',
            'no_peserta' => 'sometimes|string|max:50',
            'peran' => 'sometimes|string|max:100',
            'penyelenggara' => 'sometimes|string|max:100',
            'tempat' => 'sometimes|string|max:100',
            'lingkup' => 'sometimes|in:Nasional,Internasional,Lokal',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string',
            'dokumen_pendukung' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check no_sertifikasi uniqueness
        if ($request->has('no_sertifikasi')) {
            $existingSertifikasi = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
                ->where('no_sertifikasi', $request->no_sertifikasi)
                ->where('id', '!=', $id)
                ->first();

            if ($existingSertifikasi) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor sertifikasi "'.$request->no_sertifikasi.'" sudah ada untuk pegawai ini'
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $oldData = $dataSertifikasi->getOriginal();
            $data = $request->except(['submit_type', 'dokumen_pendukung']);

            // Reset status jika dari ditolak
            if ($dataSertifikasi->status_pengajuan === 'ditolak') {
                $data['status_pengajuan'] = 'draft';
                $data['tgl_ditolak'] = null;
                $data['keterangan'] = $request->keterangan ?? null;
            }

            // Handle submit_type
            if ($request->submit_type === 'submit') {
                $data['status_pengajuan'] = 'diajukan';
                $data['tgl_diajukan'] = now();
                $message = 'Data sertifikasi berhasil diperbarui dan diajukan untuk persetujuan';
            } else {
                $message = 'Data sertifikasi berhasil diperbarui';
            }

            $dataSertifikasi->update($data);

            // Handle dokumen pendukung update
            if ($request->has('dokumen_pendukung') && is_array($request->dokumen_pendukung)) {
                $this->updateDokumenPendukung($request->dokumen_pendukung, $dataSertifikasi);
            }

            ActivityLogger::log('update', $dataSertifikasi, $oldData);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $this->formatDataSertifikasi($dataSertifikasi->load(['jenisSertifikasi', 'bidangIlmu'])),
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete data sertifikasi
    public function destroy($id)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $dataSertifikasi = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)->find($id);

        if (!$dataSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data sertifikasi tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete dokumen pendukung
            $dokumenPendukung = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataSertifikasi')
                ->where('pendukungable_id', $id)
                ->get();

            foreach ($dokumenPendukung as $dokumen) {
                $dokumen->deleteFile();
                $dokumen->delete();
            }

            $oldData = $dataSertifikasi->toArray();
            $dataSertifikasi->delete();

            ActivityLogger::log('delete', $dataSertifikasi, $oldData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data sertifikasi berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
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

        $dataSertifikasi = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->find($id);

        if (!$dataSertifikasi) {
            return response()->json([
                'success' => false,
                'message' => 'Data sertifikasi draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $dataSertifikasi->getOriginal();
        
        $dataSertifikasi->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $dataSertifikasi, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data sertifikasi berhasil diajukan untuk persetujuan'
        ]);
    }

    // Batch delete data sertifikasi
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|uuid|exists:simpeg_data_sertifikasi,id'
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

        $dataSertifikasiList = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->get();

        if ($dataSertifikasiList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Data sertifikasi tidak ditemukan atau tidak memiliki akses'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataSertifikasiList as $dataSertifikasi) {
                try {
                    // Delete dokumen pendukung
                    $dokumenPendukung = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataSertifikasi')
                        ->where('pendukungable_id', $dataSertifikasi->id)
                        ->get();

                    foreach ($dokumenPendukung as $dokumen) {
                        $dokumen->deleteFile();
                        $dokumen->delete();
                    }

                    $oldData = $dataSertifikasi->toArray();
                    $dataSertifikasi->delete();
                    
                    ActivityLogger::log('delete', $dataSertifikasi, $oldData);
                    $deletedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $dataSertifikasi->id,
                        'no_sertifikasi' => $dataSertifikasi->no_sertifikasi,
                        'error' => 'Gagal menghapus: ' . $e->getMessage()
                    ];
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data: ' . $e->getMessage()
            ], 500);
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data sertifikasi",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data sertifikasi",
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

        $updatedCount = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'draft')
            ->whereIn('id', $request->ids)
            ->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengajukan {$updatedCount} data sertifikasi untuk persetujuan",
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

        $updatedCount = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
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

        $statistics = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
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

        $jenisSertifikasi = SimpegMasterJenisSertifikasi::orderBy('simpeg_master_jenis_sertifikasi')->get();
        $bidangIlmu = RumpunBidangIlmu::orderBy('simpeg_rumpun_bidang_ilmu')->get();

        $penyelenggara = SimpegDataSertifikasi::where('pegawai_id', $pegawai->id)
            ->distinct()
            ->pluck('penyelenggara')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'filter_options' => [
                'jenis_sertifikasi' => $jenisSertifikasi->map(function($item) {
                    return [
                        'id' => $item->id,
                        'nama' => $item->nama_jenis_sertifikasi
                    ];
                }),
                'bidang_ilmu' => $bidangIlmu->map(function($item) {
                    return [
                        'id' => $item->id,
                        'nama' => $item->nama_bidang_ilmu,
                        'kode' => $item->kode_bidang_ilmu ?? null
                    ];
                }),
                'penyelenggara' => $penyelenggara,
                'lingkup' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'Nasional', 'nama' => 'Nasional'],
                    ['id' => 'Internasional', 'nama' => 'Internasional'],
                    ['id' => 'Lokal', 'nama' => 'Lokal']
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus data sertifikasi ini?',
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua data sertifikasi yang dipilih?'
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

    // Store dokumen pendukung
    private function storeDokumenPendukung($dokumenArray, $sertifikasi)
    {
        foreach ($dokumenArray as $index => $dokumen) {
            $dokumenData = [
                'tipe_dokumen' => $dokumen['tipe_dokumen'],
                'nama_dokumen' => $dokumen['nama_dokumen'],
                'jenis_dokumen_id' => $dokumen['jenis_dokumen_id'] ?? null,
                'keterangan' => $dokumen['keterangan'] ?? null,
                'pendukungable_type' => 'App\Models\SimpegDataSertifikasi',
                'pendukungable_id' => $sertifikasi->id
            ];

            if (isset($dokumen['file']) && $dokumen['file']) {
                $file = $dokumen['file'];
                $fileName = 'sertifikasi_'.$sertifikasi->id.'_'.time().'_'.$index.'.'.$file->getClientOriginalExtension();
                $file->storeAs('public/pegawai/sertifikasi/dokumen', $fileName);
                $dokumenData['file_path'] = $fileName;
            }

            SimpegDataPendukung::create($dokumenData);
        }
    }

    // Update dokumen pendukung
    private function updateDokumenPendukung($dokumenArray, $sertifikasi)
    {
        // Hapus dokumen lama jika ada flag untuk menghapus
        if (isset($dokumenArray['delete_ids'])) {
            $deleteIds = $dokumenArray['delete_ids'];
            $oldDokumen = SimpegDataPendukung::where('pendukungable_type', 'App\Models\SimpegDataSertifikasi')
                ->where('pendukungable_id', $sertifikasi->id)
                ->whereIn('id', $deleteIds)
                ->get();

            foreach ($oldDokumen as $dok) {
                $dok->deleteFile();
                $dok->delete();
            }
        }

        // Tambah dokumen baru
        if (isset($dokumenArray['new'])) {
            $this->storeDokumenPendukung($dokumenArray['new'], $sertifikasi);
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

    // Helper: Format data sertifikasi response
    protected function formatDataSertifikasi($dataSertifikasi, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataSertifikasi->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        
        $data = [
            'id' => $dataSertifikasi->id,
            'jenis_sertifikasi' => $dataSertifikasi->jenisSertifikasi ? $dataSertifikasi->jenisSertifikasi->nama_jenis_sertifikasi : '-',
            'jenis_sertifikasi_id' => $dataSertifikasi->jenis_sertifikasi_id,
            'bidang_ilmu' => $dataSertifikasi->bidangIlmu ? $dataSertifikasi->bidangIlmu->nama_bidang_ilmu : '-',
            'bidang_ilmu_id' => $dataSertifikasi->bidang_ilmu_id,
            'no_sertifikasi' => $dataSertifikasi->no_sertifikasi,
            'tgl_sertifikasi' => $dataSertifikasi->tgl_sertifikasi,
            'no_registrasi' => $dataSertifikasi->no_registrasi,
            'no_peserta' => $dataSertifikasi->no_peserta,
            'peran' => $dataSertifikasi->peran,
            'penyelenggara' => $dataSertifikasi->penyelenggara,
            'tempat' => $dataSertifikasi->tempat,
            'lingkup' => $dataSertifikasi->lingkup,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'timestamps' => [
                'tgl_input' => $dataSertifikasi->tgl_input,
                'tgl_diajukan' => $dataSertifikasi->tgl_diajukan ?? null,
                'tgl_disetujui' => $dataSertifikasi->tgl_disetujui ?? null,
                'tgl_ditolak' => $dataSertifikasi->tgl_ditolak ?? null
            ],
            'created_at' => $dataSertifikasi->created_at,
            'updated_at' => $dataSertifikasi->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/dosen/datasertifikasidosen/{$dataSertifikasi->id}"),
                'update_url' => url("/api/dosen/datasertifikasidosen/{$dataSertifikasi->id}"),
                'delete_url' => url("/api/dosen/datasertifikasidosen/{$dataSertifikasi->id}"),
                'submit_url' => url("/api/dosen/datasertifikasidosen/{$dataSertifikasi->id}/submit"),
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
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data sertifikasi "' . $dataSertifikasi->no_sertifikasi . '"?'
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
                    'confirm_message' => 'Apakah Anda yakin ingin mengajukan data sertifikasi "' . $dataSertifikasi->no_sertifikasi . '" untuk persetujuan?'
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