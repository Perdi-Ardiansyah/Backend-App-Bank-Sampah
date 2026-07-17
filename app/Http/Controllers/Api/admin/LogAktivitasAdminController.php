<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;


class LogAktivitasAdminController extends Controller
{
    public function index(): JsonResponse
    {
        // 1. Ambil 20 data terbaru langsung dari database
        $logs = AuditLog::with('admin')
            ->latest() // Mengurutkan berdasarkan created_at DESC (terbaru di atas)
            ->limit(20) // Batasi langsung dari query database untuk efisiensi
            ->get()
            ->map(function ($l) {
                return [
                    'id' => 'audit_' . $l->id,
                    'admin' => $l->admin?->nama_lengkap ?? 'System',
                    'aksi' => $l->aksi,
                    // Gunakan diffForHumans untuk tampilan "X menit yang lalu"
                    'waktu' => \Carbon\Carbon::parse($l->created_at)->locale('id')->diffForHumans(),
                    'tanggal' => $l->created_at->format('D, M d, Y'),
                    'tipe' => $l->model ?? 'sistem',
                ];
            });

        return response()->json([
            'data' => $logs,
            'total' => $logs->count()
        ]);
    }
}

