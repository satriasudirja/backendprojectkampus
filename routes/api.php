<?php

use App\Models\JenisSertifikasi;
use App\Models\SimpegDaftarCuti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SimpegSukuController;
use App\Http\Controllers\Api\SimpegUserRoleController;
use App\Http\Controllers\Api\SimpegStatusPernikahanController;
use App\Http\Controllers\Api\SimpegStatusAktifController;
use App\Http\Controllers\Api\SimpegJabatanAkademikController;
use App\Http\Controllers\Api\SimpegBahasaController;
use App\Http\Controllers\Api\SimpegDaftarCutiController;
use App\Http\Controllers\Api\SimpegDaftarJenisLuaranController;
use App\Http\Controllers\Api\DaftarJenisPkmController;
use App\Http\Controllers\Api\SimpegDaftarJenisSkController;
use App\Http\Controllers\Api\SimpegDaftarJenisTestController;
use App\Http\Controllers\Api\SimpegOutputPenelitianController;
use App\Http\Controllers\Api\SimpegJenisJabatanStrukturalController;
use App\Http\Controllers\Api\SimpegJabatanStrukturalController;
use App\Http\Controllers\Api\SimpegMasterPangkatController;
use App\Http\Controllers\Api\SimpegEselonController;
use App\Http\Controllers\Api\SimpegUnivLuarController;
use App\Http\Controllers\Api\SimpegDataRiwayatPekerjaanController;
use App\Http\Controllers\Api\SimpegJamKerjaController;
use App\Http\Controllers\Api\SimpegMasterJenisSertifikasiController;
use App\Http\Controllers\Api\SimpegDataSertifikasiController;
use App\Http\Controllers\Api\UnitKerjaController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\SimpegBeritaController;
use App\Http\Controllers\Api\PegawaiController;


use App\Http\Controllers\Api\SimpegUnitKerjaController;
use App\Http\Controllers\Api\SimpegRiwayatPendidikanController;
use App\Http\Controllers\Api\SimpegKategoriSertifikasiController;

use App\Http\Controllers\Api\SimpegJenispelanggaranController;
use App\Http\Controllers\Api\SimpegJenisPenghargaaniController;
use App\Http\Controllers\Api\SimpegJenisPublikasiController;
use App\Http\Controllers\Api\SimpegJenisKenaikanPangkatController;
use App\Http\Controllers\Api\SimpegJenisJenisIzinController;
use App\Http\Controllers\Api\SimpegJenisHariController;
use App\Http\Controllers\Api\SimpegGajiDetailController;
use App\Http\Controllers\Api\SimpegGajiKomponenController;
use App\Http\Controllers\Api\SimpegGajiSlipController;
use App\Http\Controllers\Api\SimpegGajiPeriodeController;
use App\Http\Controllers\Api\SimpegGajiTunjanganKhususController;
use App\Http\Controllers\Api\SimpegGajiLemburController;
use App\Http\Controllers\Api\SimpegRiwayatPangkatController;

use App\Http\Controllers\Api\SimpegDataAnakController;
use App\Http\Controllers\Api\SimpegDataPasanganController;
use App\Http\Controllers\Api\SimpegDataOrangTuaController;




Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
Route::get('captcha', [AuthController::class, 'generateCaptcha']);
    
    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('menu', [AuthController::class, 'getMenu']);
    });
});

Route::middleware('auth:api')->group(function () {
    Route::get('profile', [ProfileController::class, 'index']);
    
    // Admin Routes
    Route::middleware('role:Admin')->prefix('admin')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Admin Dashboard']);
           
          
        });
        Route::get('pegawai/info-pendidikan/{pegawaiId}', [SimpegRiwayatPendidikanController::class, 'getPegawaiWithPendidikan']);
