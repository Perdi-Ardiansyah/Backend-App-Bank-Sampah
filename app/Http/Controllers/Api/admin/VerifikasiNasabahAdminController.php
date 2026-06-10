<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifikasiNasabahAdminController extends Controller
{
    private FcmService $fcm;

    public function __construct(FcmService $fcm)
    {
        $this->fcm = $fcm;
    }

    public function pending(): JsonResponse
    {
        $pending = User::where('role', 'nasabah')
            ->where('is_verified', false)
            ->select('id', 'nama_lengkap', 'email', 'created_at')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($pending);
    }

    public function aktifkan(Request $request, int $id): JsonResponse
    {
        $nasabah = User::where('role', 'nasabah')
            ->where('is_verified', false)
            ->findOrFail($id);

        $nasabah->update(['is_verified' => true]);

        $this->fcm->kirimKeUser(
            user: $nasabah,
            judul: 'Akun Anda Telah Diverifikasi 🎉',
            pesan: 'Selamat! Akun Anda sudah aktif. Silakan mulai menyetorkan sampah.',
            tipe: 'sistem',
            route: '/home',
        );

        if ($nasabah->fcm_token) {
            \App\Services\FcmService::sendNotification(
                $nasabah->fcm_token,
                'Akun Anda Telah Diverifikasi 🎉',
                'Selamat! Akun Anda sudah aktif. Silakan mulai menyetorkan sampah.',
                ['jenis' => 'sistem', 'route' => '/home']
            );
        }

        return response()->json(['message' => "Nasabah {$nasabah->nama_lengkap} berhasil diaktifkan."]);
    }

    public function nasabahAktif(): JsonResponse
    {
        $nasabah = User::where('role', 'nasabah')
            ->where('is_verified', true)
            ->select('id', 'nama_lengkap as nama', 'id_nasabah')
            ->get();

        return response()->json($nasabah);
    }
}

