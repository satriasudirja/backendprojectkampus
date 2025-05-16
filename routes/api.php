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
use App\Http\Controllers\Api\SimpegMediaPublikasiController;

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
use App\Http\Controllers\Api\SimpegHubunganKerjaController;




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






    Route::get('pegawai/riwayat-pangkat/{id}', [PegawaiController::class, 'riwayatPangkat']);
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
        Route::apiResource('kategori-sertifikasi', SimpegKategoriSertifikasiController::class);        
        Route::apiResource('hubungan-kerja', SimpegHubunganKerjaController::class);   
        Route::apiResource('media-publikasi', SimpegMediaPublikasiController::class);  
        
    });
    
    // Dosen Routes
    Route::middleware('role:Dosen')->prefix('dosen')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Dosen Dashboard']);
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