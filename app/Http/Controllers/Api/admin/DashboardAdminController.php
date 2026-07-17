<?php

namespace App\Http\Controllers\Api\Admin;

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

        // ── DATA DETAIL UNTUK TABEL FLUTTER ──
        
        // 1. Data 50 Nasabah Aktif Terbaru
        // 1. Data 50 Nasabah Aktif Terbaru
        $detail_nasabah = User::where('role', 'nasabah')
            ->where('is_verified', true)
            ->latest()
            ->take(50)
            ->get()
            ->map(function($user, $index) {
                return [
                    (string) ($index + 1),
                    $user->nama_lengkap ?? $user->name ?? '-',
                    $user->username ?? $user->id_nasabah ?? '-',
                    'Aktif'
                ];
            })->values()->toArray(); // 👈 Tambahkan ini di ujung

        // 2. Data Top 50 Nasabah dengan Poin Terbanyak
        $detail_poin = User::where('role', 'nasabah')
            ->where('total_poin', '>', 0)
            ->orderByDesc('total_poin')
            ->take(50)
            ->get()
            ->map(function($user, $index) {
                return [
                    (string) ($index + 1),
                    $user->nama_lengkap ?? $user->name ?? '-',
                    number_format($user->total_poin, 0, ',', '.')
                ];
            })->values()->toArray(); // 👈 Tambahkan ini di ujung

        // 3. Data Total Sampah Berdasarkan Kategori
        $detail_sampah = Setoran::with('kategori')
            ->where('status', 'selesai')
            ->get()
            ->groupBy('kategori_id')
            ->map(function($group) {
                $kategori_nama = $group->first()->kategori->nama_kategori ?? $group->first()->kategori->nama ?? 'Lainnya';
                return [
                    $kategori_nama,
                    $group->sum('berat_kg') . ' Kg'
                ];
            })->values()->toArray(); // 👈 Tambahkan ini di ujung

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
            // 👇 Data tabel ditambahkan di sini 👇
            'detail_nasabah' => $detail_nasabah,
            'detail_poin' => $detail_poin,
            'detail_sampah' => $detail_sampah,
        ]);
    }
}