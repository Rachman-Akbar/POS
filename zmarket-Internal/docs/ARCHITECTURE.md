# Arsitektur Ringkas

Controller tidak dipisah antara public dan admin untuk entitas yang sama.

- `CategoryController`: katalog public dan CRUD kategori.
- `ProductController`: katalog public, CRUD produk, variant, dan daftar nama atribut.
- `OrderController`: checkout/track guest dan manajemen order dashboard.
- `UserController`: CRUD user khusus admin.
- `AuthController`: login, sesi, logout.

Perbedaan hak akses ditentukan di `routes/api.php`, bukan dengan controller terpisah.

## Produk tanpa variant pilihan

Form mengirim harga, SKU, dan stok pada bagian utama. Backend otomatis membuat satu record `product_variants`:

- `name = Default`
- `attributes = []`
- `is_default = true`

## Produk dengan variant pilihan

Admin menekan **Tambah Variant**. Setiap variant memiliki nama, SKU opsional, harga, stok, status, dan banyak atribut key-value. Nama atribut dapat dipilih dari riwayat atribut yang pernah digunakan atau diketik sebagai nama baru.
