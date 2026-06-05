<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setoran extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'setoran'; // ✅ FIX: cegah auto plural "sitorans"

    protected $fillable = [
        'user_id',
        'kategori_id',
        'admin_id',
        'berat_kg',
        'poin_didapat',
        'status',
        'lokasi_tps',
        'catatan',
    ];

    protected $casts = [
        'berat_kg'     => 'float',
        'poin_didapat' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kategori()
    {
        return $this->belongsTo(KategoriSampah::class, 'kategori_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function scopeSelesai($query)
    {
        return $query->where('status', 'selesai');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeBulanIni($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }
}