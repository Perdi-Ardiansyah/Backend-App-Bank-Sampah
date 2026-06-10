<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\Setoran;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardAdminController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $grafik_mingguan = [];
        $hariIndo = ['Sun' => 'Min', 'Mon' => 'Sen', 'Tue' => 'Sel', 'Wed' => 'Rab', 'Thu' => 'Kam', 'Fri' => 'Jum', 'Sat' => 'Sab'];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);

            $total_kg = Setoran::whereDate('created_at', $date->toDateString())
                ->where('status', 'selesai')
                ->sum('berat_kg');

            $grafik_mingguan[] = [
                'day' => $hariIndo[$date->format('D')],
                'berat' => (float) $total_kg,
            ];
        }

        return response()->json([
            'total_nasabah' => User::where('role', 'nasabah')->where('is_verified', true)->count(),
            'nasabah_hari_ini' => User::where('role', 'nasabah')->whereDate('created_at', today())->count(),
            'total_poin_beredar' => User::where('role', 'nasabah')->sum('total_poin'),
            'menunggu_verifikasi' => User::where('role', 'nasabah')->where('is_verified', false)->count(),
            'total_sampah_kg' => Setoran::where('status', 'selesai')->sum('berat_kg'),
            'setoran_hari_ini' => [
                'total_kg' => Setoran::whereDate('created_at', today())->sum('berat_kg'),
                'total_transaksi' => Setoran::whereDate('created_at', today())->count(),
                'poin_diberikan' => Setoran::whereDate('created_at', today())->where('status', 'selesai')->sum('poin_didapat'),
            ],
            'grafik_mingguan' => $grafik_mingguan,
        ]);
    }
}

