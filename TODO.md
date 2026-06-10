# TODO - Refactor Controller API

## Phase 1: Persiapan
- [x] Analisis controller yang ada (AdminController, NasabahController, ProdukController, KategoriController) dan mapping route (routes/api.php).
- [ ] Buat daftar controller baru per domain (Setoran, Pencairan, Verifikasi Nasabah, Laporan, Log Aktivitas, Notifikasi; plus sisi Nasabah: Dashboard/Riwayat/Tukar/Notifikasi).

## Phase 2: Implementasi refactor (tanpa ubah format JSON)
- [ ] Buat controller baru (file-file) untuk admin per domain.
- [ ] Buat controller baru (file-file) untuk nasabah per domain.
- [ ] Pindahkan method-method dari AdminController & NasabahController ke controller baru.

## Phase 3: Update routing
- [ ] Update `routes/api.php` supaya endpoint menuju controller baru.
- [ ] Pastikan middleware role dan auth tetap sama.

## Phase 4: Cleanup
- [ ] Pangkas method yang sudah dipindah dari `AdminController` dan `NasabahController`.

## Phase 5: Validasi
- [ ] Jalankan `php artisan route:list`.
- [ ] Jalankan `php -l` / kompilasi ringan via `php artisan`.

