<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setoran;
use App\Models\Penukaran;
use App\Models\Produk;
use App\Models\Notifikasi;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class NasabahController extends Controller
{
    private FcmService $fcm;

    public function __construct(FcmService $fcm)
    {
        $this->fcm = $fcm;
    }

    // ── Dashboard ──────────────────────────────────────────────────────────

    // ── Dashboard ──────────────────────────────────────────────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $setoran = Setoran::where('user_id', $user->id)
            ->with('kategori')->latest()->take(3)->get()
            ->map(fn($s) => [
                'tipe'  => 'setoran',
                'judul' => $s->kategori->nama ?? '-',
                'poin'  => '+' . number_format($s->poin_didapat, 0, ',', '.'),
                'waktu' => $s->created_at->diffForHumans(),
            ]);

        $penukaran = Penukaran::where('user_id', $user->id)
            ->with('produk')->latest()->take(3)->get()
            ->map(fn($p) => [
                'tipe'  => 'penukaran',
                'judul' => $p->produk->nama ?? 'Pencairan Dana',
                'poin'  => '-' . number_format($p->total_poin, 0, ',', '.'),
                'waktu' => $p->created_at->diffForHumans(),
            ]);

        $transaksiTerakhir = $setoran->concat($penukaran)
            ->sortByDesc('waktu')->take(3)->values();

        // 1. Tambahkan hitungan notifikasi yang belum dibaca
        $unreadCount = Notifikasi::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        // 2. Konversi Poin ke Rupiah (Asumsi 1 Poin = Rp 1)
        $nilaiRupiah = 'Rp ' . number_format($user->total_poin, 0, ',', '.');

        // 3. Masukkan ke dalam response JSON
        return response()->json([
            'total_poin'         => $user->total_poin,
            'nilai_rupiah'       => $nilaiRupiah,
            'unread_notif_count' => $unreadCount,
            'transaksi_terakhir' => $transaksiTerakhir,
        ]);
    }

    // ── Riwayat Setoran ────────────────────────────────────────────────────

    public function riwayatSetoran(Request $request): JsonResponse
    {
        $user  = $request->user();
        $bulan = $request->query('bulan', now()->month);
        $tahun = $request->query('tahun', now()->year);

        $paginated = Setoran::where('user_id', $user->id)
            ->with('kategori')
            ->whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun)
            ->latest()->paginate(10);

        $poinBulanIni = Setoran::where('user_id', $user->id)
            ->where('status', 'selesai')
            ->whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun)
            ->sum('poin_didapat');

        $data = collect($paginated->items())->map(fn($s) => [
            'id'            => $s->id,
            'kategori_nama' => $s->kategori->nama ?? '-',
            'kategori_icon' => $s->kategori->icon_name ?? 'recycling',
            'berat_kg'      => $s->berat_kg,
            'poin_didapat'  => $s->poin_didapat,
            'status'        => $s->status,
            'lokasi_tps'    => $s->lokasi_tps,
            'catatan'       => $s->catatan,
            'tanggal'       => $s->created_at->toIso8601String(),
        ]);

        return response()->json([
            'data'           => $data,
            'total_poin'     => $user->total_poin,
            'poin_bulan_ini' => $poinBulanIni,
            'current_page'   => $paginated->currentPage(),
            'last_page'      => $paginated->lastPage(),
            'total'          => $paginated->total(),
        ]);
    }

    // ── Riwayat Penukaran ──────────────────────────────────────────────────

    public function riwayatPenukaran(Request $request): JsonResponse
    {
        $user  = $request->user();
        $bulan = $request->query('bulan', now()->month);
        $tahun = $request->query('tahun', now()->year);

        $paginated = Penukaran::where('user_id', $user->id)
            ->with('produk')
            ->whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun)
            ->latest()->paginate(10);

        $data = collect($paginated->items())->map(fn($p) => [
            'id'          => $p->id,
            'produk_nama' => $p->produk->nama ?? 'Pencairan Dana',
            'jumlah'      => $p->jumlah,
            'total_poin'  => $p->total_poin,
            'status'      => $p->status,
            'tipe'        => $p->tipe,
            'tanggal'     => $p->created_at->toIso8601String(),
        ]);

        return response()->json([
            'data'         => $data,
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
        ]);
    }

    // ── Tukar Produk ───────────────────────────────────────────────────────

    public function tukarProduk(Request $request): JsonResponse
    {
        $request->validate([
            'produk_id' => 'required|exists:produk,id',
            'jumlah'    => 'required|integer|min:1',
        ]);

        $user   = $request->user();
        $produk = Produk::findOrFail($request->produk_id);
        $jumlah = $request->jumlah;

        if (!$produk->is_active || $produk->isHabis()) {
            return response()->json(['message' => 'Produk tidak tersedia.'], 422);
        }
        if ($produk->stok < $jumlah) {
            return response()->json([
                'message' => "Stok tidak mencukupi. Tersedia: {$produk->stok}",
            ], 422);
        }

        $totalPoin = $produk->biaya_poin * $jumlah;

        if ($user->total_poin < $totalPoin) {
            return response()->json([
                'message' => 'Saldo poin tidak mencukupi.',
            ], 422);
        }

        DB::transaction(function () use ($user, $produk, $jumlah, $totalPoin) {
            $user->kurangiPoin($totalPoin);
            $produk->kurangiStok($jumlah);
            Penukaran::create([
                'user_id'    => $user->id,
                'produk_id'  => $produk->id,
                'tipe'       => 'produk',
                'jumlah'     => $jumlah,
                'total_poin' => $totalPoin,
                'status'     => 'selesai',
            ]);
        });

        // ✅ Kirim push notification
        $this->fcm->kirimKeUser(
            user:  $user,
            judul: 'Penukaran Berhasil! 🎁',
            pesan: "Penukaran {$jumlah}x {$produk->nama} berhasil. Poin berkurang {$totalPoin}.",
            tipe:  'penukaran',
            route: '/riwayat',
        );

        return response()->json([
            'message'    => 'Penukaran berhasil.',
            'sisa_poin'  => $user->fresh()->total_poin,
        ]);
    }

    // ── Tukar Cash ─────────────────────────────────────────────────────────

    // ── Tukar Cash Versi Update ─────────────────────────────────────────────

    public function tukarCash(Request $request): JsonResponse
    {
        $minPencairan = config('app.minimum_pencairan', 10000);

        $request->validate([
            'nominal' => "required|integer|min:{$minPencairan}",
            'metode' => 'required|string|in:Cash,Transfer',
            'tipe_transfer' => 'nullable|string|in:Bank,e-Wallet',
            'nama_bank_ewallet' => 'nullable|string',
            'nomor_rekening' => 'nullable|string',
        ], [
            'nominal.min' => 'Minimum pencairan Rp ' . number_format($minPencairan),
        ]);

        $user    = $request->user();
        $nominal = $request->nominal;

        if ($user->total_poin < $nominal) {
            return response()->json([
                'message' => 'Saldo poin tidak mencukupi.',
            ], 422);
        }

        // Menyusun teks catatan transfer agar Admin Panel bisa melihat detail rekening nasabah
        $keterangan = "Metode: " . $request->metode;
        if ($request->metode === 'Transfer') {
            $keterangan .= " ({$request->tipe_transfer} {$request->nama_bank_ewallet} - No.Rek/HP: {$request->nomor_rekening})";
        }

        DB::transaction(function () use ($user, $nominal, $keterangan, $request) {
            $user->kurangiPoin($nominal);
            
            Penukaran::create([
                'user_id'    => $user->id,
                'tipe'       => 'cash', // 👈 UBAH BARIS INI (Kembalikan ke 'cash')
                'jumlah'     => $nominal,
                'total_poin' => $nominal,
                'status'     => 'pending',
                'catatan'    => $keterangan, 
            ]);
        });

        // ✅ Kirim push notification
        $this->fcm->kirimKeUser(
            user:  $user,
            judul: 'Permintaan Pencairan Dikirim 💰',
            pesan: 'Permintaan pencairan Rp ' . number_format($nominal) . ' sedang diproses admin.',
            tipe:  'penukaran',
            route: '/riwayat',
        );

        return response()->json([
            'message'   => 'Permintaan pencairan berhasil dikirim.',
            'sisa_poin' => $user->fresh()->total_poin,
        ]);
    }

    // ── Notifikasi ─────────────────────────────────────────────────────────

    public function notifikasi(Request $request): JsonResponse
    {
        $user  = $request->user();
        $items = Notifikasi::where('user_id', $user->id)
            ->latest()->take(20)->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'judul'      => $n->judul,
                'pesan'      => $n->pesan,
                'tipe'       => $n->tipe,
                'is_read'    => $n->is_read,
                'created_at' => $n->created_at->toIso8601String(),
            ]);

        $unreadCount = Notifikasi::where('user_id', $user->id)
            ->where('is_read', false)->count();

        return response()->json([
            'data'         => $items,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notifikasi::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi ditandai dibaca.']);
    }
}