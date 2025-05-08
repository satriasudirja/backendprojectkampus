<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CaptchaController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/captcha/slide-captcha', [CaptchaController::class, 'showSlideCaptcha'])->name('captcha.slide-captcha');

// Route untuk menampilkan gambar captcha dari private storage
Route::get('/captcha/image/{type}/{id}', [CaptchaController::class, 'serveImage'])->name('captcha.image');