Route::get('pegawai/search', [SimpegRiwayatPendidikanController::class, 'searchPegawai']);
        //dashboard nav pegawai
         Route::get('pegawai', [PegawaiController::class, 'index']);
    Route::get('pegawai/{id}', [PegawaiController::class, 'show']);
    Route::post('pegawai', [PegawaiController::class, 'store']);
    Route::put('pegawai/{id}', [PegawaiController::class, 'update']);
    Route::delete('pegawai/{id}', [PegawaiController::class, 'destroy']);
    
    // Batch actions
    Route::post('pegawai/update-status', [PegawaiController::class, 'updateStatus']);
    Route::post('pegawai/batch-delete', [PegawaiController::class, 'destroy']);
    
    // History routes
    Route::get('pegawai/riwayat-unit-kerja/{id}', [PegawaiController::class, 'riwayatUnitKerja']);
   
    // Route::get('pegawai/search', [SimpegRiwayatPendidikanController::class, 'searchPegawai']);
    Route::get('pegawai/riwayat-unit-kerja/{id}', [PegawaiController::class, 'riwayatUnitKerja']);
    Route::post('pegawai/update-status', [PegawaiController::class, 'updateStatus']);
    Route::post('pegawai/batch-delete', [PegawaiController::class, 'destroy']);
    
    // THEN define the pendidikan routes, also in specific-to-generic order
    Route::get('pegawai/riwayat-pendidikan/all', [SimpegRiwayatPendidikanController::class, 'index']);
    Route::get('pegawai/riwayat-pendidikan/detail/{id}', [SimpegRiwayatPendidikanController::class, 'show']);
    Route::put('pegawai/riwayat-pendidikan/batch/update-status', [SimpegRiwayatPendidikanController::class, 'batchUpdateStatus']);
    Route::delete('pegawai/riwayat-pendidikan/batch/delete', [SimpegRiwayatPendidikanController::class, 'batchDelete']);
    Route::put('pegawai/riwayat-pendidikan/{id}/status', [SimpegRiwayatPendidikanController::class, 'updateStatusPengajuan']);
    Route::get('pegawai/riwayat-pendidikan/{pegawaiId}', [SimpegRiwayatPendidikanController::class, 'getByPegawai']);
    Route::post('pegawai/riwayat-pendidikan', [SimpegRiwayatPendidikanController::class, 'store']);
    Route::put('pegawai/riwayat-pendidikan/{id}', [SimpegRiwayatPendidikanController::class, 'update']);
    Route::delete('pegawai/riwayat-pendidikan/{id}', [SimpegRiwayatPendidikanController::class, 'destroy']);
    
    // FINALLY define the most generic routes with route parameters
    Route::get('pegawai', [PegawaiController::class, 'index']);
    Route::get('pegawai/{id}', [PegawaiController::class, 'show']);
    Route::post('pegawai', [PegawaiController::class, 'store']);
    Route::put('pegawai/{id}', [PegawaiController::class, 'update']);
    Route::post('pegawai/destroy', [PegawaiController::class, 'destroy']);
    
    // Rest of your admin routes...






    // Route::get('pegawai/riwayat-pangkat/{id}', [PegawaiController::class, 'riwayatPangkat']);
    Route::get('pegawai/riwayat-pangkat/all', [SimpegRiwayatPangkatController::class, 'index']);
    Route::get('pegawai/riwayat-pangkat/detail/{id}', [SimpegRiwayatPangkatController::class, 'show']);
    Route::put('pegawai/riwayat-pangkat/batch/update-status', [SimpegRiwayatPangkatController::class, 'batchUpdateStatus']);
    Route::delete('pegawai/riwayat-pangkat/batch/delete', [SimpegRiwayatPangkatController::class, 'batchDelete']);
    Route::put('pegawai/riwayat-pangkat/{id}/status', [SimpegRiwayatPangkatController::class, 'updateStatusPengajuan']);
    Route::get('pegawai/riwayat-pangkat/{pegawaiId}', [SimpegRiwayatPangkatController::class, 'getByPegawai']);
    Route::post('pegawai/riwayat-pangkat', [SimpegRiwayatPangkatController::class, 'store']);
    Route::put('pegawai/riwayat-pangkat/{id}', [SimpegRiwayatPangkatController::class, 'update']);
    Route::delete('pegawai/riwayat-pangkat/{id}', [SimpegRiwayatPangkatController::class, 'destroy']);  
    Route::get('pegawai/riwayat-fungsional/{id}', [PegawaiController::class, 'riwayatFungsional']);
    Route::get('pegawai/riwayat-jenjang-fungsional/{id}', [PegawaiController::class, 'riwayatJenjangFungsional']);
    Route::get('pegawai/riwayat-jabatan-struktural/{id}', [PegawaiController::class, 'riwayatJabatanStruktural']);
    Route::get('pegawai/riwayat-hubungan-kerja/{id}', [PegawaiController::class, 'riwayatHubunganKerja']);
    Route::get('pegawai/rekap-kehadiran/{id}', [PegawaiController::class, 'rekapKehadiran']);

        /////////////////////////////////////////////////






        Route::apiResource('berita', SimpegBeritaController::class);
        Route::apiResource('suku', SimpegSukuController::class);
        Route::apiResource('role', SimpegUserRoleController::class);


        Route::get('unit-kerja/dropdown', [SimpegUnitKerjaController::class, 'dropdown']);
        Route::apiResource('unit-kerja', SimpegUnitKerjaController::class);
        Route::apiResource('status-pernikahan', SimpegStatusPernikahanController::class);
        Route::apiResource('status-aktif', SimpegStatusAktifController::class);
        Route::apiResource('jabatan-akademik', SimpegJabatanAkademikController::class);
        Route::apiResource('bahasa', SimpegBahasaController::class);
        Route::apiResource('daftar-cuti', SimpegDaftarCutiController::class);
        Route::apiResource('jenis-luaran', SimpegDaftarJenisLuaranController::class);
        Route::apiResource('jenis-luaran', SimpegDaftarJenisLuaranController::class);
        Route::apiResource('jenis-pkm', DaftarJenisPkmController::class);
        Route::apiResource('jenis-sk', SimpegDaftarJenisSkController::class);
        Route::apiResource('jenis-test', SimpegDaftarJenisTestController::class);
        Route::apiResource('output-penelitian', SimpegOutputPenelitianController::class);
        Route::apiResource('jenis-jabatan-struktural', SimpegJenisJabatanStrukturalController::class);
        Route::apiResource('jabatan-struktural', SimpegJabatanStrukturalController::class);
        Route::apiResource('eselon', SimpegEselonController::class);
        Route::apiResource('univ-luar', SimpegUnivLuarController::class);
        Route::apiResource('master-pangkat', SimpegMasterPangkatController::class);
        Route::apiResource('data-riwayat-pekerjaan', SimpegDataRiwayatPekerjaanController::class);
        Route::apiResource('jam-kerja', SimpegJamKerjaController::class);
        Route::apiResource('master-jenis-sertifikasi', SimpegMasterJenisSertifikasiController::class);
        Route::apiResource('data-sertifikasi', SimpegDataSertifikasiController::class);

        Route::apiResource('jenis-penghargaan', SimpegJenisPenghargaanController::class);
        Route::apiResource('jenis-pelanggaran', SimpegJenisPelanggaranController::class);
        Route::apiResource('jenis-publikasi', SimpegJenisPublikasiController::class);
        Route::apiResource('jenis-kenaikan-pangkat', SimpegJenisKenaikanPangkatController::class);
        Route::apiResource('jenis-izin', SimpegJenisIzinController::class);
        Route::apiResource('gaji-detail', SimpegGajiDetailController::class);
        Route::apiResource('gaji-komponen', SimpegGajiKomponenController::class);
        Route::apiResource('gaji-tunjangan-khusus', SimpegGajiTunjanganKhususController::class);
        Route ::apiResource('gaji-slip', SimpegGajiSlipController::class);
        Route::apiResource('gaji-lembur', SimpegGajiLemburController::class);
        Route::apiResource('gaji-periode', SimpegGajiPeriodeController::class);

        Route::get('/dashboard', [AdminDashboardController::class, 'getDashboardData']);
        Route::get('/unit-kerja/dropdown', [UnitKerjaController::class, 'getUnitsDropdown']);
        Route::get('/news/{id}', [AdminDashboardController::class, 'getNewsDetail']);


        
    });
    
    // Dosen Routes
    Route::middleware('role:Dosen')->prefix('dosen')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Dosen Dashboard']);
        });
    Route::prefix('anak')->group(function () {
    Route::get('/', [SimpegDataAnakController::class, 'index']);
    Route::get('/{id}', [SimpegDataAnakController::class, 'show']);
    Route::post('/', [SimpegDataAnakController::class, 'store']);
    Route::put('/{id}', [SimpegDataAnakController::class, 'update']);
    Route::delete('/{id}', [SimpegDataAnakController::class, 'destroy']);
    // ======================================
    // STATUS PENGAJUAN ROUTES
    // ========================================
    Route::patch('/{id}/submit', [SimpegDataAnakController::class, 'submitDraft']);
    // ========================================
    // BATCH OPERATIONS ROUTES
    // ========================================
    Route::delete('/batch/delete', [SimpegDataAnakController::class, 'batchDelete']);
    Route::patch('/batch/submit', [SimpegDataAnakController::class, 'batchSubmitDrafts']);
    Route::patch('/batch/status', [SimpegDataAnakController::class, 'batchUpdateStatus']);
    // ========================================
    // CONFIGURATION & STATISTICS ROUTES
    // ========================================
    Route::get('/config/system', [SimpegDataAnakController::class, 'getSystemConfig']);
    Route::get('/statistics/status', [SimpegDataAnakController::class, 'getStatusStatistics']);
    Route::get('/filter-options', [SimpegDataAnakController::class, 'getFilterOptions']);
    Route::get('/available-actions', [SimpegDataAnakController::class, 'getAvailableActions']);
    });

    // Data Pasangan Routes
