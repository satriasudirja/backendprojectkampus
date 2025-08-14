<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataJabatanStruktural;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegJabatanStruktural;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class SimpegRiwayatJabatanStrukturalController extends Controller
{
    // Get all riwayat jabatan struktural (for admin)
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $status = $request->status;

        $query = SimpegDataJabatanStruktural::with([
            'pegawai',
            'jabatanStruktural.jenisJabatanStruktural'
        ]);

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('no_sk', 'like', '%'.$search.'%')
                  ->orWhere('pejabat_penetap', 'like', '%'.$search.'%')
                  ->orWhereHas('pegawai', function($q2) use ($search) {
                      $q2->where('nip', 'like', '%'.$search.'%')
                         ->orWhere('nama', 'like', '%'.$search.'%');
                  })
                  ->orWhereHas('jabatanStruktural.jenisJabatanStruktural', function($q2) use ($search) {
                      $q2->where('jenis_jabatan_struktural', 'like', '%'.$search.'%');
                  });
            });
        }

        // Filter by status pengajuan
        if ($status && $status != 'semua' && \Schema::hasColumn('simpeg_data_jabatan_struktural', 'status_pengajuan')) {
            $query->where('status_pengajuan', $status);
        }

        // Additional filters
        if ($request->filled('jabatan_struktural_id')) {
            $query->where('jabatan_struktural_id', $request->jabatan_struktural_id);
        }

        $riwayat = $query->orderBy('tgl_mulai', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $riwayat->map(function ($item) {
                return $this->formatRiwayatJabatanStruktural($item);
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
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ]
            ]
        ]);
    }

    // Get riwayat jabatan struktural by pegawai ID
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

        $riwayat = SimpegDataJabatanStruktural::where('pegawai_id', $pegawaiId)
            ->with(['jabatanStruktural.jenisJabatanStruktural'])
            ->orderBy('tgl_mulai', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($pegawai),
            'data' => $riwayat->map(function ($item) {
                return $this->formatRiwayatJabatanStruktural($item);
            })
        ]);
    }

    // Get detail riwayat jabatan struktural
    public function show($id)
    {
        $riwayat = SimpegDataJabatanStruktural::with([
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
            'jabatanStruktural.jenisJabatanStruktural'
        ])->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat jabatan struktural tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatJabatanStruktural($riwayat),
            'pegawai' => $this->formatPegawaiInfo($riwayat->pegawai)
        ]);
    }

    // Store new riwayat jabatan struktural
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'jabatan_struktural_id' => 'required|exists:simpeg_jabatan_struktural,id',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'nullable|date|after:tgl_mulai',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'required|string|max:100',
            'file_jabatan' => 'nullable|file|mimes:pdf|max:5120', // Max 5MB
            'status_pengajuan' => 'nullable|in:draft,diajukan,disetujui,ditolak',
            'is_aktif' => 'nullable|in:0,1,true,false'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except('file_jabatan');
        $data['tgl_input'] = now()->toDateString();
        
        // Set default status pengajuan if not provided
        if (!isset($data['status_pengajuan']) && \Schema::hasColumn('simpeg_data_jabatan_struktural', 'status_pengajuan')) {
            $data['status_pengajuan'] = 'draft';
        }

        // Convert is_aktif to boolean if column exists
        if (isset($data['is_aktif']) && \Schema::hasColumn('simpeg_data_jabatan_struktural', 'is_aktif')) {
            $data['is_aktif'] = in_array($data['is_aktif'], [1, '1', 'true', true], true);
        }

        // Handle file upload
        if ($request->hasFile('file_jabatan')) {
            $file = $request->file('file_jabatan');
            $fileName = 'jabatan_struktural_'.time().'_'.$request->pegawai_id.'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs('uploads/jabatan_struktural', $fileName, 'public');
            $data['file_jabatan'] = $filePath;
        }

        // Jika diaktifkan, nonaktifkan yang lain
        if (isset($data['is_aktif']) && $data['is_aktif'] && \Schema::hasColumn('simpeg_data_jabatan_struktural', 'is_aktif')) {
            SimpegDataJabatanStruktural::where('pegawai_id', $request->pegawai_id)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false]);
        }

        $riwayat = SimpegDataJabatanStruktural::create($data);

        ActivityLogger::log('create', $riwayat, $riwayat->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatJabatanStruktural($riwayat->load(['pegawai', 'jabatanStruktural.jenisJabatanStruktural'])),
            'message' => 'Riwayat jabatan struktural berhasil ditambahkan'
        ], 201);
    }

    // Update riwayat jabatan struktural
    public function update(Request $request, $id)
    {
        $riwayat = SimpegDataJabatanStruktural::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat jabatan struktural tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'jabatan_struktural_id' => 'sometimes|exists:simpeg_jabatan_struktural,id',
            'tgl_mulai' => 'sometimes|date',
            'tgl_selesai' => 'nullable|date|after:tgl_mulai',
            'no_sk' => 'sometimes|string|max:100',
            'tgl_sk' => 'sometimes|date',
            'pejabat_penetap' => 'sometimes|string|max:100',
            'file_jabatan' => 'nullable|file|mimes:pdf|max:5120',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            'is_aktif' => 'nullable|in:0,1,true,false'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $riwayat->getOriginal();
        $data = $request->except('file_jabatan');

        // Convert is_aktif to boolean if column exists
        if (isset($data['is_aktif']) && \Schema::hasColumn('simpeg_data_jabatan_struktural', 'is_aktif')) {
            $data['is_aktif'] = in_array($data['is_aktif'], [1, '1', 'true', true], true);
        }

        // Handle file upload
        if ($request->hasFile('file_jabatan')) {
            // Delete old file if exists
            if ($riwayat->file_jabatan) {
                Storage::disk('public')->delete($riwayat->file_jabatan);
            }
            
            $file = $request->file('file_jabatan');
            $fileName = 'jabatan_struktural_'.time().'_'.$riwayat->pegawai_id.'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs('uploads/jabatan_struktural', $fileName, 'public');
            $data['file_jabatan'] = $filePath;
        }

        // Jika diaktifkan, nonaktifkan yang lain
        if (isset($data['is_aktif']) && $data['is_aktif'] && \Schema::hasColumn('simpeg_data_jabatan_struktural', 'is_aktif')) {
            SimpegDataJabatanStruktural::where('pegawai_id', $riwayat->pegawai_id)
                ->where('id', '!=', $id)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false]);
        }

        $riwayat->update($data);

        ActivityLogger::log('update', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatJabatanStruktural($riwayat->load(['pegawai', 'jabatanStruktural.jenisJabatanStruktural'])),
            'message' => 'Riwayat jabatan struktural berhasil diperbarui'
        ]);
    }

    // Delete riwayat jabatan struktural
    public function destroy($id)
    {
        $riwayat = SimpegDataJabatanStruktural::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat jabatan struktural tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($riwayat->file_jabatan) {
            Storage::disk('public')->delete($riwayat->file_jabatan);
        }

        $oldData = $riwayat->toArray();
        $riwayat->delete();

        ActivityLogger::log('delete', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat jabatan struktural berhasil dihapus'
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

        $updatedCount = SimpegDataJabatanStruktural::whereIn('id', $request->ids)
            ->update(['status_pengajuan' => $request->status_pengajuan]);

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
            'ids.*' => 'required|uuid|exists:simpeg_data_jabatan_struktural,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $riwayat = SimpegDataJabatanStruktural::whereIn('id', $request->ids)->get();

        $deletedCount = 0;
        $errors = [];

        // Delete files and records
        foreach ($riwayat as $item) {
            try {
                if ($item->file_jabatan) {
                    Storage::disk('public')->delete($item->file_jabatan);
                }
                
                $oldData = $item->toArray();
                $item->delete();
                ActivityLogger::log('delete', $item, $oldData);
                $deletedCount++;
                
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $item->id,
                    'no_sk' => $item->no_sk,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data riwayat jabatan struktural",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data riwayat jabatan struktural",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }

    // Update status pengajuan
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

        $riwayat = SimpegDataJabatanStruktural::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat jabatan struktural tidak ditemukan'
            ], 404);
        }

        if (!\Schema::hasColumn('simpeg_data_jabatan_struktural', 'status_pengajuan')) {
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
        }

        $riwayat->update($updateData);

        ActivityLogger::log('update', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui'
        ]);
    }

    // Toggle active status
    public function toggleActive(Request $request, $id)
    {
        $riwayat = SimpegDataJabatanStruktural::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat jabatan struktural tidak ditemukan'
            ], 404);
        }

        if (!\Schema::hasColumn('simpeg_data_jabatan_struktural', 'is_aktif')) {
            return response()->json([
                'success' => false,
                'message' => 'Field is_aktif tidak tersedia di tabel ini'
            ], 400);
        }

        $currentStatus = $riwayat->is_aktif ?? false;
        $newStatus = !$currentStatus;

        // Jika mengaktifkan, nonaktifkan yang lain
        if ($newStatus) {
            SimpegDataJabatanStruktural::where('pegawai_id', $riwayat->pegawai_id)
                ->where('id', '!=', $id)
                ->update(['is_aktif' => false]);
        }

        $oldData = $riwayat->getOriginal();
        $riwayat->update(['is_aktif' => $newStatus]);

        ActivityLogger::log('update', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'message' => $newStatus ? 'Jabatan struktural berhasil diaktifkan' : 'Jabatan struktural berhasil dinonaktifkan',
            'is_aktif' => $newStatus
        ]);
    }

    // Get dropdown options for create/update forms
    public function getFormOptions()
    {
        $jabatanStruktural = SimpegJabatanStruktural::with('jenisJabatanStruktural')
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->jenisJabatanStruktural ? $item->jenisJabatanStruktural->jenis_jabatan_struktural : 'Tidak ada jenis jabatan'
                ];
            })
            ->sortBy('nama')
            ->values();

        return response()->json([
            'success' => true,
            'form_options' => [
                'jabatan_struktural' => $jabatanStruktural,
                'status_pengajuan' => [
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ]
            ]
        ]);
    }

    // Download file jabatan struktural
    public function downloadFile($id)
    {
        $riwayat = SimpegDataJabatanStruktural::find($id);

        if (!$riwayat || !$riwayat->file_jabatan) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan'
            ], 404);
        }

        $filePath = storage_path('app/public/' . $riwayat->file_jabatan);
        
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

    // Format response
    protected function formatRiwayatJabatanStruktural($riwayat)
    {
        $data = [
            'id' => $riwayat->id,
            'pegawai_id' => $riwayat->pegawai_id,
            'pegawai' => $riwayat->pegawai ? [
                'nip' => $riwayat->pegawai->nip ?? '-',
                'nama' => $riwayat->pegawai->nama ?? '-'
            ] : null,
            'tgl_mulai' => $riwayat->tgl_mulai,
            'tgl_mulai_formatted' => $riwayat->tgl_mulai ? \Carbon\Carbon::parse($riwayat->tgl_mulai)->format('d-m-Y') : '-',
            'tgl_selesai' => $riwayat->tgl_selesai,
            'tgl_selesai_formatted' => $riwayat->tgl_selesai ? \Carbon\Carbon::parse($riwayat->tgl_selesai)->format('d-m-Y') : '-',
            'no_sk' => $riwayat->no_sk,
            'tgl_sk' => $riwayat->tgl_sk,
            'tgl_sk_formatted' => $riwayat->tgl_sk ? \Carbon\Carbon::parse($riwayat->tgl_sk)->format('d-m-Y') : '-',
            'pejabat_penetap' => $riwayat->pejabat_penetap,
            'jabatan_struktural' => $riwayat->jabatanStruktural ? [
                'id' => $riwayat->jabatanStruktural->id,
                'nama' => $riwayat->jabatanStruktural->jenisJabatanStruktural ? 
                         $riwayat->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural : 
                         'Tidak ada jenis jabatan'
            ] : null,
            'dokumen' => $riwayat->file_jabatan ? [
                'nama_file' => basename($riwayat->file_jabatan),
                'url' => Storage::url($riwayat->file_jabatan),
                'download_url' => url("/api/pegawai/riwayat-jabatan-struktural/{$riwayat->id}/download")
            ] : null,
            'tgl_input' => $riwayat->tgl_input,
            'tgl_input_formatted' => $riwayat->tgl_input ? \Carbon\Carbon::parse($riwayat->tgl_input)->format('d-m-Y') : '-',
            'created_at' => $riwayat->created_at,
            'updated_at' => $riwayat->updated_at
        ];

        // Add status fields if columns exist
        if (\Schema::hasColumn('simpeg_data_jabatan_struktural', 'status_pengajuan')) {
            $data['status_pengajuan'] = $riwayat->status_pengajuan ?? 'draft';
            $statusLabels = [
                'draft' => 'Draft',
                'diajukan' => 'Diajukan', 
                'disetujui' => 'Disetujui',
                'ditolak' => 'Ditolak'
            ];
            $data['status_pengajuan_label'] = $statusLabels[$data['status_pengajuan']] ?? $data['status_pengajuan'];
            
            // Add timestamps
            $data['timestamps'] = [
                'tgl_diajukan' => $riwayat->tgl_diajukan ?? null,
                'tgl_disetujui' => $riwayat->tgl_disetujui ?? null,
                'tgl_ditolak' => $riwayat->tgl_ditolak ?? null
            ];
        }

        if (\Schema::hasColumn('simpeg_data_jabatan_struktural', 'is_aktif')) {
            $data['is_aktif'] = $riwayat->is_aktif ?? false;
            $data['is_aktif_label'] = $data['is_aktif'] ? 'Aktif' : 'Tidak Aktif';
        }

        // Add status info for permissions
        $data['status'] = [
            'is_aktif' => $data['is_aktif'] ?? false,
            'pengajuan' => $data['status_pengajuan'] ?? 'draft'
        ];

        return $data;
    }


    
}