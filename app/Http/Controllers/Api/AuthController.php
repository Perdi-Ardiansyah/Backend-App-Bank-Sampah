<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Notifikasi;  
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ── Login ──────────────────────────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
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
            'user'  => $this->userResponse($user),
        ]);
    }

    // ── Register ───────────────────────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'username'     => 'required|string|max:50|unique:users|alpha_dash',
            'email'        => 'required|email|unique:users',
            'password'     => 'required|string|min:8',
        ], [
            'username.unique'     => 'Username sudah digunakan.',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, dan underscore.',
            'email.unique'        => 'Email sudah terdaftar.',
            'password.min'        => 'Password minimal 8 karakter.',
        ]);

        $user = User::create([
            'nama_lengkap' => $request->nama_lengkap,
            'username'     => $request->username,
            'email'        => $request->email,
            'password'     => $request->password, // auto-hashed by cast
            'role'         => 'nasabah',
            'is_verified'  => false,
            'id_nasabah'   => User::generateIdNasabah(),
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
        

        return response()->json([
            'message' => 'Pendaftaran berhasil. Tunggu verifikasi admin.',
            'user'    => $this->userResponse($user),
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
            'password_lama'       => 'required|string',
            'password_baru'       => 'required|string|min:8',
            'konfirmasi_password' => 'required|same:password_baru',
        ], [
            'konfirmasi_password.same' => 'Konfirmasi password tidak cocok.',
            'password_baru.min'        => 'Password baru minimal 8 karakter.',
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
            'id'           => $user->id,
            'username'     => $user->username,
            'nama_lengkap' => $user->nama_lengkap,
            'email'        => $user->email,
            'role'         => $user->role,
            'is_verified'  => $user->is_verified,
            'total_poin'   => $user->total_poin,
            'no_hp'        => $user->no_hp,
            'id_nasabah'   => $user->id_nasabah,
            'foto_profil'  => $user->foto_profil,
        ];
    }
}