<?php

namespace App\Exports;

use App\Models\Setoran;
use App\Models\Penukaran;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\Carbon;

// Memakai ShouldAutoSize agar kolom excel otomatis melebar sesuai panjang teks
class LaporanExport implements FromView, ShouldAutoSize 
{
    protected $tipe;
    protected $dari;
    protected $sampai;

    // Menangkap lemparan parameter dari Controller
    public function __construct($tipe, $dari, $sampai)
    {
        $this->tipe = $tipe;
        $this->dari = $dari;
        $this->sampai = $sampai;
    }

    public function view(): View
    {
        // Parse tanggal
        $start = Carbon::parse($this->dari)->startOfDay();
        $end = Carbon::parse($this->sampai)->endOfDay();
        
        $data = [];
        $judul = 'Laporan';

        // 1. Tarik Data Berdasarkan Tipe Tab Laporan
        if ($this->tipe === 'setoran') {
            $judul = 'Laporan Setoran Sampah';
            $data = Setoran::with('kategori', 'user')->where('status', 'selesai')->whereBetween('created_at', [$start, $end])->latest()->get();
        } elseif ($this->tipe === 'produk') {
            $judul = 'Laporan Penukaran Sembako';
            $data = Penukaran::with('produk', 'user')->where('tipe', 'produk')->where('status', 'selesai')->whereBetween('created_at', [$start, $end])->latest()->get();
        } elseif ($this->tipe === 'cash') {
            $judul = 'Laporan Pencairan Dana';
            $data = Penukaran::with('user')->where('tipe', 'cash')->where('status', 'selesai')->whereBetween('created_at', [$start, $end])->latest()->get();
        }

        // 2. Render View menggunakan File Blade PDF yang sudah kita buat
        return view('admin.laporan.pdf', [
            'data'   => $data,
            'judul'  => $judul,
            'tipe'   => $this->tipe,
            'dari'   => $this->dari,
            'sampai' => $this->sampai
        ]);
    }
}