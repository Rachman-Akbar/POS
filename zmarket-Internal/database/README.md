# Struktur Database Marketplace Internal

Tabel utama:

1. `users`
2. `categories`
3. `product_attributes`
4. `products`
5. `product_attribute_values`
6. `product_images`
7. `product_categories`
8. `customers`
9. `orders`
10. `order_items`
11. `order_status_histories`

Aturan utama:

- Marketplace memakai satu katalog global perusahaan tanpa toko atau `store_id`.
- Setiap produk memiliki satu SKU, satu harga, dan satu stok langsung pada tabel `products`.
- Tabel variant dan seluruh foreign key variant tidak digunakan.
- Atribut produk hanya berfungsi sebagai informasi statis, misalnya bahan atau merek internal.
- Kategori memiliki `sort_order` untuk menentukan urutan pada panel kategori di header.
- Email unik berada pada tabel `customers`.
- Satu customer dapat memiliki banyak order dengan email yang sama.
- Data identitas customer disalin sebagai snapshot ke tabel `orders`.
- `customer_type` membedakan checkout `individual` dan `business`.
- `guest_division` tidak digunakan.
- Harga, SKU, nama, dan tipe produk disalin ke `order_items` agar riwayat order tetap konsisten.
