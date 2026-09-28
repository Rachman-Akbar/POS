# KishaMarket Internal

Laravel 12 API dan React Vite JavaScript SPA untuk marketplace internal perusahaan.

## Stack

- PHP 8.2+
- Laravel 12
- Sanctum session SPA
- React JavaScript (`.js`/`.jsx`), tanpa TypeScript
- Vite 6
- Tailwind CSS 4
- TanStack Query, Zustand, Axios
- MySQL

## Struktur utama

```text
app/Http/Controllers/
├── AuthController.php
├── CategoryController.php
├── ProductController.php
├── OrderController.php
├── UserController.php
└── AdminDashboardController.php

resources/js/marketplace/
├── components/
├── pages/
├── stores/
├── lib/
├── App.jsx
└── main.jsx
```

Tidak ada controller public/admin terpisah untuk kategori, produk, order, dan user. Hak akses ditentukan oleh middleware route.

## Instalasi

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan optimize:clear
npm install
```

Jalankan dua terminal:

```powershell
php artisan serve
```

```powershell
npm run dev
```

Buka `http://127.0.0.1:8000`.

## Login awal

```text
Admin
admin@company.local
admin12345

Seller
seller@company.local
seller12345
```

## SQL langsung

```powershell
cmd /c "mysql -u root -p < database\create_table_database.sql"
cmd /c "mysql -u root -p < database\seeder_database.sql"
```

File create menghapus dan membuat ulang tabel pada database `kishamarket_internal`.

## Alur CRUD produk

### Tanpa variant pilihan

Isi nama, kategori, harga, SKU opsional, dan stok. Backend otomatis membuat variant `Default`.

### Dengan variant pilihan

Tekan **Tambah Variant**. Card variant dapat dibuka/tutup. Tambahkan banyak atribut menggunakan input pencarian; pilih nama atribut lama atau ketik nama baru. Harga wajib diisi pada setiap variant.

## Route penting

Public:

```text
GET  /api/categories
GET  /api/products
GET  /api/products/{slug|id}
POST /api/checkout
GET  /api/orders/track
```

Protected admin/seller:

```text
/api/admin/categories
/api/admin/products
/api/admin/orders
```

Khusus admin:

```text
/api/admin/users
```
