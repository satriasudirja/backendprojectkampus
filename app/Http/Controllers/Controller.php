<?php

namespace App\Http\Controllers;

// PERUBAHAN: Menggunakan BaseController dari Illuminate\Routing
// yang sudah memiliki fungsionalitas middleware.
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller extends BaseController
{
    /**
     * Trait ini akan menambahkan method `middleware()` ke controller Anda.
     */
    use AuthorizesRequests, ValidatesRequests;
}