Route::prefix('pasangan')->middleware('auth:api')->group(function () {
    
    // ========================================
    // BASIC CRUD ROUTES
    // ========================================
    
    // GET /api/dosen/pasangan - List semua data pasangan pegawai yang login
    Route::get('/', [SimpegDataPasanganController::class, 'index']);
    
    // GET /api/dosen/pasangan/{id} - Detail data pasangan by ID
    Route::get('/{id}', [SimpegDataPasanganController::class, 'show']);
    
    // POST /api/dosen/pasangan - Create new data pasangan (dengan draft/submit mode)
    Route::post('/', [SimpegDataPasanganController::class, 'store']);
    
    // PUT /api/dosen/pasangan/{id} - Update data pasangan by ID
    Route::put('/{id}', [SimpegDataPasanganController::class, 'update']);
    
    // DELETE /api/dosen/pasangan/{id} - Delete data pasangan by ID
    Route::delete('/{id}', [SimpegDataPasanganController::class, 'destroy']);

    // ========================================
    // STATUS PENGAJUAN ROUTES
    // ========================================
    
    // PATCH /api/dosen/pasangan/{id}/submit - Submit draft ke diajukan
    Route::patch('/{id}/submit', [SimpegDataPasanganController::class, 'submitDraft']);

    // ========================================
    // BATCH OPERATIONS ROUTES
    // ========================================
    
    // DELETE /api/dosen/pasangan/batch/delete - Batch delete data pasangan
    Route::delete('/batch/delete', [SimpegDataPasanganController::class, 'batchDelete']);
    
    // PATCH /api/dosen/pasangan/batch/submit - Batch submit drafts
    Route::patch('/batch/submit', [SimpegDataPasanganController::class, 'batchSubmitDrafts']);
    
    // PATCH /api/dosen/pasangan/batch/status - Batch update status
    Route::patch('/batch/status', [SimpegDataPasanganController::class, 'batchUpdateStatus']);

    // ========================================
    // CONFIGURATION & STATISTICS ROUTES
    // ========================================
    
    // GET /api/dosen/pasangan/config/system - Get system configuration
    Route::get('/config/system', [SimpegDataPasanganController::class, 'getSystemConfig']);
    
    // GET /api/dosen/pasangan/statistics/status - Get status statistics
    Route::get('/statistics/status', [SimpegDataPasanganController::class, 'getStatusStatistics']);
    
    // GET /api/dosen/pasangan/filter-options - Get filter options
    Route::get('/filter-options', [SimpegDataPasanganController::class, 'getFilterOptions']);
    
    // GET /api/dosen/pasangan/available-actions - Get available actions
    Route::get('/available-actions', [SimpegDataPasanganController::class, 'getAvailableActions']);

    // ========================================
    // SEARCH & UTILITY ROUTES
    // ========================================
    
    // GET /api/dosen/pasangan/search/pegawai - Search existing pegawai for pasangan
    Route::get('/search/pegawai', [SimpegDataPasanganController::class, 'searchPegawai']);

    // ========================================
    // DATA MAINTENANCE ROUTES
    // ========================================
    
    // PATCH /api/dosen/pasangan/fix-existing - Fix existing data dengan status null
    Route::patch('/fix-existing', [SimpegDataPasanganController::class, 'fixExistingData']);
    
    // PATCH /api/dosen/pasangan/bulk-fix-existing - Bulk fix all existing data
    Route::patch('/bulk-fix-existing', [SimpegDataPasanganController::class, 'bulkFixExistingData']);
});

    // Data Orang Tua Routes
