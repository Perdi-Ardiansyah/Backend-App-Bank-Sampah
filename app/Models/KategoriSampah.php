<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class KategoriSampah extends Model
{
    use HasFactory;

    protected $table = 'kategori_sampah';

    protected $fillable = ['nama', 'deskripsi', 'poin_per_kg', 'icon_name', 'foto', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'poin_per_kg' => 'integer',
    ];

    public function setoran()
    {
        return $this->hasMany(Setoran::class, 'kategori_id');
    }

    /** Hitung poin dari berat */
    public function hitungPoin(float $beratKg): int
    {
        return (int) round($this->poin_per_kg * $beratKg);
    }
}