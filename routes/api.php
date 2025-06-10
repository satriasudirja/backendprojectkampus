<?php


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
use App\Http\Controllers\Api\SimpegMediaPublikasiController;
use App\Http\Controllers\Api\SimpegJenjangPendidikanController;

use App\Http\Controllers\Api\SimpegJenisPelanggaranController;
use App\Http\Controllers\Api\SimpegJenisPenghargaaniController;
use App\Http\Controllers\Api\SimpegJenisPublikasiController;
use App\Http\Controllers\Api\SimpegJenisKenaikanPangkatController;
use App\Http\Controllers\Api\SimpegJenisJenisIzinController;
use App\Http\Controllers\Api\SimpegJenisHariController;
use App\Http\Controllers\Api\SimpegJenisKehadiranController;
use App\Http\Controllers\Api\SimpegGajiDetailController;
use App\Http\Controllers\Api\SimpegGajiKomponenController;
use App\Http\Controllers\Api\SimpegGajiSlipController;
use App\Http\Controllers\Api\SimpegGajiPeriodeController;
use App\Http\Controllers\Api\SimpegGajiTunjanganKhususController;
use App\Http\Controllers\Api\SimpegGajiLemburController;




use App\Http\Controllers\Api\SimpegRiwayatPangkatController;
use App\Http\Controllers\Api\SimpegRiwayatPekerjaanDosenController;
use App\Http\Controllers\Api\SimpegPengajuanCutiDosenController;
use App\Http\Controllers\Api\SimpegPengajuanIzinDosenController;

use App\Http\Controllers\Api\SimpegDataAnakController;
use App\Http\Controllers\Api\SimpegDataPasanganController;
use App\Http\Controllers\Api\SimpegDataOrangTuaController;

use App\Http\Controllers\Api\SimpegHubunganKerjaController;
use App\Http\Controllers\Api\AnggotaProfesiController;

use App\Http\Controllers\Api\SimpegDataPangkatController;
use App\Http\Controllers\Api\SimpegDataJabatanAkademikController;
use App\Http\Controllers\Api\SimpegDataJabatanFungsionalController;
use App\Http\Controllers\Api\SimpegDataJabatanStrukturalController;
use App\Http\Controllers\Api\SimpegDataHubunganKerjaController;
use App\Http\Controllers\Api\BiodataController;
use App\Http\Controllers\Api\SimpegDataOrganisasiController;
use App\Http\Controllers\Api\SimpegDataKemampuanBahasaController;
use App\Http\Controllers\Api\SimpegDataDiklatController;
use App\Http\Controllers\Api\SimpegDataRiwayatPekerjaanDosenController;
use App\Http\Controllers\Api\SimpegDataPenghargaanAdmController;
use App\Http\Controllers\Api\SimpegDataPelanggaranController;
use App\Http\Controllers\Api\RiwayatKehadiranController;
use App\Http\Controllers\Api\AbsensiController;
use App\Http\Controllers\Api\RiwayatJabatanAkademikController;
use App\Http\Controllers\Api\KehadiranController;
use App\Http\Controllers\Api\SimpegRiwayatHubunganKerjaController;
use App\Http\Controllers\Api\SimpegRiwayatJabatanStrukturalController;
use App\Http\Controllers\Api\SimpegSettingKehadiranController;
use App\Http\Controllers\Api\SimpegRiwayatDiklatController;
use App\Http\Controllers\Api\EvaluasiKinerjaController;
use App\Models\JenisSertifikasi;
use App\Models\SimpegDaftarCuti;

use App\Http\Controllers\Api\SimpegRumpunBidangIlmuController;

