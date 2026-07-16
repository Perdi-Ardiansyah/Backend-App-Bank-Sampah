<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AuditLog;
// Import Model yang sesuai dengan tabel di database Anda
use App\Models\Penukaran;
use App\Models\User;
use App\Models\Produk;

class PenukaranAdminController extends Controller
{
    /**
     * Mengambil daftar penukaran sembako
     * Method: GET
     */
    public function list()
    {
        try {
            // Ambil data penukaran khusus tipe 'produk'
            // Pastikan Anda sudah membuat relasi public function user() di model Penukaran
            $penukaran = Penukaran::with('user')
                ->where('tipe', 'produk')
                ->orderByRaw("FIELD(status, 'pending', 'selesai', 'dibatalkan')")
                ->orderBy('created_at', 'desc')
                ->get();

            // Transformasi data agar sesuai dengan penamaan di Flutter (nasabah)
            $penukaran->transform(function ($item) {
                // Menyalin relasi user menjadi nasabah agar Flutter mudah membacanya
                $item->nasabah = $item->user;
                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $penukaran
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memuat data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menyetujui dan menyelesaikan penukaran
     * Method: POST
     */
    public function selesai($id)
    {
        try {
            $penukaran = Penukaran::findOrFail($id);

            if ($penukaran->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Status transaksi ini sudah tidak dapat diubah.'], 400);
            }

            $penukaran->status = 'selesai';
            $penukaran->save();
            AuditLog::create([
                'admin_id' => auth()->user()->id,
                'aksi' => 'Menyetujui penukaran sembako milik nasabah ID: ' . $penukaran->user_id,
                'model' => 'Penukaran',
                'model_id' => $penukaran->id,
                'ip_address' => request()->ip(),
            ]);
            // 👇 KIRIM NOTIFIKASI KE NASABAH 👇
            $nasabah = User::find($penukaran->user_id);
            if ($nasabah) {
                $judulNotif = 'Penukaran Disetujui! 🎉';
                $pesanNotif = 'Hore! Permintaan penukaran sembako Anda telah disetujui admin dan siap diambil.';

                \App\Models\Notifikasi::create([
                    'user_id' => $nasabah->id,
                    'judul' => $judulNotif,
                    'pesan' => $pesanNotif,
                    'tipe' => 'penukaran',
                    'is_read' => 0
                ]);

                if (!empty($nasabah->fcm_token)) {
                    \App\Services\FcmService::sendNotification($nasabah->fcm_token, $judulNotif, $pesanNotif);
                }
            }

            return response()->json(['success' => true, 'message' => 'Penukaran sembako diselesaikan.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menolak penukaran (Otomatis refund poin nasabah & kembalikan stok produk)
     * Method: POST
     */
    public function tolak($id)
    {
        DB::beginTransaction();
        try {
            $penukaran = Penukaran::findOrFail($id);

            if ($penukaran->status !== 'pending') {
                return response()->json(['success' => false, 'message' => 'Status transaksi ini sudah tidak dapat diubah.'], 400);
            }

            $nasabah = User::find($penukaran->user_id);
            if ($nasabah) {
                $nasabah->total_poin += $penukaran->total_poin;
                $nasabah->save();
            }

            if ($penukaran->produk_id) {
                $produk = Produk::find($penukaran->produk_id);
                if ($produk) {
                    $produk->stok += $penukaran->jumlah;
                    $produk->save();
                }
            }

            $penukaran->status = 'dibatalkan';
            $penukaran->save();

            // 👇 KIRIM NOTIFIKASI KE NASABAH 👇
            if ($nasabah) {
                $judulNotif = 'Penukaran Dibatalkan ❌';
                $pesanNotif = 'Maaf, permintaan penukaran sembako Anda dibatalkan admin. Saldo poin (' . $penukaran->total_poin . ' Pts) telah dikembalikan.';

                \App\Models\Notifikasi::create([
                    'user_id' => $nasabah->id,
                    'judul' => $judulNotif,
                    'pesan' => $pesanNotif,
                    'tipe' => 'penukaran',
                    'is_read' => 0
                ]);

                if (!empty($nasabah->fcm_token)) {
                    \App\Services\FcmService::sendNotification($nasabah->fcm_token, $judulNotif, $pesanNotif);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Penukaran dibatalkan. Poin dan stok dikembalikan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }
}