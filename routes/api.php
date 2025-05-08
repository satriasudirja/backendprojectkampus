<?php

use App\Models\JenisSertifikasi;
use App\Models\SimpegDaftarCuti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SimpegSukuController;
use App\Http\Controllers\Api\SimpegUserRoleController;
use App\Http\Controllers\Api\SimpegUnitKerjaController;
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




Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    
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
        Route::apiResource('suku', SimpegSukuController::class);
        Route::apiResource('role', SimpegUserRoleController::class);
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