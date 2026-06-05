<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notifikasi;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private $messaging;

    public function __construct()
    {
        // Path menuju file JSON credential (storage/app/firebase-auth.json)
        $credentialsPath = storage_path('app/firebase-auth.json');
        
        // Cek jika file dari .env digunakan atau langsung hardcoded path
        if (config('services.firebase.credentials')) {
            $credentialsPath = base_path(config('services.firebase.credentials'));
        }

        try {
            // Inisialisasi Firebase Factory dengan file JSON
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Firebase init error: ' . $e->getMessage());
        }
    }

    // ── Kirim ke satu user ─────────────────────────────────────────────────

    /**
     * Kirim notifikasi push ke satu nasabah.
     * Otomatis simpan ke tabel notifikasi.
     */
    public function kirimKeUser(
        User   $user,
        string $judul,
        string $pesan,
        string $tipe  = 'sistem',
        string $route = '/home',
        array  $data  = []
    ): bool {
        // Simpan ke database dulu
        Notifikasi::kirim($user->id, $judul, $pesan, $tipe);

        // Kirim push notification jika user punya FCM token
        if (empty($user->fcm_token) || !$this->messaging) {
            return false;
        }

        return $this->send(
            token:  $user->fcm_token,
            judul:  $judul,
            pesan:  $pesan,
            data:   array_merge(['route' => $route], $data),
        );
    }

    // ── Kirim ke banyak user ───────────────────────────────────────────────

    /**
     * Kirim notifikasi push ke semua nasabah aktif (broadcast).
     */
    public function broadcast(
        string $judul,
        string $pesan,
        string $tipe  = 'sistem',
        string $route = '/home'
    ): void {
        $users = User::where('role', 'nasabah')
                     ->where('is_verified', true)
                     ->whereNotNull('fcm_token')
                     ->select('id', 'fcm_token')
                     ->get();

        // Simpan notifikasi ke DB untuk semua user
        $users->each(fn($u) => Notifikasi::kirim($u->id, $judul, $pesan, $tipe));

        if (!$this->messaging) return;

        // Kirim FCM dalam batch (maks 500 token per request)
        $tokens = $users->pluck('fcm_token')->filter()->values()->toArray();
        $chunks = array_chunk($tokens, 500);

        foreach ($chunks as $chunk) {
            $this->sendMulticast(
                tokens: $chunk,
                judul:  $judul,
                pesan:  $pesan,
                data:   ['route' => $route],
            );
        }
    }

    // ── Core HTTP methods ──────────────────────────────────────────────────

    /** Kirim ke satu token */
    private function send(
        string $token,
        string $judul,
        string $pesan,
        array  $data = []
    ): bool {
        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($judul, $pesan))
                ->withData($data);

            $this->messaging->send($message);
            return true;
        } catch (\Exception $e) {
            Log::error('FCM send gagal: ' . $e->getMessage(), [
                'token' => substr($token, 0, 20) . '...'
            ]);
            return false;
        }
    }

    /** Kirim ke banyak token sekaligus */
    private function sendMulticast(
        array  $tokens,
        string $judul,
        string $pesan,
        array  $data = []
    ): void {
        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($judul, $pesan))
                ->withData($data);

            $this->messaging->sendMulticast($message, $tokens);
        } catch (\Exception $e) {
            Log::error('FCM multicast exception: ' . $e->getMessage());
        }
    }
}