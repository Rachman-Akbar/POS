<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Seed the POS settings (tax rates, QRIS, store profile).
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'pos.store_name', 'group' => 'pos', 'value' => 'Resto POS Online', 'label' => 'Nama Toko / Resto'],
            ['key' => 'pos.ppn_rate', 'group' => 'pos', 'value' => '11', 'label' => 'PPN (%)'],
            ['key' => 'pos.qris_id', 'group' => 'pos', 'value' => 'QRIS-STATIS-0001', 'label' => 'QRIS Static ID'],
            ['key' => 'pos.receipt_footer', 'group' => 'pos', 'value' => 'Terima kasih sudah berbelanja!', 'label' => 'Footer Struk'],
            ['key' => 'pos.cashier_show_favorites', 'group' => 'pos', 'value' => 'true', 'label' => 'Tampilkan Produk Favorit'],
            ['key' => 'pos.cashier_show_stock', 'group' => 'pos', 'value' => 'true', 'label' => 'Tampilkan Stok Produk'],
            ['key' => 'pos.cashier_enable_table', 'group' => 'pos', 'value' => 'true', 'label' => 'Input Nomor Meja'],
            ['key' => 'pos.cashier_enable_ppn', 'group' => 'pos', 'value' => 'true', 'label' => 'Penerapan PPN'],
            ['key' => 'pos.cashier_enable_prepay', 'group' => 'pos', 'value' => 'false', 'label' => 'Sistem Bayar Di Muka'],
            ['key' => 'pos.table_numbers', 'group' => 'pos', 'value' => '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20', 'label' => 'Daftar Nomor Meja'],
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
