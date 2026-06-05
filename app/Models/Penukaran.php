<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penukaran extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'penukaran'; // ✅ FIX: cegah auto plural "penukarans"

    protected $fillable = [
        'user_id',
        'produk_id',
        'tipe',
        'jumlah',
        'total_poin',
        'status',
        'metode_cash',
        'no_rekening',
        'catatan',
    ];

    protected $casts = [
        'jumlah'     => 'integer',
        'total_poin' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function produk()
    {
        return $this->belongsTo(Produk::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}