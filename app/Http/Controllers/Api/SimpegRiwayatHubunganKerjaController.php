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
use Illuminate\Support\Facades\DB;

class SimpegRiwayatHubunganKerjaController extends Controller
{
    // Get all riwayat hubungan kerja (for admin)
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $status = $request->status;

        $query = SimpegDataHubunganKerja::with([
            'pegawai',
            'hubunganKerja',
            'statusAktif'
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
                  ->orWhereHas('hubunganKerja', function($q2) use ($search) {
                      $q2->where('nama_hub_kerja', 'like', '%'.$search.'%');
                  });
            });
        }

        // Filter by status pengajuan
        if ($status && $status != 'semua' && \Schema::hasColumn('simpeg_data_hubungan_kerja', 'status_pengajuan')) {
            $query->where('status_pengajuan', $status);
        }

        // Additional filters
        if ($request->filled('hubungan_kerja_id')) {
            $query->where('hubungan_kerja_id', $request->hubungan_kerja_id);
        }
        if ($request->filled('status_aktif_id')) {
            $query->where('status_aktif_id', $request->status_aktif_id);
        }

        $riwayat = $query->orderBy('tgl_awal', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $riwayat->map(function ($item) {
                return $this->formatRiwayatHubunganKerja($item);
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

    // Get riwayat hubungan kerja by pegawai ID
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

        $riwayat = $pegawai->dataHubunganKerja()
            ->with(['hubunganKerja', 'statusAktif'])
            ->orderBy('tgl_awal', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'pegawai' => $this->formatPegawaiInfo($pegawai),
            'data' => $riwayat->map(function ($item) {
                return $this->formatRiwayatHubunganKerja($item);
            })
        ]);
    }

    // Get detail riwayat hubungan kerja
    public function show($id)
    {
        $riwayat = SimpegDataHubunganKerja::with([
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
            'hubunganKerja',
            'statusAktif'
        ])->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat hubungan kerja tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatHubunganKerja($riwayat),
            'pegawai' => $this->formatPegawaiInfo($riwayat->pegawai)
        ]);
    }

    // Store new riwayat hubungan kerja
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
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

        $data = $request->except('file_hubungan_kerja');
        $data['tgl_input'] = now()->toDateString();
        
        // Set default status pengajuan if not provided
        if (!isset($data['status_pengajuan'])) {
            $data['status_pengajuan'] = 'draft';
        }

        // Convert is_aktif to boolean
        if (isset($data['is_aktif'])) {
            $data['is_aktif'] = in_array($data['is_aktif'], [1, '1', 'true', true], true);
        }

        // Handle file upload
        if ($request->hasFile('file_hubungan_kerja')) {
            $file = $request->file('file_hubungan_kerja');
            $fileName = 'hubungan_kerja_'.time().'_'.$request->pegawai_id.'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs('uploads/hubungan_kerja', $fileName, 'public');
            $data['file_hubungan_kerja'] = $filePath;
        }

        // Jika diaktifkan, nonaktifkan yang lain
        if (isset($data['is_aktif']) && $data['is_aktif']) {
            SimpegDataHubunganKerja::where('pegawai_id', $request->pegawai_id)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false]);
        }

        $riwayat = SimpegDataHubunganKerja::create($data);

        ActivityLogger::log('create', $riwayat, $riwayat->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatHubunganKerja($riwayat->load(['pegawai', 'hubunganKerja', 'statusAktif'])),
            'message' => 'Riwayat hubungan kerja berhasil ditambahkan'
        ], 201);
    }

    // Update riwayat hubungan kerja
    public function update(Request $request, $id)
    {
        $riwayat = SimpegDataHubunganKerja::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat hubungan kerja tidak ditemukan'
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
            'file_hubungan_kerja' => 'nullable|file|mimes:pdf|max:5120',
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
        $data = $request->except('file_hubungan_kerja');

        // Convert is_aktif to boolean
        if (isset($data['is_aktif'])) {
            $data['is_aktif'] = in_array($data['is_aktif'], [1, '1', 'true', true], true);
        }

        // Handle file upload
        if ($request->hasFile('file_hubungan_kerja')) {
            // Delete old file if exists
            if ($riwayat->file_hubungan_kerja) {
                Storage::disk('public')->delete($riwayat->file_hubungan_kerja);
            }
            
            $file = $request->file('file_hubungan_kerja');
            $fileName = 'hubungan_kerja_'.time().'_'.$riwayat->pegawai_id.'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs('uploads/hubungan_kerja', $fileName, 'public');
            $data['file_hubungan_kerja'] = $filePath;
        }

        // Jika diaktifkan, nonaktifkan yang lain
        if (isset($data['is_aktif']) && $data['is_aktif']) {
            SimpegDataHubunganKerja::where('pegawai_id', $riwayat->pegawai_id)
                ->where('id', '!=', $id)
                ->where('is_aktif', true)
                ->update(['is_aktif' => false]);
        }

        $riwayat->update($data);

        ActivityLogger::log('update', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatHubunganKerja($riwayat->load(['pegawai', 'hubunganKerja', 'statusAktif'])),
            'message' => 'Riwayat hubungan kerja berhasil diperbarui'
        ]);
    }

    // Delete riwayat hubungan kerja
    public function destroy($id)
    {
        $riwayat = SimpegDataHubunganKerja::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat hubungan kerja tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($riwayat->file_hubungan_kerja) {
            Storage::disk('public')->delete($riwayat->file_hubungan_kerja);
        }

        $oldData = $riwayat->toArray();
        $riwayat->delete();

        ActivityLogger::log('delete', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat hubungan kerja berhasil dihapus'
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

        $updatedCount = SimpegDataHubunganKerja::whereIn('id', $request->ids)
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
            'ids.*' => 'required|uuid|exists:simpeg_data_hubungan_kerja,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $riwayat = SimpegDataHubunganKerja::whereIn('id', $request->ids)->get();

        $deletedCount = 0;
        $errors = [];

        // Delete files and records
        foreach ($riwayat as $item) {
            try {
                if ($item->file_hubungan_kerja) {
                    Storage::disk('public')->delete($item->file_hubungan_kerja);
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
                'message' => "Berhasil menghapus {$deletedCount} data riwayat hubungan kerja",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data riwayat hubungan kerja",
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

        $riwayat = SimpegDataHubunganKerja::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat hubungan kerja tidak ditemukan'
            ], 404);
        }

        $oldData = $riwayat->getOriginal();
        $riwayat->update(['status_pengajuan' => $request->status_pengajuan]);

        ActivityLogger::log('update', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui'
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
                'status_aktif' => $statusAktif
            ]
        ]);
    }

    // Download file hubungan kerja
    public function downloadFile($id)
    {
        $riwayat = SimpegDataHubunganKerja::find($id);

        if (!$riwayat || !$riwayat->file_hubungan_kerja) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan'
            ], 404);
        }

        $filePath = storage_path('app/public/' . $riwayat->file_hubungan_kerja);
        
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
    protected function formatRiwayatHubunganKerja($riwayat)
    {
        return [
            'id' => $riwayat->id,
            'pegawai_id' => $riwayat->pegawai_id,
            'pegawai' => $riwayat->pegawai ? [
                'nip' => $riwayat->pegawai->nip ?? '-',
                'nama' => $riwayat->pegawai->nama ?? '-'
            ] : null,
            'tgl_awal' => $riwayat->tgl_awal,
            'tgl_awal_formatted' => $riwayat->tgl_awal ? \Carbon\Carbon::parse($riwayat->tgl_awal)->format('d-m-Y') : '-',
            'tgl_akhir' => $riwayat->tgl_akhir,
            'tgl_akhir_formatted' => $riwayat->tgl_akhir ? \Carbon\Carbon::parse($riwayat->tgl_akhir)->format('d-m-Y') : '-',
            'no_sk' => $riwayat->no_sk,
            'tgl_sk' => $riwayat->tgl_sk,
            'tgl_sk_formatted' => $riwayat->tgl_sk ? \Carbon\Carbon::parse($riwayat->tgl_sk)->format('d-m-Y') : '-',
            'pejabat_penetap' => $riwayat->pejabat_penetap,
            'hubungan_kerja' => $riwayat->hubunganKerja ? [
                'id' => $riwayat->hubunganKerja->id,
                'nama' => $riwayat->hubunganKerja->nama_hub_kerja
            ] : null,
            'status_aktif' => $riwayat->statusAktif ? [
                'id' => $riwayat->statusAktif->id,
                'nama' => $riwayat->statusAktif->nama_status_aktif
            ] : null,
            'status' => [
                'is_aktif' => $riwayat->is_aktif ?? false,
                'pengajuan' => $riwayat->status_pengajuan ?? 'draft'
            ],
            'dokumen' => $riwayat->file_hubungan_kerja ? [
                'nama_file' => basename($riwayat->file_hubungan_kerja),
                'url' => Storage::url($riwayat->file_hubungan_kerja),
                'download_url' => url("/api/pegawai/riwayat-hubungan-kerja/{$riwayat->id}/download")
            ] : null,
            'tgl_input' => $riwayat->tgl_input,
            'tgl_input_formatted' => $riwayat->tgl_input ? \Carbon\Carbon::parse($riwayat->tgl_input)->format('d-m-Y') : '-',
            'created_at' => $riwayat->created_at,
            'updated_at' => $riwayat->updated_at
        ];
    }
}