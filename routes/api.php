<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\KendaraanController;
use App\Http\Controllers\Api\AreaParkirController;
use App\Http\Controllers\Api\TarifController;
use App\Http\Controllers\Api\TransaksiController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\LogAktivitasController;


// ============================================================
// PUBLIC – Auth
// ============================================================
Route::post('/login', [AuthController::class, 'login']);

// ============================================================
// AUTHENTICATED – Semua role yang aktif
// ============================================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // --------------------------------------------------------
    // ADMIN – CRUD User, Tarif, Area, Kendaraan; Lihat Log
    // --------------------------------------------------------
    Route::middleware('role:admin')->group(function () {
        // User management
        Route::apiResource('users', UserController::class, [
            'parameters' => ['users' => 'id'],
        ]);

        // Log aktivitas
        Route::get('/logs', [LogAktivitasController::class, 'index']);
        Route::get('/logs/{id}', [LogAktivitasController::class, 'show']);
    });

    // --------------------------------------------------------
    // ADMIN + PETUGAS – Kendaraan, Tarif, Area (read+write)
    // --------------------------------------------------------
    Route::middleware('role:admin,petugas')->group(function () {
        // Kendaraan CRUD
        Route::get('/kendaraan/cari', [KendaraanController::class, 'cariPlat']);
        Route::apiResource('kendaraan', KendaraanController::class, [
            'parameters' => ['kendaraan' => 'id'],
        ]);

        // Transaksi – list & detail
        Route::get('/transaksi', [TransaksiController::class, 'index']);
        Route::get('/transaksi/{id}', [TransaksiController::class, 'show']);
    });

    // --------------------------------------------------------
    // ADMIN – Tarif & Area CRUD (write) – read allowed below
    // --------------------------------------------------------
    Route::middleware('role:admin')->group(function () {
        Route::post('/tarif',        [TarifController::class, 'store']);
        Route::put('/tarif/{id}',    [TarifController::class, 'update']);
        Route::patch('/tarif/{id}',  [TarifController::class, 'update']);
        Route::delete('/tarif/{id}', [TarifController::class, 'destroy']);

        Route::post('/area',         [AreaParkirController::class, 'store']);
        Route::put('/area/{id}',     [AreaParkirController::class, 'update']);
        Route::patch('/area/{id}',   [AreaParkirController::class, 'update']);
        Route::delete('/area/{id}',  [AreaParkirController::class, 'destroy']);
    });

    // Tarif & Area – read untuk semua role
    Route::get('/tarif',       [TarifController::class, 'index']);
    Route::get('/tarif/{id}',  [TarifController::class, 'show']);
    Route::get('/area',        [AreaParkirController::class, 'index']);
    Route::get('/area/{id}',   [AreaParkirController::class, 'show']);

    // --------------------------------------------------------
    // PETUGAS – Proses masuk & keluar kendaraan, cetak struk
    // --------------------------------------------------------
    Route::middleware('role:admin,petugas')->group(function () {
        Route::post('/parkir/masuk',       [TransaksiController::class, 'kendaraanMasuk']);
        Route::post('/parkir/keluar',      [TransaksiController::class, 'kendaraanKeluar']);
        Route::get('/parkir/struk/{id}',   [TransaksiController::class, 'cetakStruk']);
    });

    // --------------------------------------------------------
    // OWNER – Laporan
    // --------------------------------------------------------
    Route::middleware('role:owner,admin')->group(function () {
        Route::get('/laporan/ringkasan',       [LaporanController::class, 'ringkasan']);
        Route::get('/laporan/detail',          [LaporanController::class, 'detail']);
        Route::get('/laporan/kendaraan-aktif', [LaporanController::class, 'kendaraanAktif']);
    });
});
