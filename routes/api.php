<?php

use App\Models\JenisSertifikasi;
use App\Models\RumpunBidangIlmu;
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
use App\Http\Controllers\Api\SimpegHubunganKerjaController;
use App\Http\Controllers\Api\SimpegJenisHariController;
use App\Http\Controllers\Api\SimpegRumpunBidangIlmuController;
use App\Http\Controllers\Api\SimpegBeritaController;
use App\Http\Controllers\Api\SimpegMasterPerguruanTinggiController;
use App\Http\Controllers\Api\SimpegMasterProdiPerguruanTinggiController;
use App\Http\Controllers\Api\SimpegJenjangPendidikanController;




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
        Route::apiResource('hubungan-kerja', SimpegHubunganKerjaController::class);
        Route::apiResource('jenis-hari', SimpegJenisHariController::class);
        Route::apiResource('rumpun-bidang-ilmu', SimpegRumpunBidangIlmuController::class);
        Route::apiResource('berita', SimpegBeritaController::class);    
        // Route untuk fitur SoftDelete
        Route::get('berita/trash', 'App\Http\Controllers\Api\SimpegBeritaController@trash');
        Route::post('berita/{id}/restore', 'App\Http\Controllers\Api\SimpegBeritaController@restore');
        Route::delete('berita/{id}/force-delete', 'App\Http\Controllers\Api\SimpegBeritaController@forceDelete');


        Route::apiResource('master-perguruan-tinggi', SimpegMasterPerguruanTinggiController::class);
    
        // Endpoint tambahan untuk SoftDelete
        Route::get('master-perguruan-tinggi/trash', 'App\Http\Controllers\Api\SimpegMasterPerguruanTinggiController@trash');
        Route::post('master-perguruan-tinggi/{id}/restore', 'App\Http\Controllers\Api\SimpegMasterPerguruanTinggiController@restore');
        Route::delete('master-perguruan-tinggi/{id}/force-delete', 'App\Http\Controllers\Api\SimpegMasterPerguruanTinggiController@forceDelete');
    
        Route::apiResource('master-prodi-perguruan-tinggi', SimpegMasterProdiPerguruanTinggiController::class);
    
        // Endpoint tambahan untuk SoftDelete
        Route::get('master-prodi-perguruan-tinggi/trash', 'App\Http\Controllers\Api\SimpegMasterProdiPerguruanTinggiController@trash');
        Route::post('master-prodi-perguruan-tinggi/{id}/restore', 'App\Http\Controllers\Api\SimpegMasterProdiPerguruanTinggiController@restore');
        Route::delete('master-prodi-perguruan-tinggi/{id}/force-delete', 'App\Http\Controllers\Api\SimpegMasterProdiPerguruanTinggiController@forceDelete');
    
       
       
        Route::apiResource('jenjang-pendidikan', SimpegJenjangPendidikanController::class);
    
        // Endpoint tambahan untuk SoftDelete
        Route::get('jenjang-pendidikan/trash', [SimpegJenjangPendidikanController::class, 'trash']);
        Route::post('jenjang-pendidikan/{id}/restore', [SimpegJenjangPendidikanController::class, 'restore']);
        Route::delete('jenjang-pendidikan/{id}/force-delete', [SimpegJenjangPendidikanController::class, 'forceDelete']);
        
        // Other routes you already have
        Route::apiResource('master-prodi-perguruan-tinggi', SimpegMasterProdiPerguruanTinggiController::class);
        
        // Endpoint tambahan untuk SoftDelete pada Program Studi
        Route::get('master-prodi-perguruan-tinggi/trash', [SimpegMasterProdiPerguruanTinggiController::class, 'trash']);
        Route::post('master-prodi-perguruan-tinggi/{id}/restore', [SimpegMasterProdiPerguruanTinggiController::class, 'restore']);
        Route::delete('master-prodi-perguruan-tinggi/{id}/force-delete', [SimpegMasterProdiPerguruanTinggiController::class, 'forceDelete']);


        
    });
    
    // Dosen Routes
    Route::middleware('role:Dosen')->prefix('dosen')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Dosen Dashboard']);
            
        });
        Route::get('berita', 'App\Http\Controllers\Api\SimpegBeritaController@index');
        Route::get('berita/{id}', 'App\Http\Controllers\Api\SimpegBeritaController@show');
    });
    
    // Dosen Praktisi Routes
    Route::middleware('role:Dosen Praktisi/Industri')->prefix('dosen-praktisi')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Dosen Praktisi Dashboard']);
        });
        Route::get('berita', 'App\Http\Controllers\Api\SimpegBeritaController@index');
        Route::get('berita/{id}', 'App\Http\Controllers\Api\SimpegBeritaController@show');
    });
    
    // Tenaga Kependidikan Routes
    Route::middleware('role:Tenaga Kependidikan')->prefix('tenaga-kependidikan')->group(function () {
        Route::get('dashboard', function () {
            return response()->json(['message' => 'Tenaga Kependidikan Dashboard']);
        });
        Route::get('berita', 'App\Http\Controllers\Api\SimpegBeritaController@index');
        Route::get('berita/{id}', 'App\Http\Controllers\Api\SimpegBeritaController@show');
    });
});