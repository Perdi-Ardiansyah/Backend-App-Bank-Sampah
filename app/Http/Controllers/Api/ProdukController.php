<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage; // Ditambahkan untuk manajemen penghapusan berkas lama

class ProdukController extends Controller
{
    /** GET /api/produk — semua produk (untuk admin tanpa filter is_active) */
    public function index(): JsonResponse
    {
        // Menghilangkan where('is_active', true) agar admin dapat melihat produk yang nonaktif
        $list = Produk::orderBy('nama')
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'nama'        => $p->nama,
                'deskripsi'   => $p->deskripsi,
                'biaya_poin'  => $p->biaya_poin, // Pastikan key ini dibaca sama oleh Flutter
                'stok'        => $p->stok,
                'is_active'   => $p->is_active,
                'foto_url'    => $p->foto_url,
            ]);

        return response()->json($list);
    }

    /** POST /api/admin/produk */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nama'        => 'required|string|max:200',
            'deskripsi'   => 'nullable|string',
            'biaya_poin'  => 'required|integer|min:1',
            'stok'        => 'required|integer|min:0',
            'foto'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // Diubah menerima file foto dari Flutter
        ]);

        $data = $request->only('nama', 'deskripsi', 'biaya_poin', 'stok');

        // Memproses penyimpanan file ke folder storage/app/public/produk
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('produk', 'public');
            $data['foto_url'] = $path; // Jalur file disimpan ke kolom foto_url yang sudah ada
        }

        $produk = Produk::create($data);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'produk'  => $produk,
        ], 201);
    }

    /** PUT /api/admin/produk/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $produk = Produk::findOrFail($id);

        $request->validate([
            'nama'        => 'required|string|max:200',
            'deskripsi'   => 'nullable|string',
            'biaya_poin'  => 'required|integer|min:1',
            'stok'        => 'required|integer|min:0',
            'foto'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // Diubah menerima file foto
        ]);

        $data = $request->only('nama', 'deskripsi', 'biaya_poin', 'stok');

        if ($request->hasFile('foto')) {
            // Hapus file gambar lama dari server jika admin mengganti fotonya
            if ($produk->foto_url && Storage::disk('public')->exists($produk->foto_url)) {
                Storage::disk('public')->delete($produk->foto_url);
            }
            
            $path = $request->file('foto')->store('produk', 'public');
            $data['foto_url'] = $path;
        }

        $produk->update($data);

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'produk'  => $produk->fresh(),
        ]);
    }

    /** PATCH /api/admin/produk/{id}/toggle */
    public function toggle(int $id): JsonResponse
    {
        $produk = Produk::findOrFail($id);
        $produk->update(['is_active' => !$produk->is_active]);

        $status = $produk->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return response()->json(['message' => "Produk berhasil {$status}."]);
    }
}