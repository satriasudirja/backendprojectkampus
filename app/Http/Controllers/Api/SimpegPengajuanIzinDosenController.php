<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPengajuanIzinDosen;
use App\Models\SimpegJenisIzin;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SimpegPengajuanIzinDosenController extends Controller
{
    // Get all data izin for logged in dosen
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
            // Hapus whereHas yang bermasalah dan gunakan validasi sederhana
            $query = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)
                ->with('jenisIzin');

            // Additional validation - hanya untuk pegawai yang valid


            // Filter by search
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('alasan_izin', 'like', '%'.$search.'%')
                      ->orWhere('no_izin', 'like', '%'.$search.'%')
                      ->orWhere('keterangan', 'like', '%'.$search.'%');
                });
            }

            // Filter by status pengajuan
            if ($statusPengajuan && $statusPengajuan != 'semua') {
                $query->where('status_pengajuan', $statusPengajuan);
            }

            // Additional filters
            if ($request->filled('jenis_izin_id')) {
                $query->where('jenis_izin_id', $request->jenis_izin_id);
            }
            if ($request->filled('tgl_mulai')) {
                $query->whereDate('tgl_mulai', $request->tgl_mulai);
            }
            if ($request->filled('tgl_selesai')) {
                $query->whereDate('tgl_selesai', $request->tgl_selesai);
            }
            if ($request->filled('jumlah_izin')) {
                $query->where('jumlah_izin', $request->jumlah_izin);
            }

            // Execute query dengan pagination
            $dataIzin = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Transform the collection to include formatted data with action URLs
            $dataIzin->getCollection()->transform(function ($item) {
                return $this->formatDataIzin($item, true);
            });

            // Get jenis izin options with error handling
            $jenisIzinOptions = collect([]);
            try {
                $jenisIzinOptions = SimpegJenisIzin::select('id', 'nama_jenis_izin', 'kode_jenis_izin')
                    ->orderBy('nama_jenis_izin')
                    ->get()
                    ->map(function($item) {
                        return [
                            'id' => $item->id,
                            'kode' => $item->kode_jenis_izin,
                            'nama' => $item->nama_jenis_izin
                        ];
                    });
            } catch (\Exception $e) {
                // If jenis izin table doesn't exist, return empty collection
                $jenisIzinOptions = collect([]);
            }

            return response()->json([
                'success' => true,
                'data' => $dataIzin,
                'empty_data' => $dataIzin->isEmpty(),
                'pegawai_info' => $this->formatPegawaiInfo($pegawai),
                'jenis_izin' => $jenisIzinOptions,
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
                    ['field' => 'no_izin', 'label' => 'No. Izin', 'sortable' => true, 'sortable_field' => 'no_izin'],
                    ['field' => 'jenis_izin', 'label' => 'Jenis Izin', 'sortable' => true, 'sortable_field' => 'jenis_izin_id'],
                    ['field' => 'tgl_mulai', 'label' => 'Tanggal Mulai', 'sortable' => true, 'sortable_field' => 'tgl_mulai'],
                    ['field' => 'tgl_selesai', 'label' => 'Tanggal Selesai', 'sortable' => true, 'sortable_field' => 'tgl_selesai'],
                    ['field' => 'jumlah_izin', 'label' => 'Jumlah Hari', 'sortable' => true, 'sortable_field' => 'jumlah_izin'],
                    ['field' => 'alasan_izin', 'label' => 'Alasan', 'sortable' => true, 'sortable_field' => 'alasan_izin'],
                    ['field' => 'status_pengajuan', 'label' => 'Status Pengajuan', 'sortable' => true, 'sortable_field' => 'status_pengajuan'],
                    ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
                ],
                'table_rows_options' => [10, 25, 50, 100],
                'tambah_data_url' => url("/api/dosen/pengajuan-izin-dosen"),
                'batch_actions' => [
                    'delete' => [
                        'url' => url("/api/dosen/pengajuan-izin-dosen/batch/delete"),
                        'method' => 'DELETE',
                        'label' => 'Hapus Terpilih',
                        'icon' => 'trash',
                        'color' => 'danger',
                        'confirm' => true
                    ],
                    'submit' => [
                        'url' => url("/api/dosen/pengajuan-izin-dosen/batch/submit"),
                        'method' => 'PATCH',
                        'label' => 'Ajukan Terpilih',
                        'icon' => 'paper-plane',
                        'color' => 'primary',
                        'confirm' => true
                    ],
                    'update_status' => [
                        'url' => url("/api/dosen/pengajuan-izin-dosen/batch/status"),
                        'method' => 'PATCH',
                        'label' => 'Update Status Terpilih',
                        'icon' => 'check-circle',
                        'color' => 'info'
                    ]
                ],
                'pagination' => [
                    'current_page' => $dataIzin->currentPage(),
                    'per_page' => $dataIzin->perPage(),
                    'total' => $dataIzin->total(),
                    'last_page' => $dataIzin->lastPage(),
                    'from' => $dataIzin->firstItem(),
                    'to' => $dataIzin->lastItem()
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


    private function parseMultipartFormData()
{
    $rawData = file_get_contents('php://input');
    
    if (empty($rawData)) {
        return ['fields' => [], 'files' => []];
    }
    
    // Extract boundary
    $boundary = substr($rawData, 0, strpos($rawData, "\r\n"));
    
    if (empty($boundary)) {
        return ['fields' => [], 'files' => []];
    }
    
    // Split data by boundary
    $parts = array_slice(explode($boundary, $rawData), 1);
    $fields = [];
    $files = [];
    
    foreach ($parts as $part) {
        if ($part == "--\r\n" || $part == "--") break;
        
        $part = ltrim($part, "\r\n");
        if (empty($part)) continue;
        
        // Split headers and body
        if (strpos($part, "\r\n\r\n") === false) continue;
        
        list($rawHeaders, $body) = explode("\r\n\r\n", $part, 2);
        $body = substr($body, 0, strlen($body) - 2); // Remove trailing \r\n
        
        // Parse headers
        $name = null;
        $filename = null;
        $contentType = null;
        
        foreach (explode("\r\n", $rawHeaders) as $header) {
            if (stripos($header, 'Content-Disposition:') === 0) {
                // Extract name
                if (preg_match('/name="([^"]*)"/', $header, $matches)) {
                    $name = $matches[1];
                }
                // Extract filename
                if (preg_match('/filename="([^"]*)"/', $header, $matches)) {
                    $filename = $matches[1];
                }
            } elseif (stripos($header, 'Content-Type:') === 0) {
                $contentType = trim(substr($header, 13));
            }
        }
        
        if ($name) {
            if (!empty($filename)) {
                // This is a file
                $files[$name] = [
                    'name' => $filename,
                    'type' => $contentType,
                    'tmp_name' => null, // We'll create temp file
                    'error' => empty($filename) ? UPLOAD_ERR_NO_FILE : UPLOAD_ERR_OK,
                    'size' => strlen($body),
                    'content' => $body
                ];
            } else {
                // This is a regular field
                $fields[$name] = $body;
            }
        }
    }
    
    return ['fields' => $fields, 'files' => $files];
}

/**
 * Create temporary file from multipart file data
 */
private function createTempFileFromMultipart($fileData)
{
    if (empty($fileData['content']) || empty($fileData['name'])) {
        return null;
    }
    
    // Create temporary file
    $tempFile = tempnam(sys_get_temp_dir(), 'multipart_');
    file_put_contents($tempFile, $fileData['content']);
    
    // Create UploadedFile instance
    return new \Illuminate\Http\UploadedFile(
        $tempFile,
        $fileData['name'],
        $fileData['type'],
        $fileData['error'],
        true // test mode
    );
}

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
            $updatedCount = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)
                ->whereNull('status_pengajuan')
                ->update([
                    'status_pengajuan' => 'draft'
                ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil memperbaiki {$updatedCount} data pengajuan izin",
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbaiki data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Get detail data izin
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

            $dataIzin = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)
                ->with('jenisIzin')
                ->find($id);

            if (!$dataIzin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan izin tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'pegawai' => $this->formatPegawaiInfo($pegawai->load([
                    'unitKerja', 'statusAktif', 'jabatanFungsional',
                    'jabatanStruktural.jenisJabatanStruktural',
                    'dataPendidikanFormal.jenjangPendidikan'
                ])),
                'data' => $this->formatDataIzin($dataIzin)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    // Generate nomor izin
    private function generateNoIzin($jenisIzinId)
    {
        try {
            $jenisIzin = SimpegJenisIzin::find($jenisIzinId);
            if (!$jenisIzin) {
                return 'IZ/TEMP/' . time();
            }

            $today = now();
            $year = $today->format('Y');
            $month = $today->format('m');
            
            // Format for izin number
            $format = 'IZ/{kode}/{no}/{bulan}/{tahun}';
            
            // Count existing records for this type of permission in the current year
            $count = SimpegPengajuanIzinDosen::where('jenis_izin_id', $jenisIzinId)
                ->whereYear('created_at', $year)
                ->count() + 1;
            
            // Format the sequence number with leading zeros
            $sequenceNumber = str_pad($count, 3, '0', STR_PAD_LEFT);
            
            // Replace placeholders in the format
            $noIzin = str_replace(
                ['{no}', '{kode}', '{tahun}', '{bulan}'],
                [$sequenceNumber, $jenisIzin->kode_jenis_izin ?? 'IZ', $year, $month],
                $format
            );
            
            return $noIzin;
        } catch (\Exception $e) {
            // Fallback jika ada error
            return 'IZ/TEMP/' . time();
        }
    }

    // Calculate jumlah izin (days between two dates)
    private function calculateJumlahIzin($tglMulai, $tglSelesai)
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

    // Store new data izin dengan draft/submit mode
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
            'jenis_izin_id' => 'required|uuid|exists:simpeg_jenis_izin,id',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
            'jumlah_izin' => 'nullable|integer|min:1',
            'alasan_izin' => 'required|string|max:255',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify jenis_izin exists (double-check)
        if (!SimpegJenisIzin::where('id', $request->jenis_izin_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis izin tidak valid'
            ], 422);
        }

        $data = $request->except(['file_pendukung', 'submit_type']);
        $data['pegawai_id'] = $pegawai->id;
        $data['no_izin'] = $this->generateNoIzin($request->jenis_izin_id);

        // Calculate jumlah_izin if not provided
        if (!$request->jumlah_izin) {
            $data['jumlah_izin'] = $this->calculateJumlahIzin($request->tgl_mulai, $request->tgl_selesai);
        }

        // Set status
        $submitType = $request->input('submit_type', 'draft');
        $data['status_pengajuan'] = ($submitType === 'submit') ? 'diajukan' : 'draft';
        $message = ($submitType === 'submit') 
            ? 'Pengajuan izin berhasil diajukan untuk persetujuan' 
            : 'Pengajuan izin berhasil disimpan sebagai draft';

        if ($submitType === 'submit') {
            $data['tgl_diajukan'] = now();
        }

        // Handle file upload
        if ($request->hasFile('file_pendukung')) {
            $file = $request->file('file_pendukung');
            $fileName = 'izin_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/izin', $fileName);
            $data['file_pendukung'] = $fileName;
        }

        $dataIzin = SimpegPengajuanIzinDosen::create($data);

        // Commit transaction
        DB::commit();

        // Log activity
        if (class_exists('App\Services\ActivityLogger')) {
            ActivityLogger::log('create', $dataIzin, $dataIzin->toArray());
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatDataIzin($dataIzin->load('jenisIzin')),
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

// ENHANCED UPDATE METHOD - Debug Request Data Issue
public function update(Request $request, $id)
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

        $dataIzin = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)->find($id);

        if (!$dataIzin) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan izin tidak ditemukan'
            ], 404);
        }

        if (!in_array($dataIzin->status_pengajuan, ['draft', 'ditolak'])) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak dapat diedit karena sudah diajukan atau disetujui'
            ], 422);
        }

        // ENHANCED: Handle both JSON and multipart data
        $requestData = [];
        $uploadedFiles = [];
        $dataSource = 'unknown';
        
        if ($request->isJson() || !empty($request->all())) {
            // Standard Laravel request handling (JSON or regular form)
            $requestData = $request->all();
            $uploadedFiles = $request->allFiles();
            $dataSource = $request->isJson() ? 'json' : 'form';
        } else if (strpos($request->header('Content-Type'), 'multipart/form-data') !== false) {
            // Manual multipart parsing for PUT requests
            $parsed = $this->parseMultipartFormData();
            $requestData = $parsed['fields'];
            
            // Handle files
            foreach ($parsed['files'] as $fieldName => $fileData) {
                if ($fileData['error'] === UPLOAD_ERR_OK && !empty($fileData['name'])) {
                    $uploadedFiles[$fieldName] = $this->createTempFileFromMultipart($fileData);
                }
            }
            $dataSource = 'multipart_manual';
        }

        // ENHANCED DEBUG
        \Log::info('=== ENHANCED DEBUG WITH MULTIPART PARSER ===');
        \Log::info('Content Type:', [$request->header('Content-Type')]);
        \Log::info('Request Method:', [$request->method()]);
        \Log::info('Data Source:', [$dataSource]);
        \Log::info('Is JSON:', [$request->isJson()]);
        \Log::info('Laravel Request All:', $request->all());
        \Log::info('Laravel Request Files:', array_keys($request->allFiles()));
        \Log::info('Parsed Request Data:', $requestData);
        \Log::info('Parsed Files:', array_keys($uploadedFiles));
        \Log::info('Before Update Data:', $dataIzin->toArray());

        if (empty($requestData)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data untuk diupdate',
                'debug_info' => [
                    'content_type' => $request->header('Content-Type'),
                    'data_source' => $dataSource,
                    'is_json' => $request->isJson(),
                    'laravel_request_all' => $request->all(),
                    'parsed_request_data' => $requestData,
                    'raw_input_length' => strlen($request->getContent())
                ]
            ], 400);
        }

        // Merge uploaded files into request for validation
        $validationData = array_merge($requestData, [
            'file_pendukung' => $uploadedFiles['file_pendukung'] ?? null
        ]);

        // Validation
        $validator = Validator::make($validationData, [
            'jenis_izin_id' => 'sometimes|uuid|exists:simpeg_jenis_izin,id',
            'tgl_mulai' => 'sometimes|date',
            'tgl_selesai' => 'sometimes|date|after_or_equal:tgl_mulai',
            'jumlah_izin' => 'sometimes|integer|min:1',
            'alasan_izin' => 'sometimes|string|max:500',
            'file_pendukung' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'sometimes|in:draft,submit',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'debug_info' => [
                    'validation_data' => array_keys($validationData),
                    'parsed_data' => $requestData
                ]
            ], 422);
        }

        // Prepare update data (exclude files and submit_type)
        $updateData = collect($requestData)->except(['file_pendukung', 'submit_type'])->toArray();
        
        // Cast data types
        if (isset($updateData['jenis_izin_id'])) {
    $updateData['jenis_izin_id'] = (int) $updateData['jenis_izin_id'];
}
if (isset($updateData['jumlah_izin'])) {
    $updateData['jumlah_izin'] = (int) $updateData['jumlah_izin'];
}

