<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Katalog menu restoran, dikelompokkan per kategori.
     *
     * Setiap item berformat:
     * [nama, harga, modal, stok, deskripsi, is_favorite?]
     *
     * SKU dibuat otomatis dari awalan kategori + nomor urut, dan kolom
     * `image` diisi path file JPEG lokal. Berkas gambarnya diunduh oleh command
     * `php artisan products:images` (dipanggil dari DatabaseSeeder), bukan
     * dari seeder ini, agar aman ketika seeder dipakai pada test.
     *
     * @var array<string, array{prefix: string, items: array<int, array<int, string|int|bool>>}>
     */
    private const CATALOG = [
        'Makanan' => [
            'prefix' => 'MKN',
            'items' => [
                ['Nasi Goreng Spesial', 25000, 12000, 50, 'Nasi goreng dengan telur mata sapi, ayam suwir, dan kerupuk udang.', true],
                ['Nasi Goreng Kampung', 18000, 8000, 55, 'Nasi goreng khas kampung dengan bawang goreng, kecap manis, dan acar.', false],
                ['Nasi Goreng Seafood', 30000, 15000, 25, 'Nasi goreng dengan udang, cumi, dan ikan asin.', false],
                ['Nasi Uduk Komplit', 20000, 9500, 35, 'Nasi uduk dengan ayam goreng, tempe orek, telur dadar, dan sambal.', false],
                ['Nasi Uduk Ayam Goreng', 19000, 9000, 32, 'Nasi uduk dengan ayam goreng, sambal kacang, dan telur rebus.', false],
                ['Nasi Bakar Ayam', 21000, 10000, 30, 'Nasi bakar isi suwiran ayam, telur, dan acar.', false],
                ['Nasi Lemak Kampung', 20000, 9500, 40, 'Nasi lemak santan dengan telur balado, ikan bilis, dan kacang.', false],
                ['Nasi Campur Bali', 26000, 12500, 25, 'Nasi dengan ayam betutu, sate lilit, dan sambal matah.', false],
                ['Nasi Kapau', 26000, 12500, 22, 'Nasi kapau khas Sumatra Barat dengan gulai ayam, gulai jengkol, dan sayur asem.', false],
                ['Mie Goreng Jawa', 22000, 10000, 45, 'Mie goreng khas Jawa dengan bakso, sosis, dan acar timun.', false],
                ['Mie Goreng Spesial', 24000, 11000, 40, 'Mie goreng dengan seafood, ayam suwir, dan telur.', false],
                ['Mie Kuah Bakso', 19000, 8500, 38, 'Mie kuah bening dengan bakso, pangsit, dan taburan bawange.', false],
                ['Mie Ayam Bakso', 18000, 8000, 40, 'Mie ayam dengan pangsit goreng, bakso, dan kuah kaldu.', false],
                ['Ayam Geprek', 20000, 9000, 40, 'Ayam goreng gurih diremas bersama sambal bawang segar.', false],
                ['Ayam Bakar Madu', 27000, 13000, 30, 'Ayam bakar dengan saus madu kecap dan sambal pencit.', false],
                ['Ayam Bakar Taliwang', 29000, 14000, 28, 'Ayam bakar khas Sumbawa dengan sambal kecap dan potongan daging.', false],
                ['Ayam Penyet', 23000, 11000, 29, 'Ayam penyet sambal kemangi dengan lalapan.', false],
                ['Sate Ayam (10 Tusuk)', 30000, 15000, 30, 'Sate ayam bakar bumbu kacang, lontong, dan irisan bawang.', true],
                ['Rendang Sapi', 32000, 16000, 25, 'Daging sapi dimasak dengan santan dan rempah khas Minang.', false],
                ['Tongseng Sapi', 32000, 16000, 20, 'Tongseng daging sapi dengan kuah santan, kol, dan tomat.', false],
                ['Soto Ayam Lamongan', 18000, 8000, 35, 'Soto ayam bening dengan koya, toge, dan sambal jeruk.', false],
                ['Soto Ayam Kampung', 17000, 7500, 34, 'Soto ayam bening dengan bakso, perkedel, dan kemangi.', false],
                ['Bakso Sapi Jumbo', 20000, 9000, 40, 'Bakso sapi ukuran jumbo dengan kuah kaldu gurih dan bawang goreng.', true],
                ['Rawon Setan', 24000, 12000, 20, 'Rawon daging sapi dengan kuah hitam kluwek dan telur asin.', false],
                ['Sup Iga Sapi', 27000, 13500, 22, 'Sup iga sapi bening dengan wortel dan seledri.', false],
                ['Ikan Gurame Goreng', 35000, 17000, 20, 'Gurame goreng renyah dengan sambal kecap dan jeruk limau.', false],
                ['Cumi Bakar Tepung', 29000, 14000, 20, 'Cumi goreng tepung kriuk disajikan dengan sambal matah.', false],
                ['Iga Bakar', 45000, 22000, 15, 'Iga sapi bakar empuk dengan saus barbekyu rumahan.', false],
                ['Iga Bakar Sambal Dabu', 47000, 24000, 14, 'Iga bakar dengan sambal dabu-dabu khas Manado.', false],
                ['Sayur Lodeh', 16000, 7000, 25, 'Sayur lodeh nangka dengan santan khas Jawa.', false],
            ],
        ],
        'Minuman' => [
            'prefix' => 'MNR',
            'items' => [
                ['Es Teh Manis', 6000, 1500, 100, 'Teh hitam dingin dengan gula, disajikan dengan es batu.', true],
                ['Es Jeruk', 8000, 2500, 100, 'Perasan jeruk segar dingin dengan tambahan es.', true],
                ['Kopi Susu Gula Aren', 15000, 6000, 60, 'Espresso dengan susu segar dan gula aren asli.', true],
                ['Es Kopi Susu', 18000, 7000, 60, 'Kopi susu dingin yang creamy dengan gula aren.', false],
                ['Kopi Hitam', 10000, 3500, 55, 'Kopi hitam tunggal dengan aroma pekat dan tidak bergula.', false],
                ['Air Mineral', 5000, 2500, 120, 'Air mineral kemasan botol 600 ml.', false],
                ['Jus Alpukat', 16000, 7000, 50, 'Jus alpukat kental dengan cokelat dan susu kental manis.', false],
                ['Jus Mangga', 15000, 6500, 50, 'Jus mangga segar tanpa tambahan gula.', false],
                ['Jus Jambu', 14000, 6000, 50, 'Jus jambu kristal manis yang menyegarkan.', false],
                ['Es Cendol', 14000, 6000, 40, 'Cendol dengan santan, gula merah, dan es serut.', false],
                ['Es Campur', 15000, 6500, 40, 'Campuran buah segar dengan sirup, susu, dan es serut.', false],
                ['Teh Tarik', 12000, 5000, 60, 'Teh susu kental manis yang dipulas, disajikan hangat atau dingin.', false],
                ['Milkshake Cokelat', 19000, 8500, 40, 'Milkshake cokelat dengan es krim vanila dan topping wafer.', false],
                ['Lemon Tea', 10000, 4000, 80, 'Teh dingin dengan perasan lemon segar.', false],
            ],
        ],
        'Snack' => [
            'prefix' => 'SNK',
            'items' => [
                ['Pisang Goreng', 10000, 4000, 60, 'Pisang raja goreng gurih dengan taburan gula.', false],
                ['Kentang Goreng', 15000, 7000, 60, 'Kentang goreng renyah dengan saus sambal dan saus keju.', false],
                ['Baso Tahu', 12000, 5000, 50, 'Tahu goreng isi bakso dengan saus kacang pedas.', false],
                ['Roti Bakar Cokelat', 13000, 6000, 50, 'Roti bakar dengan olesan cokelat dan keju yang meleleh.', false],
                ['Pastel Goreng', 9000, 4000, 50, 'Pastel ayam wortel kentang dengan kulit renyah.', false],
                ['Risol Mayo', 11000, 5000, 50, 'Risol dengan isian mayones dan telur, digoreng hingga kuning.', false],
                ['Tahu Crispy', 10000, 4500, 60, 'Tahu goreng tepung kriuk dengan sambal kecap.', false],
                ['Tempe Mendoan', 8000, 3500, 60, 'Tempe goreng setengah matang dengan sambal kecap.', false],
                ['Martabak Telur', 22000, 11000, 30, 'Martabak telur dengan daging cincang, daun bawang, dan acar.', false],
                ['Cireng Keju', 12000, 5500, 45, 'Cireng isi keju mozzarella dengan bumbu rujak.', false],
                ['Perkedel Kentang (5 pcs)', 12000, 5000, 40, 'Perkedel kentang goreng dengan mayones dan kacang.', false],
                ['Tahu Isi Telur', 15000, 6500, 30, 'Tahu goreng isi telur puyuh dengan saus sambal.', false],
            ],
        ],
        'Dessert' => [
            'prefix' => 'DST',
            'items' => [
                ['Es Krim Sundae', 15000, 7000, 40, 'Es krim sundae vanila dengan siraman cokelat.', false],
                ['Puding Cokelat', 12000, 5500, 40, 'Puding cokelat lembut dengan saus cokelat.', false],
                ['Puding Lumut', 10000, 4500, 40, 'Puding lumut pandan dengan saus santan gula merah.', false],
                ['Klepon', 8000, 3500, 50, 'Kue klepon tepung ketan isi gula merah bertabur kelapa.', false],
                ['Getuk Lindri', 9000, 4000, 45, 'Getuk singkong warna-warni dengan taburan parutan kelapa.', false],
                ['Lapis Legit', 14000, 6500, 35, 'Kue lapis legit empuk berlapis dengan aroma rempah.', false],
            ],
        ],
    ];

    /**
     * Path gambar lokal produk, sesuai berkas hasil unduhan command
     * `php artisan products:images`.
     */
    private static function imagePath(string $sku): string
    {
        return "/images/products/{$sku}.jpg";
    }

    /**
     * Seed the product catalog.
     */
    public function run(): void
    {
        foreach (self::CATALOG as $category => $definition) {
            foreach ($definition['items'] as $index => $item) {
                $sku = $definition['prefix'].'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

                Product::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => $item[0],
                        'price' => $item[1],
                        'cost_price' => $item[2],
                        'stock' => $item[3],
                        'description' => $item[4],
                        'category' => $category,
                        'image' => self::imagePath($sku),
                        'is_favorite' => $item[5] ?? false,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
