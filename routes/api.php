<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NasabahController;
use App\Http\Controllers\Api\KategoriController;
use App\Http\Controllers\Api\ProdukController;
use App\Http\Controllers\Api\AdminController;
use App\Services\FcmService;
use App\Models\User;


// ── Public routes (tidak perlu token) ────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/kirim-otp', [AuthController::class, 'kirimOtp']);
Route::post('/verifikasi-otp', [AuthController::class, 'verifikasiOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
// ── Protected routes (wajib token Sanctum) ───────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/update-fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::post('/ubah-password', [AuthController::class, 'changePassword']); 

    // Kategori & Produk (bisa diakses nasabah dan admin)
    Route::get('/kategori', [KategoriController::class, 'index']);
    Route::get('/produk', [ProdukController::class, 'index']);
    Route::post('/user/update-foto', [AuthController::class, 'updateFoto']);
    Route::post('/user/update-profil', [AuthController::class, 'updateProfil']);
    // ── Nasabah routes ────────────────────────────────────────────────────
    Route::middleware('role:nasabah')->prefix('nasabah')->group(function () {
        Route::get('/dashboard', [NasabahController::class, 'dashboard']);
        Route::get('/riwayat-setoran', [NasabahController::class, 'riwayatSetoran']);
        Route::get('/riwayat-penukaran', [NasabahController::class, 'riwayatPenukaran']);
        Route::post('/tukar-produk', [NasabahController::class, 'tukarProduk']);
        Route::post('/tukar-cash', [NasabahController::class, 'tukarCash']);
        Route::get('/notifikasi', [NasabahController::class, 'notifikasi']);
        Route::post('/notifikasi/read-all', [NasabahController::class, 'markAllRead']);
    });

    Route::post('/user/simpan-token-fcm', [AuthController::class, 'simpanTokenFcm']);

    // ── Admin routes ──────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);

        // Setoran
        Route::post('/setoran', [AdminController::class, 'simpanSetoran']);
        Route::put('/setoran/{id}/status', [AdminController::class, 'updateStatusSetoran']);

        // Verifikasi nasabah
        Route::get('/nasabah-pending', [AdminController::class, 'nasabahPending']);
        Route::post('/nasabah/{id}/aktifkan', [AdminController::class, 'aktifkanNasabah']);

        // DAFTAR NASABAH AKTIF (Untuk Autocomplete Form Setoran)
        Route::get('/nasabah-aktif', [AdminController::class, 'getNasabahAktif']);

        // Pencairan
        Route::get('/pencairan', [AdminController::class, 'listPencairan']);
        Route::post('/pencairan/{id}/selesai', [AdminController::class, 'selesaikanPencairan']);
        Route::post('/pencairan/{id}/tolak', [AdminController::class, 'tolakPencairan']);

        // Kategori CRUD
        Route::post('/kategori', [KategoriController::class, 'store']);
        Route::put('/kategori/{id}', [KategoriController::class, 'update']);
        Route::patch('/kategori/{id}/toggle', [KategoriController::class, 'toggle']);

        // Produk CRUD
        Route::post('/produk', [ProdukController::class, 'store']);
        Route::put('/produk/{id}', [ProdukController::class, 'update']);
        Route::patch('/produk/{id}/toggle', [ProdukController::class, 'toggle']);

        // Laporan
        Route::get('/laporan', [AdminController::class, 'laporan']);
        Route::get('/log-aktivitas', [AdminController::class, 'logAktivitas']);

        // Pastikan letaknya di dalam grup middleware auth/admin Anda
        Route::get('/notifikasi', [AdminController::class, 'notifikasi']);
        Route::patch('/notifikasi/baca', [AdminController::class, 'tandaiDibaca']);
    });
});