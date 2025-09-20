<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CaptchaController;
use App\Http\Controllers\Auth\SsoController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test', function () {
    return 'Test route works!';
});
// TAMBAHKAN OVERRIDE TEST INI:
Route::get('/captcha/slide-captcha', function () {
    return 'Route override works! Controller was the problem.';
});

Route::get('/auth/sso/callback', [SsoController::class, 'callback'])->name('sso.callback');

// Route::middleware('web')->group(function () {
    Route::get('/captcha/slide-captcha', [CaptchaController::class, 'showSlideCaptcha'])
         ->name('captcha.slide-captcha');
    Route::get('/captcha/image/{type}/{id}', [CaptchaController::class, 'serveImage'])
         ->name('captcha.image');

//     Route::fallback(function () {
//         return response()->view('errors.404', [], 404);
//     });
// });

// // Route untuk dokumentasi Swagger
// Route::get('/api/documentation', function () {
//     $filePath = storage_path('api-docs/api-docs.json');
    
//     if (!file_exists($filePath)) {
//         return 'File api-docs.json tidak ditemukan di storage/api-docs/';
//     }
    
//     return view('swagger-docs');
// });

// // Route untuk serve JSON
// Route::get('/api-docs.json', function () {
//     $filePath = storage_path('api-docs/api-docs.json');
    
//     if (!file_exists($filePath)) {
//         return response()->json(['error' => 'File not found'], 404);
//     }
    
//     return response()->file($filePath, [
//         'Content-Type' => 'application/json'
//     ]);
// }); // <-- Tambahkan penutup ini