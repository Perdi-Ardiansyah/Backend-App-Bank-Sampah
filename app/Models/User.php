<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'nama_lengkap',
        'username',
        'email',
        'password',
        'role',
        'is_verified',
        'no_hp',
        'id_nasabah',
        'total_poin',
        'fcm_token',
        'foto_profil',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_verified' => 'boolean',
        'total_poin' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function setoran()
    {
        return $this->hasMany(Setoran::class, 'user_id');
    }

    public function penukaran()
    {
        return $this->hasMany(Penukaran::class);
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isNasabah(): bool
    {
        return $this->role === 'nasabah';
    }

    /** Tambah poin dan simpan */
    public function tambahPoin(int $poin): void
    {
        $this->increment('total_poin', $poin);
    }

    /** Kurangi poin — return false jika tidak cukup */
    public function kurangiPoin(int $poin): bool
    {
        if ($this->total_poin < $poin) {
            return false;
        }
        $this->decrement('total_poin', $poin);
        return true;
    }

    /** Generate ID nasabah unik: BS-YYYYMMDD + 3 digit random */
    public static function generateIdNasabah(): string
    {
        do {
            $id = 'BS-' . now()->format('Ymd') . rand(100, 999);
        } while (static::where('id_nasabah', $id)->exists());

        return $id;
    }

    public function getTotalSetoranAttribute()
    {
        return (double) $this->setoran()->where('status', 'selesai')->sum('berat_kg');
    }

    // Properti otomatis untuk menentukan level nasabah
    public function getLevelAttribute()
    {
        $totalWeight = $this->total_setoran;

        if ($totalWeight >= 50) {
            return 'Gold';
        } elseif ($totalWeight >= 10) {
            return 'Silver';
        }

        return 'Bronze';
    }

    // Pastikan properti dinamis ini ikut dikirim saat model diubah ke JSON/Array
    protected $appends = ['total_setoran', 'level'];
}