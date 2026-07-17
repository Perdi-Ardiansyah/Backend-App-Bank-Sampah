<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penukaran;
use App\Models\AuditLog;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;


class PencairanAdminController extends Controller
{
    private FcmService $fcm;

    public function __construct(FcmService $fcm)
    {
        $this->fcm = $fcm;
    }

    public function list(): JsonResponse
    {
        $list = Penukaran::where('tipe', 'cash')->with('user')->latest()->paginate(10);

        $data = collect($list->items())->map(fn($p) => [
            'id' => $p->id,
            'nama_nasabah' => $p->user->nama_lengkap,
            'id_nasabah' => $p->user->id_nasabah,
            'nominal' => $p->jumlah,
            'status' => $p->status,
            'metode_cash' => $p->metode_cash,
            'no_rekening' => $p->no_rekening,
            'catatan' => $p->catatan,
            'tanggal' => $p->created_at->format('d M Y, H:i'),
        ]);

        $totalTertunda = Penukaran::where('tipe', 'cash')->where('status', 'pending')->sum('jumlah');

        return response()->json([
            'data' => $data,
            'total_tertunda' => $totalTertunda,
            'current_page' => $list->currentPage(),
            'last_page' => $list->lastPage(),
        ]);
    }

    public function selesai(int $id): JsonResponse
    {
        $pencairan = Penukaran::where('tipe', 'cash')->where('status', 'pending')->findOrFail($id);
        $pencairan->update(['status' => 'selesai']);

        // ✅ Audit log ke tabel audit_log (pencairan selesai)
        AuditLog::create([
            'admin_id' => auth()->id(),
            'aksi' => 'menyelesaikan pencairan',
            'model' => 'Penukaran',
            'model_id' => $pencairan->id,
            'data_lama' => null,
            'data_baru' => [
                'status' => 'selesai',
                'user_id' => $pencairan->user_id,
                'jumlah' => (float) $pencairan->jumlah,
            ],
            'ip_address' => request()->ip(),
        ]);

        $this->fcm->kirimKeUser(

            user: $pencairan->user,
            judul: 'Pencairan Dana Berhasil 💰',
            pesan: 'Pencairan Rp ' . number_format($pencairan->jumlah) . ' telah diproses dan dikirim.',
            tipe: 'penukaran',
            route: '/riwayat',
        );

        if ($pencairan->user->fcm_token) {
            \App\Services\FcmService::sendNotification(
                $pencairan->user->fcm_token,
                'Pencairan Dana Berhasil 💰',
                'Pencairan Rp ' . number_format($pencairan->jumlah) . ' telah diproses dan dikirim.',
                ['jenis' => 'penukaran', 'route' => '/riwayat']
            );
        }

        return response()->json(['message' => 'Pencairan berhasil diselesaikan.']);
    }

    public function tolak(int $id): JsonResponse
    {
        $pencairan = Penukaran::where('tipe', 'cash')->where('status', 'pending')->findOrFail($id);

        DB::transaction(function () use ($pencairan) {
            $pencairan->user->tambahPoin($pencairan->total_poin);
            $pencairan->update(['status' => 'dibatalkan']);

            // ✅ Audit log ke tabel audit_log (pencairan ditolak)
            AuditLog::create([
                'admin_id' => auth()->id(),
                'aksi' => 'menolak pencairan',
                'model' => 'Penukaran',
                'model_id' => $pencairan->id,
                'data_lama' => null,
                'data_baru' => [
                    'status' => 'dibatalkan',
                    'user_id' => $pencairan->user_id,
                    'jumlah' => (float) $pencairan->jumlah,
                ],
                'ip_address' => request()->ip(),
            ]);
        });


        $this->fcm->kirimKeUser(
            user: $pencairan->user,
            judul: 'Pencairan Dana Ditolak',
            pesan: 'Permintaan pencairan Rp ' . number_format($pencairan->jumlah) . ' ditolak. Poin telah dikembalikan.',
            tipe: 'penukaran',
            route: '/riwayat',
        );

        if ($pencairan->user->fcm_token) {
            \App\Services\FcmService::sendNotification(
                $pencairan->user->fcm_token,
                'Pencairan Dana Ditolak',
                'Permintaan pencairan Rp ' . number_format($pencairan->jumlah) . ' ditolak. Poin telah dikembalikan.',
                ['jenis' => 'penukaran', 'route' => '/riwayat']
            );
        }

        return response()->json(['message' => 'Pencairan ditolak dan poin dikembalikan.']);
    }
}

