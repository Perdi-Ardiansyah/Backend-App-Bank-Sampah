<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setoran;
use App\Models\Penukaran;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
// Pastikan Anda sudah membuat class export ini. Jika belum, lihat catatan di bawah.
use App\Exports\LaporanExport; 

class LaporanAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $dari = $request->query('dari', Carbon::now()->startOfMonth()->toDateString());
        $sampai = $request->query('sampai', Carbon::now()->toDateString());

        $start = Carbon::parse($dari)->startOfDay();
        $end = Carbon::parse($sampai)->endOfDay();

        // 1. Data Khusus Setoran Sampah
        $setoran = Setoran::with('kategori', 'user')
            ->where('status', 'selesai')
            ->whereBetween('created_at', [$start, $end])
            ->latest()
            ->get()
            ->map(fn($s) => [
                'nasabah' => $s->user->nama_lengkap ?? '-',
                'kategori' => $s->kategori->nama ?? '-',
                'berat' => $s->berat_kg . ' kg',
                'poin' => '+' . number_format($s->poin_didapat, 0, ',', '.'),
                'tanggal' => $s->created_at->format('d M Y'),
            ]);

        // 2. Data Khusus Tukar Sembako (Tipe Produk)
        $tukarSembako = Penukaran::with('produk', 'user')
            ->where('tipe', 'produk')
            ->where('status', 'selesai')
            ->whereBetween('created_at', [$start, $end])
            ->latest()
            ->get()
            ->map(fn($p) => [
                'nasabah' => $p->user->nama_lengkap ?? '-',
                'produk' => $p->produk->nama ?? '-',
                'jumlah' => $p->jumlah . ' pcs',
                'poin' => '-' . number_format($p->total_poin, 0, ',', '.'),
                'tanggal' => $p->created_at->format('d M Y'),
            ]);

        // 3. Data Khusus Tukar Cash / Pencairan Dana
        $tukarCash = Penukaran::with('user')
            ->where('tipe', 'cash')
            ->where('status', 'selesai')
            ->whereBetween('created_at', [$start, $end])
            ->latest()
            ->get()
            ->map(fn($c) => [
                'nasabah' => $c->user->nama_lengkap ?? '-',
                'nominal' => 'Rp ' . number_format($c->total_poin, 0, ',', '.'),
                'metode' => $c->catatan ?? 'Cash', 
                'tanggal' => $c->created_at->format('d M Y'),
            ]);

        $stats = [
            'total_transaksi' => $setoran->count() + $tukarSembako->count() + $tukarCash->count(),
            'volume_kg' => (double) Setoran::where('status', 'selesai')->whereBetween('created_at', [$start, $end])->sum('berat_kg'),
            'nilai_konversi' => (int) Setoran::where('status', 'selesai')->whereBetween('created_at', [$start, $end])->sum('poin_didapat'),
        ];

        return response()->json([
            'stats' => $stats,
            'data' => [
                'setoran' => $setoran,
                'tukar_sembako' => $tukarSembako,
                'tukar_cash' => $tukarCash,
            ]
        ]);
    }

    // ── FUNGSI EKSPOR EXCEL ──
    public function exportExcel(Request $request)
    {
        $tipe = $request->query('tipe', 'setoran');
        $dari = $request->query('dari', Carbon::now()->startOfMonth()->toDateString());
        $sampai = $request->query('sampai', Carbon::now()->toDateString());

        // Mengirimkan parameter tipe dan tanggal ke dalam class LaporanExport
        return Excel::download(new LaporanExport($tipe, $dari, $sampai), "laporan_{$tipe}_{$dari}_sd_{$sampai}.xlsx");
    }

    // ── FUNGSI EKSPOR PDF ──
    public function exportPdf(Request $request)
    {
        $tipe = $request->query('tipe', 'setoran');
        $dari = $request->query('dari', Carbon::now()->startOfMonth()->toDateString());
        $sampai = $request->query('sampai', Carbon::now()->toDateString());

        $start = Carbon::parse($dari)->startOfDay();
        $end = Carbon::parse($sampai)->endOfDay();

        $data = [];
        $judul = 'Laporan';

        // Tarik data spesifik berdasarkan tab yang sedang aktif di Flutter
        if ($tipe === 'setoran') {
            $judul = 'Laporan Setoran Sampah';
            $data = Setoran::with('kategori', 'user')->where('status', 'selesai')->whereBetween('created_at', [$start, $end])->latest()->get();
        } elseif ($tipe === 'produk') {
            $judul = 'Laporan Penukaran Sembako';
            $data = Penukaran::with('produk', 'user')->where('tipe', 'produk')->where('status', 'selesai')->whereBetween('created_at', [$start, $end])->latest()->get();
        } elseif ($tipe === 'cash') {
            $judul = 'Laporan Pencairan Dana';
            $data = Penukaran::with('user')->where('tipe', 'cash')->where('status', 'selesai')->whereBetween('created_at', [$start, $end])->latest()->get();
        }

        // Tembak data ke file view resources/views/admin/laporan/pdf.blade.php
        $pdf = Pdf::loadView('admin.laporan.pdf', compact('data', 'judul', 'tipe', 'dari', 'sampai'));
        
        return $pdf->download("laporan_{$tipe}_{$dari}_sd_{$sampai}.pdf");
    }
}