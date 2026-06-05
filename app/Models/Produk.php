<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produk extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'produk'; // ✅ FIX: cegah auto plural "produks"

    protected $fillable = [
        'nama',
        'deskripsi',
        'biaya_poin',
        'stok',
        'is_active',
        'foto_url',
    ];

    protected $casts = [
        'biaya_poin' => 'integer',
        'stok'       => 'integer',
        'is_active'  => 'boolean',
    ];

    public function penukaran()
    {
        return $this->hasMany(Penukaran::class);
    }

    public function isHabis(): bool
    {
        return $this->stok <= 0;
    }

    public function kurangiStok(int $jumlah): bool
    {
        if ($this->stok < $jumlah) {
            return false;
        }
        $this->decrement('stok', $jumlah);
        return true;
    }
}