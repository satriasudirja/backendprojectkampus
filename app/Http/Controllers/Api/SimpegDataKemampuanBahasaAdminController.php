<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataKemampuanBahasa;
use App\Models\SimpegBahasa;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use App\Models\SimpegJabatanFungsional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SimpegDataKemampuanBahasaAdminController extends Controller
{
    /**
     * Get all data kemampuan bahasa for admin with extensive filters.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $statusPengajuan = $request->status_pengajuan;
        $unitKerjaKode = $request->unit_kerja_id; // Menggunakan nama variabel yang lebih jelas: kode_unit dari request
        $jabatanFungsionalId = $request->jabatan_fungsional_id;
        $tahun = $request->tahun;
        $bahasaId = $request->bahasa_id;
        $namaLembaga = $request->nama_lembaga;
        $kemampuanMendengar = $request->kemampuan_mendengar;
        $kemampuanBicara = $request->kemampuan_bicara;
        $kemampuanMenulis = $request->kemampuan_menulis;

        $query = SimpegDataKemampuanBahasa::with([
            'bahasa',
            'pegawai' => function ($q) {
                $q->with([
                    'unitKerja',
                    'dataJabatanFungsional' => function ($subQuery) {
                        $subQuery->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1);
                    }
                ]);
            }
        ]);

        // Filter berdasarkan Unit Kerja (Hierarki)
        if ($unitKerjaKode) { // Menggunakan kode_unit dari request
            // Cari unit kerja berdasarkan kode_unit yang diterima dari request
            $unitKerjaTarget = SimpegUnitKerja::where('kode_unit', $unitKerjaKode)->first();

            if ($unitKerjaTarget) {
                // Kumpulkan semua ID unit kerja turunan (termasuk unit target itu sendiri)
                $unitIdsInScope = $this->getAllChildUnitIds($unitKerjaTarget);
                $unitIdsInScope[] = $unitKerjaTarget->id; // Tambahkan ID unit target itu sendiri

                $query->whereHas('pegawai', function ($q) use ($unitIdsInScope) {
                    // Filter pegawai yang unit_kerja_id-nya ada dalam array ID yang dikumpulkan
                    $q->whereIn('unit_kerja_id', $unitIdsInScope);
                });
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Unit Kerja yang dipilih tidak ditemukan.'
                ], 404);
            }
        }

        // Filter by search (NIP, Nama Pegawai, Tahun, Bahasa, Kemampuan, Tanggal Diajukan)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('tahun', 'like', '%' . $search . '%')
                    ->orWhere('nama_lembaga', 'like', '%' . $search . '%')
                    ->orWhere('kemampuan_mendengar', 'like', '%' . $search . '%')
                    ->orWhere('kemampuan_bicara', 'like', '%' . $search . '%')
                    ->orWhere('kemampuan_menulis', 'like', '%' . $search . '%')
                    ->orWhere('tgl_diajukan', 'like', '%' . $search . '%')
                    ->orWhereHas('bahasa', function ($q2) use ($search) {
                        $q2->where('nama_bahasa', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('pegawai', function ($q3) use ($search) {
                        $q3->where('nip', 'like', '%' . $search . '%')
                            ->orWhere('nama', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->byStatus($statusPengajuan);
        }

        // Filter by Jabatan Fungsional
        if ($jabatanFungsionalId) {
            $query->whereHas('pegawai.dataJabatanFungsional', function ($q) use ($jabatanFungsionalId) {
                $q->where('jabatan_fungsional_id', $jabatanFungsionalId);
            });
        }

        // Additional filters (dari kolom spesifik)
        if ($tahun) {
            $query->where('tahun', $tahun);
        }
        if ($bahasaId) {
            $query->where('bahasa_id', $bahasaId);
        }
        if ($namaLembaga) {
            $query->where('nama_lembaga', 'like', '%' . $namaLembaga . '%');
        }
        if ($kemampuanMendengar) {
            $query->where('kemampuan_mendengar', $kemampuanMendengar);
        }
        if ($kemampuanBicara) {
            $query->where('kemampuan_bicara', $kemampuanBicara);
        }
        if ($kemampuanMenulis) {
            $query->where('kemampuan_menulis', $kemampuanMenulis);
        }

        $dataKemampuanBahasa = $query->orderBy('tgl_diajukan', 'desc')->paginate($perPage);

        $dataKemampuanBahasa->getCollection()->transform(function ($item) {
            return $this->formatDataKemampuanBahasa($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $dataKemampuanBahasa,
            'empty_data' => $dataKemampuanBahasa->isEmpty(),
            'filters' => [
                'status_pengajuan' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'draft', 'nama' => 'Draft'],
                    ['id' => 'diajukan', 'nama' => 'Diajukan'],
                    ['id' => 'disetujui', 'nama' => 'Disetujui'],
                    ['id' => 'ditolak', 'nama' => 'Ditolak']
                ],
                'bahasa_options' => $this->getBahasaOptions(),
                'kemampuan_options' => $this->getKemampuanOptions(),
                'unit_kerja_options' => $this->getUnitKerjaOptions(),
                'jabatan_fungsional_options' => $this->getJabatanFungsionalOptions(),
                'tahun_options' => $this->getTahunOptionsForFilter(),
            ],
            'table_columns' => [
                ['field' => 'nip_pegawai', 'label' => 'NIP', 'sortable' => false],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai', 'sortable' => false],
                ['field' => 'unit_kerja_pegawai', 'label' => 'Unit Kerja', 'sortable' => false],
                ['field' => 'jabatan_fungsional_pegawai', 'label' => 'Jabatan Fungsional', 'sortable' => false],
                ['field' => 'tahun', 'label' => 'Tahun', 'sortable' => true, 'sortable_field' => 'tahun'],
                ['field' => 'nama_bahasa', 'label' => 'Bahasa', 'sortable' => false],
                ['field' => 'nama_lembaga', 'label' => 'Nama Lembaga', 'sortable' => true, 'sortable_field' => 'nama_lembaga'],
                ['field' => 'kemampuan_mendengar', 'label' => 'Mendengar', 'sortable' => true, 'sortable_field' => 'kemampuan_mendengar'],
                ['field' => 'kemampuan_bicara', 'label' => 'Bicara', 'sortable' => true, 'sortable_field' => 'kemampuan_bicara'],
                ['field' => 'kemampuan_menulis', 'label' => 'Menulis', 'sortable' => true, 'sortable_field' => 'kemampuan_menulis'],
                ['field' => 'tgl_diajukan_formatted', 'label' => 'Tgl Diajukan', 'sortable' => true, 'sortable_field' => 'tgl_diajukan'],
                ['field' => 'status_pengajuan', 'label' => 'Status', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'batch_actions' => [
                'approve' => [
                    'url' => url("/api/admin/datakemampuanbahasa/batch/approve"),
                    'method' => 'PATCH',
                    'label' => 'Setujui Terpilih',
                    'icon' => 'check',
                    'color' => 'success',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menyetujui data terpilih?',
                ],
                'reject' => [
                    'url' => url("/api/admin/datakemampuanbahasa/batch/reject"),
                    'method' => 'PATCH',
                    'label' => 'Tolak Terpilih',
                    'icon' => 'times',
                    'color' => 'danger',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menolak data terpilih?',
                    'needs_input' => true,
                    'input_placeholder' => 'Keterangan penolakan (opsional)'
                ],
                'delete' => [
                    'url' => url("/api/admin/datakemampuanbahasa/batch/delete"),
                    'method' => 'DELETE',
                    'label' => 'Hapus Terpilih',
                    'icon' => 'trash',
                    'color' => 'danger',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data terpilih?',
                ]
            ],
            'pagination' => [
                'current_page' => $dataKemampuanBahasa->currentPage(),
                'per_page' => $dataKemampuanBahasa->perPage(),
                'total' => $dataKemampuanBahasa->total(),
                'last_page' => $dataKemampuanBahasa->lastPage(),
                'from' => $dataKemampuanBahasa->firstItem(),
                'to' => $dataKemampuanBahasa->lastItem()
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * Admin dapat menambahkan data untuk pegawai manapun.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'required|exists:simpeg_pegawai,id',
            'tahun' => 'required|integer|min:1900|max:' . (date('Y') + 5),
            'bahasa_id' => 'required|exists:bahasa,id',
            'nama_lembaga' => 'nullable|string|max:100',
            'kemampuan_mendengar' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'kemampuan_bicara' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'kemampuan_menulis' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $existingData = SimpegDataKemampuanBahasa::where('pegawai_id', $request->pegawai_id)
            ->where('tahun', $request->tahun)
            ->where('bahasa_id', $request->bahasa_id)
            ->first();

        if ($existingData) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa untuk tahun dan bahasa yang sama sudah ada pada pegawai ini.'
            ], 422);
        }

        $data = $request->except(['file_pendukung']);
        $data['tgl_input'] = now()->toDateString();

        $data['status_pengajuan'] = $request->input('status_pengajuan', 'draft');
        if ($data['status_pengajuan'] === 'diajukan') {
            $data['tgl_diajukan'] = now();
        } elseif ($data['status_pengajuan'] === 'disetujui') {
            $data['tgl_disetujui'] = now();
        } elseif ($data['status_pengajuan'] === 'ditolak') {
            $data['tgl_ditolak'] = now();
        }

        if ($request->hasFile('file_pendukung')) {
            $file = $request->file('file_pendukung');
            $fileName = 'kemampuan_bahasa_' . time() . '_' . $request->pegawai_id . '_' . $request->tahun . '_' . $request->bahasa_id . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/kemampuan-bahasa', $fileName);
            $data['file_pendukung'] = $fileName;
        }

        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::create($data);

        ActivityLogger::log('admin_create_kemampuan_bahasa', $dataKemampuanBahasa, $dataKemampuanBahasa->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataKemampuanBahasa($dataKemampuanBahasa->load('bahasa', 'pegawai')),
            'message' => 'Data kemampuan bahasa berhasil ditambahkan oleh admin'
        ], 201);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::with([
            'bahasa',
            'pegawai' => function ($q) {
                $q->with([
                    'unitKerja',
                    'dataJabatanFungsional' => function ($subQuery) {
                        $subQuery->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1);
                    },
                    'dataJabatanStruktural' => function ($subQuery) {
                        $subQuery->with('jabatanStruktural.jenisJabatanStruktural')->orderBy('tgl_mulai', 'desc')->limit(1);
                    },
                    'dataPendidikanFormal' => function ($subQuery) {
                        $subQuery->with('jenjangPendidikan')->orderBy('jenjang_pendidikan_id', 'desc')->limit(1);
                    },
                    'jabatanAkademik',
                    'statusAktif'
                ]);
            }
        ])->find($id);

        if (!$dataKemampuanBahasa) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatDataKemampuanBahasa($dataKemampuanBahasa),
            'bahasa_options' => $this->getBahasaOptions(),
            'kemampuan_options' => $this->getKemampuanOptions(),
            'pegawai_info_detail' => $this->formatPegawaiInfoDetail($dataKemampuanBahasa->pegawai),
        ]);
    }

    /**
     * Update the specified resource in storage.
     * Admin bisa mengedit data apapun tanpa batasan status pengajuan pegawai.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::find($id);

        if (!$dataKemampuanBahasa) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tahun' => 'sometimes|integer|min:1900|max:' . (date('Y') + 5),
            'bahasa_id' => 'sometimes|exists:bahasa,id',
            'nama_lembaga' => 'nullable|string|max:100',
            'kemampuan_mendengar' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'kemampuan_bicara' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'kemampuan_menulis' => 'nullable|in:Sangat Baik,Baik,Cukup,Kurang',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('tahun') || $request->has('bahasa_id')) {
            $existingData = SimpegDataKemampuanBahasa::where('pegawai_id', $dataKemampuanBahasa->pegawai_id)
                ->where('tahun', $request->input('tahun', $dataKemampuanBahasa->tahun))
                ->where('bahasa_id', $request->input('bahasa_id', $dataKemampuanBahasa->bahasa_id))
                ->where('id', '!=', $id)
                ->first();

            if ($existingData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data kemampuan bahasa untuk tahun dan bahasa yang sama sudah ada pada pegawai ini.'
                ], 422);
            }
        }

        $oldData = $dataKemampuanBahasa->getOriginal();
        $data = $request->except(['file_pendukung']);

        if ($request->hasFile('file_pendukung')) {
            if ($dataKemampuanBahasa->file_pendukung) {
                Storage::delete('public/pegawai/kemampuan-bahasa/' . $dataKemampuanBahasa->file_pendukung);
            }

            $file = $request->file('file_pendukung');
            $fileName = 'kemampuan_bahasa_' . time() . '_' . $dataKemampuanBahasa->pegawai_id . '_' . ($request->tahun ?? $dataKemampuanBahasa->tahun) . '_' . ($request->bahasa_id ?? $dataKemampuanBahasa->bahasa_id) . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/kemampuan-bahasa', $fileName);
            $data['file_pendukung'] = $fileName;
        }

        if (isset($data['status_pengajuan']) && $data['status_pengajuan'] !== $dataKemampuanBahasa->status_pengajuan) {
            switch ($data['status_pengajuan']) {
                case 'diajukan':
                    $data['tgl_diajukan'] = now();
                    $data['tgl_disetujui'] = null;
                    $data['tgl_ditolak'] = null;
                    break;
                case 'disetujui':
                    $data['tgl_disetujui'] = now();
                    $data['tgl_diajukan'] = $dataKemampuanBahasa->tgl_diajukan ?? now();
                    $data['tgl_ditolak'] = null;
                    break;
                case 'ditolak':
                    $data['tgl_ditolak'] = now();
                    $data['tgl_diajukan'] = null;
                    $data['tgl_disetujui'] = null;
                    break;
                case 'draft':
                    $data['tgl_diajukan'] = null;
                    $data['tgl_disetujui'] = null;
                    $data['tgl_ditolak'] = null;
                    break;
            }
        }

        $dataKemampuanBahasa->update($data);

        ActivityLogger::log('admin_update_kemampuan_bahasa', $dataKemampuanBahasa, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataKemampuanBahasa($dataKemampuanBahasa->load('bahasa', 'pegawai.unitKerja', 'pegawai.dataJabatanFungsional.jabatanFungsional')),
            'message' => 'Data kemampuan bahasa berhasil diperbarui oleh admin'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     * Admin bisa menghapus data apapun.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::find($id);

        if (!$dataKemampuanBahasa) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa tidak ditemukan'
            ], 404);
        }

        if ($dataKemampuanBahasa->file_pendukung) {
            Storage::delete('public/pegawai/kemampuan-bahasa/' . $dataKemampuanBahasa->file_pendukung);
        }

        $oldData = $dataKemampuanBahasa->toArray();
        $dataKemampuanBahasa->delete();

        ActivityLogger::log('admin_delete_kemampuan_bahasa', $dataKemampuanBahasa, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data kemampuan bahasa berhasil dihapus oleh admin'
        ]);
    }

    /**
     * Admin: Approve a single data entry.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id)
    {
        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::find($id);

        if (!$dataKemampuanBahasa) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa tidak ditemukan'
            ], 404);
        }

        if ($dataKemampuanBahasa->status_pengajuan === 'disetujui') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah disetujui sebelumnya'
            ], 409);
        }

        $oldData = $dataKemampuanBahasa->getOriginal();
        $dataKemampuanBahasa->update([
            'status_pengajuan' => 'disetujui',
            'tgl_disetujui' => now(),
            'tgl_ditolak' => null,
        ]);

        ActivityLogger::log('admin_approve_kemampuan_bahasa', $dataKemampuanBahasa, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data kemampuan bahasa berhasil disetujui'
        ]);
    }

    /**
     * Admin: Reject a single data entry.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, $id)
    {
        $dataKemampuanBahasa = SimpegDataKemampuanBahasa::find($id);

        if (!$dataKemampuanBahasa) {
            return response()->json([
                'success' => false,
                'message' => 'Data kemampuan bahasa tidak ditemukan'
            ], 404);
        }

        if ($dataKemampuanBahasa->status_pengajuan === 'ditolak') {
            return response()->json([
                'success' => false,
                'message' => 'Data sudah ditolak sebelumnya'
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'keterangan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $dataKemampuanBahasa->getOriginal();
        $dataKemampuanBahasa->update([
            'status_pengajuan' => 'ditolak',
            'tgl_ditolak' => now(),
            'tgl_disetujui' => null,
            'keterangan' => $request->keterangan,
        ]);

        ActivityLogger::log('admin_reject_kemampuan_bahasa', $dataKemampuanBahasa, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data kemampuan bahasa berhasil ditolak'
        ]);
    }

    /**
     * Batch delete data kemampuan bahasa by admin.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
  public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_kemampuan_bahasa,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dataKemampuanBahasaList = SimpegDataKemampuanBahasa::whereIn('id', $request->ids)->get();

        if ($dataKemampuanBahasaList->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data kemampuan bahasa yang ditemukan untuk dihapus'
            ], 404);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($dataKemampuanBahasaList as $dataKemampuanBahasa) {
            try {
                if ($dataKemampuanBahasa->file_pendukung) {
                    Storage::delete('public/pegawai/kemampuan-bahasa/' . $dataKemampuanBahasa->file_pendukung);
                }

                $oldData = $dataKemampuanBahasa->toArray();
                $dataKemampuanBahasa->delete();

                ActivityLogger::log('admin_batch_delete_kemampuan_bahasa', $dataKemampuanBahasa, $oldData);
                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'id' => $dataKemampuanBahasa->id,
                    'tahun' => $dataKemampuanBahasa->tahun,
                    'error' => 'Gagal menghapus: ' . $e->getMessage()
                ];
            }
        }

        if ($deletedCount == count($request->ids)) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$deletedCount} data kemampuan bahasa",
                'deleted_count' => $deletedCount
            ]);
        } else {
            return response()->json([
                'success' => $deletedCount > 0,
                'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data kemampuan bahasa",
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ], $deletedCount > 0 ? 207 : 422);
        }
    }
    /**
     * Batch approve data kemampuan bahasa.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
  public function batchApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_kemampuan_bahasa,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // --- Perbaikan Utama untuk batchApprove ---
        $dataToProcess = SimpegDataKemampuanBahasa::whereIn('id', $request->ids)
                                                ->whereIn('status_pengajuan', ['draft', 'diajukan', 'ditolak']) // Status yang bisa diapprove
                                                ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data kemampuan bahasa yang memenuhi syarat untuk disetujui.'
            ], 404);
        }

        $updatedCount = 0;
        $approvedIds = [];
        DB::beginTransaction(); // Mulai transaksi untuk operasi batch
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => 'disetujui',
                    'tgl_disetujui' => now(),
                    'tgl_diajukan' => $item->tgl_diajukan ?? now(), // Pertahankan tgl_diajukan jika ada, jika tidak set sekarang
                    'tgl_ditolak' => null,
                    'keterangan' => null, // Set keterangan null jika disetujui
                ]);
                ActivityLogger::log('admin_approve_data_kemampuan_bahasa', $item, $oldData); // Kirim objek $item
                $updatedCount++;
                $approvedIds[] = $item->id;
            }
            DB::commit(); // Commit transaksi
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback jika ada error
            \Log::error('Error during batch approve kemampuan bahasa: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyetujui data secara batch: ' . $e->getMessage()
            ], 500);
        }
        // --- Akhir Perbaikan Utama ---

        return response()->json([
            'success' => true,
            'message' => "Berhasil menyetujui {$updatedCount} data kemampuan bahasa",
            'updated_count' => $updatedCount,
            'approved_ids' => $approvedIds
        ]);
    }

    /**
     * Batch reject data kemampuan bahasa.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
  public function batchReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:simpeg_data_kemampuan_bahasa,id',
            'keterangan' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // --- Perbaikan Utama untuk batchReject ---
        $dataToProcess = SimpegDataKemampuanBahasa::whereIn('id', $request->ids)
                                                ->whereIn('status_pengajuan', ['draft', 'diajukan', 'disetujui']) // Status yang bisa ditolak
                                                ->get();

        if ($dataToProcess->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data kemampuan bahasa yang memenuhi syarat untuk ditolak.'
            ], 404);
        }

        $updatedCount = 0;
        $rejectedIds = [];
        DB::beginTransaction(); // Mulai transaksi untuk operasi batch
        try {
            foreach ($dataToProcess as $item) {
                $oldData = $item->getOriginal();
                $item->update([
                    'status_pengajuan' => 'ditolak',
                    'tgl_ditolak' => now(),
                    'tgl_diajukan' => null, // Hilangkan tgl diajukan jika ditolak
                    'tgl_disetujui' => null, // Hilangkan tgl disetujui jika ditolak
                    'keterangan' => $request->keterangan,
                ]);
                ActivityLogger::log('admin_reject_data_kemampuan_bahasa', $item, $oldData); // Kirim objek $item
                $updatedCount++;
                $rejectedIds[] = $item->id;
            }
            DB::commit(); // Commit transaksi
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback jika ada error
            \Log::error('Error during batch reject kemampuan bahasa: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menolak data secara batch: ' . $e->getMessage()
            ], 500);
        }
        // --- Akhir Perbaikan Utama ---

        return response()->json([
            'success' => true,
            'message' => "Berhasil menolak {$updatedCount} data kemampuan bahasa",
            'updated_count' => $updatedCount,
            'rejected_ids' => $rejectedIds
        ]);
    }

    /**
     * Get status statistics for admin dashboard.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatusStatistics(Request $request)
    {
        $unitKerjaKode = $request->unit_kerja_id; // Menggunakan kode_unit dari request
        $jabatanFungsionalId = $request->jabatan_fungsional_id;

        $query = SimpegDataKemampuanBahasa::query();

        if ($unitKerjaKode) {
            $unitKerjaTarget = SimpegUnitKerja::where('kode_unit', $unitKerjaKode)->first();
            if ($unitKerjaTarget) {
                $unitIdsInScope = $this->getAllChildUnitIds($unitKerjaTarget);
                $unitIdsInScope[] = $unitKerjaTarget->id; // Tambahkan ID unit target itu sendiri

                $query->whereHas('pegawai', function ($q) use ($unitIdsInScope) {
                    $q->whereIn('unit_kerja_id', $unitIdsInScope);
                });
            }
        }

        if ($jabatanFungsionalId) {
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
            'ditolak' => 0
        ];

        $statistics = array_merge($defaultStats, $statistics);
        $statistics['total'] = array_sum($statistics);

        return response()->json([
            'success' => true,
            'statistics' => $statistics
        ]);
    }

    /**
     * Get all filter options for the admin interface.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFilterOptions()
    {
        return response()->json([
            'success' => true,
            'filter_options' => [
                'tahun' => $this->getTahunOptionsForFilter(),
                'nama_lembaga' => SimpegDataKemampuanBahasa::distinct()->pluck('nama_lembaga')->filter()->values()->toArray(),
                'bahasa_options' => $this->getBahasaOptions(),
                'kemampuan_options' => $this->getKemampuanOptions(),
                'unit_kerja_options' => $this->getUnitKerjaOptions(),
                'jabatan_fungsional_options' => $this->getJabatanFungsionalOptions(),
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

    // --- HELPER FUNCTIONS ---

    private function getBahasaOptions()
    {
        return SimpegBahasa::select('id', 'nama_bahasa')
            ->orderBy('nama_bahasa')
            ->get()
            ->map(function ($bahasa) {
                return [
                    'value' => $bahasa->id,
                    'label' => $bahasa->nama_bahasa
                ];
            });
    }

    private function getKemampuanOptions()
    {
        return [
            ['value' => 'Sangat Baik', 'label' => 'Sangat Baik'],
            ['value' => 'Baik', 'label' => 'Baik'],
            ['value' => 'Cukup', 'label' => 'Cukup'],
            ['value' => 'Kurang', 'label' => 'Kurang']
        ];
    }

    private function getUnitKerjaOptions()
    {
        // Tetap kembalikan kode_unit sebagai value karena frontend menggunakannya sebagai pengenal
        return SimpegUnitKerja::select('kode_unit as value', 'nama_unit as label')
            ->orderBy('nama_unit')
            ->get();
    }

    private function getJabatanFungsionalOptions()
    {
        return SimpegJabatanFungsional::select('id as value', 'nama_jabatan_fungsional as label')
            ->orderBy('nama_jabatan_fungsional')
            ->get();
    }

    private function getTahunOptionsForFilter()
    {
        return SimpegDataKemampuanBahasa::distinct()
            ->pluck('tahun')
            ->filter()
            ->sortDesc()
            ->values()
            ->toArray();
    }

    protected function formatDataKemampuanBahasa($dataKemampuanBahasa, $includeActions = true)
    {
        $status = $dataKemampuanBahasa->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);

        $pegawai = $dataKemampuanBahasa->pegawai;
        $nipPegawai = $pegawai ? $pegawai->nip : '-';
        $namaPegawai = $pegawai ? $pegawai->nama : '-';
        
        $unitKerjaNama = 'Tidak Ada';
        if ($pegawai->unitKerja) {
            $unitKerjaNama = $pegawai->unitKerja->nama_unit;
        } elseif ($pegawai->unit_kerja_id) {
            // Karena unit_kerja_id di SimpegPegawai menyimpan ID (integer), gunakan find()
            $unitKerja = SimpegUnitKerja::find($pegawai->unit_kerja_id);
            $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $pegawai->unit_kerja_id;
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

        $data = [
            'id' => $dataKemampuanBahasa->id,
            'nip_pegawai' => $nipPegawai,
            'nama_pegawai' => $namaPegawai,
            'unit_kerja_pegawai' => $unitKerjaNama,
            'jabatan_fungsional_pegawai' => $jabatanFungsionalNama,
            'tahun' => $dataKemampuanBahasa->tahun,
            'bahasa_id' => $dataKemampuanBahasa->bahasa_id,
            'nama_bahasa' => $dataKemampuanBahasa->bahasa->nama_bahasa ?? '-', // Menggunakan relasi langsung
            'nama_lembaga' => $dataKemampuanBahasa->nama_lembaga,
            'kemampuan_mendengar' => $dataKemampuanBahasa->kemampuan_mendengar,
            'kemampuan_bicara' => $dataKemampuanBahasa->kemampuan_bicara,
            'kemampuan_menulis' => $dataKemampuanBahasa->kemampuan_menulis,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'keterangan' => $dataKemampuanBahasa->keterangan,
            'timestamps' => [
                'tgl_input' => $dataKemampuanBahasa->tgl_input,
                'tgl_diajukan' => $dataKemampuanBahasa->tgl_diajukan,
                'tgl_disetujui' => $dataKemampuanBahasa->tgl_disetujui,
                'tgl_ditolak' => $dataKemampuanBahasa->tgl_ditolak
            ],
            'tgl_diajukan_formatted' => $dataKemampuanBahasa->tgl_diajukan ? Carbon::parse($dataKemampuanBahasa->tgl_diajukan)->format('Y-m-d') : '-',
            'dokumen' => $dataKemampuanBahasa->file_pendukung ? [
                'nama_file' => $dataKemampuanBahasa->file_pendukung,
                'url' => url('storage/pegawai/kemampuan-bahasa/' . $dataKemampuanBahasa->file_pendukung)
            ] : null,
            'created_at' => $dataKemampuanBahasa->created_at,
            'updated_at' => $dataKemampuanBahasa->updated_at
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/admin/datakemampuanbahasa/{$dataKemampuanBahasa->id}"),
                'update_url' => url("/api/admin/datakemampuanbahasa/{$dataKemampuanBahasa->id}"),
                'delete_url' => url("/api/admin/datakemampuanbahasa/{$dataKemampuanBahasa->id}"),
                'approve_url' => url("/api/admin/datakemampuanbahasa/{$dataKemampuanBahasa->id}/approve"),
                'reject_url' => url("/api/admin/datakemampuanbahasa/{$dataKemampuanBahasa->id}/reject"),
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
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin menghapus data kemampuan bahasa NIP ' . $nipPegawai . ' tahun "' . $dataKemampuanBahasa->tahun . '"?'
                ],
            ];

            if ($status === 'diajukan' || $status === 'ditolak') {
                $data['actions']['approve'] = [
                    'url' => $data['aksi']['approve_url'],
                    'method' => 'PATCH',
                    'label' => 'Setujui',
                    'icon' => 'check',
                    'color' => 'success',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENYETUJUI data kemampuan bahasa NIP ' . $nipPegawai . ' tahun "' . $dataKemampuanBahasa->tahun . '"?'
                ];
            }

            if ($status === 'diajukan' || $status === 'disetujui') {
                $data['actions']['reject'] = [
                    'url' => $data['aksi']['reject_url'],
                    'method' => 'PATCH',
                    'label' => 'Tolak',
                    'icon' => 'times',
                    'color' => 'danger',
                    'confirm' => true,
                    'confirm_message' => 'Apakah Anda yakin ingin MENOLAK data kemampuan bahasa NIP ' . $nipPegawai . ' tahun "' . $dataKemampuanBahasa->tahun . '"?',
                    'needs_input' => true,
                    'input_placeholder' => 'Masukkan keterangan penolakan (opsional)'
                ];
            }
        }

        return $data;
    }

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

    /**
     * Recursive helper to get all child unit IDs for hierarchical filtering.
     *
     * @param \App\Models\SimpegUnitKerja $unit
     * @return array
     */
    private function getAllChildUnitIds(SimpegUnitKerja $unit)
    {
        $childIds = [];
        foreach ($unit->children as $child) {
            // Perubahan: Gunakan ID (integer) dari unit kerja anak, bukan kode_unit (string)
            $childIds[] = $child->id; 
            $childIds = array_merge($childIds, $this->getAllChildUnitIds($child));
        }
        return $childIds;
    }

    private function formatPegawaiInfoDetail(?SimpegPegawai $pegawai)
    {
        if (!$pegawai) {
            return [
                'id' => null,
                'nip' => '-',
                'nama' => '-',
                'unit_kerja' => 'Tidak Ada',
                'status' => '-',
                'jab_akademik' => '-',
                'jab_fungsional' => '-',
                'jab_struktural' => '-',
                'pendidikan' => '-',
            ];
        }

        $jabatanAkademikNama = $pegawai->jabatanAkademik ? $pegawai->jabatanAkademik->jabatan_akademik : '-';

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
            if ($jabatanStruktural && $jabatanStruktural->jabatanStruktural && $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->jenisJabatanStruktural->jenis_jabatan_struktural;
            } elseif (isset($jabatanStruktural->jabatanStruktural->nama_jabatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->nama_jabatan;
            } elseif (isset($jabatanStruktural->jabatanStruktural->singkatan)) {
                $jabatanStrukturalNama = $jabatanStruktural->jabatanStruktural->singkatan;
            } elseif (isset($jabatanStruktural->nama_jabatan)) {
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
            // Perubahan: Gunakan find() karena unit_kerja_id di SimpegPegawai adalah ID integer
            $unitKerja = SimpegUnitKerja::find($pegawai->unit_kerja_id);
            $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $pegawai->unit_kerja_id;
        }

        return [
            'id' => $pegawai->id,
            'nip' => $pegawai->nip,
            'nama' => $pegawai->nama,
            'unit_kerja' => $unitKerjaNama,
            'status' => $pegawai->statusAktif ? $pegawai->statusAktif->nama_status_aktif : '-',
            'jab_akademik' => $jabatanAkademikNama,
            'jab_fungsional' => $jabatanFungsionalNama,
            'jab_struktural' => $jabatanStrukturalNama,
            'pendidikan' => $jenjangPendidikanNama
        ];
    }
}