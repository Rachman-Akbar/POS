# Marketplace Internal Perusahaan — Produk Tunggal

Paket ini berisi dua folder:

- `backend`: patch Laravel API, migration, model, controller, service, resource, factory, seeder, route, dan feature test.
- `frontend`: source React yang disesuaikan dengan API produk tunggal.

## Revisi Frontend

- Header memiliki ikon riwayat order dan ikon keranjang.
- Kolom kategori dan pencarian memiliki tinggi serta pembagian lebar yang sama.
- Tombol kategori menggunakan ikon pencarian.
- Pilihan kategori tampil sebagai bubble fleksibel sesuai panjang teks, bukan daftar vertikal dan tanpa total produk.
- Kategori aktif ditulis `Kategori : Nama kategori`.
- Product detail tidak memiliki rating, review, atau pilihan variant.
- Halaman pencarian tidak memakai sidebar.
- Judul produk unggulan pada homepage menjadi `Catalog`.
- Checkout langsung menampilkan form tanpa judul halaman.
- Cart langsung menampilkan isi keranjang tanpa judul dan tanpa tab switch.
- Riwayat order tetap memakai sidebar list dan main content detail; edit hanya dibuka melalui ikon pensil.

## Revisi Backend dan Database

- Produk memakai satu SKU, satu harga, dan satu stok pada tabel `products`.
- Seluruh model, tabel, request, resource, service, seeder, test, dan UI variant dihapus.
- Migration kompatibilitas mengambil data dari variant utama atau variant pertama instalasi lama sebelum tabel variant dihapus.
- Admin dan seller mengelola katalog global perusahaan yang sama.
- Seeder menghasilkan 50 produk tunggal.
- Akun seeder:
  - `admin@company.local` / `12345678`
  - `seller@company.local` / `12345678`

## Menjalankan Patch

Salin isi folder sesuai project utama, lalu jalankan backend:

```bash
php artisan migrate
php artisan optimize:clear
php artisan test --filter=MarketplaceFlowTest
```

Untuk database development yang boleh dihapus dan dibuat ulang:

```bash
php artisan migrate:fresh --seed
```

Paket sumber yang diterima tidak menyertakan `composer.json`, `artisan`, `vendor`, `package.json`, dan `node_modules`, sehingga build/runtime penuh tidak dapat dijalankan di lingkungan patch ini. Pemeriksaan sintaks PHP, parsing JSX/JavaScript, route-controller, konsistensi source aktif, dan integritas ZIP tetap dijalankan.
