<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CaptchaController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('api/captcha/slide-captcha', [CaptchaController::class, 'showSlideCaptcha'])->name('captcha.slide-captcha');

// Route untuk menampilkan gambar captcha dari private storage
Route::get('api/captcha/image/{type}/{id}', [CaptchaController::class, 'serveImage'])->name('captcha.image');

// Route untuk dokumentasi Swagger
Route::get('/api/documentation', function () {
    $filePath = storage_path('api-docs/api-docs.json');
    
    if (!file_exists($filePath)) {
        return 'File api-docs.json tidak ditemukan di storage/api-docs/';
    }
    
    return view('swagger-docs');
});

// Route untuk serve JSON
Route::get('/api-docs.json', function () {
    $filePath = storage_path('api-docs/api-docs.json');
    
    if (!file_exists($filePath)) {
        return response()->json(['error' => 'File not found'], 404);
    }
    
    return response()->file($filePath, [
        'Content-Type' => 'application/json'
    ]);
}); // <-- Tambahkan penutup ini