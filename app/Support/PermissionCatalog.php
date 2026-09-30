<?php

namespace App\Support;

/**
 * Sumber tunggal daftar permission aplikasi.
 *
 * Daftar modul mengikuti modul yang benar-benar tersedia di project, bukan
 * daftar generik. Backend (middleware `permission:`), seeder, dan endpoint
 * `/api/admin/permissions` membaca dari sini agar tidak pernah berbeda.
 */
class PermissionCatalog
{
    /**
     * Modul aplikasi dalam urutan tampilan untuk UI kelompok hak akses.
     *
     * @var array<string, string>
     */
    public const MODULE_LABELS = [
        'transaction' => 'Transaksi',
        'pos' => 'Kasir',
        'kitchen' => 'Dapur',
        'product' => 'Produk',
        'category' => 'Kategori',
        'customer' => 'Pelanggan',
        'cash' => 'Kas & Bank',
        'payment_method' => 'Metode Pembayaran',
        'user' => 'User',
        'role' => 'Kelompok Hak Akses',
        'settings' => 'Pengaturan',
        'accounting' => 'Akuntansi',
    ];

    /**
     * Seluruh permission, dikelompokkan per modul.
     *
     * Katalog ini adalah daftar permission yang benar-benar ditegakkan route.
     * Setiap nama harus punya route (atau pemeriksaan di controller) yang
     * menjaganya; test `test_every_catalog_permission_is_enforced_by_a_route`
     * menjaga hal itu tetap berlaku. Permission tidak dibuat dari UI karena
     * nama baru tanpa penjaga route hanya akan jadi checkbox hampa.
     *
     * @return array<string, array<string, string>> module => permission => label
     */
    public static function definitions(): array
    {
        return [
            'transaction' => [
                'transaction.view' => 'Lihat Transaksi',
                'transaction.view.all' => 'Lihat Transaksi Semua User',
                'transaction.create' => 'Membuat Pesanan',
                'transaction.update' => 'Mengubah / Menyelesaikan Pesanan',
                'transaction.void' => 'Membatalkan Transaksi (Void)',
                'transaction.refund' => 'Retur / Kembalikan Pembayaran',
            ],
            'pos' => [
                'pos.view' => 'Buka Kasir & Pembayaran',
                'pos.sell' => 'Melakukan Pembayaran',
                'pos.discount' => 'Memberi Diskon',
            ],
            'kitchen' => [
                'kitchen.view' => 'Lihat Dapur',
                'kitchen.update' => 'Ubah Status Pesanan Dapur',
            ],
            'product' => [
                'product.view' => 'Lihat Produk',
                'product.create' => 'Tambah Produk',
                'product.update' => 'Edit Produk',
                'product.delete' => 'Hapus Produk',
                'product.favorite' => 'Atur Produk Favorit',
            ],
            'category' => [
                'category.view' => 'Lihat Kategori',
                'category.create' => 'Tambah Kategori',
                'category.update' => 'Edit Kategori',
                'category.delete' => 'Hapus Kategori',
                'category.reorder' => 'Ubah Urutan Kategori',
            ],
            'customer' => [
                'customer.view' => 'Lihat Pelanggan',
                'customer.create' => 'Tambah Pelanggan',
                'customer.update' => 'Edit Pelanggan',
                'customer.delete' => 'Hapus Pelanggan',
            ],
            'cash' => [
                'cash.view' => 'Lihat Kas & Bank',
                'cash.create' => 'Tambah Kas & Bank',
                'cash.update' => 'Edit Kas & Bank',
                'cash.delete' => 'Hapus Kas & Bank',
            ],
            'payment_method' => [
                'payment_method.view' => 'Lihat Metode Pembayaran',
                'payment_method.create' => 'Tambah Metode Pembayaran',
                'payment_method.update' => 'Edit Metode Pembayaran',
                'payment_method.delete' => 'Hapus Metode Pembayaran',
            ],
            'user' => [
                'user.view' => 'Lihat User',
                'user.create' => 'Tambah User',
                'user.update' => 'Edit User',
                'user.delete' => 'Hapus User',
            ],
            'role' => [
                'role.view' => 'Lihat Kelompok Hak Akses',
                'role.create' => 'Tambah Kelompok Hak Akses',
                'role.update' => 'Edit Kelompok Hak Akses',
                'role.delete' => 'Hapus Kelompok Hak Akses',
            ],
            'settings' => [
                'settings.view' => 'Lihat Pengaturan',
                'settings.update' => 'Ubah Pengaturan',
            ],
            'accounting' => [
                'accounting.view' => 'Lihat Jurnal & Akuntansi',
            ],
        ];
    }

    /**
     * Seluruh permission sebagai daftar datar.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::flatten());
    }

    /**
     * Permission per modul dalam bentuk datar, siap disimpan ke database.
     *
     * @return array<int, array{name: string, module: string, label: string, description: string|null}>
     */
    public static function rows(): array
    {
        $rows = [];

        foreach (self::flatten() as $name => $group) {
            $rows[] = [
                'name' => $name,
                'module' => $group['module'],
                'label' => $group['label'],
                'description' => $group['description'],
            ];
        }

        return $rows;
    }

    /**
     * Permission yang belum terdaftar di katalog, dibuang saat sinkronisasi.
     *
     * @param  array<int, string>  $names
     * @return array<string, array{module: string, label: string, description: string|null}>
     */
    private static function flatten(): array
    {
        $flat = [];

        foreach (self::definitions() as $module => $permissions) {
            foreach ($permissions as $name => $label) {
                $flat[$name] = [
                    'module' => $module,
                    'label' => $label,
                    'description' => self::descriptionFor($name),
                ];
            }
        }

        return $flat;
    }

    private static function descriptionFor(string $name): ?string
    {
        return match ($name) {
            'transaction.view' => 'Melihat pesanan dan transaksi miliknya sendiri.',
            'transaction.view.all' => 'Melihat seluruh transaksi, termasuk milik kasir lain.',
            'transaction.void' => 'Membatalkan transaksi yang salah, mengembalikan stok, dan reversal jurnal.',
            'transaction.refund' => 'Mengembalikan sebagian atau seluruh pembayaran yang sudah diterima.',
            default => match (true) {
                str_ends_with($name, '.view') => 'Melihat daftar dan detail data modul ini.',
                str_ends_with($name, '.create') => 'Menambah data baru pada modul ini.',
                str_ends_with($name, '.update') => 'Mengubah data yang sudah ada pada modul ini.',
                str_ends_with($name, '.delete') => 'Menghapus data pada modul ini.',
                default => null,
            },
        };
    }
}
