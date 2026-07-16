<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use Illuminate\Http\JsonResponse;

class NotifikasiAdminController extends Controller
{
    public function index(): JsonResponse
    {
        // Menjaga behavior original: filter tipe != 'sistem'
        $notif = Notifikasi::where('user_id', auth()->id())
            ->where('tipe', '!=', 'sistem')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notif);
    }

    public function tandaiDibaca(): JsonResponse
    {
        Notifikasi::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi telah ditandai dibaca.']);
    }

    public function bersihkan()
    {
        try {
            $adminId = auth()->user()->id;

            // Menghapus semua notifikasi yang ditujukan untuk admin yang sedang login
            \App\Models\Notifikasi::where('user_id', $adminId)->delete();

            // 🔴 Opsional: Catat ke Audit Log jika Anda ingin aksi ini terekam
            \App\Models\AuditLog::create([
                'admin_id' => $adminId,
                'aksi' => 'Membersihkan semua riwayat notifikasi',
                'model' => 'Notifikasi',
                'model_id' => 0,
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Semua notifikasi berhasil dibersihkan.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membersihkan notifikasi: ' . $e->getMessage()
            ], 500);
        }
    }
}

