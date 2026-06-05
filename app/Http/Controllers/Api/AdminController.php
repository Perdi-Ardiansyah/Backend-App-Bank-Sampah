<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setoran;
use App\Models\Penukaran;
use App\Models\KategoriSampah;
use App\Models\Notifikasi;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    private FcmService $fcm;

    public function __construct(FcmService $fcm)
    {
        $this->fcm = $fcm;
    }

    // ── Dashboard ──────────────────────────────────────────────────────────

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'total_nasabah'       => User::where('role', 'nasabah')->where('is_verified', true)->count(),
            'nasabah_hari_ini'    => User::where('role', 'nasabah')->whereDate('created_at', today())->count(),
            'total_poin_beredar'  => User::where('role', 'nasabah')->sum('total_poin'),
            'menunggu_verifikasi' => User::where('role', 'nasabah')->where('is_verified', false)->count(),
            'total_sampah_kg'     => Setoran::where('status', 'selesai')->sum('berat_kg'),
            'setoran_hari_ini'    => [
                'total_kg'        => Setoran::whereDate('created_at', today())->sum('berat_kg'),
                'total_transaksi' => Setoran::whereDate('created_at', today())->count(),
                'poin_diberikan'  => Setoran::whereDate('created_at', today())->where('status', 'selesai')->sum('poin_didapat'),
            ],
        ]);
    }

    // ── Simpan Setoran ─────────────────────────────────────────────────────

    public function simpanSetoran(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'     => 'required|exists:users,id',
            'kategori_id' => 'required|exists:kategori_sampah,id',
            'berat_kg'    => 'required|numeric|min:0.1',
            'lokasi_tps'  => 'nullable|string',
            'catatan'     => 'nullable|string',
        ]);

        $nasabah  = User::findOrFail($request->user_id);
        $kategori = KategoriSampah::findOrFail($request->kategori_id);
        $poin     = $kategori->hitungPoin($request->berat_kg);

        DB::transaction(function () use ($request, $nasabah, $kategori, $poin) {
            Setoran::create([
                'user_id'      => $nasabah->id,
                'kategori_id'  => $kategori->id,
                'admin_id'     => $request->user()->id,
                'berat_kg'     => $request->berat_kg,
                'poin_didapat' => $poin,
                'status'       => 'selesai',
                'lokasi_tps'   => $request->lokasi_tps,
                'catatan'      => $request->catatan,
            ]);
            $nasabah->tambahPoin($poin);
        });

        // ✅ Push notifikasi ke nasabah
        $this->fcm->kirimKeUser(
            user:  $nasabah,
            judul: 'Setoran Berhasil Diverifikasi ✅',
            pesan: "Setoran {$request->berat_kg}kg {$kategori->nama} berhasil dicatat. Anda mendapat {$poin} poin.",
            tipe:  'setoran',
            route: '/riwayat',
        );

        return response()->json([
            'message'       => 'Setoran berhasil disimpan.',
            'poin_diberikan'=> $poin,
        ], 201);
    }

    // ── Verifikasi Nasabah ─────────────────────────────────────────────────

    public function nasabahPending()
    {
        // Pastikan Anda memanggil kolom-kolom yang akan ditampilkan di Flutter
        $pending = User::where('role', 'nasabah')
            ->where('is_verified', false)
            ->select('id', 'nama_lengkap', 'email', 'created_at') 
            // ->select('id', 'nama_lengkap', 'email', 'created_at', 'tipe_nasabah', 'lokasi') // <-- Gunakan ini jika di DB Anda ada kolom tipe_nasabah dan lokasi
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($pending);
    }

    public function aktifkanNasabah(Request $request, int $id): JsonResponse
    {
        $nasabah = User::where('role', 'nasabah')->where('is_verified', false)->findOrFail($id);
        $nasabah->update(['is_verified' => true]);

        // ✅ Push notifikasi ke nasabah
        $this->fcm->kirimKeUser(
            user:  $nasabah,
            judul: 'Akun Anda Telah Diverifikasi 🎉',
            pesan: 'Selamat! Akun Anda sudah aktif. Silakan mulai menyetorkan sampah.',
            tipe:  'sistem',
            route: '/home',
        );

        return response()->json(['message' => "Nasabah {$nasabah->nama_lengkap} berhasil diaktifkan."]);
    }

    // ── Pencairan ──────────────────────────────────────────────────────────

   public function listPencairan(): JsonResponse
    {
        $list = Penukaran::where('tipe', 'cash')->with('user')->latest()->paginate(10);

        $data = collect($list->items())->map(fn($p) => [
            'id'           => $p->id,
            'nama_nasabah' => $p->user->nama_lengkap,
            'id_nasabah'   => $p->user->id_nasabah,
            'nominal'      => $p->jumlah,
            'status'       => $p->status,
            'metode_cash'  => $p->metode_cash,
            'no_rekening'  => $p->no_rekening,
            'catatan'      => $p->catatan, // 👈 INI DIA BARIS YANG HILANG!
            'tanggal'      => $p->created_at->format('d M Y, H:i'),
        ]);

        $totalTertunda = Penukaran::where('tipe', 'cash')->where('status', 'pending')->sum('jumlah');

        return response()->json([
            'data'           => $data,
            'total_tertunda' => $totalTertunda,
            'current_page'   => $list->currentPage(),
            'last_page'      => $list->lastPage(),
        ]);
    }

    public function selesaikanPencairan(int $id): JsonResponse
    {
        $pencairan = Penukaran::where('tipe', 'cash')->where('status', 'pending')->findOrFail($id);
        $pencairan->update(['status' => 'selesai']);

        // ✅ Push notifikasi ke nasabah
        $this->fcm->kirimKeUser(
            user:  $pencairan->user,
            judul: 'Pencairan Dana Berhasil 💰',
            pesan: 'Pencairan Rp ' . number_format($pencairan->jumlah) . ' telah diproses dan dikirim.',
            tipe:  'penukaran',
            route: '/riwayat',
        );

        return response()->json(['message' => 'Pencairan berhasil diselesaikan.']);
    }

    public function tolakPencairan(int $id): JsonResponse
    {
        $pencairan = Penukaran::where('tipe', 'cash')->where('status', 'pending')->findOrFail($id);

        DB::transaction(function () use ($pencairan) {
            $pencairan->user->tambahPoin($pencairan->total_poin);
            $pencairan->update(['status' => 'dibatalkan']);
        });

        // ✅ Push notifikasi ke nasabah
        $this->fcm->kirimKeUser(
            user:  $pencairan->user,
            judul: 'Pencairan Dana Ditolak',
            pesan: 'Permintaan pencairan Rp ' . number_format($pencairan->jumlah) . ' ditolak. Poin telah dikembalikan.',
            tipe:  'penukaran',
            route: '/riwayat',
        );

        return response()->json(['message' => 'Pencairan ditolak dan poin dikembalikan.']);
    }

    // ── Laporan ────────────────────────────────────────────────────────────

    public function laporan(Request $request): JsonResponse
    {
        $dari   = $request->query('dari',   now()->startOfMonth()->toDateString());
        $sampai = $request->query('sampai', now()->endOfMonth()->toDateString());

        $setoran   = Setoran::with(['user', 'kategori'])
            ->whereBetween('created_at', [$dari . ' 00:00:00', $sampai . ' 23:59:59'])
            ->where('status', 'selesai')->latest()->paginate(10);

        $stats = Setoran::whereBetween('created_at', [$dari . ' 00:00:00', $sampai . ' 23:59:59'])
            ->where('status', 'selesai')
            ->selectRaw('COUNT(*) as total_transaksi, SUM(berat_kg) as volume_kg, SUM(poin_didapat) as total_poin')
            ->first();

        $data = collect($setoran->items())->map(fn($s) => [
            'id'           => 'TRX-' . str_pad($s->id, 4, '0', STR_PAD_LEFT),
            'tanggal'      => $s->created_at->format('d M Y, H:i'),
            'nasabah'      => $s->user->nama_lengkap,
            'kategori'     => $s->kategori->nama,
            'berat_kg'     => $s->berat_kg,
            'poin_didapat' => $s->poin_didapat,
        ]);

        return response()->json([
            'stats'        => [
                'total_transaksi' => $stats->total_transaksi ?? 0,
                'volume_kg'       => $stats->volume_kg ?? 0,
                'nilai_konversi'  => $stats->total_poin ?? 0,
            ],
            'data'         => $data,
            'current_page' => $setoran->currentPage(),
            'last_page'    => $setoran->lastPage(),
        ]);
    }

    // ── Log Aktivitas ──────────────────────────────────────────────────────

    public function logAktivitas(): JsonResponse
    {
        $logs = collect();

        Setoran::with(['user', 'admin', 'kategori'])->latest()->take(20)->get()
            ->each(fn($s) => $logs->push([
                'id'      => 'setoran_' . $s->id,
                'admin'   => $s->admin->nama_lengkap ?? 'System',
                'aksi'    => "menginput setoran {$s->berat_kg}kg {$s->kategori->nama}",
                'waktu'   => $s->created_at->format('h:i A'),
                'tanggal' => $s->created_at->format('D, M d, Y'),
                'tipe'    => 'setoran',
            ]));

        User::where('role', 'nasabah')->where('is_verified', true)
            ->latest('updated_at')->take(10)->get()
            ->each(fn($u) => $logs->push([
                'id'      => 'verif_' . $u->id,
                'admin'   => 'Admin',
                'aksi'    => "memverifikasi nasabah {$u->nama_lengkap}",
                'waktu'   => $u->updated_at->format('h:i A'),
                'tanggal' => $u->updated_at->format('D, M d, Y'),
                'tipe'    => 'verifikasi',
            ]));

        $sorted = $logs->sortByDesc('tanggal')->take(20)->values();

        return response()->json(['data' => $sorted, 'total' => $sorted->count()]);
    }

    public function getNasabahAktif() 
    {
        // Ambil user dengan role nasabah yang sudah diverifikasi
        $nasabah = User::where('role', 'nasabah')
                       ->where('is_verified', true)
                       ->select('id', 'nama_lengkap as nama', 'id_nasabah')
                       ->get();
                       
        return response()->json($nasabah);
    }

    // ── Notifikasi ─────────────────────────────────────────────────────────

    public function notifikasi(): JsonResponse
    {
        // Mengambil notifikasi milik user yang sedang login (Admin)
        $notif = Notifikasi::where('user_id', auth()->id())
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