use App\Http\Controllers\Api\SimpegDataRiwayatTesController;
use App\Http\Controllers\Api\SimpegDataSertifikasidosenController;


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
   Route::group(['prefix' => 'evaluasi-kinerja'], function() {
    Route::get('/pegawai/{id}', [EvaluasiKinerjaController::class, 'show'])->name('evaluasi-kinerja.show');
    Route::get('/create', [EvaluasiKinerjaController::class, 'create'])->name('evaluasi-kinerja.create');
    Route::get('/{id}/edit', [EvaluasiKinerjaController::class, 'edit'])->name('evaluasi-kinerja.edit');
    Route::get('/evaluation/{id}', [EvaluasiKinerjaController::class, 'showEvaluation'])->name('evaluasi-kinerja.evaluation.show');
});


        Route::prefix('pegawai/riwayat-diklat')->group(function () {
    
    // GET: Mendapatkan semua riwayat diklat dengan filter dan pagination
    Route::get('/', [SimpegRiwayatDiklatController::class, 'index']);
    
    // GET: Mendapatkan dropdown options untuk form
    Route::get('/form-options', [SimpegRiwayatDiklatController::class, 'getFormOptions']);
    
    // GET: Mendapatkan riwayat diklat berdasarkan pegawai ID
    Route::get('/pegawai/{pegawaiId}', [SimpegRiwayatDiklatController::class, 'getByPegawai']);
    
    // GET: Mendapatkan detail riwayat diklat
    Route::get('/{id}', [SimpegRiwayatDiklatController::class, 'show']);
    
    // POST: Membuat riwayat diklat baru
    Route::post('/', [SimpegRiwayatDiklatController::class, 'store']);
    
    // PUT/PATCH: Update riwayat diklat
    Route::put('/{id}', [SimpegRiwayatDiklatController::class, 'update']);
    Route::patch('/{id}', [SimpegRiwayatDiklatController::class, 'update']);
    
    // DELETE: Hapus riwayat diklat
    Route::delete('/{id}', [SimpegRiwayatDiklatController::class, 'destroy']);
    
    // POST: Batch update status
    Route::post('/batch/update-status', [SimpegRiwayatDiklatController::class, 'batchUpdateStatus']);
    
    // POST: Batch delete
    Route::post('/batch/delete', [SimpegRiwayatDiklatController::class, 'batchDelete']);
    
    // PUT/PATCH: Update status pengajuan
    Route::put('/{id}/status', [SimpegRiwayatDiklatController::class, 'updateStatusPengajuan']);
    Route::patch('/{id}/status', [SimpegRiwayatDiklatController::class, 'updateStatusPengajuan']);
    
    // PUT/PATCH: Toggle active status
    Route::put('/{id}/toggle-active', [SimpegRiwayatDiklatController::class, 'toggleActive']);
    Route::patch('/{id}/toggle-active', [SimpegRiwayatDiklatController::class, 'toggleActive']);
    
    // GET: Download file dokumen
    Route::get('/{id}/download/{fileId}', [SimpegRiwayatDiklatController::class, 'downloadFile']);
});
        Route::prefix('setting-kehadiran')->group(function () {
    // Main routes
    Route::get('/', [SimpegSettingKehadiranController::class, 'index']); // Get setting / show form
    Route::post('/', [SimpegSettingKehadiranController::class, 'store']); // Save setting (auto create/update)
    Route::get('/detail', [SimpegSettingKehadiranController::class, 'show']); // Get detail setting
    Route::get('/detail/{id}', [SimpegSettingKehadiranController::class, 'show']); // Get detail by ID
    
    // Utility routes
    Route::post('/reset-default', [SimpegSettingKehadiranController::class, 'resetToDefault']); // Reset to default
    Route::post('/test-coordinates', [SimpegSettingKehadiranController::class, 'testCoordinates']); // Test coordinates
    Route::get('/system-info', [SimpegSettingKehadiranController::class, 'getSystemInfo']); // Get system info
});

        // Routes untuk Riwayat Jabatan Struktural
        Route::get('pegawai/riwayat-jabatan-struktural/all', [SimpegRiwayatJabatanStrukturalController::class, 'index']);
        Route::get('pegawai/riwayat-jabatan-struktural/detail/{id}', [SimpegRiwayatJabatanStrukturalController::class, 'show']);
        Route::put('pegawai/riwayat-jabatan-struktural/batch/update-status', [SimpegRiwayatJabatanStrukturalController::class, 'batchUpdateStatus']);
        Route::delete('pegawai/riwayat-jabatan-struktural/batch/delete', [SimpegRiwayatJabatanStrukturalController::class, 'batchDelete']);
        Route::put('pegawai/riwayat-jabatan-struktural/{id}/status', [SimpegRiwayatJabatanStrukturalController::class, 'updateStatusPengajuan']);
        Route::get('pegawai/riwayat-jabatan-struktural/{pegawaiId}', [SimpegRiwayatJabatanStrukturalController::class, 'getByPegawai']);
        Route::post('pegawai/riwayat-jabatan-struktural', [SimpegRiwayatJabatanStrukturalController::class, 'store']);
        Route::put('pegawai/riwayat-jabatan-struktural/{id}', [SimpegRiwayatJabatanStrukturalController::class, 'update']);
        Route::delete('pegawai/riwayat-jabatan-struktural/{id}', [SimpegRiwayatJabatanStrukturalController::class, 'destroy']);

        // Route tambahan untuk form options, download file, dan toggle active
        Route::get('pegawai/riwayat-jabatan-struktural/options/form', [SimpegRiwayatJabatanStrukturalController::class, 'getFormOptions']);
        Route::get('pegawai/riwayat-jabatan-struktural/{id}/download', [SimpegRiwayatJabatanStrukturalController::class, 'downloadFile']);
        Route::post('pegawai/riwayat-jabatan-struktural/{id}/toggle-active', [SimpegRiwayatJabatanStrukturalController::class, 'toggleActive']);

        // Route lama yang masih bisa digunakan untuk kompatibilitas
        Route::get('pegawai/riwayat-jabatan-struktural/{id}', [PegawaiController::class, 'riwayatJabatanStruktural']);


        Route::get('pegawai/riwayat-hubungan-kerja/all', [SimpegRiwayatHubunganKerjaController::class, 'index']);
        Route::get('pegawai/riwayat-hubungan-kerja/detail/{id}', [SimpegRiwayatHubunganKerjaController::class, 'show']);
        Route::put('pegawai/riwayat-hubungan-kerja/batch/update-status', [SimpegRiwayatHubunganKerjaController::class, 'batchUpdateStatus']);
        Route::delete('pegawai/riwayat-hubungan-kerja/batch/delete', [SimpegRiwayatHubunganKerjaController::class, 'batchDelete']);
        Route::put('pegawai/riwayat-hubungan-kerja/{id}/status', [SimpegRiwayatHubunganKerjaController::class, 'updateStatusPengajuan']);
        Route::get('pegawai/riwayat-hubungan-kerja/{pegawaiId}', [SimpegRiwayatHubunganKerjaController::class, 'getByPegawai']);
        Route::post('pegawai/riwayat-hubungan-kerja', [SimpegRiwayatHubunganKerjaController::class, 'store']);
        Route::put('pegawai/riwayat-hubungan-kerja/{id}', [SimpegRiwayatHubunganKerjaController::class, 'update']);
        Route::delete('pegawai/riwayat-hubungan-kerja/{id}', [SimpegRiwayatHubunganKerjaController::class, 'destroy']);

        // Route tambahan untuk form options dan download file
        Route::get('pegawai/riwayat-hubungan-kerja/options/form', [SimpegRiwayatHubunganKerjaController::class, 'getFormOptions']);
        Route::get('pegawai/riwayat-hubungan-kerja/{id}/download', [SimpegRiwayatHubunganKerjaController::class, 'downloadFile']);

        // Route lama yang masih bisa digunakan untuk kompatibilitas
        Route::get('pegawai/riwayat-hubungan-kerja/{id}', [PegawaiController::class, 'riwayatHubunganKerja']);

        Route::prefix('pegawai/riwayat-kehadiran')->group(function () {
            // Halaman utama rekap kehadiran
            Route::get('/', [KehadiranController::class, 'index'])
                ->name('admin.rekap-kehadiran.index');

            Route::get('/detail', [KehadiranController::class, 'detail']);

            // Cetak rekap kehadiran
            Route::get('/print', [KehadiranController::class, 'print'])
                ->name('admin.rekap-kehadiran.print');
        });
        Route::prefix('pegawai/riwayat-jabatan-akademik')->group(function () {

            // Main CRUD routes
            Route::get('/', [RiwayatJabatanAkademikController::class, 'index']);
            // GET /api/{prefix}/jabatanakademik

            Route::post('/', [RiwayatJabatanAkademikController::class, 'store']);
            // POST /api/{prefix}/jabatanakademik

            Route::get('/{id}', [RiwayatJabatanAkademikController::class, 'show']);
            // GET /api/{prefix}/jabatanakademik/{id}

            Route::put('/{id}', [RiwayatJabatanAkademikController::class, 'update']);
            // PUT /api/{prefix}/jabatanakademik/{id}

            Route::delete('/{id}', [RiwayatJabatanAkademikController::class, 'destroy']);
            // DELETE /api/{prefix}/jabatanakademik/{id}

            // Batch operations (Admin only)
            Route::patch('/batch/status', [RiwayatJabatanAkademikController::class, 'batchUpdateStatus']);
            // PATCH /api/{prefix}/jabatanakademik/batch/status

            Route::delete('/batch/delete', [RiwayatJabatanAkademikController::class, 'batchDelete']);
            // DELETE /api/{prefix}/jabatanakademik/batch/delete

            // Status management
            Route::patch('/{id}/status', [RiwayatJabatanAkademikController::class, 'updateStatusPengajuan']);
            // PATCH /api/{prefix}/jabatanakademik/{id}/status

            Route::patch('/{id}/submit', [RiwayatJabatanAkademikController::class, 'submitDraft']);
            // PATCH /api/{prefix}/jabatanakademik/{id}/submit

            // Pegawai specific (Admin only)
            Route::get('/pegawai/{pegawaiId}', [RiwayatJabatanAkademikController::class, 'getByPegawai']);
            // GET /api/{prefix}/jabatanakademik/pegawai/{pegawaiId}

            // File operations
            Route::get('/{id}/download', [RiwayatJabatanAkademikController::class, 'downloadFile']);
            // GET /api/{prefix}/jabatanakademik/{id}/download
        });

        Route::prefix('datapelanggaran')->group(function () {
            // CRUD Operations
            Route::get('/', [SimpegDataPelanggaranController::class, 'index']);
            Route::post('/', [SimpegDataPelanggaranController::class, 'store']);
            Route::get('/{id}', [SimpegDataPelanggaranController::class, 'show']);
            Route::put('/{id}', [SimpegDataPelanggaranController::class, 'update']);
            Route::delete('/{id}', [SimpegDataPelanggaranController::class, 'destroy']);

            // Batch Operations
            Route::delete('/batch/delete', [SimpegDataPelanggaranController::class, 'batchDelete']);

            // Utility Routes
            Route::get('/options/pegawai', [SimpegDataPelanggaranController::class, 'getPegawaiOptions']);
            Route::get('/options/filter', [SimpegDataPelanggaranController::class, 'getFilterOptions']);
            Route::get('/options/form', [SimpegDataPelanggaranController::class, 'getFormOptions']);

            // Statistics & Export
            Route::get('/statistics/dashboard', [SimpegDataPelanggaranController::class, 'getStatistics']);
            Route::get('/export/excel', [SimpegDataPelanggaranController::class, 'export']);

            // Validation
            Route::post('/validate/duplicate', [SimpegDataPelanggaranController::class, 'validateDuplicate']);
        });
        Route::prefix('datapenghargaan')->group(function () {
            // === CRUD Operations ===

            // List all penghargaan dengan filter dan search
            Route::get('/', [SimpegDataPenghargaanAdmController::class, 'index']);

            // Create new penghargaan
            Route::post('/', [SimpegDataPenghargaanAdmController::class, 'store']);

            // Get detail penghargaan
            Route::get('/{id}', [SimpegDataPenghargaanAdmController::class, 'show']);

            // Update penghargaan
            Route::put('/{id}', [SimpegDataPenghargaanAdmController::class, 'update']);
            Route::patch('/{id}', [SimpegDataPenghargaanAdmController::class, 'update']); // Alternative method

            // Delete single penghargaan
            Route::delete('/{id}', [SimpegDataPenghargaanAdmController::class, 'destroy']);

            // === Batch Operations ===

            // Batch delete penghargaan
            Route::delete('/batch/delete', [SimpegDataPenghargaanAdmController::class, 'batchDelete']);

            // === Form & Options ===

            // Get form options untuk dropdown create/edit form
            Route::get('/form/options', [SimpegDataPenghargaanAdmController::class, 'getFormOptions']);

            // Get pegawai options untuk dropdown pegawai
            Route::get('/pegawai/options', [SimpegDataPenghargaanAdmController::class, 'getPegawaiOptions']);

            // Get filter options untuk dropdown filter
            Route::get('/filters/options', [SimpegDataPenghargaanAdmController::class, 'getFilterOptions']);

            // === Validation & Utilities ===

            // Validate duplicate data
            Route::post('/validate/duplicate', [SimpegDataPenghargaanAdmController::class, 'validateDuplicate']);

            // === Reports & Analytics ===

            // Get statistics untuk dashboard
            Route::get('/statistics/summary', [SimpegDataPenghargaanAdmController::class, 'getStatistics']);

            // Export data penghargaan
            Route::post('/export', [SimpegDataPenghargaanAdmController::class, 'export']);
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


        // Route::get('pegawai/riwayat-pangkat/{id}', [PegawaiController::class, 'riwayatPangkat']);



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

        Route::get('pegawai/riwayat-pangkat/{id}', [PegawaiController::class, 'riwayatPangkat']);

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

        Route::get('pegawai/riwayat-pangkat/{id}', [PegawaiController::class, 'riwayatPangkat']);
        Route::get('pegawai/riwayat-fungsional/{id}', [PegawaiController::class, 'riwayatFungsional']);
        Route::get('pegawai/riwayat-jenjang-fungsional/{id}', [PegawaiController::class, 'riwayatJenjangFungsional']);
        Route::get('pegawai/riwayat-jabatan-struktural/{id}', [PegawaiController::class, 'riwayatJabatanStruktural']);
        Route::get('pegawai/riwayat-hubungan-kerja/{id}', [PegawaiController::class, 'riwayatHubunganKerja']);
        Route::get('pegawai/rekap-kehadiran/{id}', [PegawaiController::class, 'rekapKehadiran']);






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

        Route::apiResource('kategori-sertifikasi', SimpegKategoriSertifikasiController::class);
        Route::apiResource('hubungan-kerja', SimpegHubunganKerjaController::class);
        Route::apiResource('media-publikasi', SimpegMediaPublikasiController::class);


        // Route::apiResource('jenis-penghargaan', SimpegJenisPenghargaanController::class);
        Route::apiResource('jenis-pelanggaran', SimpegJenisPelanggaranController::class);
        Route::apiResource('jenis-publikasi', SimpegJenisPublikasiController::class);
        Route::apiResource('jenis-kenaikan-pangkat', SimpegJenisKenaikanPangkatController::class);
        // Route::apiResource('jenis-izin', SimpegJenisIzinController::class);
        Route::apiResource('gaji-detail', SimpegGajiDetailController::class);
        Route::apiResource('gaji-komponen', SimpegGajiKomponenController::class);
        Route::apiResource('gaji-tunjangan-khusus', SimpegGajiTunjanganKhususController::class);
        Route::apiResource('gaji-slip', SimpegGajiSlipController::class);
        Route::apiResource('gaji-lembur', SimpegGajiLemburController::class);
        Route::apiResource('gaji-periode', SimpegGajiPeriodeController::class);
        Route::apiResource('jenis-hari', SimpegJenisHariController::class);
        Route::apiResource('jenis-kehadiran', SimpegJenisKehadiranController::class);
        Route::apiResource('rumpun-bidang-ilmu', SimpegJenisKehadiranController::class);

        Route::get('/dashboard', [AdminDashboardController::class, 'getDashboardData']);
        Route::get('/unit-kerja/dropdown', [UnitKerjaController::class, 'getUnitsDropdown']);
        Route::get('/news/{id}', [AdminDashboardController::class, 'getNewsDetail']);

        // Endpoint tambahan untuk SoftDelete
        Route::get('jenjang-pendidikan/trash', [SimpegJenjangPendidikanController::class, 'trash']);
        Route::post('jenjang-pendidikan/{id}/restore', [SimpegJenjangPendidikanController::class, 'restore']);
        Route::delete('jenjang-pendidikan/{id}/force-delete', [SimpegJenjangPendidikanController::class, 'forceDelete']);
        Route::apiResource('jenjang-pendidikan', SimpegJenjangPendidikanController::class); // Setelah routes spesifik
    });

    // Dosen Routes
    Route::middleware('role:Dosen,Tenaga Kependidikan,Dosen Praktisi/Industri')->prefix('dosen')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Dosen Dashboard']);
        });

