<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\Setoran;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class LogAktivitasAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $logs = collect();

        Setoran::with(['user', 'admin', 'kategori'])->latest()->take(20)->get()
            ->each(fn($s) => $logs->push([
                'id' => 'setoran_' . $s->id,
                'admin' => $s->admin->nama_lengkap ?? 'System',
                'aksi' => "menginput setoran {$s->berat_kg}kg {$s->kategori->nama}",
                'waktu' => $s->created_at->format('h:i A'),
                'tanggal' => $s->created_at->format('D, M d, Y'),
                'tipe' => 'setoran',
            ]));

        User::where('role', 'nasabah')->where('is_verified', true)
            ->latest('updated_at')->take(10)->get()
            ->each(fn($u) => $logs->push([
                'id' => 'verif_' . $u->id,
                'admin' => 'Admin',
                'aksi' => "memverifikasi nasabah {$u->nama_lengkap}",
                'waktu' => $u->updated_at->format('h:i A'),
                'tanggal' => $u->updated_at->format('D, M d, Y'),
                'tipe' => 'verifikasi',
            ]));

        $sorted = $logs->sortByDesc('tanggal')->take(20)->values();

        return response()->json(['data' => $sorted, 'total' => $sorted->count()]);
    }
}

