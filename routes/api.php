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
use App\Http\Controllers\Api\SimpegMasterProdiPerguruanTinggiController;
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
use App\Http\Controllers\Api\SimpegPendidikanController;
use App\Http\Controllers\Api\SimpegUnitKerjaController;
use App\Http\Controllers\Api\SimpegRiwayatPendidikanController;
use App\Http\Controllers\Api\SimpegKategoriSertifikasiController;
use App\Http\Controllers\Api\SimpegMediaPublikasiController;
use App\Http\Controllers\Api\SimpegJenjangPendidikanController;
use App\Http\Controllers\Api\SimpegPekerjaanController;
use App\Http\Controllers\Api\SimpegJenisPelanggaranController;
use App\Http\Controllers\Api\SimpegJenisPenghargaanController;
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
use App\Http\Controllers\Api\MonitoringPresensiController;
use App\Http\Controllers\Api\MonitoringKegiatanController;
use App\Http\Controllers\Api\AdminDataKeluargaController;
use App\Http\Controllers\Api\InputPresensiController;
use App\Http\Controllers\Api\MonitoringHubunganKerjaController;
use App\Http\Controllers\Api\AdminMonitoringValidasiIzinController;
use App\Http\Controllers\Api\AdminMonitoringValidasiCutiController;
use App\Http\Controllers\Api\SimpegDataKemampuanBahasaAdminController; 
use App\Http\Controllers\Api\SimpegDataOrganisasiAdminController; 




use App\Models\JenisSertifikasi;



use App\Models\SimpegDaftarCuti;

use App\Http\Controllers\Api\simpegRumpunBidangIlmuController;
use App\Http\Controllers\Api\SimpegGolonganDarahController;
use App\Http\Controllers\Api\SimpegDataRiwayatTesController;
use App\Http\Controllers\Api\SimpegDataSertifikasidosenController;
use App\Http\Controllers\Api\SimpegAgamaController;
use App\Http\Controllers\Api\SimpegDataPenghargaanValidasiController;
use App\Http\Controllers\Api\SimpegDataRiwayatTesAdminController;

use App\Http\Controllers\Api\SimpegBankController;
// use App\Http\Controllers\Api\SimpegPekerjaanController;
use App\Http\Controllers\Api\SimpegBeritaDosenController;
use App\Http\Controllers\Api\SimpegDataSertifikasiAdminController;
use App\Http\Controllers\Api\SimpegDataPendidikanFormalAdminController;
use App\Http\Controllers\Api\SimpegDataRiwayatPekerjaanDosenAdminController;
use App\Http\Controllers\Api\MonitoringValidasiController;
use App\Http\Controllers\Api\SimpegDataHubunganKerjaAdminController;
use App\Http\Controllers\Api\SimpegDataJabatanAkademikAdminController;
use App\Http\Controllers\Api\SimpegDataJabatanFungsionalAdminController;
use App\Http\Controllers\Api\SimpegDataJabatanStrukturalAdminController; 
use App\Http\Controllers\Api\SimpegDataPangkatAdminController;

