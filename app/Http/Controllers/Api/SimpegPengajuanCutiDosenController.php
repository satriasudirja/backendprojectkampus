<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegCutiRecord;
use App\Models\SimpegDaftarCuti;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SimpegPengajuanCutiDosenController extends Controller
{
    // Get all data cuti for logged in dosen
    public function index(Request $request) 
    {
        try {
            // Pastikan user sudah login
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Silakan login terlebih dahulu'
                ], 401);
            }

            // Eager load semua relasi yang diperlukan untuk menghindari N+1 query problem
            $pegawai = Auth::user()->pegawai;
            $pegawai->load([
                'unitKerja',
                'statusAktif', 
                'jabatanFungsional',
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

            // Query HANYA untuk pegawai yang sedang login dengan additional security check
            $query = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
                ->with('jenisCuti');

            // Additional validation - hanya untuk pegawai yang valid
            

            // Filter by search
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('alasan_cuti', 'like', '%'.$search.'%')
                      ->orWhere('no_urut_cuti', 'like', '%'.$search.'%')
                      ->orWhere('alamat', 'like', '%'.$search.'%');
                });
            }

            // Filter by status pengajuan
            if ($statusPengajuan && $statusPengajuan != 'semua') {
                $query->where('status_pengajuan', $statusPengajuan);
            }

            // Additional filters
            if ($request->filled('jenis_cuti_id')) {
                $query->where('jenis_cuti_id', $request->jenis_cuti_id);
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
            $dataCuti = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Transform the collection to include formatted data with action URLs
            $dataCuti->getCollection()->transform(function ($item) {
                return $this->formatDataCuti($item, true);
            });

            // Get jenis cuti options with error handling
            $jenisCutiOptions = collect([]);
            try {
                $jenisCutiOptions = SimpegDaftarCuti::select('id', 'nama_jenis_cuti', 'kode', 'standar_cuti')
                    ->orderBy('nama_jenis_cuti')
                    ->get()
                    ->map(function($item) {
                        return [
                            'id' => $item->id,
                            'kode' => $item->kode,
                            'nama' => $item->nama_jenis_cuti,
                            'standar_cuti' => $item->standar_cuti
                        ];
                    });
            } catch (\Exception $e) {
                // If jenis cuti table doesn't exist, return empty collection
                $jenisCutiOptions = collect([]);
            }

            return response()->json([
                'success' => true,
                'data' => $dataCuti,
                'empty_data' => $dataCuti->isEmpty(),
                'pegawai_info' => $this->formatPegawaiInfo($pegawai),
                'jenis_cuti' => $jenisCutiOptions,
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
                    ['field' => 'no_urut_cuti', 'label' => 'No. Cuti', 'sortable' => true, 'sortable_field' => 'no_urut_cuti'],
                    ['field' => 'jenis_cuti', 'label' => 'Jenis Cuti', 'sortable' => true, 'sortable_field' => 'jenis_cuti_id'],
                    ['field' => 'tgl_mulai', 'label' => 'Tanggal Mulai', 'sortable' => true, 'sortable_field' => 'tgl_mulai'],
                    ['field' => 'tgl_selesai', 'label' => 'Tanggal Selesai', 'sortable' => true, 'sortable_field' => 'tgl_selesai'],
                    ['field' => 'jumlah_cuti', 'label' => 'Jumlah Hari', 'sortable' => true, 'sortable_field' => 'jumlah_cuti'],
                    ['field' => 'alasan_cuti', 'label' => 'Alasan', 'sortable' => true, 'sortable_field' => 'alasan_cuti'],
                    ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                    ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
                ],
                'table_rows_options' => [10, 25, 50, 100],
                'tambah_data_url' => url("/api/dosen/pengajuan-cuti-dosen"),
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
                    'current_page' => $dataCuti->currentPage(),
                    'per_page' => $dataCuti->perPage(),
                    'total' => $dataCuti->total(),
                    'last_page' => $dataCuti->lastPage(),
                    'from' => $dataCuti->firstItem(),
                    'to' => $dataCuti->lastItem()
                ]
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database-specific errors with more user-friendly messages
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada database. Pastikan tabel dan kolom yang diperlukan sudah ada.',
                'error_code' => 'DATABASE_ERROR',
                'debug_info' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem',
                'error_code' => 'SYSTEM_ERROR',
                'debug_info' => config('app.debug') ? $e->getMessage() : 'System error occurred'
            ], 500);
        }
    }

    /**
     * Check if pegawai is dosen based on available data
     */
    

    // Fix existing data dengan status_pengajuan null
    public function fixExistingData()
    {
        try {
            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            // Update data yang status_pengajuan-nya null menjadi draft
            $updatedCount = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
                ->whereNull('status_pengajuan')
                ->update([
                    'status_pengajuan' => 'draft'
                ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil memperbaiki {$updatedCount} data pengajuan cuti",
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbaiki data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Get detail data cuti
    public function show($id)
    {
        try {
            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            $dataCuti = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
                ->with('jenisCuti')
                ->find($id);

            if (!$dataCuti) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan cuti tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'pegawai' => $this->formatPegawaiInfo($pegawai->load([
                    'unitKerja', 'statusAktif', 'jabatanFungsional',
                    'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
                    'dataPendidikanFormal.jenjangPendidikan'
                ])),
                'data' => $this->formatDataCuti($dataCuti)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    // Generate nomor cuti
    private function generateNoUrutCuti($jenisCutiId)
    {
        $jenisCuti = SimpegDaftarCuti::find($jenisCutiId);
        
        if (!$jenisCuti) {
            return 'CT/TEMP/' . date('Y') . '/' . Str::random(6);
        }

        $today = now();
        $year = $today->format('Y');
        $month = $today->format('m');
        
        // Hitung jumlah cuti jenis ini di tahun berjalan
        $count = SimpegCutiRecord::where('jenis_cuti_id', $jenisCutiId)
            ->whereYear('created_at', $year)
            ->count() + 1;

        // Format default jika tidak ada template
        $format = $jenisCuti->format_nomor_surat ?? 'CT/{kode}/{no}/{tahun}';
        
        // Ganti placeholder
            return str_replace(
            ['{{no}}', '{{kode}}', '{{tahun}}', '{{bulan}}', '{{urutan}}'],
            [
                str_pad($count, 3, '0', STR_PAD_LEFT),
                $jenisCuti->kode ?? 'CT',
                $year,
                $month,
                $count
            ],
            $format
        );
    }

    // Calculate jumlah cuti (days between two dates)
    private function calculateJumlahCuti($tglMulai, $tglSelesai)
    {
        try {
            $start = new \DateTime($tglMulai);
            $end = new \DateTime($tglSelesai);
            $interval = $start->diff($end);
            
            // Add 1 because the calculation is inclusive of the start and end dates
            return $interval->days + 1;
        } catch (\Exception $e) {
            return 1; // Default value
        }
    }

    // Store new data cuti dengan draft/submit mode
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }


            $validator = Validator::make($request->all(), [
                'jenis_cuti_id' => 'required|uuid|exists:simpeg_daftar_cuti,id',
                'tgl_mulai' => 'required|date',
                'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
                'jumlah_cuti' => 'nullable|integer|min:1',
                'alasan_cuti' => 'required|string|max:255',
                'alamat' => 'required|string|max:500',
                'no_telp' => 'required|string|max:20',
                'file_cuti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'submit_type' => 'sometimes|in:draft,submit'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify jenis_cuti exists (double-check)
            if (!SimpegDaftarCuti::where('id', $request->jenis_cuti_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis cuti tidak valid'
                ], 422);
            }

            $data = $request->except(['file_cuti', 'submit_type']);
            $data['pegawai_id'] = $pegawai->id;
            $data['no_urut_cuti'] = $this->generateNoUrutCuti($request->jenis_cuti_id);

            // Calculate jumlah_cuti if not provided
            if (!$request->jumlah_cuti) {
                $data['jumlah_cuti'] = $this->calculateJumlahCuti($request->tgl_mulai, $request->tgl_selesai);
            }

            // Set status
            $submitType = $request->input('submit_type', 'draft');
            $data['status_pengajuan'] = ($submitType === 'submit') ? 'diajukan' : 'draft';
            $message = ($submitType === 'submit') 
                ? 'Pengajuan cuti berhasil diajukan untuk persetujuan' 
                : 'Pengajuan cuti berhasil disimpan sebagai draft';

            if ($submitType === 'submit') {
                $data['tgl_diajukan'] = now();
            }

            // Handle file upload
            if ($request->hasFile('file_cuti')) {
                $file = $request->file('file_cuti');
                $fileName = 'cuti_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
                // ==================================================================
                // PERBAIKAN: Menyimpan file ke disk 'public'
                // ==================================================================
                $file->storeAs('pegawai/cuti', $fileName, 'public');
                $data['file_cuti'] = $fileName;
            }

            $dataCuti = SimpegCutiRecord::create($data);

            // Commit transaction
            DB::commit();

            // Log activity
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('create', $dataCuti, $dataCuti->toArray());
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatDataCuti($dataCuti->load('jenisCuti')),
                'message' => $message
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: ' . $e->getMessage(),
                'error_details' => env('APP_DEBUG') ? $e->getTrace() : null
            ], 500);
        }
    }

    
    // Update data cuti dengan validasi status
    // Replace your update method with this fixed version
    public function update(Request $request, $id)
    {
        try {
            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            $dataCuti = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
                ->find($id);

            if (!$dataCuti) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan cuti tidak ditemukan'
                ], 404);
            }

            // Validasi apakah bisa diedit berdasarkan status
            $editableStatuses = ['draft', 'ditolak'];
            if (!in_array($dataCuti->status_pengajuan, $editableStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
                ], 422);
            }

            // FIXED VALIDATION: Use 'sometimes' instead of 'required' for update operations
            $validationRules = [
                'file_cuti' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
                'submit_type' => 'sometimes|in:draft,submit'
            ];

            // Add conditional validation - only validate fields that are present
            if ($request->has('jenis_cuti_id')) {
                $validationRules['jenis_cuti_id'] = 'sometimes|uuid|exists:simpeg_daftar_cuti,id';
            }

            if ($request->has('tgl_mulai')) {
                $validationRules['tgl_mulai'] = 'sometimes|date';
            }

            if ($request->has('tgl_selesai')) {
                // For tgl_selesai, we need to check against either the new tgl_mulai or existing one
                $tglMulai = $request->input('tgl_mulai', $dataCuti->tgl_mulai);
                $validationRules['tgl_selesai'] = 'sometimes|date|after_or_equal:' . $tglMulai;
            }

            if ($request->has('jumlah_cuti')) {
                $validationRules['jumlah_cuti'] = 'sometimes|integer|min:1';
            }

            if ($request->has('alasan_cuti')) {
                $validationRules['alasan_cuti'] = 'sometimes|string|max:255';
            }

            if ($request->has('alamat')) {
                $validationRules['alamat'] = 'sometimes|string|max:500';
            }

            if ($request->has('no_telp')) {
                $validationRules['no_telp'] = 'sometimes|string|max:20';
            }

            // Handle form-data integer conversion
            $requestData = $request->all();
            if (isset($requestData['jenis_cuti_id'])) {
                $requestData['jenis_cuti_id'] = (int) $requestData['jenis_cuti_id'];
            }
            if (isset($requestData['jumlah_cuti'])) {
                $requestData['jumlah_cuti'] = (int) $requestData['jumlah_cuti'];
            }

            $validator = Validator::make($requestData, $validationRules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                    'debug_data' => config('app.debug') ? [
                        'received_data' => $requestData,
                        'validation_rules' => $validationRules
                    ] : null
                ], 422);
            }

            $oldData = $dataCuti->getOriginal();
            $data = $request->except(['file_cuti', 'submit_type']);

            // Convert string integers to actual integers for form-data
            if (isset($data['jenis_cuti_id'])) {
                $data['jenis_cuti_id'] = (int) $data['jenis_cuti_id'];
            }
            if (isset($data['jumlah_cuti'])) {
                $data['jumlah_cuti'] = (int) $data['jumlah_cuti'];
            }

            // Update no_urut_cuti if jenis_cuti_id changed
            if ($request->has('jenis_cuti_id') && $request->jenis_cuti_id != $dataCuti->jenis_cuti_id) {
                $data['no_urut_cuti'] = $this->generateNoUrutCuti($request->jenis_cuti_id);
            }

            // Calculate jumlah_cuti if tgl_mulai or tgl_selesai changed
            if (($request->has('tgl_mulai') || $request->has('tgl_selesai')) && !$request->has('jumlah_cuti')) {
                $tglMulai = $request->tgl_mulai ?? $dataCuti->tgl_mulai;
                $tglSelesai = $request->tgl_selesai ?? $dataCuti->tgl_selesai;
                $data['jumlah_cuti'] = $this->calculateJumlahCuti($tglMulai, $tglSelesai);
            }

            // Reset status jika dari ditolak
            if ($dataCuti->status_pengajuan === 'ditolak') {
                $data['status_pengajuan'] = 'draft';
                $data['tgl_ditolak'] = null;
            }

            // Handle submit_type
            if ($request->submit_type === 'submit') {
                $data['status_pengajuan'] = 'diajukan';
                $data['tgl_diajukan'] = now();
                $message = 'Pengajuan cuti berhasil diperbarui dan diajukan untuk persetujuan';
            } else {
                $message = 'Pengajuan cuti berhasil diperbarui';
            }

            // Handle file upload
            if ($request->hasFile('file_cuti')) {
                // ==================================================================
                // PERBAIKAN: Hapus file lama dari disk 'public'
                // ==================================================================
                if ($dataCuti->file_cuti) {
                    Storage::disk('public')->delete('pegawai/cuti/'.$dataCuti->file_cuti);
                }

                $file = $request->file('file_cuti');
                $fileName = 'cuti_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
                // ==================================================================
                // PERBAIKAN: Menyimpan file ke disk 'public'
                // ==================================================================
                $file->storeAs('pegawai/cuti', $fileName, 'public');
                $data['file_cuti'] = $fileName;
            }

            $dataCuti->update($data);

            // Log activity if ActivityLogger exists
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('update', $dataCuti, $oldData);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatDataCuti($dataCuti->fresh(['jenisCuti'])),
                'message' => $message
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
                'error_code' => 'DATABASE_ERROR'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal update data: ' . $e->getMessage(),
                'error_code' => 'SYSTEM_ERROR'
            ], 500);
        }
    }

    public function getRemainingCuti()
    {
        $user = auth()->user(); // pastikan pakai autentikasi
        // Perbaikan: Ambil ID pegawai dari model User
        $pegawaiId = $user->id; 

        // ambil total cuti dari jenis cuti
        $standarCuti = 12; // misalnya default 12, atau bisa dari DB

        // ambil total cuti yang sudah diambil user ini dari tabel pengajuan cuti
        $cutiTerpakai = SimpegCutiRecord::where('pegawai_id', $pegawaiId)
                        ->where('status_pengajuan', 'disetujui') // hanya yang disetujui
                        ->sum('jumlah_cuti');

        $sisaCuti = max(0, $standarCuti - $cutiTerpakai);

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai_id' => $pegawaiId,
                'standar_cuti' => $standarCuti,
                'terpakai' => (int) $cutiTerpakai,
                'sisa_cuti' => $sisaCuti,
            ]
        ]);
    }

    public function getAvailableActions()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'data' => []], 401);
        }

        // Ambil data pegawai yang terhubung
        $pegawai = $user->pegawai;

        if (!$pegawai) {
            return response()->json(['success' => false, 'data' => []], 404);
        }

        $actions = [];

        // Cek pertama: Apakah pegawai ini adalah admin?
        if ($pegawai->is_admin) {
            $actions = [
                'approve', 'reject', 'delete', 'edit'
            ];
        } 
        // Jika bukan admin, baru cek nama perannya
        elseif ($pegawai->role && $pegawai->role->nama === 'Dosen') { // Contoh untuk Dosen
            $actions = [
                'edit', 'submit'
            ];
        }
        elseif ($pegawai->role && $pegawai->role->nama === 'Tenaga Kependidikan') { // Contoh untuk Dosen
            $actions = [
                'edit', 'submit'
            ];
        }else{
           $actions = [
                'edit', 'submit'
            ]; 
        }
        // Anda bisa menambahkan elseif lain untuk role yang berbeda
        // elseif ($pegawai->role && $pegawai->role->nama === 'Tenaga Kependidikan') { ... }

        return response()->json([
            'success' => true,
            'data' => $actions,
        ]);
    }



    // Delete data cuti
    public function destroy($id)
    {
        try {
            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            $dataCuti = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
                ->find($id);

            if (!$dataCuti) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan cuti tidak ditemukan'
                ], 404);
            }

            // Delete file if exists
            if ($dataCuti->file_cuti) {
                // ==================================================================
                // PERBAIKAN: Hapus file dari disk 'public'
                // ==================================================================
                Storage::disk('public')->delete('pegawai/cuti/'.$dataCuti->file_cuti);
            }

            $oldData = $dataCuti->toArray();
            $dataCuti->delete();

            // Log activity if ActivityLogger exists
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('delete', $dataCuti, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data pengajuan cuti berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Submit draft ke diajukan
    public function submitDraft($id)
    {
        try {
            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            $dataCuti = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
                ->where('status_pengajuan', 'draft')
                ->find($id);

            if (!$dataCuti) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan cuti draft tidak ditemukan atau sudah diajukan'
                ], 404);
            }

            $oldData = $dataCuti->getOriginal();
            
            $dataCuti->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

            // Log activity if ActivityLogger exists
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('update', $dataCuti, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti berhasil diajukan untuk persetujuan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Batch delete data cuti
    public function batchDelete(Request $request)
    {
        try {
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

            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            $dataCutiList = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
                ->whereIn('id', $request->ids)
                ->get();

            if ($dataCutiList->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan cuti tidak ditemukan atau tidak memiliki akses'
                ], 404);
            }

            $deletedCount = 0;
            $errors = [];

            foreach ($dataCutiList as $dataCuti) {
                try {
                    // Delete file if exists
                    if ($dataCuti->file_cuti) {
                        // ==================================================================
                        // PERBAIKAN: Hapus file dari disk 'public'
                        // ==================================================================
                        Storage::disk('public')->delete('pegawai/cuti/'.$dataCuti->file_cuti);
                    }

                    $oldData = $dataCuti->toArray();
                    $dataCuti->delete();
                    
                    // Log activity if ActivityLogger exists
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('delete', $dataCuti, $oldData);
                    }
                    $deletedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $dataCuti->id,
                        'no_urut_cuti' => $dataCuti->no_urut_cuti,
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

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal batch delete: ' . $e->getMessage()
            ], 500);
        }
    }

    // Batch submit drafts
    public function batchSubmitDrafts(Request $request)
    {
        try {
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

            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            $updatedCount = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
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

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal batch submit: ' . $e->getMessage()
            ], 500);
        }
    }

    // Batch update status
    public function batchUpdateStatus(Request $request)
    {
        try {
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

            $pegawai = Auth::user()->pegawai;

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

            $updatedCount = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
                ->whereIn('id', $request->ids)
                ->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Status pengajuan berhasil diperbarui',
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal batch update status: ' . $e->getMessage()
            ], 500);
        }
    }

    // Get status statistics untuk dashboard
    public function getStatusStatistics()
    {
        try {
            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            $statistics = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
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

            // Get statistics by jenis cuti
            $jenisStat = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
                ->where('status_pengajuan', 'disetujui')
                ->whereYear('tgl_mulai', date('Y'))
                ->join('simpeg_daftar_cuti', 'simpeg_cuti_record.jenis_cuti_id', '=', 'simpeg_daftar_cuti.id')
                ->selectRaw('simpeg_daftar_cuti.nama_jenis_cuti, COUNT(*) as total, SUM(jumlah_cuti) as jumlah_hari')
                ->groupBy('simpeg_daftar_cuti.nama_jenis_cuti')
                ->get();

            $statistics['by_jenis'] = $jenisStat;

            return response()->json([
                'success' => true,
                'statistics' => $statistics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik: ' . $e->getMessage()
            ], 500);
        }
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
        try {
            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            $jenisCuti = collect([]);
            try {
                $jenisCuti = SimpegDaftarCuti::select('id', 'nama_jenis_cuti')
                    ->orderBy('nama_jenis_cuti')
                    ->get()
                    ->map(function($item) {
                        return ['id' => $item->id, 'nama' => $item->nama_jenis_cuti];
                    });
            } catch (\Exception $e) {
                // If table doesn't exist, return empty collection
            }

            $jumlahCuti = collect([]);
            try {
                $jumlahCuti = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
                    ->distinct()
                    ->pluck('jumlah_cuti')
                    ->filter()
                    ->sort()
                    ->values();
            } catch (\Exception $e) {
                // If table doesn't exist, return empty collection
            }

            return response()->json([
                'success' => true,
                'filter_options' => [
                    'jenis_cuti' => $jenisCuti,
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

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil filter options: ' . $e->getMessage()
            ], 500);
        }
    }

    // Print cuti document
    public function printCutiDocument($id)
    {
        try {
            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            $dataCuti = SimpegCutiRecord::where('pegawai_id', $pegawai->id)
                ->with(['jenisCuti', 'pegawai.unitKerja'])
                ->find($id);

            if (!$dataCuti) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan cuti tidak ditemukan'
                ], 404);
            }

            if ($dataCuti->status_pengajuan !== 'disetujui') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya pengajuan cuti yang sudah disetujui yang dapat dicetak'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatDataCuti($dataCuti),
                'print_url' => url("/api/dosen/pengajuan-cuti-dosen/{$id}/print/generate-pdf"),
                'message' => 'Data pengajuan cuti siap untuk dicetak'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses print document: ' . $e->getMessage()
            ], 500);
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
            try {
                $unitKerja = SimpegUnitKerja::find($pegawai->unit_kerja_id);
                $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $pegawai->unit_kerja_id;
            } catch (\Exception $e) {
                $unitKerjaNama = 'Unit Kerja #' . $pegawai->unit_kerja_id;
            }
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

    // Helper: Format data cuti response
    protected function formatDataCuti($dataCuti, $includeActions = true)
    {
        // Handle null status_pengajuan - set default to draft
        $status = $dataCuti->status_pengajuan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);
        
        $canEdit = in_array($status, ['draft', 'ditolak']);
        $canSubmit = $status === 'draft';
        $canDelete = in_array($status, ['draft', 'ditolak']);
        $canPrint = $status === 'disetujui';
        
        $data = [
            'id' => $dataCuti->id,
            'no_urut_cuti' => $dataCuti->no_urut_cuti,
            'jenis_cuti_id' => $dataCuti->jenis_cuti_id,
            'jenis_cuti' => $dataCuti->jenisCuti ? $dataCuti->jenisCuti->nama_jenis_cuti : null,
            'tgl_mulai' => $dataCuti->tgl_mulai,
            'tgl_selesai' => $dataCuti->tgl_selesai,
            'jumlah_cuti' => $dataCuti->jumlah_cuti,
            'alasan_cuti' => $dataCuti->alasan_cuti,
            'alamat' => $dataCuti->alamat,
            'no_telp' => $dataCuti->no_telp,
            'status_pengajuan' => $status,
            'status_info' => $statusInfo,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'can_delete' => $canDelete,
            'can_print' => $canPrint,
            'timestamps' => [
                'tgl_diajukan' => $dataCuti->tgl_diajukan ?? null,
                'tgl_disetujui' => $dataCuti->tgl_disetujui ?? null,
                'tgl_ditolak' => $dataCuti->tgl_ditolak ?? null
            ],
            'dokumen' => $dataCuti->file_cuti ? [
                'nama_file' => $dataCuti->file_cuti,
                // ==================================================================
                // PERBAIKAN: Membuat URL dari disk 'public'
                // ==================================================================
                'url' => Storage::disk('public')->url('pegawai/cuti/'.$dataCuti->file_cuti)
            ] : null,
            'created_at' => $dataCuti->created_at,
            'updated_at' => $dataCuti->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            // Get URL prefix from request
            $request = request();
            $prefix = $request->segment(2) ?? 'dosen'; // fallback to 'dosen'
            
            $data['aksi'] = [
                'detail_url' => url("/api/{$prefix}/pengajuan-cuti-dosen/{$dataCuti->id}"),
                'update_url' => url("/api/{$prefix}/pengajuan-cuti-dosen/{$dataCuti->id}"),
                'delete_url' => url("/api/{$prefix}/pengajuan-cuti-dosen/{$dataCuti->id}"),
                'submit_url' => url("/api/{$prefix}/pengajuan-cuti-dosen/{$dataCuti->id}/submit"),
                'print_url' => url("/api/{$prefix}/pengajuan-cuti-dosen/{$dataCuti->id}/print"),
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
                    'color' => 'success',
                    'target' => '_blank'
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
