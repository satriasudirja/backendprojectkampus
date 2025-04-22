<?php

use App\Models\JenisSertifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\Api\JenisSKController;
// use App\Http\Controllers\Api\GelarAkademikController;
// use App\Http\Controllers\Api\MediaPublikasiController;
// use App\Http\Controllers\Api\JenisSertifikasiController;
// use App\Http\Controllers\Api\JenisTesController;
// use App\Http\Controllers\Api\JenisPKMController;
// use App\Http\Controllers\Api\OutputPenelitianController;
// use App\Http\Controllers\Api\AuthController;
// use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
// use App\Http\Controllers\Api\Admin\DashboardController as DosenDashboardController;
// use App\Http\Controllers\Api\Dosen_Industri\DashboardController as DosenIndustriDashboardController;
// use App\Http\Controllers\Api\Tenaga_Kependidikan\DashboardController as TenagaKependidikanDashboardController;

// routes/api.php
// Route::get('/test', function () {
//     return response()->json(['message' => 'konek backendnya bang']);
// });
// Group untuk autentikasi
// Route::prefix('auth')->group(function () {
//     Route::post('login', [AuthController::class, 'login']);
    
//     // Semua route dalam group ini membutuhkan JWT valid
//     Route::middleware(['jwt.verify'])->group(function () {
//         Route::post('logout', [AuthController::class, 'logout']);
//         Route::post('refresh', [AuthController::class, 'refresh']);
//         Route::get('me', [AuthController::class, 'me']);
//     });
// });

// // Group untuk route yang diproteksi berdasarkan role
// Route::middleware(['jwt.verify'])->group(function () {
//     // Hanya untuk admin (role_id 1) 
//     Route::middleware(['role:1'])->group(function () {
//         Route::get('/admin/dashboard', [AdminDashboardController::class, 'dashboard']);
      
//     });
    
//     // Hanya untuk dosen (role_id 2) 
//     Route::middleware(['role:2'])->group(function () {
//         Route::get('/dosen/dashboard', [DosenDashboardController::class, 'dashboard']);
        
//     });

//     Route::middleware(['role:3'])->group(function () {
//         Route::get('/tenaga_kependidikan/dashboard', [TenagaKependidikanDashboardController::class, 'dashboard']);
        
//     });

//     Route::middleware(['role:4'])->group(function () {
//         Route::get('/dosen_industri/dashboard', [DosenIndustriDashboardController::class, 'dashboard']);
        
//     });
    
    
//     // // Route untuk multiple role
//     // Route::middleware(['role:1,2'])->group(function () {
//     //     Route::get('/common-feature', [CommonController::class, 'index']);
//     // });
// });

// //satria sudirja
// // GET http://localhost:8000/api/jenis-sk?sort_by=kode&sort_dir=desc
// //  
// // GET http://localhost:8000/api/jenis-sk/PK
// // PUT http://localhost:8000/api/jenis-sk/PK
// // DELETE http://localhost:8000/api/jenis-sk/PK
// Route::apiResource('jenis-sk', JenisSKController::class);
// //gelar akademik
// //GET http://localhost:8000/api/gelar-akademik
// //POST http://localhost:8000/api/gelar-akademik
// //DELETE http://localhost:8000/api/gelar-akademik/S.S.
// //PUT http://localhost:8000/api/gelar-akademik/S.S.
// Route::apiResource('gelar-akademik', GelarAkademikController::class);
// //mediapublikasi
// Route::apiResource('media-publikasi', MediaPublikasiController::class);
// //jenis sertifikasi
// Route::apiResource('jenis-sertifikasi', JenisSertifikasiController::class);
// //jenis tes
// Route::apiResource('jenis-tes', JenisTesController::class);
// //jenis pkm
// Route::apiResource('jenis-pkm', JenisPKMController::class);
// //output penelitian
// Route::apiResource('output-penelitian', OutputPenelitianController::class);