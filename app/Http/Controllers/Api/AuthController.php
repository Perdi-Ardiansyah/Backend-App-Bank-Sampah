<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\ResetPasswordOtp;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use App\Services\FcmService;

class AuthController extends Controller
{
    // ── Login ──────────────────────────────────────────────────────────────

   public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'fcm_token' => 'nullable|string', // 👈 1. Tambahkan validasi fcm_token
        ]);

        // Cari user by username ATAU email
        $user = User::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Username atau password salah.',
            ], 401);
        }

        // 👈 2. Simpan fcm_token ke database jika dikirim dari Flutter
        if ($request->filled('fcm_token')) {
            $user->update([
                'fcm_token' => $request->fcm_token
            ]);
        }

        // Hapus token lama agar tidak menumpuk
        $user->tokens()->delete();

        // Buat token baru dengan expiry dari .env
        $expiryMinutes = config('sanctum.expiration'); // null = tidak expired
        $token = $user->createToken(
            'auth_token',
            ['*'],
            $expiryMinutes ? now()->addMinutes($expiryMinutes) : null
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userResponse($user),
        ]);
    }

    // ── Register ───────────────────────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users|alpha_dash',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
        ], [
            'username.unique' => 'Username sudah digunakan.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, dan underscore.',
            'email.unique' => 'Email sudah terdaftar.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $user = User::create([
            'nama_lengkap' => $request->nama_lengkap,
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password, // auto-hashed by cast
            'role' => 'nasabah',
            'is_verified' => false,
            'id_nasabah' => User::generateIdNasabah(),
        ]);

        $admin = \App\Models\User::where('role', 'admin')->first();

        // 2. Jika admin ditemukan, kirim notifikasi ke ID Admin tersebut
        if ($admin) {
            \App\Models\Notifikasi::kirim(
                $admin->id,
                "{$user->nama_lengkap} mengajukan pendaftaran", // 👈 Sudah dinamis!
                "Nasabah baru bernama {$user->nama_lengkap} memerlukan verifikasi data segera.",
                'verifikasi'
            );
        }

        $admins = User::where('role', 'admin')->get();
        $fcm = new FcmService();

        // Tembak notifikasi ke masing-masing admin
        foreach ($admins as $admin) {
            $fcm->kirimKeUser(
                $admin,
                'Nasabah Baru Butuh Verifikasi',
                "Ada akun baru atas nama {$request->nama_lengkap} menunggu persetujuan.",
                'verifikasi'
            );
        }


        return response()->json([
            'message' => 'Pendaftaran berhasil. Tunggu verifikasi admin.',
            'user' => $this->userResponse($user),
        ], 201);
    }

    // ── Logout ─────────────────────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        // Hapus hanya token yang sedang digunakan
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    // ── Change Password ────────────────────────────────────────────────────

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'password_lama' => 'required|string',
            'password_baru' => 'required|string|min:8',
            'konfirmasi_password' => 'required|same:password_baru',
        ], [
            'konfirmasi_password.same' => 'Konfirmasi password tidak cocok.',
            'password_baru.min' => 'Password baru minimal 8 karakter.',
        ]);

        $user = $request->user();

        if (!Hash::check($request->password_lama, $user->password)) {
            return response()->json([
                'message' => 'Password lama tidak sesuai.',
            ], 422);
        }

        $user->update(['password' => $request->password_baru]);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    // ── Update FCM Token ───────────────────────────────────────────────────

    public function updateFcmToken(Request $request): JsonResponse
    {
        $request->validate(['fcm_token' => 'required|string']);
        $request->user()->update(['fcm_token' => $request->fcm_token]);
        return response()->json(['message' => 'FCM token diperbarui.']);
    }

    // ── Helper ─────────────────────────────────────────────────────────────

    private function userResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'nama_lengkap' => $user->nama_lengkap,
            'email' => $user->email,
            'role' => $user->role,
            'is_verified' => $user->is_verified,
            'total_poin' => $user->total_poin,
            'no_hp' => $user->no_hp,
            'id_nasabah' => $user->id_nasabah,
            'foto_profil' => $user->foto_profil,
        ];
    }

    public function updateFoto(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Maksimal 2MB
        ]);

        $user = auth()->user(); // Ambil user yang sedang login

        if ($request->hasFile('foto')) {
            if ($user->foto && Storage::disk('public')->exists($user->foto)) {
                Storage::disk('public')->delete($user->foto);
            }

            $path = $request->file('foto')->store('profil', 'public');

            // ❌ JANGAN PAKAI INI: $user->update(['foto' => $path]);

            //  GANTI DENGAN CARA INI (Jauh lebih aman dan pasti masuk):
            $user->foto_profil = $path; // 👈 Pastikan kata 'foto' ini sama dengan nama kolom di database Anda
            $user->save(); // 👈 Memaksa laravel menyimpan langsung

            return response()->json([
                'success' => true,
                'message' => 'Foto profil berhasil diperbarui',
                'foto_url' => asset('storage/' . $path)
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Tidak ada foto yang diunggah'], 400);
    }

    public function updateProfil(Request $request)
    {
        $user = auth()->user();

        // Validasi input data nasabah
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'no_hp' => 'required|string|max:15|unique:users,no_hp,' . $user->id,
        ]);

        // Update data di database
        $user->nama_lengkap = $request->nama_lengkap;
        $user->email = $request->email;
        $user->no_hp = $request->no_hp;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'user' => $user
        ]);
    }

    public function simpanTokenFcm(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = auth()->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Token FCM berhasil disimpan ke database'
        ]);
    }

    public function updateToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required']);

        // Simpan token ke user yang sedang login
        $user = auth()->user();
        $user->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'Token FCM berhasil disimpan.']);
    }

    public function kirimOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'Email tidak terdaftar di sistem kami.'], 404);
        }

        // Buat 6 digit angka acak
        $otp = rand(100000, 999999);

        // Simpan ke Cache selama 10 Menit
        Cache::put('otp_' . $user->email, $otp, now()->addMinutes(10));

        // Kirim Email
        Mail::to($user->email)->send(new ResetPasswordOtp($otp));

        return response()->json(['message' => 'Kode OTP berhasil dikirim ke email Anda.']);
    }

    // 2. Fungsi Verifikasi OTP (Hanya untuk mengecek apakah kodenya benar)
    public function verifikasiOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|numeric'
        ]);

        $cachedOtp = Cache::get('otp_' . $request->email);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'Kode OTP salah atau sudah kedaluwarsa.'], 400);
        }

        return response()->json(['message' => 'OTP Valid! Silakan masukkan password baru.']);
    }

    // 3. Fungsi Simpan Password Baru
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'otp'      => 'required|numeric',
            'password' => 'required|min:8|confirmed' 
            // 'confirmed' berarti di Flutter harus mengirim parameter 'password_confirmation' juga
        ]);

        // Cek lagi OTP-nya untuk keamanan ganda sebelum mengubah password
        $cachedOtp = Cache::get('otp_' . $request->email);
        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'Sesi reset password tidak valid.'], 400);
        }

        // Ubah Password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Hapus OTP dari memori agar tidak bisa dipakai 2 kali
        Cache::forget('otp_' . $request->email);

        return response()->json(['message' => 'Password berhasil diubah! Silakan login.']);
    }
}