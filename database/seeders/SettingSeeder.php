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
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
