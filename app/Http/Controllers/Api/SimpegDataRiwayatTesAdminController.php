<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataTes;
use App\Models\SimpegDaftarJenisTest;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai; // Import SimpegPegawai
use App\Models\SimpegJabatanFungsional; // Import SimpegJabatanFungsional for filter options
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder; // Import for type-hinting scopes

class SimpegDataRiwayatTesAdminController extends Controller
{
    /**
     * Get all data riwayat tes for admin (all pegawai).
     * Admin can view data for any employee.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $pegawaiId = $request->pegawai_id; // Admin can filter by pegawai_id
        $unitKerjaId = $request->unit_kerja_id; // Admin can filter by unit_kerja_id
        $jabatanFungsionalId = $request->jabatan_fungsional_id; // Admin can filter by jabatan_fungsional_id
        $jenisTesId = $request->jenis_tes_id;
        $namaTes = $request->nama_tes;
        $penyelenggara = $request->penyelenggara;
        $tglTes = $request->tgl_tes;
        $skorMin = $request->skor_min;
        $skorMax = $request->skor_max;
        $statusPengajuan = $request->status_pengajuan ?? 'semua'; // Default to 'semua'

        // Eager load all necessary relations to avoid N+1 query problem
        $query = SimpegDataTes::with([
            'jenisTes',
            'pegawai' => function ($q) {
                $q->select('id', 'nip', 'nama', 'unit_kerja_id')
                    ->with([
                        'unitKerja:id,nama_unit',
                        'dataJabatanFungsional' => function ($subQuery) {
                            $subQuery->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1);
                        }
                    ]);
            }
        ]);

        // Filter by pegawai_id (if admin wants to see specific employee data)
        if ($pegawaiId && $pegawaiId != 'semua') {
            $query->where('pegawai_id', $pegawaiId);
        }

        // Filter by Unit Kerja (Hierarchy)
        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $unitKerjaTarget = SimpegUnitKerja::find($unitKerjaId);

            if ($unitKerjaTarget) {
                $unitIdsInScope = SimpegUnitKerja::getAllChildIdsRecursively($unitKerjaTarget);
                $query->whereHas('pegawai', function ($q) use ($unitIdsInScope) {
                    $q->whereIn('unit_kerja_id', $unitIdsInScope);
                });
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unit Kerja yang dipilih tidak ditemukan.'
                ], 404);
            }
        }

        // Filter by Jabatan Fungsional
        if ($jabatanFungsionalId && $jabatanFungsionalId != 'semua') {
            $query->whereHas('pegawai.dataJabatanFungsional', function ($q) use ($jabatanFungsionalId) {
                $q->where('jabatan_fungsional_id', $jabatanFungsionalId);
            });
        }

        // Global search (NIP, Nama Pegawai, Nama Tes, Penyelenggara, Skor, Tgl. Tes, Jenis Tes)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_tes', 'like', '%' . $search . '%')
                    ->orWhere('penyelenggara', 'like', '%' . $search . '%')
                    ->orWhere('skor', 'like', '%' . $search . '%')
                    ->orWhere('tgl_tes', 'like', '%' . $search . '%')
                    ->orWhereHas('jenisTes', function ($jq) use ($search) {
                        $jq->where('jenis_tes', 'like', '%' . $search . '%')
                            ->orWhere('kode', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('pegawai', function ($q2) use ($search) {
                        $q2->where('nip', 'like', '%' . $search . '%')
                            ->orWhere('nama', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Specific filters
        if ($jenisTesId && $jenisTesId != 'semua') {
            $query->where('jenis_tes_id', $jenisTesId);
        }
        if ($namaTes) {
            $query->where('nama_tes', 'like', '%' . $namaTes . '%');
        }
        if ($penyelenggara) {
            $query->where('penyelenggara', 'like', '%' . $penyelenggara . '%');
        }
        if ($tglTes) {
            $query->whereDate('tgl_tes', $tglTes);
        }
        if ($skorMin) {
            $query->where('skor', '>=', $skorMin);
        }
        if ($skorMax) {
            $query->where('skor', '<=', $skorMax);
        }

        // Execute query with pagination
        $dataRiwayatTes = $query->orderBy('tgl_tes', 'desc')->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $dataRiwayatTes->getCollection()->transform(function ($item) {
            return $this->formatDataRiwayatTes($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataRiwayatTes,
            'empty_data' => $dataRiwayatTes->isEmpty(),
            'filters' => $this->getFilterOptions(),
            'table_columns' => [
                ['field' => 'nip_pegawai', 'label' => 'NIP', 'sortable' => true, 'sortable_field' => 'pegawai.nip'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => true, 'sortable_field' => 'pegawai.nama'],
                ['field' => 'jenis_tes_label', 'label' => 'Jenis Tes', 'sortable' => true, 'sortable_field' => 'jenis_tes_id'],
                ['field' => 'nama_tes', 'label' => 'Nama Tes', 'sortable' => true, 'sortable_field' => 'nama_tes'],
                ['field' => 'penyelenggara', 'label' => 'Penyelenggara', 'sortable' => true, 'sortable_field' => 'penyelenggara'],
                ['field' => 'tgl_tes', 'label' => 'Tanggal Tes', 'sortable' => true, 'sortable_field' => 'tgl_tes'],
                ['field' => 'skor', 'label' => 'Skor', 'sortable' => true, 'sortable_field' => 'skor'],
                ['field' => 'status_info.label', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/datariwayattesadm/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/datariwayattesadm/batch/reject"),
                    'method' => 'PATCH',
                    'label' => 'Tolak Terpilih',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menolak data terpilih?',
                    'needs_input' => true,
                    'input_placeholder' => 'Keterangan penolakan (opsional)'
                ],
                'to_draft' => [ // New action: Change to Draft
                    'url' => url("/api/admin/datariwayattesadm/batch/todraft"),
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft Terpilih',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data terpilih menjadi draft?'
                ],
                'delete' => [
                    'url' => url("/api/admin/datariwayattesadm/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data terpilih?',
                ]
            ],
            'pagination' => [
                'current_page' => $dataRiwayatTes->currentPage(),
                'per_page' => $dataRiwayatTes->perPage(),
                'total' => $dataRiwayatTes->total(),
                'last_page' => $dataRiwayatTes->lastPage(),
                'from' => $dataRiwayatTes->firstItem(),
                'to' => $dataRiwayatTes->lastItem()
            ]
        ]);
    }

    /**
     * Get detail data riwayat tes.
     * Admin can view details for any employee's data.
     */
    public function show($id)
    {
        $dataRiwayatTes = SimpegDataTes::with([
            'jenisTes',
            'pegawai' => function ($q) {
                $q->with([
                    'unitKerja', 'statusAktif', 'jabatanAkademik',
                    'dataJabatanFungsional' => function ($query) {
                        $query->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1);
                    },
                    'dataJabatanStruktural' => function ($query) {
                        $query->with('jabatanStruktural.jenisJabatanStruktural')->orderBy('tgl_mulai', 'desc')->limit(1);
                    },
                    'dataPendidikanFormal' => function ($query) {
                        $query->with('jenjangPendidikan')->orderBy('jenjang_pendidikan_id', 'desc')->limit(1);
                    }
                ]);
            }
        ])->find($id);

