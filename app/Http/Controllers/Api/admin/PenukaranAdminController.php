<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penukaran;
use App\Models\AuditLog;
use App\Models\Produk;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PenukaranAdminController extends Controller
{
    private FcmService $fcm;

    public function __construct(FcmService $fcm)
    {
        $this->fcm = $fcm;
    }

    public function list(): JsonResponse
    {
        $penukaran = Penukaran::with('user')
            ->where('tipe', 'produk')
            ->orderByRaw("FIELD(status, 'pending', 'selesai', 'dibatalkan')")
            ->latest()
            ->get();

        $penukaran->transform(function ($item) {
            $item->nasabah = $item->user;
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $penukaran
        ]);
    }

    public function selesai(int $id): JsonResponse
    {
        $penukaran = Penukaran::where('tipe', 'produk')->where('status', 'pending')->findOrFail($id);
        $penukaran->update(['status' => 'selesai']);

        // ✅ Audit log ke tabel audit_log (penukaran selesai)
        AuditLog::create([
            'admin_id' => auth()->id(),
            'aksi' => 'menyelesaikan penukaran produk',
            'model' => 'Penukaran',
            'model_id' => $penukaran->id,
            'data_lama' => null,
            'data_baru' => [
                'status' => 'selesai',
                'user_id' => $penukaran->user_id,
                'produk_id' => $penukaran->produk_id,
                'jumlah' => (float) $penukaran->jumlah,
            ],
            'ip_address' => request()->ip(),
        ]);

        $this->fcm->kirimKeUser(
            user: $penukaran->user,
            judul: 'Penukaran Disetujui! 🎉',
            pesan: 'Permintaan penukaran produk Anda telah disetujui admin dan siap diambil.',
            tipe: 'penukaran',
            route: '/riwayat',
        );

        return response()->json(['message' => 'Penukaran produk berhasil diselesaikan.']);
    }

    public function tolak(int $id): JsonResponse
    {
        $penukaran = Penukaran::where('tipe', 'produk')->where('status', 'pending')->findOrFail($id);

        DB::transaction(function () use ($penukaran) {
            // 1. Refund poin menggunakan method helper yang sama di pencairan
            $penukaran->user->tambahPoin($penukaran->total_poin);
            
            // 2. Kembalikan stok produk
            if ($penukaran->produk_id) {
                $produk = Produk::find($penukaran->produk_id);
                if ($produk) {
                    $produk->stok += $penukaran->jumlah;
                    $produk->save();
                }
            }

            $penukaran->update(['status' => 'dibatalkan']);

            // ✅ Audit log ke tabel audit_log (penukaran ditolak)
            AuditLog::create([
                'admin_id' => auth()->id(),
                'aksi' => 'menolak penukaran produk',
                'model' => 'Penukaran',
                'model_id' => $penukaran->id,
                'data_lama' => null,
                'data_baru' => [
                    'status' => 'dibatalkan',
                    'user_id' => $penukaran->user_id,
                    'produk_id' => $penukaran->produk_id,
                    'jumlah' => (float) $penukaran->jumlah,
                ],
                'ip_address' => request()->ip(),
            ]);
        });

        $this->fcm->kirimKeUser(
            user: $penukaran->user,
            judul: 'Penukaran Dibatalkan ❌',
            pesan: 'Permintaan penukaran produk dibatalkan. Poin (' . $penukaran->total_poin . ' Pts) telah dikembalikan.',
            tipe: 'penukaran',
            route: '/riwayat',
        );

        return response()->json(['message' => 'Penukaran ditolak. Poin dan stok dikembalikan.']);
    }
}