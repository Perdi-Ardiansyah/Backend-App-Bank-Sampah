<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\KategoriSampah;
use App\Models\Produk;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        
        // ── Super Admin ────────────────────────────────────────────────────
        User::create([
            'nama_lengkap' => 'Super Admin',
            'username'     => 'superadmin',
            'email'        => 'admin@banksampah.id',
            'password'     => Hash::make('admin123'),
            'role'         => 'admin',
            'is_verified'  => true,
        ]);

        // ── Nasabah demo ───────────────────────────────────────────────────
        User::create([
            'nama_lengkap' => 'Andi Saputra',
            'username'     => 'andi_saputra',
            'email'        => 'andi.saputra@email.com',
            'password'     => Hash::make('password123'),
            'role'         => 'nasabah',
            'is_verified'  => true,
            'id_nasabah'   => 'BS-20231012',
            'no_hp'        => '0812-3456-7890',
            'total_poin'   => 24500,
        ]);

        // ── Kategori Sampah ────────────────────────────────────────────────
        $kategoris = [
            ['nama'=>'Plastik',        'deskripsi'=>'Botol PET, Gelas Plastik, Kemasan HDPE',  'poin_per_kg'=>500,  'icon_name'=>'recycling'],
            ['nama'=>'Kertas & Kardus','deskripsi'=>'Kertas HVS, Koran bekas, Kardus kering',  'poin_per_kg'=>300,  'icon_name'=>'paper'],
            ['nama'=>'Logam',          'deskripsi'=>'Kaleng aluminium, Besi tua, Seng',        'poin_per_kg'=>800,  'icon_name'=>'hardware'],
            ['nama'=>'Kaca',           'deskripsi'=>'Botol kaca bening atau berwarna',         'poin_per_kg'=>400,  'icon_name'=>'glass'],
            ['nama'=>'Minyak Jelantah','deskripsi'=>'Minyak goreng bekas per liter',           'poin_per_kg'=>1200, 'icon_name'=>'oil'],
            ['nama'=>'Elektronik',     'deskripsi'=>'HP bekas, Baterai, Kabel elektronik',     'poin_per_kg'=>1500, 'icon_name'=>'electronic'],
        ];

        foreach ($kategoris as $k) {
            KategoriSampah::create($k);
        }

        // ── Produk ─────────────────────────────────────────────────────────
        $produks = [
            ['nama'=>'Beras Premium 5kg', 'deskripsi'=>'Beras putih pulen berkualitas tinggi', 'biaya_poin'=>65000, 'stok'=>24],
            ['nama'=>'Minyak Goreng 2L',  'deskripsi'=>'Minyak goreng kelapa sawit murni',     'biaya_poin'=>35000, 'stok'=>12],
            ['nama'=>'Gula Pasir 1kg',    'deskripsi'=>'Gula kristal putih murni',             'biaya_poin'=>15000, 'stok'=>0],
            ['nama'=>'Sabun Mandi 4pcs',  'deskripsi'=>'Sabun mandi padat antibakteri',        'biaya_poin'=>12000, 'stok'=>50],
        ];

        foreach ($produks as $p) {
            Produk::create($p);
        }
    }
}