<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NasabahController;
use App\Http\Controllers\Api\KategoriController;
use App\Http\Controllers\Api\ProdukController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\Admin\DashboardAdminController;
use App\Http\Controllers\Api\Admin\SetoranAdminController;
use App\Http\Controllers\Api\Admin\VerifikasiNasabahAdminController;
use App\Http\Controllers\Api\Admin\PencairanAdminController;
// 👇 TAMBAHKAN IMPORT INI 👇
use App\Http\Controllers\Api\Admin\PenukaranAdminController;
use App\Http\Controllers\Api\Admin\LaporanAdminController;
use App\Http\Controllers\Api\Admin\LogAktivitasAdminController;
use App\Http\Controllers\Api\Admin\NotifikasiAdminController;
use App\Services\FcmService;
use App\Models\User;

// ── Public routes (tidak perlu token) ────────────────────────────────────────

Route::post('/login', [AuthController::class, 'login'])->name('login');
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

    Route::get('/user/me', function (Illuminate\Http\Request $request) {
        return response()->json(['data' => $request->user()]);
    });

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
        Route::delete('/notifikasi/bersihkan', [NasabahController::class, 'bersihkanNotifikasi']); // 👈 TAMBAHKAN INI
    });

    Route::post('/user/simpan-token-fcm', [AuthController::class, 'simpanTokenFcm']);

    // ── Admin routes ──────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', DashboardAdminController::class);

        // Setoran
        Route::post('/setoran', [SetoranAdminController::class, 'simpan']);
        Route::put('/setoran/{id}/status', [SetoranAdminController::class, 'updateStatus']);

        // Verifikasi nasabah
        Route::get('/nasabah-pending', [VerifikasiNasabahAdminController::class, 'pending']);
        Route::post('/nasabah/{id}/aktifkan', [VerifikasiNasabahAdminController::class, 'aktifkan']);

        // DAFTAR NASABAH AKTIF (Untuk Autocomplete Form Setoran)
        Route::get('/nasabah-aktif', [VerifikasiNasabahAdminController::class, 'nasabahAktif']);

        // Pencairan Dana
        Route::get('/pencairan', [PencairanAdminController::class, 'list']);
        Route::post('/pencairan/{id}/selesai', [PencairanAdminController::class, 'selesai']);
        Route::post('/pencairan/{id}/tolak', [PencairanAdminController::class, 'tolak']);

        // 👇 TAMBAHKAN BLOK PENUKARAN INI 👇
        // Penukaran Sembako / Produk
        Route::get('/penukaran', [PenukaranAdminController::class, 'list']);
        Route::post('/penukaran/{id}/selesai', [PenukaranAdminController::class, 'selesai']);
        Route::post('/penukaran/{id}/tolak', [PenukaranAdminController::class, 'tolak']);

        // Kategori CRUD
        Route::post('/kategori', [KategoriController::class, 'store']);
        Route::put('/kategori/{id}', [KategoriController::class, 'update']);
        Route::patch('/kategori/{id}/toggle', [KategoriController::class, 'toggle']);

        // Produk CRUD
        Route::post('/produk', [ProdukController::class, 'store']);
        Route::put('/produk/{id}', [ProdukController::class, 'update']);
        Route::patch('/produk/{id}/toggle', [ProdukController::class, 'toggle']);

        // Laporan
        Route::get('/laporan', [LaporanAdminController::class, 'index']);
        Route::get('/log-aktivitas', [LogAktivitasAdminController::class, 'index']);
        Route::get('/laporan/excel', [LaporanAdminController::class, 'exportExcel']);
        Route::get('/laporan/pdf', [LaporanAdminController::class, 'exportPdf']);

        // Notifikasi
        Route::get('/notifikasi', [NotifikasiAdminController::class, 'index']);
        Route::patch('/notifikasi/baca', [NotifikasiAdminController::class, 'tandaiDibaca']);

        // Notifikasi Admin
        Route::get('/notifikasi', [NotifikasiAdminController::class, 'index']);
        Route::patch('/notifikasi/baca', [NotifikasiAdminController::class, 'tandaiDibaca']);
        Route::delete('/notifikasi/bersihkan', [NotifikasiAdminController::class, 'bersihkan']); // 👈 TAMBAHKAN INI
    });
});