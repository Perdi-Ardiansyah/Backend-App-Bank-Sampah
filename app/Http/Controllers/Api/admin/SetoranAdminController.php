<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\KategoriSampah;
use App\Models\Setoran;
use App\Models\User;
use App\Models\Notifikasi;
use App\Models\AuditLog;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class SetoranAdminController extends Controller
{
    private FcmService $fcm;

    public function __construct(FcmService $fcm)
    {
        $this->fcm = $fcm;
    }

    public function simpan(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'kategori_id' => 'required|exists:kategori_sampah,id',
            'berat_kg' => 'required|numeric|min:0.1',
            'lokasi_tps' => 'nullable|string',
            'catatan' => 'nullable|string',
        ]);

        $nasabah = User::findOrFail($request->user_id);
        $kategori = KategoriSampah::findOrFail($request->kategori_id);
        $poin = $kategori->hitungPoin($request->berat_kg);

        DB::transaction(function () use ($request, $nasabah, $kategori, $poin) {
            $setoran = Setoran::create([
                'user_id' => $nasabah->id,
                'kategori_id' => $kategori->id,
                'admin_id' => $request->user()->id,
                'berat_kg' => $request->berat_kg,
                'poin_didapat' => $poin,
                'status' => 'selesai',
                'lokasi_tps' => $request->lokasi_tps,
                'catatan' => $request->catatan,
            ]);

            $nasabah->tambahPoin($poin);

            // ✅ Audit log ke tabel audit_log
            AuditLog::create([
                'admin_id' => auth()->user()->id,
                'aksi' => 'Mencatat setoran sampah seberat ' . $setoran->berat_kg . ' kg',
                'model' => 'Setoran',
                'model_id' => $setoran->id,
                'ip_address' => request()->ip(),
            ]);
        });


        if ($nasabah->fcm_token) {
            FcmService::sendNotification(
                $nasabah->fcm_token,
                'Setoran Berhasil Diverifikasi ✅',
                "Setoran {$request->berat_kg}kg {$kategori->nama} berhasil dicatat. Anda mendapat {$poin} poin.",
                ['jenis' => 'setoran', 'route' => '/riwayat']
            );
        }

        Notifikasi::create([
            'user_id' => 1,
            'judul' => 'Setoran Sampah Baru',
            'pesan' => "Terdapat setoran sampah baru sebesar {$request->berat_kg} Kg. Harap periksa!",
            'tipe' => 'setoran',
            'is_read' => false,
        ]);

        return response()->json([
            'message' => 'Setoran berhasil disimpan.',
            'poin_diberikan' => $poin,
        ], 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        // Endpoint di routes saat ini: put('/setoran/{id}/status', [AdminController::class, 'updateStatusSetoran'])
        // Pada AdminController yang terbaca, method updateStatusSetoran tidak ada.
        // Agar refactor tidak memutus endpoint, kita kembalikan 501.
        return response()->json(['message' => 'updateStatusSetoran belum diimplementasi.'], 501);
    }
}


