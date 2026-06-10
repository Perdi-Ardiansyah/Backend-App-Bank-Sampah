<?php

namespace App\Services;

use Google_Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Notifikasi; // 👈 Pastikan Model Notifikasi dipanggil

class FcmService
{
    // ── Fungsi FCM (Push Notif ke Layar HP) yang tadi kita buat ──
    public static function sendNotification($fcmToken, $title, $body, $data = [])
    {
        try {
            $keyPath = storage_path('app/firebase-auth.json');
            
            if (!file_exists($keyPath)) {
                Log::error('FCM Error: File firebase-auth.json tidak ditemukan.');
                return false;
            }

            $keyData = json_decode(file_get_contents($keyPath), true);
            $projectId = $keyData['project_id'];

            $client = new Google_Client();
            $client->setAuthConfig($keyPath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->useApplicationDefaultCredentials();
            $token = $client->fetchAccessTokenWithAssertion();
            $accessToken = $token['access_token'];

            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $data,
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            return $response->successful();

        } catch (\Exception $e) {
            Log::error('FCM Send Error: ' . $e->getMessage());
            return false;
        }
    }

    // ── INI FUNGSI YANG HILANG (Untuk simpan notif ke Database) ──
    public function kirimKeUser($user, $judul, $pesan, $tipe = 'sistem', $route = '/')
    {
        try {
            // 1. Simpan ke Database (Agar muncul di halaman Notifikasi UI Flutter)
            Notifikasi::create([
                'user_id' => $user->id,
                'judul'   => $judul,
                'pesan'   => $pesan,
                'tipe'    => $tipe,
                'route'   => $route,
                'is_read' => false,
            ]);

            // 2. 🚀 TEMBAK KE HP (Jika user memiliki fcm_token) 🚀
            if (!empty($user->fcm_token)) {
                // Panggil fungsi static sendNotification di atasnya
                self::sendNotification(
                    $user->fcm_token, 
                    $judul, 
                    $pesan, 
                    ['tipe' => $tipe, 'route' => $route] // Bawa data tambahan kalau diperlukan
                );
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Gagal menyimpan/mengirim notifikasi: ' . $e->getMessage());
            return false;
        }
    }
}