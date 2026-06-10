<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\Setoran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaporanAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $dari = $request->query('dari', now()->startOfMonth()->toDateString());
        $sampai = $request->query('sampai', now()->endOfMonth()->toDateString());

        $setoran = Setoran::with(['user', 'kategori'])
            ->whereBetween('created_at', [$dari . ' 00:00:00', $sampai . ' 23:59:59'])
            ->where('status', 'selesai')->latest()->paginate(10);

        $stats = Setoran::whereBetween('created_at', [$dari . ' 00:00:00', $sampai . ' 23:59:59'])
            ->where('status', 'selesai')
            ->selectRaw('COUNT(*) as total_transaksi, SUM(berat_kg) as volume_kg, SUM(poin_didapat) as total_poin')
            ->first();

        $data = collect($setoran->items())->map(fn($s) => [
            'id' => 'TRX-' . str_pad($s->id, 4, '0', STR_PAD_LEFT),
            'tanggal' => $s->created_at->format('d M Y, H:i'),
            'nasabah' => $s->user->nama_lengkap,
            'kategori' => $s->kategori->nama,
            'berat_kg' => $s->berat_kg,
            'poin_didapat' => $s->poin_didapat,
        ]);

        return response()->json([
            'stats' => [
                'total_transaksi' => $stats->total_transaksi ?? 0,
                'volume_kg' => $stats->volume_kg ?? 0,
                'nilai_konversi' => $stats->total_poin ?? 0,
            ],
            'data' => $data,
            'current_page' => $setoran->currentPage(),
            'last_page' => $setoran->lastPage(),
        ]);
    }
}

