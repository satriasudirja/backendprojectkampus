<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// routes/api.php
Route::get('/test', function () {
    return response()->json(['message' => 'konek backendnya bang']);
});
