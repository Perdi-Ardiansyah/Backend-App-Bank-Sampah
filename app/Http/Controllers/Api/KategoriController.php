<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KategoriSampah;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class KategoriController extends Controller
{
    /** GET /api/kategori — semua kategori aktif (nasabah & admin) */
    public function index(): JsonResponse
    {
        // Hapus filter is_active agar admin bisa melihat yang nonaktif juga
        $list = KategoriSampah::orderBy('nama')
            ->get()
            ->map(fn($k) => [
                'id'          => $k->id,
                'nama'        => $k->nama,
                'deskripsi'   => $k->deskripsi,
                'poin_per_kg' => $k->poin_per_kg,
                'icon_name'   => $k->icon_name,
                'foto'        => $k->foto,
                'is_active'   => $k->is_active,
            ]);

        // Pastikan mengembalikan list langsung
        return response()->json($list);
    }

    /** POST /api/admin/kategori */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nama'        => 'required|string|max:100|unique:kategori_sampah,nama',
            'deskripsi'   => 'nullable|string',
            'poin_per_kg' => 'required|integer|min:1',
            'icon_name'   => 'nullable|string',
            'foto'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // Validasi foto dari Flutter
        ]);

        $data = $request->only('nama', 'deskripsi', 'poin_per_kg', 'icon_name');

        // Proses upload foto jika admin melampirkan gambar
        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('kategori', 'public');
            $data['foto'] = $path;
        }

        $kategori = KategoriSampah::create($data);

        return response()->json([
            'message'  => 'Kategori berhasil ditambahkan.',
            'kategori' => $kategori,
        ], 201);
    }

    /** PUT /api/admin/kategori/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $kategori = KategoriSampah::findOrFail($id);

        $request->validate([
            'nama'        => 'required|string|max:100|unique:kategori_sampah,nama,' . $id,
            'deskripsi'   => 'nullable|string',
            'poin_per_kg' => 'required|integer|min:1',
            'icon_name'   => 'nullable|string',
            'foto'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $data = $request->only('nama', 'deskripsi', 'poin_per_kg', 'icon_name');

        // Jika ada foto baru yang diunggah saat proses edit
        if ($request->hasFile('foto')) {
            // Hapus foto lama di storage jika ada agar tidak memenuhi server
            if ($kategori->foto && Storage::disk('public')->exists($kategori->foto)) {
                Storage::disk('public')->delete($kategori->foto);
            }

            // Simpan foto baru
            $path = $request->file('foto')->store('kategori', 'public');
            $data['foto'] = $path;
        }

        $kategori->update($data);

        return response()->json([
            'message'  => 'Kategori berhasil diperbarui.',
            'kategori' => $kategori->fresh(),
        ]);
    }

    /** PATCH /api/admin/kategori/{id}/toggle — aktif/nonaktif */
    public function toggle(int $id): JsonResponse
    {
        $kategori = KategoriSampah::findOrFail($id);
        $kategori->update(['is_active' => !$kategori->is_active]);

        $status = $kategori->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return response()->json(['message' => "Kategori berhasil {$status}."]);
    }
}