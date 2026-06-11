<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;


class LogAktivitasAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $logs = AuditLog::with('admin')
            ->latest()
            ->take(50)
            ->get()
            ->map(function ($l) {
                return [
                    'id' => 'audit_' . $l->id,
                    'admin' => $l->admin?->nama_lengkap ?? 'System',
                    'aksi' => $l->aksi,
                    'waktu' => $l->created_at->format('h:i A'),
                    'tanggal' => $l->created_at->format('D, M d, Y'),
                    'tipe' => $l->model ?? 'sistem',
                ];
            })
            ->values();

        return response()->json(['data' => $logs->take(20), 'total' => $logs->count()]);

    }
}

