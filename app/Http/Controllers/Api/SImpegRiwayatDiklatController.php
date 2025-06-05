<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataDiklat;
use App\Models\SimpegDataPendukung;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegJabatanFungsional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SimpegRiwayatDiklatController extends Controller
{
    // Get all riwayat diklat (for admin with hierarchical unit filter)
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $status = $request->status;
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;

        $query = SimpegDataDiklat::with([
            'pegawai' => function($query) {
                $query->with([
                    'unitKerja',
                    'dataJabatanFungsional' => function($q) {
                        $q->with('jabatanFungsional')
                          ->orderBy('tmt_jabatan', 'desc')
                          ->limit(1);
                    }
                ]);
            },
            'dataPendukung'
        ]);

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama_diklat', 'like', '%'.$search.'%')
                  ->orWhere('jenis_diklat', 'like', '%'.$search.'%')
                  ->orWhere('penyelenggara', 'like', '%'.$search.'%')
                  ->orWhere('kategori_diklat', 'like', '%'.$search.'%')
                  ->orWhere('no_sertifikat', 'like', '%'.$search.'%')
                  ->orWhereHas('pegawai', function($q2) use ($search) {
                      $q2->where('nip', 'like', '%'.$search.'%')
                         ->orWhere('nama', 'like', '%'.$search.'%');
                  });
            });
        }

        // Filter by status pengajuan
        if ($status && $status != 'semua' && \Schema::hasColumn('simpeg_data_diklat', 'status_pengajuan')) {
            $query->where('status_pengajuan', $status);
        }

        // Hierarchical Unit Kerja Filter
        if ($unitKerjaId) {
            $unitKerja = SimpegUnitKerja::where('kode_unit', $unitKerjaId)->first();
            if ($unitKerja) {
                // Tentukan level unit kerja berdasarkan struktur
                $tingkatUnit = $this->getTingkatUnitKerja($unitKerja);
                
                switch ($tingkatUnit) {
                    case 'universitas':
                        // Tampilkan semua data (tidak perlu filter tambahan)
                        break;
                        
                    case 'fakultas':
                        // Filter pegawai yang unit kerjanya berada di fakultas yang sama
                        $unitKerjaIds = $this->getUnitKerjaByFakultas($unitKerja);
                        $query->whereHas('pegawai', function($q) use ($unitKerjaIds) {
                            $q->whereIn('unit_kerja_id', $unitKerjaIds);
                        });
                        break;
                        
                    case 'prodi':
                        // Filter pegawai yang unit kerjanya sama persis (prodi)
                        $query->whereHas('pegawai', function($q) use ($unitKerjaId) {
                            $q->where('unit_kerja_id', $unitKerjaId);
                        });
                        break;
                }
            }
        }

        // Filter by jabatan fungsional
        if ($jabatanFungsionalId) {
            $query->whereHas('pegawai.dataJabatanFungsional', function($q) use ($jabatanFungsionalId) {
                $q->where('jabatan_fungsional_id', $jabatanFungsionalId);
            });
        }

        // Additional filters
        if ($request->filled('jenis_diklat')) {
            $query->where('jenis_diklat', 'like', '%'.$request->jenis_diklat.'%');
        }
        if ($request->filled('tahun_penyelenggaraan')) {
            $query->where('tahun_penyelenggaraan', $request->tahun_penyelenggaraan);
        }

        $riwayat = $query->orderBy('tgl_mulai', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $riwayat->map(function ($item) {
                return $this->formatRiwayatDiklat($item);
            }),
            'pagination' => [
                'current_page' => $riwayat->currentPage(),
                'per_page' => $riwayat->perPage(),
                'total' => $riwayat->total(),
                'last_page' => $riwayat->lastPage(),
                'from' => $riwayat->firstItem(),
                'to' => $riwayat->lastItem()
            ],
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                    ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan']
                ],
                'unit_kerja' => $this->getUnitKerjaOptions(),
                'jabatan_fungsional' => $this->getJabatanFungsionalOptions()
            ],
            'table_columns' => [
                ['field' => 'nip', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'nama_pegawai'],
                ['field' => 'nama_diklat', 'label' => 'Nama Diklat', 'sortable' => true, 'sortable_field' => 'nama_diklat'],
                ['field' => 'jenis_diklat', 'label' => 'Jenis Diklat', 'sortable' => true, 'sortable_field' => 'jenis_diklat'],
                ['field' => 'penyelenggara', 'label' => 'Penyelenggara', 'sortable' => true, 'sortable_field' => 'penyelenggara'],
                ['field' => 'tahun_penyelenggaraan', 'label' => 'Tahun', 'sortable' => true, 'sortable_field' => 'tahun_penyelenggaraan'],
                ['field' => 'tgl_diajukan', 'label' => 'Tgl. Diajukan', 'sortable' => true, 'sortable_field' => 'tgl_diajukan'],
                ['field' => 'status_pengajuan', 'label' => 'Status', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ]
        ]);
    }

    // Get riwayat diklat by pegawai ID
    public function getByPegawai($pegawaiId)
    {
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
            },
            'dataPendidikanFormal' => function($query) {
                $query->with('jenjangPendidikan')
                      ->orderBy('jenjang_pendidikan_id', 'desc')
                      ->limit(1);
            }
        ])->find($pegawaiId);

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan'
            ], 404);
        }

        $riwayat = SimpegDataDiklat::where('pegawai_id', $pegawaiId)
            ->with(['dataPendukung'])
            ->orderBy('tgl_mulai', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($pegawai),
            'data' => $riwayat->map(function ($item) {
                return $this->formatRiwayatDiklat($item);
            })
        ]);
    }

    // Get detail riwayat diklat
    public function show($id)
    {
        $riwayat = SimpegDataDiklat::with([
            'pegawai' => function($query) {
                $query->with([
                    'unitKerja',
                    'statusAktif', 
                    'jabatanAkademik',
                    'dataJabatanFungsional' => function($q) {
                        $q->with('jabatanFungsional')
                          ->orderBy('tmt_jabatan', 'desc')
                          ->limit(1);
                    },
                    'dataJabatanStruktural' => function($q) {
                        $q->with('jabatanStruktural.jenisJabatanStruktural')
                          ->orderBy('tgl_mulai', 'desc')
                          ->limit(1);
                    },
                    'dataPendidikanFormal' => function($q) {
                        $q->with('jenjangPendidikan')
                          ->orderBy('jenjang_pendidikan_id', 'desc')
                          ->limit(1);
                    }
                ]);
            },
            'dataPendukung'
        ])->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat diklat tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatDiklat($riwayat),
            'pegawai' => $this->formatPegawaiInfo($riwayat->pegawai)
        ]);
    }

    // Store new riwayat diklat
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'jenis_diklat' => 'required|string|max:100',
            'kategori_diklat' => 'required|string|max:100',
            'tingkat_diklat' => 'required|string|max:100',
            'nama_diklat' => 'required|string|max:255',
            'penyelenggara' => 'required|string|max:255',
            'peran' => 'nullable|string|max:100',
            'jumlah_jam' => 'nullable|integer|min:1',
            'no_sertifikat' => 'nullable|string|max:100',
            'tgl_sertifikat' => 'nullable|date',
            'tahun_penyelenggaraan' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'tempat' => 'nullable|string|max:255',
            'sk_penugasan' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string',
            'status_pengajuan' => 'nullable|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
            'is_aktif' => 'nullable|in:0,1,true,false',
            // Files untuk data pendukung
            'files' => 'nullable|array',
            'files.*.file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'files.*.tipe_dokumen' => 'required|string|max:100',
            'files.*.nama_dokumen' => 'required|string|max:255',
            'files.*.jenis_dokumen_id' => 'nullable|integer',
            'files.*.keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['files']);
        $data['tgl_input'] = now()->toDateString();
        
        // Set default status pengajuan if not provided
        if (!isset($data['status_pengajuan']) && \Schema::hasColumn('simpeg_data_diklat', 'status_pengajuan')) {
            $data['status_pengajuan'] = 'draft';
        }

        // Convert is_aktif to boolean if column exists
        if (isset($data['is_aktif']) && \Schema::hasColumn('simpeg_data_diklat', 'is_aktif')) {
            $data['is_aktif'] = in_array($data['is_aktif'], [1, '1', 'true', true], true);
        }

        DB::beginTransaction();
        try {
            // Jika diaktifkan, nonaktifkan yang lain
            if (isset($data['is_aktif']) && $data['is_aktif'] && \Schema::hasColumn('simpeg_data_diklat', 'is_aktif')) {
                SimpegDataDiklat::where('pegawai_id', $request->pegawai_id)
                    ->where('is_aktif', true)
                    ->update(['is_aktif' => false]);
            }

            $riwayat = SimpegDataDiklat::create($data);

            // Handle multiple file uploads untuk data pendukung
            if ($request->has('files') && is_array($request->files)) {
                foreach ($request->files as $index => $fileData) {
                    if (isset($fileData['file']) && $fileData['file']->isValid()) {
                        $file = $fileData['file'];
                        $fileName = 'diklat_'.time().'_'.$request->pegawai_id.'_'.$index.'.'.$file->getClientOriginalExtension();
                        $filePath = $file->storeAs('uploads/diklat/dokumen', $fileName, 'public');

                        SimpegDataPendukung::create([
                            'tipe_dokumen' => $request->input("files.{$index}.tipe_dokumen"),
                            'file_path' => $fileName,
                            'nama_dokumen' => $request->input("files.{$index}.nama_dokumen"),
                            'jenis_dokumen_id' => $request->input("files.{$index}.jenis_dokumen_id"),
                            'keterangan' => $request->input("files.{$index}.keterangan"),
                            'pendukungable_type' => SimpegDataDiklat::class,
                            'pendukungable_id' => $riwayat->id
                        ]);
                    }
                }
            }

            DB::commit();
            
            // Log activity if service exists
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('create', $riwayat, $riwayat->toArray());
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatRiwayatDiklat($riwayat->load(['pegawai', 'dataPendukung'])),
                'message' => 'Riwayat diklat berhasil ditambahkan'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan riwayat diklat: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update riwayat diklat
    public function update(Request $request, $id)
    {
        $riwayat = SimpegDataDiklat::with(['dataPendukung'])->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat diklat tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'jenis_diklat' => 'sometimes|string|max:100',
            'kategori_diklat' => 'sometimes|string|max:100',
            'tingkat_diklat' => 'sometimes|string|max:100',
            'nama_diklat' => 'sometimes|string|max:255',
            'penyelenggara' => 'sometimes|string|max:255',
            'peran' => 'nullable|string|max:100',
            'jumlah_jam' => 'nullable|integer|min:1',
            'no_sertifikat' => 'nullable|string|max:100',
            'tgl_sertifikat' => 'nullable|date',
            'tahun_penyelenggaraan' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'tgl_mulai' => 'sometimes|date',
            'tgl_selesai' => 'sometimes|date|after_or_equal:tgl_mulai',
            'tempat' => 'nullable|string|max:255',
            'sk_penugasan' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
            'is_aktif' => 'nullable|in:0,1,true,false',
            // Files untuk data pendukung
            'files' => 'nullable|array',
            'files.*.file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            'files.*.tipe_dokumen' => 'required|string|max:100',
            'files.*.nama_dokumen' => 'required|string|max:255',
            'files.*.jenis_dokumen_id' => 'nullable|integer',
            'files.*.keterangan' => 'nullable|string',
            // Untuk menghapus file yang sudah ada
            'remove_files' => 'nullable|array',
            'remove_files.*' => 'nullable|integer|exists:simpeg_data_pendukung,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldData = $riwayat->getOriginal();
            $data = $request->except(['files', 'remove_files']);

            // Convert is_aktif to boolean if column exists
            if (isset($data['is_aktif']) && \Schema::hasColumn('simpeg_data_diklat', 'is_aktif')) {
                $data['is_aktif'] = in_array($data['is_aktif'], [1, '1', 'true', true], true);
            }

            // Jika diaktifkan, nonaktifkan yang lain
            if (isset($data['is_aktif']) && $data['is_aktif'] && \Schema::hasColumn('simpeg_data_diklat', 'is_aktif')) {
                SimpegDataDiklat::where('pegawai_id', $riwayat->pegawai_id)
                    ->where('id', '!=', $id)
                    ->where('is_aktif', true)
                    ->update(['is_aktif' => false]);
            }

            $riwayat->update($data);

            // Handle file removal
            if ($request->has('remove_files') && is_array($request->remove_files)) {
                foreach ($request->remove_files as $fileId) {
                    $pendukung = $riwayat->dataPendukung()->find($fileId);
                    if ($pendukung) {
                        Storage::disk('public')->delete('uploads/diklat/dokumen/'.$pendukung->file_path);
                        $pendukung->delete();
                    }
                }
            }

            // Handle new file uploads
            if ($request->has('files') && is_array($request->files)) {
                foreach ($request->files as $index => $fileData) {
                    if (isset($fileData['file']) && $fileData['file']->isValid()) {
                        $file = $fileData['file'];
                        $fileName = 'diklat_'.time().'_'.$riwayat->pegawai_id.'_'.$index.'.'.$file->getClientOriginalExtension();
                        $filePath = $file->storeAs('uploads/diklat/dokumen', $fileName, 'public');

                        SimpegDataPendukung::create([
                            'tipe_dokumen' => $request->input("files.{$index}.tipe_dokumen"),
                            'file_path' => $fileName,
                            'nama_dokumen' => $request->input("files.{$index}.nama_dokumen"),
                            'jenis_dokumen_id' => $request->input("files.{$index}.jenis_dokumen_id"),
                            'keterangan' => $request->input("files.{$index}.keterangan"),
                            'pendukungable_type' => SimpegDataDiklat::class,
                            'pendukungable_id' => $riwayat->id
                        ]);
                    }
                }
            }

            DB::commit();
            
            // Log activity if service exists
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('update', $riwayat, $oldData);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatRiwayatDiklat($riwayat->load(['pegawai', 'dataPendukung'])),
                'message' => 'Riwayat diklat berhasil diperbarui'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui riwayat diklat: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete riwayat diklat
    public function destroy($id)
    {
        $riwayat = SimpegDataDiklat::with(['dataPendukung'])->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat diklat tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete files dari data pendukung
            foreach ($riwayat->dataPendukung as $pendukung) {
                Storage::disk('public')->delete('uploads/diklat/dokumen/'.$pendukung->file_path);
                $pendukung->delete();
            }

            $oldData = $riwayat->toArray();
            $riwayat->delete();

            DB::commit();
            
            // Log activity if service exists
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('delete', $riwayat, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Riwayat diklat berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus riwayat diklat: ' . $e->getMessage()
            ], 500);
        }
    }

    // Batch update status
    public function batchUpdateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak,ditangguhkan'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
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
            case 'ditangguhkan':
                $updateData['tgl_ditangguhkan'] = now();
                break;
        }

        $updatedCount = SimpegDataDiklat::whereIn('id', $request->ids)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => "Status pengajuan berhasil diperbarui untuk {$updatedCount} data",
            'updated_count' => $updatedCount
        ]);
    }

    // Batch delete
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_diklat,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $riwayat = SimpegDataDiklat::with(['dataPendukung'])->whereIn('id', $request->ids)->get();

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($riwayat as $item) {
                try {
                    // Delete files dari data pendukung
                    foreach ($item->dataPendukung as $pendukung) {
                        Storage::disk('public')->delete('uploads/diklat/dokumen/'.$pendukung->file_path);
                        $pendukung->delete();
                    }
                    
                    $oldData = $item->toArray();
                    $item->delete();
                    
                    // Log activity if service exists
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('delete', $item, $oldData);
                    }
                    
                    $deletedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $item->id,
                        'nama_diklat' => $item->nama_diklat,
                        'error' => 'Gagal menghapus: ' . $e->getMessage()
                    ];
                }
            }

            DB::commit();

            if ($deletedCount == count($request->ids)) {
                return response()->json([
                    'success' => true,
                    'message' => "Berhasil menghapus {$deletedCount} data riwayat diklat",
                    'deleted_count' => $deletedCount
                ]);
            } else {
                return response()->json([
                    'success' => $deletedCount > 0,
                    'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data riwayat diklat",
                    'deleted_count' => $deletedCount,
                    'errors' => $errors
                ], $deletedCount > 0 ? 207 : 422);
            }

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan batch delete: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update status pengajuan
    public function updateStatusPengajuan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak,ditangguhkan'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $riwayat = SimpegDataDiklat::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat diklat tidak ditemukan'
            ], 404);
        }

        if (!\Schema::hasColumn('simpeg_data_diklat', 'status_pengajuan')) {
            return response()->json([
                'success' => false,
                'message' => 'Field status_pengajuan tidak tersedia di tabel ini'
            ], 400);
        }

        $oldData = $riwayat->getOriginal();
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
            case 'ditangguhkan':
                $updateData['tgl_ditangguhkan'] = now();
                break;
        }

        $riwayat->update($updateData);

        // Log activity if service exists
        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('update', $riwayat, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui'
        ]);
    }

    // Toggle active status
    public function toggleActive(Request $request, $id)
    {
        $riwayat = SimpegDataDiklat::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat diklat tidak ditemukan'
            ], 404);
        }

        if (!\Schema::hasColumn('simpeg_data_diklat', 'is_aktif')) {
            return response()->json([
                'success' => false,
                'message' => 'Field is_aktif tidak tersedia di tabel ini'
            ], 400);
        }

        $currentStatus = $riwayat->is_aktif ?? false;
        $newStatus = !$currentStatus;

        // Jika mengaktifkan, nonaktifkan yang lain
        if ($newStatus) {
            SimpegDataDiklat::where('pegawai_id', $riwayat->pegawai_id)
                ->where('id', '!=', $id)
                ->update(['is_aktif' => false]);
        }

        $oldData = $riwayat->getOriginal();
        $riwayat->update(['is_aktif' => $newStatus]);

        // Log activity if service exists
        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('update', $riwayat, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => $newStatus ? 'Diklat berhasil diaktifkan' : 'Diklat berhasil dinonaktifkan',
            'is_aktif' => $newStatus
        ]);
    }

    // Get dropdown options for create/update forms
    public function getFormOptions()
    {
        return response()->json([
            'success' => true,
            'form_options' => [
                'status_pengajuan' => [
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                    ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan']
                ],
                'unit_kerja' => $this->getUnitKerjaOptions(),
                'jabatan_fungsional' => $this->getJabatanFungsionalOptions()
            ]
        ]);
    }

    // Download file dokumen
    public function downloadFile($id, $fileId)
    {
        $riwayat = SimpegDataDiklat::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat diklat tidak ditemukan'
            ], 404);
        }

        $pendukung = $riwayat->dataPendukung()->find($fileId);

        if (!$pendukung) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan'
            ], 404);
        }

        $filePath = storage_path('app/public/uploads/diklat/dokumen/' . $pendukung->file_path);
        
        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan di storage'
            ], 404);
        }

        return response()->download($filePath);
    }

    // Helper: Get tingkat unit kerja
    private function getTingkatUnitKerja($unitKerja)
    {
        // Logika untuk menentukan tingkat unit kerja
        // Ini bisa disesuaikan dengan struktur organisasi yang sebenarnya
        
        $namaUnit = strtolower($unitKerja->nama_unit);
        
        if (strpos($namaUnit, 'universitas') !== false || strpos($namaUnit, 'pusat') !== false) {
            return 'universitas';
        } elseif (strpos($namaUnit, 'fakultas') !== false) {
            return 'fakultas';
        } else {
            return 'prodi'; // Default untuk program studi
        }
    }

    // Helper: Get unit kerja by fakultas - FIXED
    private function getUnitKerjaByFakultas($unitKerja)
    {
        // Ambil semua unit kerja yang masih dalam satu fakultas
        // Menggunakan kode_unit sebagai key dan parent_unit_id sebagai foreign key
        
        $fakultasKode = $unitKerja->parent_unit_id ?? $unitKerja->kode_unit;
        
        return SimpegUnitKerja::where('parent_unit_id', $fakultasKode)
            ->orWhere('kode_unit', $fakultasKode)
            ->pluck('kode_unit')
            ->toArray();
    }

    // Helper: Get unit kerja options - FIXED
    private function getUnitKerjaOptions()
    {
        return SimpegUnitKerja::select('kode_unit', 'nama_unit', 'parent_unit_id')
            ->orderBy('nama_unit')
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->kode_unit,
                    'nama' => $item->nama_unit,
                    'tingkat' => $this->getTingkatUnitKerja($item)
                ];
            });
    }

    // Helper: Get jabatan fungsional options
    private function getJabatanFungsionalOptions()
    {
        return SimpegJabatanFungsional::select('id', 'nama_jabatan_fungsional')
            ->orderBy('nama_jabatan_fungsional')
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama_jabatan_fungsional ?? $item->nama ?? 'Tidak Diketahui'
                ];
            });
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
            $unitKerja = SimpegUnitKerja::where('kode_unit', $pegawai->unit_kerja_id)->first();
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

    // Format response
    protected function formatRiwayatDiklat($riwayat)
    {
        $data = [
            'id' => $riwayat->id,
            'pegawai_id' => $riwayat->pegawai_id,
            'pegawai' => $riwayat->pegawai ? [
                'nip' => $riwayat->pegawai->nip ?? '-',
                'nama' => $riwayat->pegawai->nama ?? '-',
                'unit_kerja' => $riwayat->pegawai->unitKerja ? $riwayat->pegawai->unitKerja->nama_unit : '-',
                'jabatan_fungsional' => $this->getPegawaiJabatanFungsional($riwayat->pegawai)
            ] : null,
            'nip' => $riwayat->pegawai ? $riwayat->pegawai->nip : '-',
            'nama_pegawai' => $riwayat->pegawai ? $riwayat->pegawai->nama : '-',
            'nama_diklat' => $riwayat->nama_diklat,
            'jenis_diklat' => $riwayat->jenis_diklat,
            'kategori_diklat' => $riwayat->kategori_diklat,
            'tingkat_diklat' => $riwayat->tingkat_diklat,
            'penyelenggara' => $riwayat->penyelenggara,
            'peran' => $riwayat->peran,
            'jumlah_jam' => $riwayat->jumlah_jam,
            'no_sertifikat' => $riwayat->no_sertifikat,
            'tgl_sertifikat' => $riwayat->tgl_sertifikat,
            'tgl_sertifikat_formatted' => $riwayat->tgl_sertifikat ? \Carbon\Carbon::parse($riwayat->tgl_sertifikat)->format('d-m-Y') : '-',
            'tahun_penyelenggaraan' => $riwayat->tahun_penyelenggaraan,
            'tgl_mulai' => $riwayat->tgl_mulai,
            'tgl_mulai_formatted' => $riwayat->tgl_mulai ? \Carbon\Carbon::parse($riwayat->tgl_mulai)->format('d-m-Y') : '-',
            'tgl_selesai' => $riwayat->tgl_selesai,
            'tgl_selesai_formatted' => $riwayat->tgl_selesai ? \Carbon\Carbon::parse($riwayat->tgl_selesai)->format('d-m-Y') : '-',
            'tempat' => $riwayat->tempat,
            'sk_penugasan' => $riwayat->sk_penugasan,
            'keterangan' => $riwayat->keterangan,
            'tgl_input' => $riwayat->tgl_input,
            'tgl_input_formatted' => $riwayat->tgl_input ? \Carbon\Carbon::parse($riwayat->tgl_input)->format('d-m-Y') : '-',
            'created_at' => $riwayat->created_at,
            'updated_at' => $riwayat->updated_at
        ];

        // Add status fields if columns exist
        if (\Schema::hasColumn('simpeg_data_diklat', 'status_pengajuan')) {
            $data['status_pengajuan'] = $riwayat->status_pengajuan ?? 'draft';
            $statusLabels = [
                'draft' => 'Draft',
                'diajukan' => 'Diajukan', 
                'disetujui' => 'Disetujui',
                'ditolak' => 'Ditolak',
                'ditangguhkan' => 'Ditangguhkan'
            ];
            $data['status_pengajuan_label'] = $statusLabels[$data['status_pengajuan']] ?? $data['status_pengajuan'];
            
            // Add timestamps
            $data['tgl_diajukan'] = $riwayat->tgl_diajukan ?? null;
            $data['tgl_diajukan_formatted'] = $riwayat->tgl_diajukan ? \Carbon\Carbon::parse($riwayat->tgl_diajukan)->format('d-m-Y') : '-';
            $data['timestamps'] = [
                'tgl_diajukan' => $riwayat->tgl_diajukan ?? null,
                'tgl_disetujui' => $riwayat->tgl_disetujui ?? null,
                'tgl_ditolak' => $riwayat->tgl_ditolak ?? null,
                'tgl_ditangguhkan' => $riwayat->tgl_ditangguhkan ?? null
            ];
        }

        if (\Schema::hasColumn('simpeg_data_diklat', 'is_aktif')) {
            $data['is_aktif'] = $riwayat->is_aktif ?? false;
            $data['is_aktif_label'] = $data['is_aktif'] ? 'Aktif' : 'Tidak Aktif';
        }

        // Format data pendukung (files) - FIXED URL
        if ($riwayat->dataPendukung) {
            $data['dokumen_pendukung'] = $riwayat->dataPendukung->map(function($pendukung) use ($riwayat) {
                return [
                    'id' => $pendukung->id,
                    'tipe_dokumen' => $pendukung->tipe_dokumen,
                    'nama_dokumen' => $pendukung->nama_dokumen,
                    'jenis_dokumen_id' => $pendukung->jenis_dokumen_id,
                    'keterangan' => $pendukung->keterangan,
                    'file_path' => $pendukung->file_path,
                    'url' => Storage::url('uploads/diklat/dokumen/'.$pendukung->file_path),
                    // FIXED: URL sekarang konsisten dengan route
                    'download_url' => url("/api/admin/pegawai/riwayat-diklat/{$riwayat->id}/download/{$pendukung->id}"),
                    'created_at' => $pendukung->created_at
                ];
            });
        } else {
            $data['dokumen_pendukung'] = [];
        }

        // Add status info for permissions
        $data['status'] = [
            'is_aktif' => $data['is_aktif'] ?? false,
            'pengajuan' => $data['status_pengajuan'] ?? 'draft'
        ];

        return $data;
    }

    // Helper: Get pegawai jabatan fungsional
    private function getPegawaiJabatanFungsional($pegawai)
    {
        if ($pegawai && $pegawai->dataJabatanFungsional && $pegawai->dataJabatanFungsional->isNotEmpty()) {
            $jabatanFungsional = $pegawai->dataJabatanFungsional->first()->jabatanFungsional;
            if ($jabatanFungsional) {
                return $jabatanFungsional->nama_jabatan_fungsional ?? $jabatanFungsional->nama ?? '-';
            }
        }
        return '-';
    }
}