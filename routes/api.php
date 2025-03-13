<?php

use App\Models\JenisSertifikasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JenisSKController;
use App\Http\Controllers\Api\GelarAkademikController;
use App\Http\Controllers\Api\MediaPublikasiController;
use App\Http\Controllers\Api\JenisSertifikasiController;
use App\Http\Controllers\Api\JenisTesController;
use App\Http\Controllers\Api\JenisPKMController;
use App\Http\Controllers\Api\OutputPenelitianController;


// routes/api.php
// Route::get('/test', function () {
//     return response()->json(['message' => 'konek backendnya bang']);
// });



//satria sudirja
// GET http://localhost:8000/api/jenis-sk?sort_by=kode&sort_dir=desc
//  
// GET http://localhost:8000/api/jenis-sk/PK
// PUT http://localhost:8000/api/jenis-sk/PK
// DELETE http://localhost:8000/api/jenis-sk/PK
Route::apiResource('jenis-sk', JenisSKController::class);
//gelar akademik
//GET http://localhost:8000/api/gelar-akademik
//POST http://localhost:8000/api/gelar-akademik
//DELETE http://localhost:8000/api/gelar-akademik/S.S.
//PUT http://localhost:8000/api/gelar-akademik/S.S.
Route::apiResource('gelar-akademik', GelarAkademikController::class);
//mediapublikasi
Route::apiResource('media-publikasi', MediaPublikasiController::class);
//jenis sertifikasi
Route::apiResource('jenis-sertifikasi', JenisSertifikasiController::class);
//jenis tes
Route::apiResource('jenis-tes', JenisTesController::class);
//jenis pkm
Route::apiResource('jenis-pkm', JenisPKMController::class);
//output penelitian
Route::apiResource('output-penelitian', OutputPenelitianController::class);