// FIX TIMEZONE - TAMBAHKAN CODE INI
if (isset($updateData['tgl_mulai'])) {
    $updateData['tgl_mulai'] = \Carbon\Carbon::parse($updateData['tgl_mulai'])
        ->setTimezone('Asia/Jakarta')
        ->format('Y-m-d');
}
if (isset($updateData['tgl_selesai'])) {
    $updateData['tgl_selesai'] = \Carbon\Carbon::parse($updateData['tgl_selesai'])
        ->setTimezone('Asia/Jakarta')
        ->format('Y-m-d');
}
// END FIX TIMEZONE

// Generate no_izin if jenis_izin_id changed
if (isset($updateData['jenis_izin_id']) && $updateData['jenis_izin_id'] != $dataIzin->jenis_izin_id) {
    $updateData['no_izin'] = $this->generateNoIzin($updateData['jenis_izin_id']);
}
        // Handle submit_type
        $message = 'Pengajuan izin berhasil diperbarui';
        $submitType = $requestData['submit_type'] ?? null;
        if ($submitType === 'submit') {
            $updateData['status_pengajuan'] = 'diajukan';
            $updateData['tgl_diajukan'] = now();
            $message = 'Pengajuan izin berhasil diperbarui dan diajukan untuk persetujuan';
        }

        // Handle file upload
        $fileUploaded = false;
        if (isset($uploadedFiles['file_pendukung']) && $uploadedFiles['file_pendukung']) {
            $file = $uploadedFiles['file_pendukung'];
            
            // Delete old file
            if ($dataIzin->file_pendukung) {
                Storage::delete('public/pegawai/izin/'.$dataIzin->file_pendukung);
            }

            // Store new file
            $fileName = 'izin_'.time().'_'.$pegawai->id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/izin', $fileName);
            $updateData['file_pendukung'] = $fileName;
            $fileUploaded = true;
        }

        \Log::info('Final Update Data:', $updateData);
        \Log::info('File Uploaded:', [$fileUploaded]);

        // Perform update
        $updated = $dataIzin->update($updateData);

        if (!$updated) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan update data'
            ], 500);
        }

        // Refresh data
        $dataIzin = $dataIzin->fresh(['jenisIzin']);
        
        \Log::info('After Update:', $dataIzin->toArray());

        DB::commit();

        // Clean up temporary files
        foreach ($uploadedFiles as $file) {
            if ($file && method_exists($file, 'getRealPath')) {
                $tempPath = $file->getRealPath();
                if (file_exists($tempPath) && strpos($tempPath, sys_get_temp_dir()) === 0) {
                    @unlink($tempPath);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatDataIzin($dataIzin),
            'message' => $message,
            'debug_info' => [
                'table_used' => $dataIzin->getTable(),
                'updated_fields' => array_keys($updateData),
                'total_fields' => count($updateData),
                'data_source' => $dataSource,
                'file_uploaded' => $fileUploaded,
                'request_method' => $request->method(),
                'content_type' => $request->header('Content-Type')
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('UPDATE ERROR:', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Gagal update data: ' . $e->getMessage(),
            'debug_info' => config('app.debug') ? [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ] : null
        ], 500);
    }
}

    // Delete data izin
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

            $dataIzin = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)
                ->find($id);

            if (!$dataIzin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan izin tidak ditemukan'
                ], 404);
            }

            // Delete file if exists
            if ($dataIzin->file_pendukung) {
                Storage::delete('public/pegawai/izin/'.$dataIzin->file_pendukung);
            }

            $oldData = $dataIzin->toArray();
            $dataIzin->delete();

            // Log activity if ActivityLogger exists
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('delete', $dataIzin, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data pengajuan izin berhasil dihapus'
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

            $dataIzin = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)
                ->where('status_pengajuan', 'draft')
                ->find($id);

            if (!$dataIzin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan izin draft tidak ditemukan atau sudah diajukan'
                ], 404);
            }

            $oldData = $dataIzin->getOriginal();
            
            $dataIzin->update([
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => now()
            ]);

            // Log activity if ActivityLogger exists
            if (class_exists('App\Services\ActivityLogger')) {
                ActivityLogger::log('update', $dataIzin, $oldData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin berhasil diajukan untuk persetujuan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Batch delete data izin
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

            $dataIzinList = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)
                ->whereIn('id', $request->ids)
                ->get();

            if ($dataIzinList->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan izin tidak ditemukan atau tidak memiliki akses'
                ], 404);
            }

            $deletedCount = 0;
            $errors = [];

            foreach ($dataIzinList as $dataIzin) {
                try {
                    // Delete file if exists
                    if ($dataIzin->file_pendukung) {
                        Storage::delete('public/pegawai/izin/'.$dataIzin->file_pendukung);
                    }

                    $oldData = $dataIzin->toArray();
                    $dataIzin->delete();
                    
                    // Log activity if ActivityLogger exists
                    if (class_exists('App\Services\ActivityLogger')) {
                        ActivityLogger::log('delete', $dataIzin, $oldData);
                    }
                    $deletedCount++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'id' => $dataIzin->id,
                        'no_izin' => $dataIzin->no_izin,
                        'error' => 'Gagal menghapus: ' . $e->getMessage()
                    ];
                }
            }

            if ($deletedCount == count($request->ids)) {
                return response()->json([
                    'success' => true,
                    'message' => "Berhasil menghapus {$deletedCount} data pengajuan izin",
                    'deleted_count' => $deletedCount
                ]);
            } else {
                return response()->json([
                    'success' => $deletedCount > 0,
                    'message' => "Berhasil menghapus {$deletedCount} dari " . count($request->ids) . " data pengajuan izin",
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

            $updatedCount = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)
                ->where('status_pengajuan', 'draft')
                ->whereIn('id', $request->ids)
                ->update([
                    'status_pengajuan' => 'diajukan',
                    'tgl_diajukan' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil mengajukan {$updatedCount} data pengajuan izin untuk persetujuan",
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

            $updatedCount = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)
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

        // Basic statistics
        $statistics = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)
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

        // BETTER APPROACH: Gunakan Eloquent relationship
        $jenisStat = SimpegPengajuanIzinDosen::with('jenisIzin')
            ->where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'disetujui')
            ->whereYear('tgl_mulai', date('Y'))
            ->get()
            ->groupBy(function($item) {
                return $item->jenisIzin ? $item->jenisIzin->nama_jenis_izin : 'Unknown';
            })
            ->map(function($group, $jenisNama) {
                return [
                    'nama_jenis_izin' => $jenisNama,
                    'total' => $group->count(),
                    'jumlah_hari' => $group->sum('jumlah_izin')
                ];
            })
            ->values();

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

            $jenisIzin = collect([]);
            try {
                $jenisIzin = SimpegJenisIzin::select('id', 'nama_jenis_izin')
                    ->orderBy('nama_jenis_izin')
                    ->get()
                    ->map(function($item) {
                        return ['id' => $item->id, 'nama' => $item->nama_jenis_izin];
                    });
            } catch (\Exception $e) {
                // If table doesn't exist, return empty collection
            }

            $jumlahIzin = collect([]);
            try {
                $jumlahIzin = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)
                    ->distinct()
                    ->pluck('jumlah_izin')
                    ->filter()
                    ->sort()
                    ->values();
            } catch (\Exception $e) {
                // If table doesn't exist, return empty collection
            }

            return response()->json([
                'success' => true,
                'filter_options' => [
                    'jenis_izin' => $jenisIzin,
                    'jumlah_izin' => $jumlahIzin,
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus data pengajuan izin ini?',
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
                        'confirm_message' => 'Apakah Anda yakin ingin menghapus semua data pengajuan izin yang dipilih?'
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

    // Print izin document
    public function printIzinDocument($id)
    {
        try {
            $pegawai = Auth::user()->pegawai;

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            $dataIzin = SimpegPengajuanIzinDosen::where('pegawai_id', $pegawai->id)
                ->with(['jenisIzin', 'pegawai.unitKerja'])
                ->find($id);

            if (!$dataIzin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan izin tidak ditemukan'
                ], 404);
            }

            if ($dataIzin->status_pengajuan !== 'disetujui') {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya pengajuan izin yang sudah disetujui yang dapat dicetak'
                ], 422);
            }

            // In a real implementation, we'd generate a PDF here
            // For now, just return the data needed for printing

            return response()->json([
                'success' => true,
                'data' => $this->formatDataIzin($dataIzin),
                'print_url' => url("/api/dosen/pengajuan-izin-dosen/{$id}/print/generate-pdf"),
                'message' => 'Data pengajuan izin siap untuk dicetak'
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

    // Helper: Format data izin response
    protected function formatDataIzin($dataIzin, $includeActions = true)
{
    // Handle null status_pengajuan - set default to draft
    $status = $dataIzin->status_pengajuan ?? 'draft';
    $statusInfo = $this->getStatusInfo($status);
    
    $canEdit = in_array($status, ['draft', 'ditolak']);
    $canSubmit = $status === 'draft';
    $canDelete = in_array($status, ['draft', 'ditolak']);
    $canPrint = $status === 'disetujui';
    
    // FIX TIMEZONE - Format tanggal dengan benar
    $tglMulai = $dataIzin->tgl_mulai;
    $tglSelesai = $dataIzin->tgl_selesai;
    
    // Jika masih dalam format datetime, convert ke date saja
    if ($tglMulai instanceof \Carbon\Carbon || $tglMulai instanceof \DateTime) {
        $tglMulai = $tglMulai->format('Y-m-d');
    }
    if ($tglSelesai instanceof \Carbon\Carbon || $tglSelesai instanceof \DateTime) {
        $tglSelesai = $tglSelesai->format('Y-m-d');
    }
    
    // Jika masih string dengan timezone, parse dan format ulang
    if (is_string($tglMulai) && strpos($tglMulai, 'T') !== false) {
        $tglMulai = \Carbon\Carbon::parse($tglMulai)->format('Y-m-d');
    }
    if (is_string($tglSelesai) && strpos($tglSelesai, 'T') !== false) {
        $tglSelesai = \Carbon\Carbon::parse($tglSelesai)->format('Y-m-d');
    }
    
    $data = [
        'id' => $dataIzin->id,
        'no_izin' => $dataIzin->no_izin,
        'jenis_izin_id' => $dataIzin->jenis_izin_id,
        'jenis_izin' => $dataIzin->jenisIzin ? $dataIzin->jenisIzin->nama_jenis_izin : null,
        'tgl_mulai' => $tglMulai,        // ← FIX: Gunakan variable yang sudah di-format
        'tgl_selesai' => $tglSelesai,    // ← FIX: Gunakan variable yang sudah di-format
        'jumlah_izin' => $dataIzin->jumlah_izin,
        'alasan_izin' => $dataIzin->alasan_izin,
        'status_pengajuan' => $status,
        'status_info' => $statusInfo,
        'keterangan' => $dataIzin->keterangan,
        'can_edit' => $canEdit,
        'can_submit' => $canSubmit,
        'can_delete' => $canDelete,
        'can_print' => $canPrint,
        'timestamps' => [
            'tgl_diajukan' => $dataIzin->tgl_diajukan,
            'tgl_disetujui' => $dataIzin->tgl_disetujui,
            'tgl_ditolak' => $dataIzin->tgl_ditolak
        ],
        'dokumen' => $dataIzin->file_pendukung ? [
            'nama_file' => $dataIzin->file_pendukung,
            'url' => url('storage/pegawai/izin/'.$dataIzin->file_pendukung)
        ] : null,
        'created_at' => $dataIzin->created_at,
        'updated_at' => $dataIzin->updated_at
    ];

    // Add action URLs if requested
    if ($includeActions) {
        // Get URL prefix from request
        $request = request();
        $prefix = $request->segment(2) ?? 'dosen'; // fallback to 'dosen'
        
        $data['aksi'] = [
            'detail_url' => url("/api/{$prefix}/pengajuan-izin-dosen/{$dataIzin->id}"),
            'update_url' => url("/api/{$prefix}/pengajuan-izin-dosen/{$dataIzin->id}"),
            'delete_url' => url("/api/{$prefix}/pengajuan-izin-dosen/{$dataIzin->id}"),
            'submit_url' => url("/api/{$prefix}/pengajuan-izin-dosen/{$dataIzin->id}/submit"),
            'print_url' => url("/api/{$prefix}/pengajuan-izin-dosen/{$dataIzin->id}/print"),
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
                'confirm_message' => 'Apakah Anda yakin ingin menghapus pengajuan izin ini?'
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
                'confirm_message' => 'Apakah Anda yakin ingin mengajukan izin ini untuk persetujuan?'
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