use App\Http\Controllers\Api\SimpegPendidikanFormalDosenController;


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
        
         Route::get('datapangkatadm/filter-options', [SimpegDataPangkatAdminController::class, 'getFilterOptions']);
    Route::get('datapangkatadm/form-options', [SimpegDataPangkatAdminController::class, 'getFormOptions']);
    Route::get('datapangkatadm/status-statistics', [SimpegDataPangkatAdminController::class, 'getStatusStatistics']);

    Route::patch('datapangkatadm/batch/approve', [SimpegDataPangkatAdminController::class, 'batchApprove']);
    Route::patch('datapangkatadm/batch/reject', [SimpegDataPangkatAdminController::class, 'batchReject']);
    Route::patch('datapangkatadm/batch/todraft', [SimpegDataPangkatAdminController::class, 'batchToDraft']);
    Route::delete('datapangkatadm/batch/delete', [SimpegDataPangkatAdminController::class, 'batchDelete']);

    Route::patch('datapangkatadm/{id}/approve', [SimpegDataPangkatAdminController::class, 'approve']);
    Route::patch('datapangkatadm/{id}/reject', [SimpegDataPangkatAdminController::class, 'reject']);
    Route::patch('datapangkatadm/{id}/todraft', [SimpegDataPangkatAdminController::class, 'toDraft']);

    Route::apiResource('datapangkatadm', SimpegDataPangkatAdminController::class)->except(['create', 'edit']);

          // --- NEW: Routes for Jabatan Struktural Validation ---
    Route::get('datajabatanstrukturaladm/filter-options', [SimpegDataJabatanStrukturalAdminController::class, 'getFilterOptions']);
    Route::get('datajabatanstrukturaladm/form-options', [SimpegDataJabatanStrukturalAdminController::class, 'getFormOptions']);
    Route::get('datajabatanstrukturaladm/status-statistics', [SimpegDataJabatanStrukturalAdminController::class, 'getStatusStatistics']);

    Route::patch('datajabatanstrukturaladm/batch/approve', [SimpegDataJabatanStrukturalAdminController::class, 'batchApprove']);
    Route::patch('datajabatanstrukturaladm/batch/reject', [SimpegDataJabatanStrukturalAdminController::class, 'batchReject']);
    Route::patch('datajabatanstrukturaladm/batch/todraft', [SimpegDataJabatanStrukturalAdminController::class, 'batchToDraft']);
    Route::delete('datajabatanstrukturaladm/batch/delete', [SimpegDataJabatanStrukturalAdminController::class, 'batchDelete']);

    Route::patch('datajabatanstrukturaladm/{id}/approve', [SimpegDataJabatanStrukturalAdminController::class, 'approve']);
    Route::patch('datajabatanstrukturaladm/{id}/reject', [SimpegDataJabatanStrukturalAdminController::class, 'reject']);
    Route::patch('datajabatanstrukturaladm/{id}/todraft', [SimpegDataJabatanStrukturalAdminController::class, 'toDraft']);

    Route::apiResource('datajabatanstrukturaladm', SimpegDataJabatanStrukturalAdminController::class)->except(['create', 'edit']);

        Route::get('datajabatanfungsionaladm/filter-options', [SimpegDataJabatanFungsionalAdminController::class, 'getFilterOptions']);
    Route::get('datajabatanfungsionaladm/form-options', [SimpegDataJabatanFungsionalAdminController::class, 'getFormOptions']);
    Route::get('datajabatanfungsionaladm/status-statistics', [SimpegDataJabatanFungsionalAdminController::class, 'getStatusStatistics']);

    Route::patch('datajabatanfungsionaladm/batch/approve', [SimpegDataJabatanFungsionalAdminController::class, 'batchApprove']);
    Route::patch('datajabatanfungsionaladm/batch/reject', [SimpegDataJabatanFungsionalAdminController::class, 'batchReject']);
    Route::patch('datajabatanfungsionaladm/batch/todraft', [SimpegDataJabatanFungsionalAdminController::class, 'batchToDraft']);
    Route::delete('datajabatanfungsionaladm/batch/delete', [SimpegDataJabatanFungsionalAdminController::class, 'batchDelete']);

    Route::patch('datajabatanfungsionaladm/{id}/approve', [SimpegDataJabatanFungsionalAdminController::class, 'approve']);
    Route::patch('datajabatanfungsionaladm/{id}/reject', [SimpegDataJabatanFungsionalAdminController::class, 'reject']);
    Route::patch('datajabatanfungsionaladm/{id}/todraft', [SimpegDataJabatanFungsionalAdminController::class, 'toDraft']);

    Route::apiResource('datajabatanfungsionaladm', SimpegDataJabatanFungsionalAdminController::class)->except(['create', 'edit']);
         // --- NEW: Routes for Jabatan Akademik Validation ---
    Route::get('datajabatanakademikadm/filter-options', [SimpegDataJabatanAkademikAdminController::class, 'getFilterOptions']);
    Route::get('datajabatanakademikadm/form-options', [SimpegDataJabatanAkademikAdminController::class, 'getFormOptions']);
    Route::get('datajabatanakademikadm/status-statistics', [SimpegDataJabatanAkademikAdminController::class, 'getStatusStatistics']);

    Route::patch('datajabatanakademikadm/batch/approve', [SimpegDataJabatanAkademikAdminController::class, 'batchApprove']);
    Route::patch('datajabatanakademikadm/batch/reject', [SimpegDataJabatanAkademikAdminController::class, 'batchReject']);
    Route::patch('datajabatanakademikadm/batch/todraft', [SimpegDataJabatanAkademikAdminController::class, 'batchToDraft']);
    Route::delete('datajabatanakademikadm/batch/delete', [SimpegDataJabatanAkademikAdminController::class, 'batchDelete']);

    Route::patch('datajabatanakademikadm/{id}/approve', [SimpegDataJabatanAkademikAdminController::class, 'approve']);
    Route::patch('datajabatanakademikadm/{id}/reject', [SimpegDataJabatanAkademikAdminController::class, 'reject']);
    Route::patch('datajabatanakademikadm/{id}/todraft', [SimpegDataJabatanAkademikAdminController::class, 'toDraft']);

    Route::apiResource('datajabatanakademikadm', SimpegDataJabatanAkademikAdminController::class)->except(['create', 'edit']);

    // Filter options, status statistics, and form options must come before the apiResource
    Route::get('datahubungankerjaadm/filter-options', [SimpegDataHubunganKerjaAdminController::class, 'getFilterOptions']);
    Route::get('datahubungankerjaadm/form-options', [SimpegDataHubunganKerjaAdminController::class, 'getFormOptions']);
    Route::get('datahubungankerjaadm/status-statistics', [SimpegDataHubunganKerjaAdminController::class, 'getStatusStatistics']);

    // Batch operations must also come before the apiResource
    Route::patch('datahubungankerjaadm/batch/approve', [SimpegDataHubunganKerjaAdminController::class, 'batchApprove']);
    Route::patch('datahubungankerjaadm/batch/reject', [SimpegDataHubunganKerjaAdminController::class, 'batchReject']);
    Route::patch('datahubungankerjaadm/batch/todraft', [SimpegDataHubunganKerjaAdminController::class, 'batchToDraft']);
    Route::delete('datahubungankerjaadm/batch/delete', [SimpegDataHubunganKerjaAdminController::class, 'batchDelete']);


    // Single record actions (approve, reject, todraft) also need to be explicit if they conflict with resource
    // Usually, Laravel's resource routes handle them well if they are PATCH on {id}/{action}
    // but explicit definition before resource is safer to ensure correct routing
    Route::patch('datahubungankerjaadm/{id}/approve', [SimpegDataHubunganKerjaAdminController::class, 'approve']);
    Route::patch('datahubungankerjaadm/{id}/reject', [SimpegDataHubunganKerjaAdminController::class, 'reject']);
    Route::patch('datahubungankerjaadm/{id}/todraft', [SimpegDataHubunganKerjaAdminController::class, 'toDraft']);

    // Finally, define the API resource
    Route::apiResource('datahubungankerjaadm', SimpegDataHubunganKerjaAdminController::class)->except(['create', 'edit']);




        Route::get('/monitoring/validasi', [MonitoringValidasiController::class, 'index']);
        Route::get('/monitoring/pegawai-list', [MonitoringValidasiController::class, 'getPegawaiList']); // Rute baru


        Route::get('/dashboard', [AdminDashboardController::class, 'getDashboardData']);
        Route::get('/unit-kerja/dropdown', [UnitKerjaController::class, 'getUnitsDropdown']);
        Route::get('/news/{id}', [AdminDashboardController::class, 'getNewsDetail']);


         Route::delete('datariwayatpekerjaanadm/batch/delete', [SimpegDataRiwayatPekerjaanDosenAdminController::class, 'batchDelete']);
    Route::patch('datariwayatpekerjaanadm/batch/approve', [SimpegDataRiwayatPekerjaanDosenAdminController::class, 'batchApprove']);
    Route::patch('datariwayatpekerjaanadm/batch/reject', [SimpegDataRiwayatPekerjaanDosenAdminController::class, 'batchReject']);
    Route::patch('datariwayatpekerjaanadm/batch/todraft', [SimpegDataRiwayatPekerjaanDosenAdminController::class, 'batchToDraft']); // New batch action

    // Routes for fetching options and statistics (also more specific than resource route for /{id})
    Route::get('datariwayatpekerjaanadm/options/filters', [SimpegDataRiwayatPekerjaanDosenAdminController::class, 'getFilterOptions']);
    Route::get('datariwayatpekerjaanadm/options/form', [SimpegDataRiwayatPekerjaanDosenAdminController::class, 'getFormOptions']);
    Route::get('datariwayatpekerjaanadm/statistics', [SimpegDataRiwayatPekerjaanDosenAdminController::class, 'getStatusStatistics']);

    // Route for getting employee options (if needed separately for other forms/filters)
    Route::get('pegawai_options', [SimpegDataRiwayatPekerjaanDosenAdminController::class, 'getPegawaiOptions']); // Re-using if not defined elsewhere

    // Now, define the resource routes.
    // This single line covers: index, store, show, update, destroy
    Route::apiResource('datariwayatpekerjaanadm', SimpegDataRiwayatPekerjaanDosenAdminController::class);

    // Specific routes for single record actions (these will typically match after apiResource, which is fine)
    Route::patch('datariwayatpekerjaanadm/{id}/approve', [SimpegDataRiwayatPekerjaanDosenAdminController::class, 'approve']);
    Route::patch('datariwayatpekerjaanadm/{id}/reject', [SimpegDataRiwayatPekerjaanDosenAdminController::class, 'reject']);
    Route::patch('datariwayatpekerjaanadm/{id}/todraft', [SimpegDataRiwayatPekerjaanDosenAdminController::class, 'toDraft']);
        
    // Routes for batch operations
    Route::delete('datapendidikanformaladm/batch/delete', [SimpegDataPendidikanFormalAdminController::class, 'batchDelete']);
    Route::patch('datapendidikanformaladm/batch/approve', [SimpegDataPendidikanFormalAdminController::class, 'batchApprove']);
    Route::patch('datapendidikanformaladm/batch/reject', [SimpegDataPendidikanFormalAdminController::class, 'batchReject']);
    Route::patch('datapendidikanformaladm/batch/todraft', [SimpegDataPendidikanFormalAdminController::class, 'batchToDraft']); // New batch action

    // Routes for fetching options and statistics (also more specific than resource route for /{id})
    Route::get('datapendidikanformaladm/options/filters', [SimpegDataPendidikanFormalAdminController::class, 'getFilterOptions']);
        Route::get('datapendidikanformaladm/options/form', [SimpegDataPendidikanFormalAdminController::class, 'getFormOptions']);
        Route::get('datapendidikanformaladm/statistics', [SimpegDataPendidikanFormalAdminController::class, 'getStatusStatistics']);

    // Route for getting employee options (if needed separately for other forms/filters)
    // This route is general and can be shared across multiple admin controllers if the method exists.
    Route::get('pegawai_options', [SimpegDataPendidikanFormalAdminController::class, 'getPegawaiOptions']);

    Route::apiResource('datapendidikanformaladm', SimpegDataPendidikanFormalAdminController::class);

    // Specific routes for single record actions (these will typically match after apiResource, which is fine)
    // PATCH /api/admin/datapendidikanformaladm/{id}/approve
    Route::patch('datapendidikanformaladm/{id}/approve', [SimpegDataPendidikanFormalAdminController::class, 'approve']);
    // PATCH /api/admin/datapendidikanformaladm/{id}/reject
    Route::patch('datapendidikanformaladm/{id}/reject', [SimpegDataPendidikanFormalAdminController::class, 'reject']);
    // PATCH /api/admin/datapendidikanformaladm/{id}/todraft
    Route::patch('datapendidikanformaladm/{id}/todraft', [SimpegDataPendidikanFormalAdminController::class, 'toDraft']);


         Route::delete('datasertifikasiadm/batch/delete', [SimpegDataSertifikasiAdminController::class, 'batchDelete']);
    Route::patch('datasertifikasiadm/batch/approve', [SimpegDataSertifikasiAdminController::class, 'batchApprove']);
    Route::patch('datasertifikasiadm/batch/reject', [SimpegDataSertifikasiAdminController::class, 'batchReject']);
    Route::patch('datasertifikasiadm/batch/todraft', [SimpegDataSertifikasiAdminController::class, 'batchToDraft']);

    Route::get('datasertifikasiadm/options/filters', [SimpegDataSertifikasiAdminController::class, 'getFilterOptions']);
    Route::get('datasertifikasiadm/options/form', [SimpegDataSertifikasiAdminController::class, 'getFormOptions']);
    Route::get('datasertifikasiadm/statistics', [SimpegDataSertifikasiAdminController::class, 'getStatusStatistics']);

    Route::get('pegawai_options', [SimpegDataSertifikasiAdminController::class, 'getPegawaiOptions']); // Jika ingin menggunakan yang di controller ini

    Route::apiResource('datasertifikasiadm', SimpegDataSertifikasiAdminController::class);

    Route::patch('datasertifikasiadm/{id}/approve', [SimpegDataSertifikasiAdminController::class, 'approve']);
    Route::patch('datasertifikasiadm/{id}/reject', [SimpegDataSertifikasiAdminController::class, 'reject']);
    Route::patch('datasertifikasiadm/{id}/todraft', [SimpegDataSertifikasiAdminController::class, 'toDraft']);
     // Routes for batch operations
    Route::delete('datariwayattesadm/batch/delete', [SimpegDataRiwayatTesAdminController::class, 'batchDelete']);
    Route::patch('datariwayattesadm/batch/approve', [SimpegDataRiwayatTesAdminController::class, 'batchApprove']);
    Route::patch('datariwayattesadm/batch/reject', [SimpegDataRiwayatTesAdminController::class, 'batchReject']);
    Route::patch('datariwayattesadm/batch/todraft', [SimpegDataRiwayatTesAdminController::class, 'batchToDraft']); // New batch action

    // Routes for fetching options and statistics (also more specific than resource route for /{id})
    Route::get('datariwayattesadm/options/filters', [SimpegDataRiwayatTesAdminController::class, 'getFilterOptions']);
    Route::get('datariwayattesadm/options/form', [SimpegDataRiwayatTesAdminController::class, 'getFormOptions']);
    Route::get('datariwayattesadm/statistics', [SimpegDataRiwayatTesAdminController::class, 'getStatusStatistics']);

    // Route for getting employee options (can be here or a more general 'admin' route)
    Route::get('pegawai_options', [SimpegDataRiwayatTesAdminController::class, 'getPegawaiOptions']);

    // Now, define the resource routes. The /{id} placeholder won't clash with 'batch', 'options', 'statistics' anymore.
    Route::apiResource('datariwayattesadm', SimpegDataRiwayatTesAdminController::class);

    // Specific routes for single record actions (these will typically match after apiResource, which is fine)
    Route::patch('datariwayattesadm/{id}/approve', [SimpegDataRiwayatTesAdminController::class, 'approve']);
    Route::patch('datariwayattesadm/{id}/reject', [SimpegDataRiwayatTesAdminController::class, 'reject']);
    Route::patch('datariwayattesadm/{id}/todraft', [SimpegDataRiwayatTesAdminController::class, 'toDraft']); // New action
   Route::prefix('datapenghargaan')->group(function () {
            // Rute kustom/statis untuk Penghargaan Operasional harus di atas apiResource
            Route::delete('batch/delete', [SimpegDataPenghargaanAdmController::class, 'batchDelete']);
            Route::get('pegawai-options', [SimpegDataPenghargaanAdmController::class, 'getPegawaiOptions']);
            Route::get('filter-options', [SimpegDataPenghargaanAdmController::class, 'getFilterOptions']);
            Route::get('form-options', [SimpegDataPenghargaanAdmController::class, 'getFormOptions']);
            Route::get('statistics', [SimpegDataPenghargaanAdmController::class, 'getStatistics']);
            Route::get('export', [SimpegDataPenghargaanAdmController::class, 'export']);
            Route::post('validate-duplicate', [SimpegDataPenghargaanAdmController::class, 'validateDuplicate']);
            // Kemudian apiResource untuk Penghargaan Operasional
               Route::get('/{id}', [SimpegDataPenghargaanAdmController::class, 'show']);
             Route::delete('/{id}', [SimpegDataPenghargaanAdmController::class, 'destroy']);
            Route::apiResource('/', SimpegDataPenghargaanAdmController::class);
        });


        // --- RUTE SPESIFIK UNTUK VALIDASI PENGHARGAAN (ADMIN PENUNJANG/VALIDASI) ---
        Route::prefix('validasi-penghargaan')->group(function () {
            // Rute kustom/statis untuk Validasi Penghargaan harus di atas apiResource
            Route::patch('batch/approve', [SimpegDataPenghargaanValidasiController::class, 'batchApprove']);
            Route::patch('batch/reject', [SimpegDataPenghargaanValidasiController::class, 'batchReject']);
            Route::patch('batch/tangguhkan', [SimpegDataPenghargaanValidasiController::class, 'batchTangguhkan']);
            // Jika admin validasi diizinkan menghapus data (meskipun mungkin tidak disarankan)
            // Route::delete('batch/delete', [SimpegDataPenghargaanValidasiController::class, 'batchDelete']); 
            Route::get('filter-options', [SimpegDataPenghargaanValidasiController::class, 'getFilterOptions']);
            Route::get('/{id}', [SimpegDataPenghargaanValidasiController::class, 'show']);
            // Rute single action (approve, reject, tangguhkan)
            // Ini harus di atas apiResource karena /{id} akan menangkap semuanya
            Route::patch('{id}/approve', [SimpegDataPenghargaanValidasiController::class, 'approve']);
            Route::patch('{id}/reject', [SimpegDataPenghargaanValidasiController::class, 'reject']);
            Route::patch('{id}/tangguhkan', [SimpegDataPenghargaanValidasiController::class, 'tangguhkan']);

            // Kemudian apiResource untuk Validasi Penghargaan
            // Karena store/update/destroy mungkin tidak diizinkan, gunakan only(['index', 'show'])
            Route::apiResource('/', SimpegDataPenghargaanValidasiController::class)->only(['index', 'show']);
        });
        
        Route::patch('dataorganisasi/batch/approve', [SimpegDataOrganisasiAdminController::class, 'batchApprove']);
        Route::patch('dataorganisasi/batch/reject', [SimpegDataOrganisasiAdminController::class, 'batchReject']);
        Route::delete('dataorganisasi/batch/delete', [SimpegDataOrganisasiAdminController::class, 'batchDelete']);
        Route::get('dataorganisasi/statistics', [SimpegDataOrganisasiAdminController::class, 'getStatusStatistics']);
        Route::get('dataorganisasi/filter-options', [SimpegDataOrganisasiAdminController::class, 'getFilterOptions']);
        Route::patch('dataorganisasi/bulk-fix-existing', [SimpegDataOrganisasiAdminController::class, 'bulkFixExistingData']);
        
       
        Route::patch('dataorganisasi/{id}/approve', [SimpegDataOrganisasiAdminController::class, 'approve']);
        Route::patch('dataorganisasi/{id}/reject', [SimpegDataOrganisasiAdminController::class, 'reject']);

        Route::apiResource('dataorganisasi', SimpegDataOrganisasiAdminController::class);

    Route::prefix('datakemampuanbahasa')->group(function () {
    // Define specific routes first
    Route::get('filter-options', [SimpegDataKemampuanBahasaAdminController::class, 'getFilterOptions']);
    Route::get('statistics', [SimpegDataKemampuanBahasaAdminController::class, 'getStatusStatistics']);

    // Define batch actions BEFORE generic {id} routes
    Route::patch('batch/approve', [SimpegDataKemampuanBahasaAdminController::class, 'batchApprove']);
    Route::patch('batch/reject', [SimpegDataKemampuanBahasaAdminController::class, 'batchReject']);
    Route::delete('batch/delete', [SimpegDataKemampuanBahasaAdminController::class, 'batchDelete']);

    // Then define generic resource routes or {id} routes
    Route::get('/', [SimpegDataKemampuanBahasaAdminController::class, 'index']); // GET /api/admin/datakemampuanbahasa
    Route::post('/', [SimpegDataKemampuanBahasaAdminController::class, 'store']); // POST /api/admin/datakemampuanbahasa
    
    // Resource routes for single item operations
    Route::get('{id}', [SimpegDataKemampuanBahasaAdminController::class, 'show']); // GET /api/admin/datakemampuanbahasa/{id}
    Route::put('{id}', [SimpegDataKemampuanBahasaAdminController::class, 'update']); // PUT /api/admin/datakemampuanbahasa/{id}
    Route::delete('{id}', [SimpegDataKemampuanBahasaAdminController::class, 'destroy']); // DELETE /api/admin/datakemampuanbahasa/{id}

    // Specific actions for a single item (approve/reject) should come after the generic {id}
    Route::patch('{id}/approve', [SimpegDataKemampuanBahasaAdminController::class, 'approve']);
    Route::patch('{id}/reject', [SimpegDataKemampuanBahasaAdminController::class, 'reject']);
});

        Route::prefix('validasi-cuti')->group(function () {
    // List monitoring pengajuan cuti
    Route::get('/', [AdminMonitoringValidasiCutiController::class, 'index']);
    
    // Detail pengajuan cuti
    Route::get('/{id}', [AdminMonitoringValidasiCutiController::class, 'show']);
    
    // Approve pengajuan
    Route::patch('/{id}/approve', [AdminMonitoringValidasiCutiController::class, 'approvePengajuan']);
    
    // Reject/Batalkan pengajuan
    Route::patch('/{id}/reject', [AdminMonitoringValidasiCutiController::class, 'rejectPengajuan']);
    
    // Batch actions
    Route::patch('/batch/approve', [AdminMonitoringValidasiCutiController::class, 'batchApprove']);
    Route::patch('/batch/reject', [AdminMonitoringValidasiCutiController::class, 'batchReject']);
    
    // Statistics
    Route::get('/statistics/dashboard', [AdminMonitoringValidasiCutiController::class, 'getStatistics']);
});


 Route::patch('validasi-izin/batch/approve', [AdminMonitoringValidasiIzinController::class, 'batchApprove']);
    Route::patch('validasi-izin/batch/reject', [AdminMonitoringValidasiIzinController::class, 'batchReject']);

    // Contoh rute khusus yang mungkin punya konflik ID jika diletakkan setelah {id}
    Route::get('validasi-izin/belum-diajukan', [AdminMonitoringValidasiIzinController::class, 'getPegawaiBelumMengajukan']);
    Route::patch('validasi-izin/remind/{id}', [AdminMonitoringValidasiIzinController::class, 'remindPegawai']); // Jika Anda memiliki method ini
    Route::post('validasi-izin/create-for-pegawai/{id}', [AdminMonitoringValidasiIzinController::class, 'createIzinForPegawai']); // Jika Anda memiliki method ini
    
    // Rute untuk mendapatkan statistik
    Route::get('validasi-izin/statistics', [AdminMonitoringValidasiIzinController::class, 'getStatistics']);

    // Rute untuk approve/reject single berdasarkan ID
    Route::patch('validasi-izin/{id}/approve', [AdminMonitoringValidasiIzinController::class, 'approvePengajuan']);
    Route::patch('validasi-izin/{id}/reject', [AdminMonitoringValidasiIzinController::class, 'rejectPengajuan']);

    // Rute utama (index dan show). Pastikan ini diletakkan paling akhir untuk 'validasi-izin'
    // Jika Anda TIDAK menggunakan Route::apiResource dan hanya ingin index dan show:
    Route::get('validasi-izin', [AdminMonitoringValidasiIzinController::class, 'index']);
    Route::get('validasi-izin/{id}', [AdminMonitoringValidasiIzinController::class, 'show']);
        // Di routes/api.php

 Route::get('monitoring/hubungan-kerja/filter-options', [MonitoringHubunganKerjaController::class, 'getFilterOptions']); // Specific filter route
    Route::get('monitoring/hubungan-kerja/{id}/download', [MonitoringHubunganKerjaController::class, 'downloadFile']); // Download specific file
    Route::get('monitoring/hubungan-kerja/pegawai/{pegawaiId}', [MonitoringHubunganKerjaController::class, 'getRiwayatByPegawai']); // Get all history for a specific employee
    Route::get('monitoring/hubungan-kerja/{id}', [MonitoringHubunganKerjaController::class, 'show']); // Detail of a single monitoring record
    Route::get('monitoring/hubungan-kerja', [MonitoringHubunganKerjaController::class, 'index']); // Main index route

    
        Route::controller(InputPresensiController::class)->prefix('input-presensi')->group(function () {
            
            // Main CRUD Operations
            Route::get('/', 'index');                          // GET /api/admin/input-presensi
            Route::post('/', 'store');                         // POST /api/admin/input-presensi
            Route::get('/{id}', 'show');                       // GET /api/admin/input-presensi/{id}
            Route::put('/{id}', 'update');                     // PUT /api/admin/input-presensi/{id}
            Route::delete('/{id}', 'destroy');                 // DELETE /api/admin/input-presensi/{id}
            
            // Batch Operations
            Route::delete('/batch/delete', 'batchDestroy');    // DELETE /api/admin/input-presensi/batch/delete
            
            // Import/Export Operations
            Route::post('/import', 'import');                  // POST /api/admin/input-presensi/import
            
            // Utility Endpoints
            Route::get('/utils/pegawai-list', 'getPegawaiList'); // GET /api/admin/input-presensi/utils/pegawai-list
            Route::get('/utils/jenis-kehadiran-list', 'getJenisKehadiranList'); // GET /api/admin/input-presensi/utils/jenis-kehadiran-list
            
        });

         Route::controller(AdminDataKeluargaController::class)->prefix('data-keluarga')->group(function () {
            Route::get('/', 'index');                          // GET /api/admin/data-keluarga
            Route::get('/{id}', 'show');                       // GET /api/admin/data-keluarga/{id}
            Route::patch('/{id}/approve', 'approve');          // PATCH /api/admin/data-keluarga/{id}/approve
            Route::patch('/{id}/reject', 'reject');            // PATCH /api/admin/data-keluarga/{id}/reject
            Route::post('/batch-approve', 'batchApprove');     // POST /api/admin/data-keluarga/batch-approve
            Route::post('/batch-reject', 'batchReject');       // POST /api/admin/data-keluarga/batch-reject
        });
         Route::prefix('monitoring-kegiatan')->group(function () {
        
        // List kegiatan dengan filter dan search
        Route::get('/', [MonitoringKegiatanController::class, 'index']);
        
        // Detail kegiatan specific
        Route::get('/{id}', [MonitoringKegiatanController::class, 'show']);
        
    });

          Route::get('/monitoring-presensi', [MonitoringPresensiController::class, 'index']);
