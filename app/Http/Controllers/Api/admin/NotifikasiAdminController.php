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
}

