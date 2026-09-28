# Full Repair Report

## Controller disederhanakan

- `CategoryController` menangani katalog public dan CRUD kategori.
- `ProductController` menangani katalog public, CRUD produk, variant, dan opsi atribut.
- `OrderController` menangani checkout/track guest dan CRUD order dashboard.
- `UserController` menangani CRUD user khusus admin.

Controller `Admin*`, `Public*`, dan `GuestOrderController` yang duplikat telah dihapus. Proteksi akses tetap dilakukan di `routes/api.php`.

## CRUD Product

### Produk tanpa pilihan variant

Form menampilkan SKU, harga, dan stok langsung. Saat disimpan, backend otomatis membuat satu variant:

```text
name: Default
attributes: []
is_default: true
```

### Produk dengan variant

Tombol **Tambah Variant** mengaktifkan mode variant. Setiap variant mempunyai card buka/tutup, harga, SKU opsional, stok, status, default, serta banyak atribut.

Nama atribut menggunakan input search dropdown. Admin dapat memilih nama atribut lama atau mengetik atribut baru. Tidak ada tabel atribut tambahan; opsi diambil dari JSON `product_variants.attributes`.

## Database

Migration lama yang bertabrakan dihapus dan diganti lima migration bersih. Kolom `order_items.variant_attributes` tersedia. Tabel user memiliki role, phone, department, dan status aktif.

Dua SQL final tersedia:

- `database/create_table_database.sql`
- `database/seeder_database.sql`

## Frontend

Semua file React menggunakan JavaScript `.js`/`.jsx`. Tidak ada `.ts`, `.tsx`, type annotation, atau dependency TypeScript.
