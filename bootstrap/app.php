<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule; // <-- PASTIKAN IMPORT INI ADA
use App\Console\Commands\CleanupTrashFiles;
use App\Console\Commands\GenerateMonthlyPayroll; // <-- DAN IMPORT INI JUGA

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
        $middleware->alias([
            'jwt.verify' => \App\Http\Middleware\JwtMiddleware::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withCommands([
        // Register custom commands
        CleanupTrashFiles::class,
        GenerateMonthlyPayroll::class, // <-- Daftarkan command payroll di sini
    ])
    ->withSchedule(function (Schedule $schedule) { // <-- Panggil withSchedule di sini
        // Jalankan command payroll pada tanggal 1 setiap bulan jam 02:00 pagi.
        // Command ini akan memproses gaji untuk BULAN SEBELUMNYA.
        // Contoh: Pada 1 Juli 02:00, command akan berjalan untuk memproses gaji bulan Juni.
        $schedule->command('payroll:generate')
                 ->monthlyOn(1, '02:00')
                 ->timezone('Asia/Jakarta') // Tentukan timezone server Anda
                 ->withoutOverlapping(); // Mencegah tugas berjalan ganda jika proses sebelumnya belum selesai
    })
    ->create();