Route::prefix('evaluasi-kinerja')->group(function () {
        
        /**
         * GET /api/evaluasi-kinerja
         * Mendapatkan daftar pegawai yang dapat dievaluasi oleh penilai yang sedang login.
         * Query Params: ?search=... & per_page=...
         */
        Route::get('/', [EvaluasiKinerjaController::class, 'index']);

        /**
         * POST /api/evaluasi-kinerja
         * Menyimpan data evaluasi kinerja baru untuk seorang pegawai.
         * Body akan divalidasi berdasarkan jenis pegawai (dosen/tendik).
         */
        Route::post('/', [EvaluasiKinerjaController::class, 'store']);

        /**
         * GET /api/evaluasi-kinerja/{pegawaiId}
         * Menampilkan detail informasi seorang pegawai dan riwayat evaluasinya.
         */
        Route::get('/{pegawaiId}', [EvaluasiKinerjaController::class, 'show']);

        /**
         * PUT /api/evaluasi-kinerja/{id}
         * Memperbarui data evaluasi kinerja yang sudah ada berdasarkan ID evaluasi.
         */
        Route::put('/{id}', [EvaluasiKinerjaController::class, 'update']);

        /**
         * DELETE /api/evaluasi-kinerja/{id}
         * Menghapus data evaluasi kinerja berdasarkan ID evaluasi.
         */
        Route::delete('/{id}', [EvaluasiKinerjaController::class, 'destroy']);
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

            // // List all penghargaan dengan filter dan search
            // Route::get('/', [SimpegDataPenghargaanAdmController::class, 'index']);

            // // Create new penghargaan
            // Route::post('/', [SimpegDataPenghargaanAdmController::class, 'store']);

            // // Get detail penghargaan
            // Route::get('/{id}', [SimpegDataPenghargaanAdmController::class, 'show']);

            // // Update penghargaan
            // Route::put('/{id}', [SimpegDataPenghargaanAdmController::class, 'update']);
            // Route::patch('/{id}', [SimpegDataPenghargaanAdmController::class, 'update']); // Alternative method

            // // Delete single penghargaan
            // Route::delete('/{id}', [SimpegDataPenghargaanAdmController::class, 'destroy']);

            // // === Batch Operations ===

            // // Batch delete penghargaan
            // Route::delete('/batch/delete', [SimpegDataPenghargaanAdmController::class, 'batchDelete']);

            // // === Form & Options ===

            // // Get form options untuk dropdown create/edit form
            // Route::get('/form/options', [SimpegDataPenghargaanAdmController::class, 'getFormOptions']);

            // // Get pegawai options untuk dropdown pegawai
            // Route::get('/pegawai/options', [SimpegDataPenghargaanAdmController::class, 'getPegawaiOptions']);

            // // Get filter options untuk dropdown filter
            // Route::get('/filters/options', [SimpegDataPenghargaanAdmController::class, 'getFilterOptions']);

            // // === Validation & Utilities ===

            // // Validate duplicate data
            // Route::post('/validate/duplicate', [SimpegDataPenghargaanAdmController::class, 'validateDuplicate']);

            // // === Reports & Analytics ===

            // // Get statistics untuk dashboard
            // Route::get('/statistics/summary', [SimpegDataPenghargaanAdmController::class, 'getStatistics']);

            // // Export data penghargaan
            // Route::post('/export', [SimpegDataPenghargaanAdmController::class, 'export']);
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
        Route::apiResource('master-prodi-perguruan-tinggi', SimpegMasterProdiPerguruanTinggiController::class);
        Route::apiResource('data-riwayat-pekerjaan', SimpegDataRiwayatPekerjaanController::class);
        Route::apiResource('jam-kerja', SimpegJamKerjaController::class);
        Route::apiResource('master-jenis-sertifikasi', SimpegMasterJenisSertifikasiController::class);
        Route::apiResource('data-sertifikasi', SimpegDataSertifikasiController::class);

        Route::apiResource('kategori-sertifikasi', SimpegKategoriSertifikasiController::class);
        Route::apiResource('hubungan-kerja', SimpegHubunganKerjaController::class);
        Route::apiResource('media-publikasi', SimpegMediaPublikasiController::class);


        Route::apiResource('jenis-penghargaan', SimpegJenisPenghargaanController::class);
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
        Route::apiResource('rumpun-bidang-ilmu', simpegRumpunBidangIlmuController::class);
        Route::apiResource('jenjang-pendidikan', SimpegJenjangPendidikanController::class);
        Route::apiResource('agama', SimpegAgamaController::class);
        Route::apiResource('golongan-darah', SimpegGolonganDarahController::class);
        Route::apiResource('pekerjaan', SimpegPekerjaanController::class);
        Route::apiResource('bank', SimpegBankController::class); // Setelah routes spesifik
    });

    // Dosen Routes
    Route::middleware('role:Dosen,Tenaga Kependidikan,Dosen Praktisi/Industri,Admin')->prefix('dosen')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Dosen Dashboard']);
        });


        // Berita Routes untuk Dosen
        Route::prefix('berita')->group(function () {
            // ========================================
            // CONFIGURATION & STATISTICS ROUTES (HARUS PALING ATAS!)
            // ========================================
            Route::get('/config/system', [SimpegBeritaDosenController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegBeritaDosenController::class, 'getStatusStatistics']);
            Route::get('/filter-options', [SimpegBeritaDosenController::class, 'getFilterOptions']);
            Route::get('/available-actions', [SimpegBeritaDosenController::class, 'getAvailableActions']);
            
            // ========================================
            // BASIC CRUD OPERATIONS (ROUTES TANPA PARAMETER)
            // ========================================
            Route::get('/', [SimpegBeritaDosenController::class, 'index']);
            
            // ========================================
            // ROUTES DENGAN PARAMETER {id} (HARUS PALING BAWAH!)
            // ========================================
            Route::get('/{id}', [SimpegBeritaDosenController::class, 'show']);
            Route::get('/{id}/download', [SimpegBeritaDosenController::class, 'downloadFile']);
        });

        
     Route::prefix('evaluasi-kinerja')->group(function () {
        
        /**
         * GET /api/evaluasi-kinerja
         * Mendapatkan daftar pegawai yang dapat dievaluasi oleh penilai yang sedang login.
         * Query Params: ?search=... & per_page=...
         */
        Route::get('/', [EvaluasiKinerjaController::class, 'index']);

        /**
         * POST /api/evaluasi-kinerja
         * Menyimpan data evaluasi kinerja baru untuk seorang pegawai.
         * Body akan divalidasi berdasarkan jenis pegawai (dosen/tendik).
         */
        Route::post('/', [EvaluasiKinerjaController::class, 'store']);

        /**
         * GET /api/evaluasi-kinerja/{pegawaiId}
         * Menampilkan detail informasi seorang pegawai dan riwayat evaluasinya.
         */
        Route::get('/{pegawaiId}', [EvaluasiKinerjaController::class, 'show']);

        /**
         * PUT /api/evaluasi-kinerja/{id}
         * Memperbarui data evaluasi kinerja yang sudah ada berdasarkan ID evaluasi.
         */
        Route::put('/{id}', [EvaluasiKinerjaController::class, 'update']);

        /**
         * DELETE /api/evaluasi-kinerja/{id}
         * Menghapus data evaluasi kinerja berdasarkan ID evaluasi.
         */
        Route::delete('/{id}', [EvaluasiKinerjaController::class, 'destroy']);
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

        Route::prefix('pendidikanformaldosen')->group(function () {
        // ========================================
        // STATIC ROUTES (HARUS DI ATAS!)
        // ========================================
        
        // Configuration & Statistics Routes
        Route::get('/config/system', [SimpegPendidikanFormalDosenController::class, 'getSystemConfig']);
        Route::get('/statistics/status', [SimpegPendidikanFormalDosenController::class, 'getStatusStatistics']);
        Route::get('/filter-options', [SimpegPendidikanFormalDosenController::class, 'getFilterOptions']);
        Route::get('/available-actions', [SimpegPendidikanFormalDosenController::class, 'getAvailableActions']);
        
        // Utility Routes
        Route::patch('/fix-existing-data', [SimpegPendidikanFormalDosenController::class, 'fixExistingData']);
        Route::patch('/bulk-fix-existing-data', [SimpegPendidikanFormalDosenController::class, 'bulkFixExistingData']);
        
        // ========================================
        // BATCH OPERATIONS ROUTES (HARUS SEBELUM {id} ROUTES!)
        // ========================================
        Route::delete('/batch/delete', [SimpegPendidikanFormalDosenController::class, 'batchDelete']);
        Route::patch('/batch/submit', [SimpegPendidikanFormalDosenController::class, 'batchSubmitDrafts']);
        Route::patch('/batch/status', [SimpegPendidikanFormalDosenController::class, 'batchUpdateStatus']);
        
        // ========================================
        // CRUD OPERATIONS (PARAMETER ROUTES DI BAWAH!)
        // ========================================
        Route::get('/', [SimpegPendidikanFormalDosenController::class, 'index']);
        Route::post('/', [SimpegPendidikanFormalDosenController::class, 'store']);
        Route::get('/{id}', [SimpegPendidikanFormalDosenController::class, 'show']);
        Route::put('/{id}', [SimpegPendidikanFormalDosenController::class, 'update']);
        Route::delete('/{id}', [SimpegPendidikanFormalDosenController::class, 'destroy']);
        
        // ========================================
        // STATUS PENGAJUAN ROUTES (DENGAN {id} DI BAWAH!)
        // ========================================
        Route::patch('/{id}/submit', [SimpegPendidikanFormalDosenController::class, 'submitDraft']);
    });

        Route::prefix('pengajuan-izin-dosen')->group(function () {
                // 1. BATCH OPERATIONS - HARUS PALING ATASAdd commentMore actions
            Route::delete('/batch/delete', [SimpegPengajuanIzinDosenController::class, 'batchDelete']);
            Route::patch('/batch/submit', [SimpegPengajuanIzinDosenController::class, 'batchSubmitDrafts']);
            Route::patch('/batch/status', [SimpegPengajuanIzinDosenController::class, 'batchUpdateStatus']);
            
            // 2. CONFIGURATION & STATISTICS ROUTES
            Route::get('/config/system', [SimpegPengajuanIzinDosenController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegPengajuanIzinDosenController::class, 'getStatusStatistics']);
            Route::get('/filter-options', [SimpegPengajuanIzinDosenController::class, 'getFilterOptions']);
            Route::get('/available-actions', [SimpegPengajuanIzinDosenController::class, 'getAvailableActions']);
            
            // 3. MAIN CRUD ROUTES (TANPA PARAMETER)
            Route::get('/', [SimpegPengajuanIzinDosenController::class, 'index']);
            Route::post('/', [SimpegPengajuanIzinDosenController::class, 'store']);
            
            // 4. ROUTES DENGAN PARAMETER {id} - PALING BAWAH
            Route::get('/{id}', [SimpegPengajuanIzinDosenController::class, 'show']);
            Route::put('/{id}', [SimpegPengajuanIzinDosenController::class, 'update']);
            Route::delete('/{id}', [SimpegPengajuanIzinDosenController::class, 'destroy']);
            
            // 5. ROUTES DENGAN PARAMETER {id} + SUFFIX
            Route::patch('/{id}/submit', [SimpegPengajuanIzinDosenController::class, 'submitDraft']);
            Route::get('/{id}/print', [SimpegPengajuanIzinDosenController::class, 'printIzinDocument']);
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

        // Berita Routes untuk Dosen
        Route::prefix('berita')->group(function () {
            // Basic CRUD Operations
            Route::get('/', [SimpegBeritaDosenController::class, 'index']);
            Route::get('/{id}', [SimpegBeritaDosenController::class, 'show']);
            
            // ========================================
            // DOWNLOAD & FILE OPERATIONS
            // ========================================
            Route::get('/{id}/download', [SimpegBeritaDosenController::class, 'downloadFile']);
            
            // ========================================
            // CONFIGURATION & STATISTICS ROUTES
            // ========================================
            Route::get('/config/system', [SimpegBeritaDosenController::class, 'getSystemConfig']);
            Route::get('/statistics/status', [SimpegBeritaDosenController::class, 'getStatusStatistics']);
            Route::get('/filter-options', [SimpegBeritaDosenController::class, 'getFilterOptions']);
            Route::get('/available-actions', [SimpegBeritaDosenController::class, 'getAvailableActions']);
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