        if (!$dataRiwayatTes) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat tes tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai_info_detail' => $this->formatPegawaiInfo($dataRiwayatTes->pegawai),
            'data' => $this->formatDataRiwayatTes($dataRiwayatTes, false),
            'form_options' => $this->getFormOptions(), // Form options for create/update
        ]);
    }

    /**
     * Store new data riwayat tes (Admin Operational).
     * Admin can add data for any employee. Auto-sets status to 'disetujui' by default.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|integer|exists:simpeg_pegawai,id', // Required for admin
            'jenis_tes_id' => 'required|integer|exists:simpeg_daftar_jenis_test,id',
            'nama_tes' => 'required|string|max:100',
            'penyelenggara' => 'required|string|max:100',
            'tgl_tes' => 'required|date|before_or_equal:today',
            'skor' => 'required|numeric|min:0|max:999.99',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan', // Allow admin to set status
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $validatedData = $validator->validated();
            
            if ($request->hasFile('file_pendukung')) {
                $file = $request->file('file_pendukung');
                // Store in a general admin folder, not specific to a user
                $filePath = $file->store('tes_dokumen', 'public'); // path: storage/app/public/tes_dokumen
                $validatedData['file_pendukung'] = $filePath;
            }

            // Admin can set the status directly
            $validatedData['status_pengajuan'] = $request->input('status_pengajuan', 'disetujui'); // Default to disetujui for admin input

            // Set timestamps based on status
            if ($validatedData['status_pengajuan'] === 'disetujui') {
                $validatedData['tgl_disetujui'] = now();
                $validatedData['tgl_diajukan'] = $validatedData['tgl_diajukan'] ?? now(); 
            } elseif ($validatedData['status_pengajuan'] === 'diajukan') {
                $validatedData['tgl_diajukan'] = now();
                $validatedData['tgl_disetujui'] = null;
            } elseif ($validatedData['status_pengajuan'] === 'ditolak') {
                $validatedData['tgl_ditolak'] = now();
                $validatedData['tgl_diajukan'] = null;
                $validatedData['tgl_disetujui'] = null;
            } elseif ($validatedData['status_pengajuan'] === 'ditangguhkan') {
                $validatedData['tgl_ditangguhkan'] = now();
                $validatedData['tgl_diajukan'] = null;
                $validatedData['tgl_disetujui'] = null;
                $validatedData['tgl_ditolak'] = null;
            } else { // 'draft'
                $validatedData['tgl_diajukan'] = null;
                $validatedData['tgl_disetujui'] = null;
                $validatedData['tgl_ditolak'] = null;
                $validatedData['tgl_ditangguhkan'] = null;
            }
            $validatedData['tgl_input'] = now(); // Set input date

            $dataRiwayatTes = SimpegDataTes::create($validatedData);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_create_riwayat_tes', $dataRiwayatTes, $dataRiwayatTes->toArray());
            }

            return response()->json([
                'success' => true,
                'message' => 'Data riwayat tes berhasil ditambahkan oleh admin',
                'data' => $this->formatDataRiwayatTes($dataRiwayatTes->load(['jenisTes', 'pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional']))
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan data riwayat tes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update data riwayat tes (Admin Operational).
     * Admin can edit any data regardless of status.
     */
    public function update(Request $request, $id)
    {
        $dataRiwayatTes = SimpegDataTes::find($id);

        if (!$dataRiwayatTes) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat tes tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'sometimes|integer|exists:simpeg_pegawai,id', // Can update pegawai_id
            'jenis_tes_id' => 'sometimes|integer|exists:simpeg_daftar_jenis_test,id',
            'nama_tes' => 'sometimes|string|max:100',
            'penyelenggara' => 'sometimes|string|max:100',
            'tgl_tes' => 'sometimes|date|before_or_equal:today',
            'skor' => 'sometimes|numeric|min:0|max:999.99',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'file_pendukung_clear' => 'nullable|boolean', // Added for clearing file
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
            'keterangan' => 'nullable|string',
            'keterangan_penolakan' => 'nullable|string|max:500' // Added for admin reject notes
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $validatedData = $validator->validated();
            
            // Handle file upload
            if ($request->hasFile('file_pendukung')) {
                if ($dataRiwayatTes->file_pendukung) {
                    Storage::disk('public')->delete($dataRiwayatTes->file_pendukung);
                }
                $file = $request->file('file_pendukung');
                $filePath = $file->store('tes_dokumen', 'public');
                $validatedData['file_pendukung'] = $filePath;
            } elseif ($request->has('file_pendukung_clear') && (bool)$request->file_pendukung_clear) {
                if ($dataRiwayatTes->file_pendukung) {
                    Storage::disk('public')->delete($dataRiwayatTes->file_pendukung);
                }
                $validatedData['file_pendukung'] = null;
            } else {
                // If no new file and not clearing, retain existing file
                $validatedData['file_pendukung'] = $dataRiwayatTes->file_pendukung;
            }

            // Handle status_pengajuan and timestamps related
            if (isset($validatedData['status_pengajuan']) && $validatedData['status_pengajuan'] !== $dataRiwayatTes->status_pengajuan) {
                switch ($validatedData['status_pengajuan']) {
                    case 'diajukan':
                        $validatedData['tgl_diajukan'] = now();
                        $validatedData['tgl_disetujui'] = null;
                        $validatedData['tgl_ditolak'] = null;
                        $validatedData['tgl_ditangguhkan'] = null;
                        break;
                    case 'disetujui':
                        $validatedData['tgl_disetujui'] = now();
                        $validatedData['tgl_diajukan'] = $dataRiwayatTes->tgl_diajukan ?? now(); 
                        $validatedData['tgl_ditolak'] = null;
                        $validatedData['tgl_ditangguhkan'] = null;
                        break;
                    case 'ditolak':
                        $validatedData['tgl_ditolak'] = now();
                        $validatedData['tgl_diajukan'] = null;
                        $validatedData['tgl_disetujui'] = null;
                        $validatedData['tgl_ditangguhkan'] = null;
                        break;
                    case 'ditangguhkan':
                        $validatedData['tgl_ditangguhkan'] = now();
                        $validatedData['tgl_diajukan'] = null;
                        $validatedData['tgl_disetujui'] = null;
                        $validatedData['tgl_ditolak'] = null;
                        break;
                    case 'draft':
                        $validatedData['tgl_diajukan'] = null;
                        $validatedData['tgl_disetujui'] = null;
                        $validatedData['tgl_ditolak'] = null;
                        $validatedData['tgl_ditangguhkan'] = null;
                        break;
                }
            } else {
                // If status is not changed, retain existing timestamps
                $validatedData['tgl_diajukan'] = $dataRiwayatTes->tgl_diajukan;
                $validatedData['tgl_disetujui'] = $dataRiwayatTes->tgl_disetujui;
                $validatedData['tgl_ditolak'] = $dataRiwayatTes->tgl_ditolak;
                $validatedData['tgl_ditangguhkan'] = $dataRiwayatTes->tgl_ditangguhkan;
            }
            // Retain existing 'keterangan_penolakan' if not explicitly updated to null or a new value
            if (!isset($validatedData['keterangan_penolakan'])) {
                $validatedData['keterangan_penolakan'] = $dataRiwayatTes->keterangan_penolakan;
            }


            $oldData = $dataRiwayatTes->getOriginal();
            $dataRiwayatTes->update($validatedData);

            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('admin_update_riwayat_tes', $dataRiwayatTes, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data riwayat tes berhasil diperbarui oleh admin',
                'data' => $this->formatDataRiwayatTes($dataRiwayatTes->load(['jenisTes', 'pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional']))
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data riwayat tes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete data riwayat tes.
     * Admin can delete any data.
     */
    public function destroy($id)
    {
        $dataRiwayatTes = SimpegDataTes::find($id);

        if (!$dataRiwayatTes) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat tes tidak ditemukan'
            ], 404);
        }

        // Delete file if exists
        if ($dataRiwayatTes->file_pendukung) {
            Storage::disk('public')->delete($dataRiwayatTes->file_pendukung);
        }

        $oldData = $dataRiwayatTes->toArray();
        $dataRiwayatTes->delete();

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_delete_riwayat_tes', $dataRiwayatTes, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat tes berhasil dihapus'
        ]);
    }

    /**
     * Admin: Approve a single data entry.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id)
    {
        $dataRiwayatTes = SimpegDataTes::find($id);

        if (!$dataRiwayatTes) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat tes tidak ditemukan'
            ], 404);
        }

        if ($dataRiwayatTes->status_pengajuan === 'disetujui') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah disetujui sebelumnya'
            ], 409);
        }

        $oldData = $dataRiwayatTes->getOriginal();
        $dataRiwayatTes->update([
            'status_pengajuan' => 'disetujui',
            'tgl_disetujui' => now(),
            'tgl_ditolak' => null,
            'tgl_ditangguhkan' => null, // Clear if approved
            'keterangan_penolakan' => null, // Clear rejection note
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_approve_riwayat_tes', $dataRiwayatTes, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat tes berhasil disetujui'
        ]);
    }

    /**
     * Admin: Reject a single data entry.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, $id)
    {
        $dataRiwayatTes = SimpegDataTes::find($id);

        if (!$dataRiwayatTes) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat tes tidak ditemukan'
            ], 404);
        }

        if ($dataRiwayatTes->status_pengajuan === 'ditolak') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah ditolak sebelumnya'
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'keterangan_penolakan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $dataRiwayatTes->getOriginal();
        $dataRiwayatTes->update([
            'status_pengajuan' => 'ditolak',
            'tgl_ditolak' => now(),
            'tgl_diajukan' => null,
            'tgl_disetujui' => null,
            'tgl_ditangguhkan' => null,
            'keterangan_penolakan' => $request->keterangan_penolakan,
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_reject_riwayat_tes', $dataRiwayatTes, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat tes berhasil ditolak'
        ]);
    }

    /**
     * Admin: Change status to 'draft' for a single data entry.
     * This handles the "ditangguhkan jadi draft saja" requirement.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toDraft($id)
    {
        $dataRiwayatTes = SimpegDataTes::find($id);

        if (!$dataRiwayatTes) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat tes tidak ditemukan'
            ], 404);
        }

        if ($dataRiwayatTes->status_pengajuan === 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah dalam status draft'
            ], 409);
        }

        $oldData = $dataRiwayatTes->getOriginal();
        $dataRiwayatTes->update([
            'status_pengajuan' => 'draft',
            'tgl_diajukan' => null,
            'tgl_disetujui' => null,
            'tgl_ditolak' => null,
            'tgl_ditangguhkan' => null, // Clear all other timestamps if moved to draft
            'keterangan_penolakan' => null, // Clear rejection note
        ]);

        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('admin_to_draft_riwayat_tes', $dataRiwayatTes, $oldData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status riwayat tes berhasil diubah menjadi draft'
        ]);
    }

    /**
     * Admin: Batch delete data riwayat tes.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_tes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataRiwayatTesList = SimpegDataTes::whereIn('id', $request->ids)->get();

        if ($dataRiwayatTesList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data riwayat tes yang ditemukan untuk dihapus'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataRiwayatTesList as $dataRiwayatTes) {
                if ($dataRiwayatTes->file_pendukung) {
                    Storage::disk('public')->delete($dataRiwayatTes->file_pendukung);
                }

                $oldData = $dataRiwayatTes->toArray();
                $dataRiwayatTes->delete();
                
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_batch_delete_riwayat_tes', $dataRiwayatTes, $oldData);
                }
                $deletedCount++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error during batch delete riwayat tes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus data secara batch: ' . $e->getMessage(),
                'errors' => $errors
            ], 500);
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data riwayat tes",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data riwayat tes",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }

    /**
     * Admin: Batch approve data riwayat tes.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_tes,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataTes::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', ['draft', 'diajukan', 'ditolak', 'ditangguhkan']) // All statuses can be approved by admin
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data riwayat tes yang memenuhi syarat untuk disetujui.'
            ], 404);
        }

        $updatedCount = 0;
        $approvedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => 'disetujui',
                    'tgl_disetujui' => now(),
                    'tgl_diajukan' => $item->tgl_diajukan ?? now(), // Retain if already submitted, otherwise set now
                    'tgl_ditolak' => null,
                    'tgl_ditangguhkan' => null,
                    'keterangan_penolakan' => null,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_approve_riwayat_tes', $item, $oldData);
                }
                $updatedCount++;
                $approvedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error during batch approve riwayat tes: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data riwayat tes",
            'updated_count' => $updatedCount,
            'approved_ids' => $approvedIds
        ]);
    }

    /**
     * Admin: Batch reject data riwayat tes.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_tes,id',
            'keterangan_penolakan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataTes::whereIn('id', $request->ids)
            ->whereIn('status_pengajuan', ['draft', 'diajukan', 'disetujui', 'ditangguhkan']) // All statuses can be rejected by admin
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data riwayat tes yang memenuhi syarat untuk ditolak.'
            ], 404);
        }

        $updatedCount = 0;
        $rejectedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => 'ditolak',
                    'tgl_ditolak' => now(),
                    'tgl_diajukan' => null,
                    'tgl_disetujui' => null,
                    'tgl_ditangguhkan' => null,
                    'keterangan_penolakan' => $request->keterangan_penolakan,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_reject_riwayat_tes', $item, $oldData);
                }
                $updatedCount++;
                $rejectedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error during batch reject riwayat tes: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data riwayat tes",
            'updated_count' => $updatedCount,
            'rejected_ids' => $rejectedIds
        ]);
    }

    /**
     * Admin: Batch change status to 'draft'.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchToDraft(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_tes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToProcess = SimpegDataTes::whereIn('id', $request->ids)
            ->where('status_pengajuan', '!=', 'draft') // Only process if not already draft
            ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data riwayat tes yang memenuhi syarat untuk diubah menjadi draft.'
            ], 404);
        }

        $updatedCount = 0;
        $draftedIds = [];
        DB::beginTransaction();
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => 'draft',
                    'tgl_diajukan' => null,
                    'tgl_disetujui' => null,
                    'tgl_ditolak' => null,
                    'tgl_ditangguhkan' => null,
                    'keterangan_penolakan' => null,
                ]);
                if (class_exists('App\Services\ActivityLogger')) {
                    ActivityLogger::log('admin_batch_to_draft_riwayat_tes', $item, $oldData);
                }
                $updatedCount++;
                $draftedIds[] = $item->id;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error during batch change to draft for riwayat tes: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengubah status ke draft secara batch: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Berhasil mengubah {$updatedCount} data riwayat tes menjadi draft",
            'updated_count' => $updatedCount,
            'drafted_ids' => $draftedIds
        ]);
    }

    /**
     * Get status statistics for dashboard.
     * Admin can filter statistics by unit, functional position, and employee.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatusStatistics(Request $request)
    {
        $unitKerjaId = $request->unit_kerja_id;
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $pegawaiId = $request->pegawai_id;

        $query = SimpegDataTes::query();

        if ($pegawaiId && $pegawaiId != 'semua') {
            $query->where('pegawai_id', $pegawaiId);
        }

        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $unitKerjaTarget = SimpegUnitKerja::find($unitKerjaId);
            if ($unitKerjaTarget) {
                $unitIdsInScope = SimpegUnitKerja::getAllChildIdsRecursively($unitKerjaTarget);
                $query->whereHas('pegawai', function ($q) use ($unitIdsInScope) {
                    $q->whereIn('unit_kerja_id', $unitIdsInScope);
                });
            }
        }

        if ($jabatanFungsionalId && $jabatanFungsionalId != 'semua') {
            $query->whereHas('pegawai.dataJabatanFungsional', function ($q) use ($jabatanFungsionalId) {
                $q->where('jabatan_fungsional_id', $jabatanFungsionalId);
            });
        }

        $statistics = $query->selectRaw('status_pengajuan, COUNT(*) as total')
            ->groupBy('status_pengajuan')
            ->get()
            ->pluck('total', 'status_pengajuan')
            ->toArray();

        $defaultStats = [
            'draft' => 0,
            'diajukan' => 0,
            'disetujui' => 0,
            'ditolak' => 0,
            'ditangguhkan' => 0, // Include ditangguhkan in default stats
        ];

        $statistics = array_merge($defaultStats, $statistics);
        $statistics['total'] = array_sum($statistics);

        return response()->json([
            'success' => true,
            'statistics' => $statistics
        ]);
    }

    /**
     * Get filter options for the admin interface.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFilterOptions()
    {
        // Get distinct values for specific filters
        $jenisTesList = SimpegDaftarJenisTest::select('id', 'jenis_tes as nama', 'kode')
            ->orderBy('jenis_tes')
            ->get()
            ->prepend(['id' => 'semua', 'nama' => 'Semua Jenis Tes']);

        $namaTesList = SimpegDataTes::distinct()->pluck('nama_tes')->filter()->values()->map(function($item) { return ['id' => $item, 'nama' => $item]; })->prepend(['id' => 'semua', 'nama' => 'Semua Nama Tes'])->toArray();
        $penyelenggaraList = SimpegDataTes::distinct()->pluck('penyelenggara')->filter()->values()->map(function($item) { return ['id' => $item, 'nama' => $item]; })->prepend(['id' => 'semua', 'nama' => 'Semua Penyelenggara'])->toArray();
        
        return response()->json([
            'success' => true,
            'filter_options' => [
                'jenis_tes' => $jenisTesList,
                'nama_tes_options' => $namaTesList,
                'penyelenggara_options' => $penyelenggaraList,
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                    ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan'], // Include ditangguhkan in filter options
                ],
                'unit_kerja_options' => SimpegUnitKerja::select('id as id', 'nama_unit as nama')->orderBy('nama_unit')->get()->prepend(['id' => 'semua', 'nama' => 'Semua Unit Kerja']),
                'jabatan_fungsional_options' => SimpegJabatanFungsional::select('id', 'nama_jabatan_fungsional as nama')->orderBy('nama_jabatan_fungsional')->get()->prepend(['id' => 'semua', 'nama' => 'Semua Jabatan Fungsional']),
                'pegawai_options' => SimpegPegawai::select('id as value', 'nama as label', 'nip')->orderBy('nama')->get()->map(function($item) { return ['value' => $item->value, 'label' => $item->nip . ' - ' . $item->label]; })->prepend(['value' => 'semua', 'label' => 'Semua Pegawai']),
            ]
        ]);
    }

    /**
     * Get form options for create/update forms.
     * This includes basic validation rules and field notes.
     */
    public function getFormOptions()
    {
        $jenisTesOptions = SimpegDaftarJenisTest::select('id', 'kode', 'jenis_tes as nama', 'nilai_minimal', 'nilai_maksimal')
            ->orderBy('jenis_tes')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'label' => $item->jenis_tes . ' (' . $item->kode . ')',
                    'min_score' => $item->nilai_minimal,
                    'max_score' => $item->nilai_maksimal,
                ];
            });

        return [
            'form_options' => [
                'jenis_tes' => $jenisTesOptions,
                'status_pengajuan' => [ // Admin can select status directly
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak'],
                    ['id' => 'ditangguhkan', 'nama' => 'Ditangguhkan'],
                ]
            ],
            'validation_rules' => [
                'pegawai_id' => 'required|integer',
                'jenis_tes_id' => 'required|integer|exists:simpeg_daftar_jenis_test,id',
                'nama_tes' => 'required|string|max:100',
                'penyelenggara' => 'required|string|max:100',
                'tgl_tes' => 'required|date|before_or_equal:today',
                'skor' => 'required|numeric|min:0|max:999.99',
                'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak,ditangguhkan',
                'keterangan' => 'nullable|string|max:1000',
            ],
            'field_notes' => [
                'pegawai_id' => 'Pilih pegawai yang bersangkutan.',
                'jenis_tes_id' => 'Pilih jenis tes dari daftar.',
                'nama_tes' => 'Nama lengkap dari tes yang diikuti (e.g., TOEFL, IELTS, CPNS).',
                'penyelenggara' => 'Institusi atau badan yang menyelenggarakan tes.',
                'tgl_tes' => 'Tanggal pelaksanaan tes.',
                'skor' => 'Skor atau nilai yang diperoleh.',
                'file_pendukung' => 'Unggah sertifikat atau dokumen pendukung tes (PDF/gambar, maks 2MB).',
                'keterangan' => 'Catatan atau informasi tambahan mengenai tes.',
            ],
        ];
    }

    /**
     * Get pegawai options for dropdown.
     */
    public function getPegawaiOptions(Request $request)
    {
        $search = $request->search;
        $unitKerjaId = $request->unit_kerja_id;

        $query = SimpegPegawai::select('id', 'nip', 'nama', 'unit_kerja_id')
            ->with('unitKerja:id,nama_unit');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nip', 'like', '%' . $search . '%')
                    ->orWhere('nama', 'like', '%' . $search . '%');
            });
        }

        if ($unitKerjaId && $unitKerjaId != 'semua') {
            $unitKerjaTarget = SimpegUnitKerja::find($unitKerjaId);

            if ($unitKerjaTarget) {
                $unitIdsInScope = SimpegUnitKerja::getAllChildIdsRecursively($unitKerjaTarget);
                $query->whereIn('unit_kerja_id', $unitIdsInScope);
            } else {
                return response()->json(['success' => true, 'data' => [], 'search_info' => ['query' => $search, 'total_results' => 0, 'search_fields' => ['nip', 'nama'], 'message' => 'Unit Kerja tidak ditemukan.']], 200);
            }
        }

        $pegawai = $query->orderBy('nama')
                         ->limit(50)
                         ->get()
                         ->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'nip' => $item->nip,
                                'nama' => $item->nama,
                                'unit_kerja' => $item->unitKerja->nama_unit ?? '-',
                                'label' => $item->nip . ' - ' . $item->nama . ' (' . ($item->unitKerja->nama_unit ?? 'No Unit') . ')',
                                'search_text' => $item->nip . ' ' . $item->nama
                            ];
                         });

        return response()->json([
            'success' => true,
            'data' => $pegawai,
            'search_info' => [
                'query' => $search,
                'total_results' => $pegawai->count(),
                'search_fields' => ['nip', 'nama'],
                'message' => $search ? "Hasil pencarian untuk: '{$search}'" : 'Semua pegawai'
            ]
        ]);
    }

    /**
     * Helper: Format pegawai info for display in details
     */
    private function formatPegawaiInfo($pegawai)
    {
        if (!$pegawai) {
            return null;
        }

        $jabatanAkademikNama = '-';
        if ($pegawai->jabatanAkademik) {
            $jabatanAkademikNama = $pegawai->jabatanAkademik->jabatan_akademik ?? '-';
        }

        $jabatanFungsionalNama = '-';
        if ($pegawai->dataJabatanFungsional && $pegawai->dataJabatanFungsional->isNotEmpty()) {
            $jabatanFungsional = $pegawai->dataJabatanFungsional->sortByDesc('tmt_jabatan')->first()->jabatanFungsional;
            if ($jabatanFungsional) {
                $jabatanFungsionalNama = $jabatanFungsional->nama_jabatan_fungsional ?? $jabatanFungsional->nama ?? '-';
            }
        }

        $jabatanStrukturalNama = '-';
        if ($pegawai->dataJabatanStruktural && $pegawai->dataJabatanStruktural->isNotEmpty()) {
            $jabatanStruktural = $pegawai->dataJabatanStruktural->sortByDesc('tgl_mulai')->first();
            
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
            $highestEducation = $pegawai->dataPendidikanFormal->sortByDesc('jenjang_pendidikan_id')->first();
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

    /**
     * Helper: Format data riwayat tes response for display.
     */
    protected function formatDataRiwayatTes($dataRiwayatTes, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataRiwayatTes->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $pegawai = $dataRiwayatTes->pegawai;
        $nipPegawai = $pegawai ? $pegawai->nip : '-';
        $namaPegawai = $pegawai ? $pegawai->nama : '-';

        $data = [
            'id' => $dataRiwayatTes->id,
            'pegawai_id' => $dataRiwayatTes->pegawai_id,
            'nip_pegawai' => $nipPegawai,
            'nama_pegawai' => $namaPegawai,
            'jenis_tes_id' => $dataRiwayatTes->jenis_tes_id,
            'jenis_tes_label' => $dataRiwayatTes->jenisTes ? $dataRiwayatTes->jenisTes->jenis_tes : '-',
            'nama_tes' => $dataRiwayatTes->nama_tes,
            'penyelenggara' => $dataRiwayatTes->penyelenggara,
            'tgl_tes' => $dataRiwayatTes->tgl_tes,
            'tgl_tes_formatted' => $dataRiwayatTes->tgl_tes ? Carbon::parse($dataRiwayatTes->tgl_tes)->format('d M Y') : '-',
            'skor' => $dataRiwayatTes->skor,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataRiwayatTes->keterangan,
            'keterangan_penolakan' => $dataRiwayatTes->keterangan_penolakan, // Include rejection note
            'timestamps' => [
                'tgl_input' => $dataRiwayatTes->tgl_input,
                'tgl_diajukan' => $dataRiwayatTes->tgl_diajukan ?? null,
                'tgl_disetujui' => $dataRiwayatTes->tgl_disetujui ?? null,
                'tgl_ditolak' => $dataRiwayatTes->tgl_ditolak ?? null,
                'tgl_ditangguhkan' => $dataRiwayatTes->tgl_ditangguhkan ?? null,
            ],
            'dokumen' => $dataRiwayatTes->file_pendukung ? [
                'nama_file' => $dataRiwayatTes->file_pendukung,
                'url' => url('storage/' . $dataRiwayatTes->file_pendukung) // Adjusted path
            ] : null,
            'created_at' => $dataRiwayatTes->created_at,
            'updated_at' => $dataRiwayatTes->updated_at
        ];

        // Add action URLs if requested (for admin view)
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datariwayattesadm/{$dataRiwayatTes->id}"),
                'update_url' => url("/api/admin/datariwayattesadm/{$dataRiwayatTes->id}"),
                'delete_url' => url("/api/admin/datariwayattesadm/{$dataRiwayatTes->id}"),
                'approve_url' => url("/api/admin/datariwayattesadm/{$dataRiwayatTes->id}/approve"),
                'reject_url' => url("/api/admin/datariwayattesadm/{$dataRiwayatTes->id}/reject"),
                'to_draft_url' => url("/api/admin/datariwayattesadm/{$dataRiwayatTes->id}/todraft"),
            ];

            $data['actions'] = [
                'view' => [
                    'url' => $data['aksi']['detail_url'],
                    'method' => 'GET',
                    'label' => 'Lihat Detail',
                    'icon' => 'eye',
                    'color' => 'info'
                ],
                'edit' => [
                    'url' => $data['aksi']['update_url'],
                    'method' => 'PUT',
                    'label' => 'Edit',
                    'icon' => 'edit',
                    'color' => 'warning'
                ],
                'delete' => [
                    'url' => $data['aksi']['delete_url'],
                    'method' => 'DELETE',
                    'label' => 'Hapus',
                    'icon' => 'trash',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data riwayat tes "' . $dataRiwayatTes->nama_tes . '"?'
                ],
            ];

            // Admin specific actions based on status
            if (in_array($status, ['diajukan', 'ditolak', 'draft', 'ditangguhkan'])) {
                 $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data riwayat tes "' . $dataRiwayatTes->nama_tes . '"?'
                ];
            }

            if (in_array($status, ['diajukan', 'disetujui', 'draft', 'ditangguhkan'])) {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Tolak',
                    'icon' => 'times',
                    'color' => 'danger',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data riwayat tes "' . $dataRiwayatTes->nama_tes . '"?',
                    'needs_input' => true,
                    'input_placeholder' => 'Masukkan keterangan penolakan (opsional)'
                ];
            }

            // "Ditangguhkan jadi draft saja" is implemented here as a specific admin action.
            // Admin can always force to draft from any non-draft status
            if ($status !== 'draft') {
                $data['actions']['to_draft'] = [
                    'url' => $data['aksi']['to_draft_url'],
                    'method' => 'PATCH',
                    'label' => 'Set ke Draft',
                    'icon' => 'edit',
                    'color' => 'secondary',
                    "confirm" => true,
                    'confirm_message' => 'Apakah Anda yakin ingin mengubah status data riwayat tes "' . $dataRiwayatTes->nama_tes . '" menjadi draft?'
                ];
            }
        }

        return $data;
    }

    /**
     * Helper: Get status info and styling.
     */
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
            ],
            'ditangguhkan' => [ // Kept as a status, but admin actions allow changing it to draft or other states
                'label' => 'Ditangguhkan',
                'color' => 'warning',
                'icon' => 'pause-circle',
                'description' => 'Dalam peninjauan/ditangguhkan sementara'
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