Route::group(['prefix' => 'evaluasi-kinerja'], function() {
        
        // GET /api/dosen/evaluasi-kinerja
        // Mendapatkan daftar pegawai yang bisa dievaluasi (INDEX)
        Route::get('/', [EvaluasiKinerjaController::class, 'index'])
            ->name('evaluasi-kinerja.index');
        
        // GET /api/dosen/evaluasi-kinerja/pegawai/{id}
        // Mendapatkan detail pegawai untuk evaluasi (SHOW PEGAWAI)
        Route::get('/pegawai/{id}', [EvaluasiKinerjaController::class, 'show'])
            ->name('evaluasi-kinerja.show')
            ->where('id', '[0-9]+');
        
        // GET /api/dosen/evaluasi-kinerja/create
        // Form data untuk create evaluasi baru (akan redirect ke index dengan parameter)
        Route::get('/create', function(Request $request) {
            return redirect()->route('evaluasi-kinerja.index', $request->all());
        })->name('evaluasi-kinerja.create');
        
        // GET /api/dosen/evaluasi-kinerja/{id}/edit
        // Form data untuk edit evaluasi (akan redirect ke show evaluation)
        Route::get('/{id}/edit', function($id) {
            return redirect()->route('evaluasi-kinerja.evaluation.show', $id);
        })->name('evaluasi-kinerja.edit')->where('id', '[0-9]+');
        
        // GET /api/dosen/evaluasi-kinerja/evaluation/{id}
        // Mendapatkan detail evaluasi kinerja (alias untuk show dengan evaluation)
        Route::get('/evaluation/{id}', function($id) {
            $controller = new EvaluasiKinerjaController();
            // Untuk sementara redirect ke show pegawai, atau bisa dibuat method baru jika diperlukan
            return response()->json([
                'success' => true,
                'message' => 'Untuk detail evaluasi, gunakan endpoint show pegawai atau implementasikan method showEvaluation',
                'redirect_to' => url("/api/dosen/evaluasi-kinerja/periode?evaluation_id={$id}")
            ]);
        })->name('evaluasi-kinerja.evaluation.show')->where('id', '[0-9]+');
        
        // POST /api/dosen/evaluasi-kinerja
        // Menambahkan evaluasi kinerja baru (STORE)
        Route::post('/', [EvaluasiKinerjaController::class, 'store'])
            ->name('evaluasi-kinerja.store');
        
        // PUT /api/dosen/evaluasi-kinerja/{id}
        // Mengupdate evaluasi kinerja (UPDATE)
        Route::put('/{id}', [EvaluasiKinerjaController::class, 'update'])
            ->name('evaluasi-kinerja.update')
            ->where('id', '[0-9]+');
        
        // PATCH /api/dosen/evaluasi-kinerja/{id}
        // Mengupdate evaluasi kinerja secara partial (PATCH)
        Route::patch('/{id}', [EvaluasiKinerjaController::class, 'update'])
            ->name('evaluasi-kinerja.patch')
            ->where('id', '[0-9]+');
        
        // DELETE /api/dosen/evaluasi-kinerja/{id}
        // Menghapus evaluasi kinerja (DESTROY)
        Route::delete('/{id}', [EvaluasiKinerjaController::class, 'destroy'])
            ->name('evaluasi-kinerja.destroy')
            ->where('id', '[0-9]+');
        
        // ==================== ADDITIONAL UTILITY ROUTES ====================
        
        // GET /api/dosen/evaluasi-kinerja/debug
        // Debug hierarki dan statistik (untuk testing)
        Route::get('/debug', [EvaluasiKinerjaController::class, 'debugHierarki'])
            ->name('evaluasi-kinerja.debug');
        
        // GET /api/dosen/evaluasi-kinerja/periode
        // Mendapatkan evaluasi berdasarkan periode
        Route::get('/periode', [EvaluasiKinerjaController::class, 'getEvaluasiByPeriode'])
            ->name('evaluasi-kinerja.periode');
        
        // GET /api/dosen/evaluasi-kinerja/export
        // Export data pegawai untuk laporan
        Route::get('/export', [EvaluasiKinerjaController::class, 'exportPegawaiList'])
            ->name('evaluasi-kinerja.export');
    });
        Route::prefix('absensi')->group(function () {

            // Get status absensi hari ini
            Route::get('/status', [AbsensiController::class, 'getAbsensiStatus'])
                ->name('absensi.status');

            // Absen masuk
            Route::post('/masuk', [AbsensiController::class, 'absenMasuk'])
                ->name('absensi.masuk');

            // Absen keluar
            Route::post('/keluar', [AbsensiController::class, 'absenKeluar'])
                ->name('absensi.keluar');

            // History absensi
            Route::get('/history', [AbsensiController::class, 'getHistory'])
                ->name('absensi.history');

            // Detail absensi
            Route::get('/detail/{id}', [AbsensiController::class, 'getDetail'])
                ->name('absensi.detail');

            // Request koreksi absensi
            Route::post('/koreksi/{id}', [AbsensiController::class, 'requestCorrection'])
                ->name('absensi.koreksi');

            // Dashboard statistics
            Route::get('/dashboard', [AbsensiController::class, 'getDashboardStats'])
                ->name('absensi.dashboard');
        });

        Route::prefix('riwayat-kehadiran')->group(function () {

            // Menampilkan riwayat kehadiran per tahun untuk pegawai yang login
            Route::get('/', [RiwayatKehadiranController::class, 'index'])
                ->name('riwayat-kehadiran.index');

            // Detail presensi harian untuk bulan tertentu
            Route::get('/detail', [RiwayatKehadiranController::class, 'detail'])
                ->name('riwayat-kehadiran.detail');

            // Print/cetak daftar riwayat kehadiran semua pegawai
            Route::get('/print', [RiwayatKehadiranController::class, 'print'])
                ->name('riwayat-kehadiran.print');
        });
        Route::prefix('biodata')->group(function () {
            Route::get('/', [BiodataController::class, 'index']);
            Route::get('/riwayat-pendidikan', [BiodataController::class, 'riwayatPendidikan']);
            Route::get('/riwayat-pangkat', [BiodataController::class, 'riwayatPangkat']);
            Route::get('/riwayat-unit-kerja', [BiodataController::class, 'riwayatUnitKerja']);
            Route::get('/riwayat-jabatan-akademik', [BiodataController::class, 'riwayatJabatanAkademik']);
            Route::get('/riwayat-jabatan-fungsional', [BiodataController::class, 'riwayatJabatanFungsional']);
            Route::get('/riwayat-jabatan-struktural', [BiodataController::class, 'riwayatJabatanStruktural']);
            Route::get('/riwayat-hubungan-kerja', [BiodataController::class, 'riwayatHubunganKerja']);
            Route::get('/rekap-kehadiran', [BiodataController::class, 'rekapKehadiran']);
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

        // Data Riwayat Tes Routes
        Route::prefix('datariwayattes')->group(function () {
            // ========================================
            // STATIC ROUTES (HARUS DI ATAS!)
            // ========================================
            
            // Configuration & Statistics Routes
            Route::get('/config/system', [SimpegDataRiwayatTesController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegDataRiwayatTesController::class, 'getStatusStatistics']);
            Route::get('/filter-options', [SimpegDataRiwayatTesController::class, 'getFilterOptions']);
            Route::get('/available-actions', [SimpegDataRiwayatTesController::class, 'getAvailableActions']);
            
            // Utility Routes
            Route::get('/jenis-tes/list', [SimpegDataRiwayatTesController::class, 'getJenisTes']);
            Route::patch('/fix-existing-data', [SimpegDataRiwayatTesController::class, 'fixExistingData']);
            Route::patch('/bulk-fix-existing-data', [SimpegDataRiwayatTesController::class, 'bulkFixExistingData']);
            
            // ========================================
            // BATCH OPERATIONS ROUTES (HARUS SEBELUM {id} ROUTES!)
            // ========================================
            Route::delete('/batch/delete', [SimpegDataRiwayatTesController::class, 'batchDelete']);
            Route::patch('/batch/submit', [SimpegDataRiwayatTesController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegDataRiwayatTesController::class, 'batchUpdateStatus']);
            
            // ========================================
            // CRUD OPERATIONS (PARAMETER ROUTES DI BAWAH!)
            // ========================================
            Route::get('/', [SimpegDataRiwayatTesController::class, 'index']);
            Route::post('/', [SimpegDataRiwayatTesController::class, 'store']);
            Route::get('/{id}', [SimpegDataRiwayatTesController::class, 'show']);
            Route::put('/{id}', [SimpegDataRiwayatTesController::class, 'update']);
            Route::delete('/{id}', [SimpegDataRiwayatTesController::class, 'destroy']);
            
            // ========================================
            // STATUS PENGAJUAN ROUTES (DENGAN {id} DI BAWAH!)
            // ========================================
            Route::patch('/{id}/submit', [SimpegDataRiwayatTesController::class, 'submitDraft']);
        });


        // Data Sertifikasi Dosen Routes
        Route::prefix('datasertifikasidosen')->group(function () {
            // ======================================
            // BATCH OPERATIONS ROUTES (HARUS DI ATAS!)
            // ======================================
            Route::delete('/batch/delete', [SimpegDataSertifikasidosenController::class, 'batchDelete']);
            Route::patch('/batch/submit', [SimpegDataSertifikasidosenController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegDataSertifikasidosenController::class, 'batchUpdateStatus']);
            
            // ======================================
            // CONFIGURATION & STATISTICS ROUTES
            // ======================================
            Route::get('/config/system', [SimpegDataSertifikasidosenController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegDataSertifikasidosenController::class, 'getStatusStatistics']);
            Route::get('/filter-options', [SimpegDataSertifikasidosenController::class, 'getFilterOptions']);
            Route::get('/available-actions', [SimpegDataSertifikasidosenController::class, 'getAvailableActions']);
            
            // ======================================
            // DATA FIX ROUTES
            // ======================================
            Route::patch('/fix/existing-data', [SimpegDataSertifikasidosenController::class, 'fixExistingData']);
            
            // ======================================
            // CRUD ROUTES (HARUS DI BAWAH SEMUA ROUTE STATIS!)
            // ======================================
            Route::get('/', [SimpegDataSertifikasidosenController::class, 'index']);
            Route::post('/', [SimpegDataSertifikasidosenController::class, 'store']);
            Route::get('/{id}', [SimpegDataSertifikasidosenController::class, 'show']);
            Route::put('/{id}', [SimpegDataSertifikasidosenController::class, 'update']);
            Route::delete('/{id}', [SimpegDataSertifikasidosenController::class, 'destroy']);
            
            // ======================================
            // STATUS PENGAJUAN ROUTES
            // ======================================
            Route::patch('/{id}/submit', [SimpegDataSertifikasidosenController::class, 'submitDraft']);
        });


        // Data Organisasi Routes
        Route::prefix('dataorganisasi')->group(function () {
            // ========================================
            // CONFIGURATION & STATISTICS ROUTES (HARUS DI ATAS!)
            // ========================================
            Route::get('/config/system', [SimpegDataOrganisasiController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegDataOrganisasiController::class, 'getStatusStatistics']);
            Route::get('/filter-options', [SimpegDataOrganisasiController::class, 'getFilterOptions']);
            Route::get('/available-actions', [SimpegDataOrganisasiController::class, 'getAvailableActions']);

            // ========================================
            // UTILITY ROUTES
            // ========================================
            Route::patch('/fix-existing-data', [SimpegDataOrganisasiController::class, 'fixExistingData']);
            Route::patch('/bulk-fix-existing-data', [SimpegDataOrganisasiController::class, 'bulkFixExistingData']);

            // ========================================
            // BATCH OPERATIONS ROUTES
            // ========================================
            Route::delete('/batch/delete', [SimpegDataOrganisasiController::class, 'batchDelete']);
            Route::patch('/batch/submit', [SimpegDataOrganisasiController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegDataOrganisasiController::class, 'batchUpdateStatus']);

            // ========================================
            // CRUD ROUTES (HARUS DI BAWAH ROUTE SPESIFIK!)
            // ========================================
            Route::get('/', [SimpegDataOrganisasiController::class, 'index']);
            Route::post('/', [SimpegDataOrganisasiController::class, 'store']);
            Route::get('/{id}', [SimpegDataOrganisasiController::class, 'show']);
            Route::put('/{id}', [SimpegDataOrganisasiController::class, 'update']);
            Route::delete('/{id}', [SimpegDataOrganisasiController::class, 'destroy']);

            // ======================================
            // STATUS PENGAJUAN ROUTES
            // ========================================
            Route::patch('/{id}/submit', [SimpegDataOrganisasiController::class, 'submitDraft']);
        });

        // Data Kemampuan Bahasa Routes
        Route::prefix('datakemampuanbahasa')->group(function () {
            // ========================================
            // CONFIGURATION & STATISTICS ROUTES
            // ========================================
            // ⚠️ PENTING: Routes spesifik HARUS di atas routes generic {id}
            Route::get('/config/system', [SimpegDataKemampuanBahasaController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegDataKemampuanBahasaController::class, 'getStatusStatistics']);
            Route::get('/filter-options', [SimpegDataKemampuanBahasaController::class, 'getFilterOptions']);

            // ========================================
            // BATCH OPERATIONS ROUTES
            // ========================================
            Route::delete('/batch/delete', [SimpegDataKemampuanBahasaController::class, 'batchDelete']);
            Route::patch('/batch/submit', [SimpegDataKemampuanBahasaController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegDataKemampuanBahasaController::class, 'batchUpdateStatus']);

            // ========================================
            // DATA CORRECTION ROUTES
            // ========================================
            Route::patch('/fix-existing-data', [SimpegDataKemampuanBahasaController::class, 'fixExistingData']);

            // ========================================
            // CRUD OPERATIONS - Generic routes dengan {id} di BAWAH
            // ========================================
            Route::get('/', [SimpegDataKemampuanBahasaController::class, 'index']);
            Route::post('/', [SimpegDataKemampuanBahasaController::class, 'store']);

            // ⚠️ PENTING: Routes dengan {id} parameter HARUS di paling bawah
            Route::get('/{id}', [SimpegDataKemampuanBahasaController::class, 'show']);
            Route::put('/{id}', [SimpegDataKemampuanBahasaController::class, 'update']);
            Route::delete('/{id}', [SimpegDataKemampuanBahasaController::class, 'destroy']);

            // ========================================
            // STATUS PENGAJUAN ROUTES dengan {id}
            // ========================================
            Route::patch('/{id}/submit', [SimpegDataKemampuanBahasaController::class, 'submitDraft']);
        });

        // Data diklat Routes
        Route::prefix('data-diklat')->group(function () {
            // Main CRUD routes
            Route::get('/', [SimpegDataDiklatController::class, 'index']);
            Route::get('/{id}', [SimpegDataDiklatController::class, 'show']);
            Route::post('/', [SimpegDataDiklatController::class, 'store']);
            Route::put('/{id}', [SimpegDataDiklatController::class, 'update']);
            Route::delete('/{id}', [SimpegDataDiklatController::class, 'destroy']);

            // ======================================
            // STATUS PENGAJUAN ROUTES
            // ======================================
            Route::patch('/{id}/submit', [SimpegDataDiklatController::class, 'submitDraft']);

            // ======================================
            // BATCH OPERATIONS ROUTES
            // ======================================
            Route::patch('/batch/submit-drafts', [SimpegDataDiklatController::class, 'batchSubmitDrafts']);
            Route::delete('/batch/delete', [SimpegDataDiklatController::class, 'batchDelete']);
            Route::patch('/batch/status', [SimpegDataDiklatController::class, 'batchUpdateStatus']);

            // ======================================
            // CONFIGURATION & STATISTICS ROUTES
            // ======================================
            Route::get('/config/system', [SimpegDataDiklatController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegDataDiklatController::class, 'getStatusStatistics']);
            Route::get('/options/filter', [SimpegDataDiklatController::class, 'getFilterOptions']);
            Route::get('/actions/available', [SimpegDataDiklatController::class, 'getAvailableActions']);

            // ======================================
            // UTILITY ROUTES
            // ======================================
            Route::patch('/fix/existing-data', [SimpegDataDiklatController::class, 'fixExistingData']);
            Route::patch('/fix/bulk-existing-data', [SimpegDataDiklatController::class, 'bulkFixExistingData']);
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

        Route::prefix('data-riwayat-pekerjaan-dosen')->group(function () {
            // Configuration & Statistics Routes (specific paths first)
            Route::get('/filter-options', [SimpegDataRiwayatPekerjaanDosenController::class, 'getFilterOptions']);
            Route::get('/available-actions', [SimpegDataRiwayatPekerjaanDosenController::class, 'getAvailableActions']);
            Route::get('/config/system', [SimpegDataRiwayatPekerjaanDosenController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegDataRiwayatPekerjaanDosenController::class, 'getStatusStatistics']);

            // Batch Operations Routes
            Route::delete('/batch/delete', [SimpegDataRiwayatPekerjaanDosenController::class, 'batchDelete']);
            Route::patch('/batch/submit', [SimpegDataRiwayatPekerjaanDosenController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegDataRiwayatPekerjaanDosenController::class, 'batchUpdateStatus']);

            // Status Pengajuan Routes
            Route::patch('/{id}/submit', [SimpegDataRiwayatPekerjaanDosenController::class, 'submitDraft']);

            // CRUD Routes (parameterized routes last)
            Route::get('/', [SimpegDataRiwayatPekerjaanDosenController::class, 'index']);
            Route::post('/', [SimpegDataRiwayatPekerjaanDosenController::class, 'store']);
            Route::get('/{id}', [SimpegDataRiwayatPekerjaanDosenController::class, 'show']);
            Route::put('/{id}', [SimpegDataRiwayatPekerjaanDosenController::class, 'update']);
            Route::delete('/{id}', [SimpegDataRiwayatPekerjaanDosenController::class, 'destroy']);
        });

        // Pengajuan Cuti Dosen Routes
        Route::prefix('pengajuan-cuti-dosen')->group(function () {
            Route::get('/', [SimpegPengajuanCutiDosenController::class, 'index']);
            Route::get('/{id}', [SimpegPengajuanCutiDosenController::class, 'show']);
            Route::post('/', [SimpegPengajuanCutiDosenController::class, 'store']);
            Route::put('/{id}', [SimpegPengajuanCutiDosenController::class, 'update']);
            Route::delete('/{id}', [SimpegPengajuanCutiDosenController::class, 'destroy']);

            // Status Pengajuan Routes
            Route::patch('/{id}/submit', [SimpegPengajuanCutiDosenController::class, 'submitDraft']);
            Route::get('/{id}/print', [SimpegPengajuanCutiDosenController::class, 'printCuti']);

            // Batch Operations Routes
            Route::delete('/batch/delete', [SimpegPengajuanCutiDosenController::class, 'batchDelete']);
            Route::patch('/batch/submit', [SimpegPengajuanCutiDosenController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegPengajuanCutiDosenController::class, 'batchUpdateStatus']);

            // Configuration & Statistics Routes
            Route::get('/config/system', [SimpegPengajuanCutiDosenController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegPengajuanCutiDosenController::class, 'getStatusStatistics']);
            Route::get('/filter-options', [SimpegPengajuanCutiDosenController::class, 'getFilterOptions']);
            Route::get('/available-actions', [SimpegPengajuanCutiDosenController::class, 'getAvailableActions']);
            Route::get('/remaining-cuti', [SimpegPengajuanCutiDosenController::class, 'getRemainingCuti']);
        });

        // Pengajuan Izin Dosen routes
        Route::prefix('pengajuan-izin-dosen')->group(function () {
            Route::get('/', [SimpegPengajuanIzinDosenController::class, 'index']);
            Route::get('/{id}', [SimpegPengajuanIzinDosenController::class, 'show']);
            Route::post('/', [SimpegPengajuanIzinDosenController::class, 'store']);
            Route::put('/{id}', [SimpegPengajuanIzinDosenController::class, 'update']);
            Route::delete('/{id}', [SimpegPengajuanIzinDosenController::class, 'destroy']);

            // STATUS PENGAJUAN ROUTES
            Route::patch('/{id}/submit', [SimpegPengajuanIzinDosenController::class, 'submitDraft']);

            // BATCH OPERATIONS ROUTES
            Route::delete('/batch/delete', [SimpegPengajuanIzinDosenController::class, 'batchDelete']);
            Route::patch('/batch/submit', [SimpegPengajuanIzinDosenController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegPengajuanIzinDosenController::class, 'batchUpdateStatus']);

            // CONFIGURATION & STATISTICS ROUTES
            Route::get('/config/system', [SimpegPengajuanIzinDosenController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegPengajuanIzinDosenController::class, 'getStatusStatistics']);
            Route::get('/filter-options', [SimpegPengajuanIzinDosenController::class, 'getFilterOptions']);
            Route::get('/available-actions', [SimpegPengajuanIzinDosenController::class, 'getAvailableActions']);

            // PRINT ROUTE
            Route::get('/{id}/print', [SimpegPengajuanIzinDosenController::class, 'printIzinDocument']);
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

        Route::prefix('pangkat')->middleware('auth:api')->group(function () {
            Route::get('/', [SimpegDataPangkatController::class, 'index']);

            // GET /api/dosen/pangkat/{id} - Detail data pangkat by ID
            Route::get('/{id}', [SimpegDataPangkatController::class, 'show']);

            // POST /api/dosen/pangkat - Create new data pangkat (dengan draft/submit mode)
            Route::post('/', [SimpegDataPangkatController::class, 'store']);

            // PUT /api/dosen/pangkat/{id} - Update data pangkat by ID
            Route::put('/{id}', [SimpegDataPangkatController::class, 'update']);

            // DELETE /api/dosen/pangkat/{id} - Delete data pangkat by ID
            Route::delete('/{id}', [SimpegDataPangkatController::class, 'destroy']);

            // ========================================
            // FILE MANAGEMENT ROUTES
            // ========================================

            // GET /api/dosen/pangkat/{id}/download - Download file pangkat
            Route::get('/{id}/download', [SimpegDataPangkatController::class, 'downloadFile']);

            // ========================================
            // STATUS PENGAJUAN ROUTES
            // ========================================

            // PATCH /api/dosen/pangkat/{id}/submit - Submit draft ke diajukan
            Route::patch('/{id}/submit', [SimpegDataPangkatController::class, 'submitDraft']);

            // ========================================
            // BATCH OPERATIONS ROUTES
            // ========================================

            // DELETE /api/dosen/pangkat/batch/delete - Batch delete data pangkat
            Route::delete('/batch/delete', [SimpegDataPangkatController::class, 'batchDelete']);

            // PATCH /api/dosen/pangkat/batch/submit - Batch submit drafts
            Route::patch('/batch/submit', [SimpegDataPangkatController::class, 'batchSubmitDrafts']);

            // PATCH /api/dosen/pangkat/batch/status - Batch update status
            Route::patch('/batch/status', [SimpegDataPangkatController::class, 'batchUpdateStatus']);

            // ========================================
            // CONFIGURATION & STATISTICS ROUTES
            // ========================================

            // GET /api/dosen/pangkat/config/system - Get system configuration
            Route::get('/config/system', [SimpegDataPangkatController::class, 'getSystemConfig']);

            // GET /api/dosen/pangkat/statistics/status - Get status statistics
            Route::get('/statistics/status', [SimpegDataPangkatController::class, 'getStatusStatistics']);

            // ========================================
            // FORM & UTILITY ROUTES
            // ========================================

            // GET /api/dosen/pangkat/form-options - Get dropdown options for forms
            Route::get('/form-options', [SimpegDataPangkatController::class, 'getFormOptions']);

            // ========================================
            // DATA MAINTENANCE ROUTES
            // ========================================

            // PATCH /api/dosen/pangkat/fix-existing - Fix existing data dengan status null
            Route::patch('/fix-existing', [SimpegDataPangkatController::class, 'fixExistingData']);

            // PATCH /api/dosen/pangkat/bulk-fix-existing - Bulk fix all existing data
            Route::patch('/bulk-fix-existing', [SimpegDataPangkatController::class, 'bulkFixExistingData']);
        });

        Route::prefix('jabatanakademik')->middleware('auth:api')->group(function () {
            // ========================================
            // BASIC CRUD ROUTES
            // ========================================

            // GET /api/dosen/jabatanakademik - List semua data jabatan akademik pegawai yang login
            Route::get('/', [SimpegDataJabatanAkademikController::class, 'index']);

            // GET /api/dosen/jabatanakademik/{id} - Detail data jabatan akademik by ID
            Route::get('/{id}', [SimpegDataJabatanAkademikController::class, 'show']);

            // POST /api/dosen/jabatanakademik - Create new data jabatan akademik (dengan draft/submit mode)
            Route::post('/', [SimpegDataJabatanAkademikController::class, 'store']);

            // PUT /api/dosen/jabatanakademik/{id} - Update data jabatan akademik by ID
            Route::put('/{id}', [SimpegDataJabatanAkademikController::class, 'update']);

            // DELETE /api/dosen/jabatanakademik/{id} - Delete data jabatan akademik by ID
            Route::delete('/{id}', [SimpegDataJabatanAkademikController::class, 'destroy']);

            // ========================================
            // FILE MANAGEMENT ROUTES
            // ========================================

            // GET /api/dosen/jabatanakademik/{id}/download - Download file jabatan akademik
            Route::get('/{id}/download', [SimpegDataJabatanAkademikController::class, 'downloadFile']);

            // ========================================
            // STATUS PENGAJUAN ROUTES
            // ========================================

            // PATCH /api/dosen/jabatanakademik/{id}/submit - Submit draft ke diajukan
            Route::patch('/{id}/submit', [SimpegDataJabatanAkademikController::class, 'submitDraft']);

            // ========================================
            // BATCH OPERATIONS ROUTES
            // ========================================

            // DELETE /api/dosen/jabatanakademik/batch/delete - Batch delete data jabatan akademik
            Route::delete('/batch/delete', [SimpegDataJabatanAkademikController::class, 'batchDelete']);

            // PATCH /api/dosen/jabatanakademik/batch/submit - Batch submit drafts
            Route::patch('/batch/submit', [SimpegDataJabatanAkademikController::class, 'batchSubmitDrafts']);

            // PATCH /api/dosen/jabatanakademik/batch/status - Batch update status
            Route::patch('/batch/status', [SimpegDataJabatanAkademikController::class, 'batchUpdateStatus']);

            // ========================================
            // CONFIGURATION & STATISTICS ROUTES
            // ========================================

            // GET /api/dosen/jabatanakademik/config/system - Get system configuration
            Route::get('/config/system', [SimpegDataJabatanAkademikController::class, 'getSystemConfig']);

            // GET /api/dosen/jabatanakademik/statistics/status - Get status statistics
            Route::get('/statistics/status', [SimpegDataJabatanAkademikController::class, 'getStatusStatistics']);

            // ========================================
            // FORM & UTILITY ROUTES
            // ========================================

            // GET /api/dosen/jabatanakademik/form-options - Get dropdown options for forms
            Route::get('/form-options', [SimpegDataJabatanAkademikController::class, 'getFormOptions']);

            // ========================================
            // DATA MAINTENANCE ROUTES
            // ========================================

            // PATCH /api/dosen/jabatanakademik/fix-existing - Fix existing data dengan status null
            Route::patch('/fix-existing', [SimpegDataJabatanAkademikController::class, 'fixExistingData']);

            // PATCH /api/dosen/jabatanakademik/bulk-fix-existing - Bulk fix all existing data
            Route::patch('/bulk-fix-existing', [SimpegDataJabatanAkademikController::class, 'bulkFixExistingData']);
        });

        Route::prefix('jabatanfungsional')->middleware('auth:api')->group(function () {
            // ========================================
            // BASIC CRUD ROUTES
            // ========================================

            // GET /api/dosen/jabatanfungsional - List semua data jabatan fungsional pegawai yang login
            Route::get('/', [SimpegDataJabatanFungsionalController::class, 'index']);

            // GET /api/dosen/jabatanfungsional/{id} - Detail data jabatan fungsional by ID
            Route::get('/{id}', [SimpegDataJabatanFungsionalController::class, 'show']);

            // POST /api/dosen/jabatanfungsional - Create new data jabatan fungsional (dengan draft/submit mode)
            Route::post('/', [SimpegDataJabatanFungsionalController::class, 'store']);

            // PUT /api/dosen/jabatanfungsional/{id} - Update data jabatan fungsional by ID
            Route::put('/{id}', [SimpegDataJabatanFungsionalController::class, 'update']);

            // DELETE /api/dosen/jabatanfungsional/{id} - Delete data jabatan fungsional by ID
            Route::delete('/{id}', [SimpegDataJabatanFungsionalController::class, 'destroy']);

            // ========================================
            // FILE MANAGEMENT ROUTES
            // ========================================

            // GET /api/dosen/jabatanfungsional/{id}/download - Download file jabatan fungsional
            Route::get('/{id}/download', [SimpegDataJabatanFungsionalController::class, 'downloadFile']);

            // ========================================
            // STATUS PENGAJUAN ROUTES
            // ========================================

            // PATCH /api/dosen/jabatanfungsional/{id}/submit - Submit draft ke diajukan
            Route::patch('/{id}/submit', [SimpegDataJabatanFungsionalController::class, 'submitDraft']);

            // ========================================
            // BATCH OPERATIONS ROUTES
            // ========================================

            // DELETE /api/dosen/jabatanfungsional/batch/delete - Batch delete data jabatan fungsional
            Route::delete('/batch/delete', [SimpegDataJabatanFungsionalController::class, 'batchDelete']);

            // PATCH /api/dosen/jabatanfungsional/batch/submit - Batch submit drafts
            Route::patch('/batch/submit', [SimpegDataJabatanFungsionalController::class, 'batchSubmitDrafts']);

            // PATCH /api/dosen/jabatanfungsional/batch/status - Batch update status
            Route::patch('/batch/status', [SimpegDataJabatanFungsionalController::class, 'batchUpdateStatus']);

            // ========================================
            // CONFIGURATION & STATISTICS ROUTES
            // ========================================

            // GET /api/dosen/jabatanfungsional/config/system - Get system configuration
            Route::get('/config/system', [SimpegDataJabatanFungsionalController::class, 'getSystemConfig']);

            // GET /api/dosen/jabatanfungsional/statistics/status - Get status statistics
            Route::get('/statistics/status', [SimpegDataJabatanFungsionalController::class, 'getStatusStatistics']);

            // ========================================
            // FORM & UTILITY ROUTES
            // ========================================

            // GET /api/dosen/jabatanfungsional/form-options - Get dropdown options for forms
            Route::get('/form-options', [SimpegDataJabatanFungsionalController::class, 'getFormOptions']);

            // ========================================
            // DATA MAINTENANCE ROUTES
            // ========================================

            // PATCH /api/dosen/jabatanfungsional/fix-existing - Fix existing data dengan status null
            Route::patch('/fix-existing', [SimpegDataJabatanFungsionalController::class, 'fixExistingData']);

            // PATCH /api/dosen/jabatanfungsional/bulk-fix-existing - Bulk fix all existing data
            Route::patch('/bulk-fix-existing', [SimpegDataJabatanFungsionalController::class, 'bulkFixExistingData']);
        });

        Route::prefix('jabatanstruktural')->middleware('auth:api')->group(function () {
            // ========================================
            // BASIC CRUD ROUTES
            // ========================================

            // GET /api/dosen/jabatanstruktural - List semua data jabatan struktural pegawai yang login
            Route::get('/', [SimpegDataJabatanStrukturalController::class, 'index']);

            // GET /api/dosen/jabatanstruktural/{id} - Detail data jabatan struktural by ID
            Route::get('/{id}', [SimpegDataJabatanStrukturalController::class, 'show']);

            // POST /api/dosen/jabatanstruktural - Create new data jabatan struktural (dengan draft/submit mode)
            Route::post('/', [SimpegDataJabatanStrukturalController::class, 'store']);

            // PUT /api/dosen/jabatanstruktural/{id} - Update data jabatan struktural by ID
            Route::put('/{id}', [SimpegDataJabatanStrukturalController::class, 'update']);

            // DELETE /api/dosen/jabatanstruktural/{id} - Delete data jabatan struktural by ID
            Route::delete('/{id}', [SimpegDataJabatanStrukturalController::class, 'destroy']);

            // ========================================
            // FILE MANAGEMENT ROUTES
            // ========================================

            // GET /api/dosen/jabatanstruktural/{id}/download - Download file jabatan struktural
            Route::get('/{id}/download', [SimpegDataJabatanStrukturalController::class, 'downloadFile']);

            // ========================================
            // STATUS PENGAJUAN ROUTES
            // ========================================

            // PATCH /api/dosen/jabatanstruktural/{id}/submit - Submit draft ke diajukan
            Route::patch('/{id}/submit', [SimpegDataJabatanStrukturalController::class, 'submitDraft']);

            // ========================================
            // BATCH OPERATIONS ROUTES
            // ========================================

            // DELETE /api/dosen/jabatanstruktural/batch/delete - Batch delete data jabatan struktural
            Route::delete('/batch/delete', [SimpegDataJabatanStrukturalController::class, 'batchDelete']);

            // PATCH /api/dosen/jabatanstruktural/batch/submit - Batch submit drafts
            Route::patch('/batch/submit', [SimpegDataJabatanStrukturalController::class, 'batchSubmitDrafts']);

            // PATCH /api/dosen/jabatanstruktural/batch/status - Batch update status
            Route::patch('/batch/status', [SimpegDataJabatanStrukturalController::class, 'batchUpdateStatus']);

            // ========================================
            // CONFIGURATION & STATISTICS ROUTES
            // ========================================

            // GET /api/dosen/jabatanstruktural/config/system - Get system configuration
            Route::get('/config/system', [SimpegDataJabatanStrukturalController::class, 'getSystemConfig']);

            // GET /api/dosen/jabatanstruktural/statistics/status - Get status statistics
            Route::get('/statistics/status', [SimpegDataJabatanStrukturalController::class, 'getStatusStatistics']);

            // ========================================
            // FORM & UTILITY ROUTES
            // ========================================

            // GET /api/dosen/jabatanstruktural/form-options - Get dropdown options for forms
            Route::get('/form-options', [SimpegDataJabatanStrukturalController::class, 'getFormOptions']);

            // ========================================
            // DATA MAINTENANCE ROUTES
            // ========================================

            // PATCH /api/dosen/jabatanstruktural/fix-existing - Fix existing data dengan status null
            Route::patch('/fix-existing', [SimpegDataJabatanStrukturalController::class, 'fixExistingData']);

            // PATCH /api/dosen/jabatanstruktural/bulk-fix-existing - Bulk fix all existing data
            Route::patch('/bulk-fix-existing', [SimpegDataJabatanStrukturalController::class, 'bulkFixExistingData']);
        });

        Route::prefix('hubungankerja')->middleware('auth:api')->group(function () {
            // ========================================
            // BASIC CRUD ROUTES
            // ========================================
            Route::get('/form-options', [SimpegDataHubunganKerjaController::class, 'getFormOptions']);
            // GET /api/dosen/hubungankerja - List semua data hubungan kerja pegawai yang login
            Route::get('/', [SimpegDataHubunganKerjaController::class, 'index']);

            // GET /api/dosen/hubungankerja/{id} - Detail data hubungan kerja by ID
            Route::get('/{id}', [SimpegDataHubunganKerjaController::class, 'show']);

            // POST /api/dosen/hubungankerja - Create new data hubungan kerja
            Route::post('/', [SimpegDataHubunganKerjaController::class, 'store']);

            // PUT /api/dosen/hubungankerja/{id} - Update data hubungan kerja by ID
            Route::put('/{id}', [SimpegDataHubunganKerjaController::class, 'update']);

            // DELETE /api/dosen/hubungankerja/{id} - Delete data hubungan kerja by ID
            Route::delete('/{id}', [SimpegDataHubunganKerjaController::class, 'destroy']);

            // ========================================
            // FILE MANAGEMENT ROUTES
            // ========================================

            // GET /api/dosen/hubungankerja/{id}/download - Download file hubungan kerja
            Route::get('/{id}/download', [SimpegDataHubunganKerjaController::class, 'downloadFile']);

            // ========================================
            // BATCH OPERATIONS ROUTES
            // ========================================

            // DELETE /api/dosen/hubungankerja/batch/delete - Batch delete data hubungan kerja
            Route::delete('/batch/delete', [SimpegDataHubunganKerjaController::class, 'batchDelete']);
            Route::patch('/batch/status', [SimpegDataHubunganKerjaController::class, 'batchUpdateStatus']);

            // ========================================
            // CONFIGURATION & STATISTICS ROUTES
            // ========================================

            // GET /api/dosen/hubungankerja/config/system - Get system configuration
            Route::get('/config/system', [SimpegDataHubunganKerjaController::class, 'getSystemConfig']);

            // GET /api/dosen/hubungankerja/statistics/status - Get status statistics
            Route::get('/statistics/status', [SimpegDataHubunganKerjaController::class, 'getStatusStatistics']);

            // ========================================
            // DATA MAINTENANCE ROUTES (Optional - if workflow system added later)
            // ========================================

            // PATCH /api/dosen/hubungankerja/fix-existing - Fix existing data dengan status null
            Route::patch('/fix-existing', [SimpegDataHubunganKerjaController::class, 'fixExistingData']);

            // PATCH /api/dosen/hubungankerja/bulk-fix-existing - Bulk fix all existing data
            Route::patch('/bulk-fix-existing', [SimpegDataHubunganKerjaController::class, 'bulkFixExistingData']);
        });

        //   ROUTES ANGGOTA PROFESI 
        Route::get('anggota-profesi-options', [AnggotaProfesiController::class, 'getOptions']);
        Route::get('anggota-profesi-trash', [AnggotaProfesiController::class, 'trash']);
        Route::patch('anggota-profesi/{id}/restore', [AnggotaProfesiController::class, 'restore']);
        Route::delete('anggota-profesi/{id}/force-delete', [AnggotaProfesiController::class, 'forceDelete']);
        Route::patch('anggota-profesi/{id}/status', [AnggotaProfesiController::class, 'updateStatus']);
        Route::post('anggota-profesi-bulk', [AnggotaProfesiController::class, 'bulkAction']);
        Route::apiResource('anggota-profesi', AnggotaProfesiController::class);
    });

    // Alternative untuk pegawai (jika diperlukan)
    Route::prefix('pegawai')->middleware(['auth:api'])->group(function () {
        Route::prefix('data-diklat')->group(function () {
            Route::get('/', [SimpegDataDiklatController::class, 'index']);
            Route::get('/{id}', [SimpegDataDiklatController::class, 'show']);
            Route::post('/', [SimpegDataDiklatController::class, 'store']);
            Route::put('/{id}', [SimpegDataDiklatController::class, 'update']);
            Route::delete('/{id}', [SimpegDataDiklatController::class, 'destroy']);
            Route::patch('/{id}/submit', [SimpegDataDiklatController::class, 'submitDraft']);
            Route::delete('/batch/delete', [SimpegDataDiklatController::class, 'batchDelete']);
            Route::patch('/batch/submit-drafts', [SimpegDataDiklatController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegDataDiklatController::class, 'batchUpdateStatus']);
            Route::get('/config/system', [SimpegDataDiklatController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegDataDiklatController::class, 'getStatusStatistics']);
            Route::get('/options/filter', [SimpegDataDiklatController::class, 'getFilterOptions']);
            Route::get('/actions/available', [SimpegDataDiklatController::class, 'getAvailableActions']);
            Route::patch('/fix/existing-data', [SimpegDataDiklatController::class, 'fixExistingData']);
            Route::patch('/fix/bulk-existing-data', [SimpegDataDiklatController::class, 'bulkFixExistingData']);
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


        // Data Organisasi Routes
        Route::prefix('dataorganisasi')->group(function () {
            // ========================================
            // CONFIGURATION & STATISTICS ROUTES (HARUS DI ATAS!)
            // ========================================
            Route::get('/config/system', [SimpegDataOrganisasiController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegDataOrganisasiController::class, 'getStatusStatistics']);
            Route::get('/filter-options', [SimpegDataOrganisasiController::class, 'getFilterOptions']);
            Route::get('/available-actions', [SimpegDataOrganisasiController::class, 'getAvailableActions']);

            // ========================================
            // UTILITY ROUTES
            // ========================================
            Route::patch('/fix-existing-data', [SimpegDataOrganisasiController::class, 'fixExistingData']);
            Route::patch('/bulk-fix-existing-data', [SimpegDataOrganisasiController::class, 'bulkFixExistingData']);

            // ========================================
            // BATCH OPERATIONS ROUTES
            // ========================================
            Route::delete('/batch/delete', [SimpegDataOrganisasiController::class, 'batchDelete']);
            Route::patch('/batch/submit', [SimpegDataOrganisasiController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegDataOrganisasiController::class, 'batchUpdateStatus']);

            // ========================================
            // CRUD ROUTES (HARUS DI BAWAH ROUTE SPESIFIK!)
            // ========================================
            Route::get('/', [SimpegDataOrganisasiController::class, 'index']);
            Route::post('/', [SimpegDataOrganisasiController::class, 'store']);
            Route::get('/{id}', [SimpegDataOrganisasiController::class, 'show']);
            Route::put('/{id}', [SimpegDataOrganisasiController::class, 'update']);
            Route::delete('/{id}', [SimpegDataOrganisasiController::class, 'destroy']);

            // ======================================
            // STATUS PENGAJUAN ROUTES
            // ========================================
            Route::patch('/{id}/submit', [SimpegDataOrganisasiController::class, 'submitDraft']);
        });




        // Data Kemampuan Bahasa Routes
        Route::prefix('datakemampuanbahasa')->group(function () {

            // ========================================
            // CONFIGURATION & STATISTICS ROUTES
            // ========================================
            // ⚠️ PENTING: Routes spesifik HARUS di atas routes generic {id}
            Route::get('/config/system', [SimpegDataKemampuanBahasaController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegDataKemampuanBahasaController::class, 'getStatusStatistics']);
            Route::get('/filter-options', [SimpegDataKemampuanBahasaController::class, 'getFilterOptions']);

            // ========================================
            // BATCH OPERATIONS ROUTES
            // ========================================
            Route::delete('/batch/delete', [SimpegDataKemampuanBahasaController::class, 'batchDelete']);
            Route::patch('/batch/submit', [SimpegDataKemampuanBahasaController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegDataKemampuanBahasaController::class, 'batchUpdateStatus']);

            // ========================================
            // DATA CORRECTION ROUTES
            // ========================================
            Route::patch('/fix-existing-data', [SimpegDataKemampuanBahasaController::class, 'fixExistingData']);

            // ========================================
            // CRUD OPERATIONS - Generic routes dengan {id} di BAWAH
            // ========================================
            Route::get('/', [SimpegDataKemampuanBahasaController::class, 'index']);
            Route::post('/', [SimpegDataKemampuanBahasaController::class, 'store']);

            // ⚠️ PENTING: Routes dengan {id} parameter HARUS di paling bawah
            Route::get('/{id}', [SimpegDataKemampuanBahasaController::class, 'show']);
            Route::put('/{id}', [SimpegDataKemampuanBahasaController::class, 'update']);
            Route::delete('/{id}', [SimpegDataKemampuanBahasaController::class, 'destroy']);

            // ========================================
            // STATUS PENGAJUAN ROUTES dengan {id}
            // ========================================
            Route::patch('/{id}/submit', [SimpegDataKemampuanBahasaController::class, 'submitDraft']);

        });


        // Data diklat Routes
        Route::prefix('data-diklat')->group(function () {
            // Main CRUD routes
            Route::get('/', [SimpegDataDiklatController::class, 'index']);
            Route::get('/{id}', [SimpegDataDiklatController::class, 'show']);
            Route::post('/', [SimpegDataDiklatController::class, 'store']);
            Route::put('/{id}', [SimpegDataDiklatController::class, 'update']);
            Route::delete('/{id}', [SimpegDataDiklatController::class, 'destroy']);

            // ======================================
            // STATUS PENGAJUAN ROUTES
            // ======================================
            Route::patch('/{id}/submit', [SimpegDataDiklatController::class, 'submitDraft']);

            // ======================================
            // BATCH OPERATIONS ROUTES
            // ======================================
            Route::patch('/batch/submit-drafts', [SimpegDataDiklatController::class, 'batchSubmitDrafts']);
            Route::delete('/batch/delete', [SimpegDataDiklatController::class, 'batchDelete']);
            Route::patch('/batch/status', [SimpegDataDiklatController::class, 'batchUpdateStatus']);

            // ======================================
            // CONFIGURATION & STATISTICS ROUTES
            // ======================================
            Route::get('/config/system', [SimpegDataDiklatController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegDataDiklatController::class, 'getStatusStatistics']);
            Route::get('/options/filter', [SimpegDataDiklatController::class, 'getFilterOptions']);
            Route::get('/actions/available', [SimpegDataDiklatController::class, 'getAvailableActions']);

            // ======================================
            // UTILITY ROUTES
            // ======================================
            Route::patch('/fix/existing-data', [SimpegDataDiklatController::class, 'fixExistingData']);
            Route::patch('/fix/bulk-existing-data', [SimpegDataDiklatController::class, 'bulkFixExistingData']);
        });

    });





    // Alternative untuk pegawai (jika diperlukan)
    Route::prefix('pegawai')->middleware(['auth:api'])->group(function () {
        Route::prefix('data-diklat')->group(function () {
            Route::get('/', [SimpegDataDiklatController::class, 'index']);
            Route::get('/{id}', [SimpegDataDiklatController::class, 'show']);
            Route::post('/', [SimpegDataDiklatController::class, 'store']);
            Route::put('/{id}', [SimpegDataDiklatController::class, 'update']);
            Route::delete('/{id}', [SimpegDataDiklatController::class, 'destroy']);
            Route::patch('/{id}/submit', [SimpegDataDiklatController::class, 'submitDraft']);
            Route::delete('/batch/delete', [SimpegDataDiklatController::class, 'batchDelete']);
            Route::patch('/batch/submit-drafts', [SimpegDataDiklatController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegDataDiklatController::class, 'batchUpdateStatus']);
            Route::get('/config/system', [SimpegDataDiklatController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegDataDiklatController::class, 'getStatusStatistics']);
            Route::get('/options/filter', [SimpegDataDiklatController::class, 'getFilterOptions']);
            Route::get('/actions/available', [SimpegDataDiklatController::class, 'getAvailableActions']);
            Route::patch('/fix/existing-data', [SimpegDataDiklatController::class, 'fixExistingData']);
            Route::patch('/fix/bulk-existing-data', [SimpegDataDiklatController::class, 'bulkFixExistingData']);

        });

        // Routes for the logged-in user
        Route::middleware(['auth:api'])->prefix('pegawai')->group(function () {
            // Riwayat Pekerjaan Routes
        });


        // Admin routes (optional)
        Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
            // Admin-only bulk operations
            Route::post('/riwayat-pekerjaan/bulk-fix-data', [SimpegDataRiwayatPekerjaanDosenController::class, 'bulkFixExistingData']);

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

            Route::prefix('data-riwayat-pekerjaan-dosen')->group(function () {
                // Configuration & Statistics Routes (specific paths first)
                Route::get('/filter-options', [SimpegDataRiwayatPekerjaanDosenController::class, 'getFilterOptions']);
                Route::get('/available-actions', [SimpegDataRiwayatPekerjaanDosenController::class, 'getAvailableActions']);
                Route::get('/config/system', [SimpegDataRiwayatPekerjaanDosenController::class, 'getSystemConfig']);
                Route::get('/statistics/status', [SimpegDataRiwayatPekerjaanDosenController::class, 'getStatusStatistics']);

                // Batch Operations Routes
                Route::delete('/batch/delete', [SimpegDataRiwayatPekerjaanDosenController::class, 'batchDelete']);
                Route::patch('/batch/submit', [SimpegDataRiwayatPekerjaanDosenController::class, 'batchSubmitDrafts']);
                Route::patch('/batch/status', [SimpegDataRiwayatPekerjaanDosenController::class, 'batchUpdateStatus']);

                // Status Pengajuan Routes
                Route::patch('/{id}/submit', [SimpegDataRiwayatPekerjaanDosenController::class, 'submitDraft']);

                // CRUD Routes (parameterized routes last)
                Route::get('/', [SimpegDataRiwayatPekerjaanDosenController::class, 'index']);
                Route::post('/', [SimpegDataRiwayatPekerjaanDosenController::class, 'store']);
                Route::get('/{id}', [SimpegDataRiwayatPekerjaanDosenController::class, 'show']);
                Route::put('/{id}', [SimpegDataRiwayatPekerjaanDosenController::class, 'update']);
                Route::delete('/{id}', [SimpegDataRiwayatPekerjaanDosenController::class, 'destroy']);
            });

            // Pengajuan Cuti Dosen Routes
            Route::prefix('pengajuan-cuti-dosen')->group(function () {
                Route::get('/', [SimpegPengajuanCutiDosenController::class, 'index']);
                Route::get('/{id}', [SimpegPengajuanCutiDosenController::class, 'show']);
                Route::post('/', [SimpegPengajuanCutiDosenController::class, 'store']);
                Route::put('/{id}', [SimpegPengajuanCutiDosenController::class, 'update']);
                Route::delete('/{id}', [SimpegPengajuanCutiDosenController::class, 'destroy']);

                // Status Pengajuan Routes
                Route::patch('/{id}/submit', [SimpegPengajuanCutiDosenController::class, 'submitDraft']);
                Route::get('/{id}/print', [SimpegPengajuanCutiDosenController::class, 'printCuti']);

                // Batch Operations Routes
                Route::delete('/batch/delete', [SimpegPengajuanCutiDosenController::class, 'batchDelete']);
                Route::patch('/batch/submit', [SimpegPengajuanCutiDosenController::class, 'batchSubmitDrafts']);
                Route::patch('/batch/status', [SimpegPengajuanCutiDosenController::class, 'batchUpdateStatus']);

                // Configuration & Statistics Routes
                Route::get('/config/system', [SimpegPengajuanCutiDosenController::class, 'getSystemConfig']);
                Route::get('/statistics/status', [SimpegPengajuanCutiDosenController::class, 'getStatusStatistics']);
                Route::get('/filter-options', [SimpegPengajuanCutiDosenController::class, 'getFilterOptions']);
                Route::get('/available-actions', [SimpegPengajuanCutiDosenController::class, 'getAvailableActions']);
                Route::get('/remaining-cuti', [SimpegPengajuanCutiDosenController::class, 'getRemainingCuti']);
            });

            // Pengajuan Izin Dosen routes
            Route::prefix('pengajuan-izin-dosen')->group(function () {
                Route::get('/', [SimpegPengajuanIzinDosenController::class, 'index']);
                Route::get('/{id}', [SimpegPengajuanIzinDosenController::class, 'show']);
                Route::post('/', [SimpegPengajuanIzinDosenController::class, 'store']);
                Route::put('/{id}', [SimpegPengajuanIzinDosenController::class, 'update']);
                Route::delete('/{id}', [SimpegPengajuanIzinDosenController::class, 'destroy']);

                // STATUS PENGAJUAN ROUTES
                Route::patch('/{id}/submit', [SimpegPengajuanIzinDosenController::class, 'submitDraft']);

                // BATCH OPERATIONS ROUTES
                Route::delete('/batch/delete', [SimpegPengajuanIzinDosenController::class, 'batchDelete']);
                Route::patch('/batch/submit', [SimpegPengajuanIzinDosenController::class, 'batchSubmitDrafts']);
                Route::patch('/batch/status', [SimpegPengajuanIzinDosenController::class, 'batchUpdateStatus']);

                // CONFIGURATION & STATISTICS ROUTES
                Route::get('/config/system', [SimpegPengajuanIzinDosenController::class, 'getSystemConfig']);
                Route::get('/statistics/status', [SimpegPengajuanIzinDosenController::class, 'getStatusStatistics']);
                Route::get('/filter-options', [SimpegPengajuanIzinDosenController::class, 'getFilterOptions']);
                Route::get('/available-actions', [SimpegPengajuanIzinDosenController::class, 'getAvailableActions']);

                // PRINT ROUTE
                Route::get('/{id}/print', [SimpegPengajuanIzinDosenController::class, 'printIzinDocument']);
            });
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

            // Routes for the logged-in user
            Route::middleware(['auth:api'])->prefix('pegawai')->group(function () {

                // Riwayat Pekerjaan Routes

            });

            // Admin routes (optional)
            Route::middleware(['auth:api', 'role:admin'])->prefix('admin')->group(function () {
                // Admin-only bulk operations
                Route::post('/riwayat-pekerjaan/bulk-fix-data', [SimpegDataRiwayatPekerjaanDosenController::class, 'bulkFixExistingData']);
            });


        });

        //   ROUTES ANGGOTA PROFESI 
        Route::get('anggota-profesi-options', [AnggotaProfesiController::class, 'getOptions']);
        Route::get('anggota-profesi-trash', [AnggotaProfesiController::class, 'trash']);
        Route::patch('anggota-profesi/{id}/restore', [AnggotaProfesiController::class, 'restore']);
        Route::delete('anggota-profesi/{id}/force-delete', [AnggotaProfesiController::class, 'forceDelete']);
        Route::patch('anggota-profesi/{id}/status', [AnggotaProfesiController::class, 'updateStatus']);
        Route::post('anggota-profesi-bulk', [AnggotaProfesiController::class, 'bulkAction']);
        Route::apiResource('anggota-profesi', AnggotaProfesiController::class);

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