Route::prefix('orangtua')->middleware('auth:api')->group(function () {
    
    // ========================================
    // BASIC CRUD ROUTES
    // ========================================
    
    // GET /api/dosen/orangtua - List semua data orang tua pegawai yang login
    Route::get('/', [SimpegDataOrangTuaController::class, 'index']);
    
    // GET /api/dosen/orangtua/{id} - Detail data orang tua by ID
    Route::get('/{id}', [SimpegDataOrangTuaController::class, 'show']);
    
    // POST /api/dosen/orangtua - Create new data orang tua (dengan draft/submit mode)
    Route::post('/', [SimpegDataOrangTuaController::class, 'store']);
    
    // PUT /api/dosen/orangtua/{id} - Update data orang tua by ID
    Route::put('/{id}', [SimpegDataOrangTuaController::class, 'update']);
    
    // DELETE /api/dosen/orangtua/{id} - Delete data orang tua by ID
    Route::delete('/{id}', [SimpegDataOrangTuaController::class, 'destroy']);

    // ========================================
    // STATUS PENGAJUAN ROUTES
    // ========================================
    
    // PATCH /api/dosen/orangtua/{id}/submit - Submit draft ke diajukan
    Route::patch('/{id}/submit', [SimpegDataOrangTuaController::class, 'submitDraft']);

    // ========================================
    // BATCH OPERATIONS ROUTES
    // ========================================
    
    // DELETE /api/dosen/orangtua/batch/delete - Batch delete data orang tua
    Route::delete('/batch/delete', [SimpegDataOrangTuaController::class, 'batchDelete']);
    
    // PATCH /api/dosen/orangtua/batch/submit - Batch submit drafts
    Route::patch('/batch/submit', [SimpegDataOrangTuaController::class, 'batchSubmitDrafts']);
    
    // PATCH /api/dosen/orangtua/batch/status - Batch update status
    Route::patch('/batch/status', [SimpegDataOrangTuaController::class, 'batchUpdateStatus']);

    // ========================================
    // CONFIGURATION & STATISTICS ROUTES
    // ========================================
    
    // GET /api/dosen/orangtua/config/system - Get system configuration
    Route::get('/config/system', [SimpegDataOrangTuaController::class, 'getSystemConfig']);
    
    // GET /api/dosen/orangtua/statistics/status - Get status statistics
    Route::get('/statistics/status', [SimpegDataOrangTuaController::class, 'getStatusStatistics']);
    
    // GET /api/dosen/orangtua/filter-options - Get filter options
    Route::get('/filter-options', [SimpegDataOrangTuaController::class, 'getFilterOptions']);
    
    // GET /api/dosen/orangtua/available-actions - Get available actions
    Route::get('/available-actions', [SimpegDataOrangTuaController::class, 'getAvailableActions']);

    // ========================================
    // UTILITY ROUTES
    // ========================================
    
    // GET /api/dosen/orangtua/check-available-status - Check available parent status
    Route::get('/check-available-status', [SimpegDataOrangTuaController::class, 'checkAvailableStatus']);

    // ========================================
    // DATA MAINTENANCE ROUTES
    // ========================================
    
    // PATCH /api/dosen/orangtua/fix-existing - Fix existing data dengan status null
    Route::patch('/fix-existing', [SimpegDataOrangTuaController::class, 'fixExistingData']);
    
    // PATCH /api/dosen/orangtua/bulk-fix-existing - Bulk fix all existing data
    Route::patch('/bulk-fix-existing', [SimpegDataOrangTuaController::class, 'bulkFixExistingData']);
});

    });
    
    // Dosen Praktisi Routes
    Route::middleware('role:Dosen Praktisi/Industri')->prefix('dosen-praktisi')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Dosen Praktisi Dashboard']);
        });
    });
    
    // Tenaga Kependidikan Routes
    Route::middleware('role:Tenaga Kependidikan')->prefix('tenaga-kependidikan')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Tenaga Kependidikan Dashboard']);
        });
    });
});