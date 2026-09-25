<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Seed a realistic restaurant menu.
     */
    public function run(): void
    {
        $products = [
            // Makanan
            ['name' => 'Nasi Goreng Spesial', 'category' => 'Makanan', 'price' => 25000, 'cost_price' => 12000, 'stock' => 50, 'image' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=400&h=400&fit=crop', 'is_favorite' => true, 'description' => 'Nasi goreng dengan telur mata sapi, ayam suwir, dan kerupuk udang.'],
            ['name' => 'Mie Goreng Jawa', 'category' => 'Makanan', 'price' => 22000, 'cost_price' => 10000, 'stock' => 50, 'image' => 'https://images.unsplash.com/photo-1612929633738-8fe44f7ec841?w=400&h=400&fit=crop', 'description' => 'Mie goreng khas Jawa dengan bakso, sosis, dan acar timun.'],
            ['name' => 'Ayam Geprek', 'category' => 'Makanan', 'price' => 20000, 'cost_price' => 9000, 'stock' => 40, 'image' => 'https://images.unsplash.com/photo-1562967914-608f82629710?w=400&h=400&fit=crop', 'description' => 'Ayam goreng gurih diremas bersama sambal bawang segar.'],
            ['name' => 'Sate Ayam (10 tusuk)', 'category' => 'Makanan', 'price' => 30000, 'cost_price' => 15000, 'stock' => 30, 'image' => 'https://images.unsplash.com/photo-1529193591184-b1d58069ecdd?w=400&h=400&fit=crop', 'is_favorite' => true, 'description' => 'Sate ayam bakar bumbu kacang, lontong, dan irisan bawang.'],
            ['name' => 'Gado-Gado', 'category' => 'Makanan', 'price' => 18000, 'cost_price' => 8000, 'stock' => 30, 'image' => 'https://images.unsplash.com/photo-1541696432-82c129e44543?w=400&h=400&fit=crop', 'description' => 'Sayuran rebus dengan bumbu kacang, telur, dan kerupuk.'],
            ['name' => 'Nasi Uduk Komplit', 'category' => 'Makanan', 'price' => 20000, 'cost_price' => 9500, 'stock' => 35, 'description' => 'Nasi uduk dengan ayam goreng, tempe orek, telur dadar, dan sambal.'],
            ['name' => 'Rendang Sapi', 'category' => 'Makanan', 'price' => 32000, 'cost_price' => 16000, 'stock' => 25, 'description' => 'Daging sapi dimasak dengan santan dan rempah khas Minang.'],
            ['name' => 'Soto Ayam Lamongan', 'category' => 'Makanan', 'price' => 18000, 'cost_price' => 8000, 'stock' => 35, 'description' => 'Soto ayam bening dengan koya, toge, dan sambal jeruk.'],
            ['name' => 'Bakso Sapi Jumbo', 'category' => 'Makanan', 'price' => 20000, 'cost_price' => 9000, 'stock' => 40, 'description' => 'Bakso sapi ukuran jumbo dengan kuah kaldu gurih dan bawang goreng.'],
            ['name' => 'Mie Ayam Bakso', 'category' => 'Makanan', 'price' => 18000, 'cost_price' => 8000, 'stock' => 40, 'description' => 'Mie ayam dengan pangsit goreng, bakso, dan kuah kaldu.'],
            ['name' => 'Ayam Bakar Madu', 'category' => 'Makanan', 'price' => 27000, 'cost_price' => 13000, 'stock' => 30, 'description' => 'Ayam bakar dengan saus madu kecap dan sambal pencit.'],
            ['name' => 'Ikan Gurame Goreng', 'category' => 'Makanan', 'price' => 35000, 'cost_price' => 17000, 'stock' => 20, 'description' => 'Gurame goreng renyah dengan sambal kecap dan jeruk limau.'],
            ['name' => 'Iga Bakar', 'category' => 'Makanan', 'price' => 45000, 'cost_price' => 22000, 'stock' => 15, 'description' => 'Iga sapi bakar empuk dengan saus barbekyu rumahan.'],
            ['name' => 'Nasi Goreng Seafood', 'category' => 'Makanan', 'price' => 30000, 'cost_price' => 15000, 'stock' => 25, 'description' => 'Nasi goreng dengan udang, cumi, dan ikan asin.'],
            ['name' => 'Tongseng Sapi', 'category' => 'Makanan', 'price' => 32000, 'cost_price' => 16000, 'stock' => 20, 'description' => 'Tongseng kambing dengan kuah santan, kol, dan tomat.'],
            ['name' => 'Capcay Goreng', 'category' => 'Makanan', 'price' => 17000, 'cost_price' => 7500, 'stock' => 30, 'description' => 'Sayuran segar ditumis dengan bakso dan saus tiram.'],
            ['name' => 'Cumi Bakar Tepung', 'category' => 'Makanan', 'price' => 29000, 'cost_price' => 14000, 'stock' => 20, 'description' => 'Cumi goreng tepung kriuk disajikan dengan sambal matah.'],
            ['name' => 'Rawon Setan', 'category' => 'Makanan', 'price' => 24000, 'cost_price' => 12000, 'stock' => 20, 'description' => 'Rawon daging sapi dengan kuah hitam kluwek dan telur asin.'],
            ['name' => 'Nasi Campur Bali', 'category' => 'Makanan', 'price' => 26000, 'cost_price' => 12500, 'stock' => 25, 'description' => 'Nasi dengan ayam betutu, sate lilit, dan sambal matah.'],

            // Minuman
            ['name' => 'Es Teh Manis', 'category' => 'Minuman', 'price' => 6000, 'cost_price' => 1500, 'stock' => 100, 'image' => 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=400&h=400&fit=crop', 'is_favorite' => true, 'description' => 'Teh hitam dingin dengan gula, disajikan dengan es batu.'],
            ['name' => 'Es Jeruk', 'category' => 'Minuman', 'price' => 8000, 'cost_price' => 2500, 'stock' => 100, 'image' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?w=400&h=400&fit=crop', 'is_favorite' => true, 'description' => 'Perasan jeruk segar dingin dengan tambahan es.'],
            ['name' => 'Kopi Susu Gula Aren', 'category' => 'Minuman', 'price' => 15000, 'cost_price' => 6000, 'stock' => 60, 'image' => 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=400&h=400&fit=crop', 'is_favorite' => true, 'description' => 'Espresso dengan susu segar dan gula aren asli.'],
            ['name' => 'Air Mineral', 'category' => 'Minuman', 'price' => 5000, 'cost_price' => 2500, 'stock' => 120, 'image' => 'https://images.unsplash.com/photo-1616118132534-381148898bb4?w=400&h=400&fit=crop', 'description' => 'Air mineral kemasan botol 600 ml.'],
            ['name' => 'Es Kopi Susu', 'category' => 'Minuman', 'price' => 18000, 'cost_price' => 7000, 'stock' => 60, 'description' => 'Kopi susu dingin yang creamy dengan gula aren.'],
            ['name' => 'Jus Alpukat', 'category' => 'Minuman', 'price' => 16000, 'cost_price' => 7000, 'stock' => 50, 'description' => 'Jus alpukat kental dengan cokelat dan susu kental manis.'],
            ['name' => 'Jus Mangga', 'category' => 'Minuman', 'price' => 15000, 'cost_price' => 6500, 'stock' => 50, 'description' => 'Jus mangga segar tanpa tambahan gula.'],
            ['name' => 'Es Cendol', 'category' => 'Minuman', 'price' => 14000, 'cost_price' => 6000, 'stock' => 40, 'description' => 'Cendol dengan santan, gula merah, dan es serut.'],
            ['name' => 'Es Campur', 'category' => 'Minuman', 'price' => 15000, 'cost_price' => 6500, 'stock' => 40, 'description' => 'Campuran buah segar dengan sirup, susu, dan es serut.'],
            ['name' => 'Teh Tarik', 'category' => 'Minuman', 'price' => 12000, 'cost_price' => 5000, 'stock' => 60, 'description' => 'Teh susu kental manis yang dipulas, disajikan hangat atau dingin.'],
            ['name' => 'Milkshake Cokelat', 'category' => 'Minuman', 'price' => 19000, 'cost_price' => 8500, 'stock' => 40, 'description' => 'Milkshake cokelat dengan es krim vanila dan topping wafer.'],
            ['name' => 'Lemon Tea', 'category' => 'Minuman', 'price' => 10000, 'cost_price' => 4000, 'stock' => 80, 'description' => 'Teh dingin dengan perasan lemon segar.'],

            // Snack
            ['name' => 'Pisang Goreng', 'category' => 'Snack', 'price' => 10000, 'cost_price' => 4000, 'stock' => 60, 'image' => 'https://images.unsplash.com/photo-1611485988300-b7530defb8e2?w=400&h=400&fit=crop', 'description' => 'Pisang raja goreng gurih dengan taburan gula.'],
            ['name' => 'Kentang Goreng', 'category' => 'Snack', 'price' => 15000, 'cost_price' => 7000, 'stock' => 60, 'image' => 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=400&h=400&fit=crop', 'description' => 'Kentang goreng renyah dengan saus sambal dan saus keju.'],
            ['name' => 'Baso Tahu', 'category' => 'Snack', 'price' => 12000, 'cost_price' => 5000, 'stock' => 50, 'image' => 'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?w=400&h=400&fit=crop', 'description' => 'Tahu goreng isi bakso dengan saus kacang pedas.'],
            ['name' => 'Roti Bakar Cokelat', 'category' => 'Snack', 'price' => 13000, 'cost_price' => 6000, 'stock' => 50, 'description' => 'Roti bakar dengan olesan cokelat dan keju yang meleleh.'],
            ['name' => 'Pastel Goreng', 'category' => 'Snack', 'price' => 9000, 'cost_price' => 4000, 'stock' => 50, 'description' => 'Pastel ayam wortel kentang dengan kulit renyah.'],
            ['name' => 'Risol Mayo', 'category' => 'Snack', 'price' => 11000, 'cost_price' => 5000, 'stock' => 50, 'description' => 'Risol dengan isian mayones dan telur, digoreng hingga kuning.'],
            ['name' => 'Tahu Crispy', 'category' => 'Snack', 'price' => 10000, 'cost_price' => 4500, 'stock' => 60, 'description' => 'Tahu goreng tepung kriuk dengan sambal kecap.'],
            ['name' => 'Tempe Mendoan', 'category' => 'Snack', 'price' => 8000, 'cost_price' => 3500, 'stock' => 60, 'description' => 'Tempe goreng tepung setengah matang dengan sambal kecap.'],
            ['name' => 'Martabak Telur', 'category' => 'Snack', 'price' => 22000, 'cost_price' => 11000, 'stock' => 30, 'description' => 'Martabak telur dengan daging cincang, daun bawang, dan acar.'],
            ['name' => 'Cireng Keju', 'category' => 'Snack', 'price' => 12000, 'cost_price' => 5500, 'stock' => 45, 'description' => 'Cireng isi keju mozzarella dengan bumbu rujak.'],

            // Dessert
            ['name' => 'Es Krim Sundae', 'category' => 'Dessert', 'price' => 15000, 'cost_price' => 7000, 'stock' => 40, 'description' => 'Es krim sundae vanila dengan siraman cokelat.'],
            ['name' => 'Puding Cokelat', 'category' => 'Dessert', 'price' => 12000, 'cost_price' => 5500, 'stock' => 40, 'description' => 'Puding cokelat lembut dengan saus cokelat.'],
            ['name' => 'Puding Lumut', 'category' => 'Dessert', 'price' => 10000, 'cost_price' => 4500, 'stock' => 40, 'description' => 'Puding lumut pandan dengan saus santan gula merah.'],
            ['name' => 'Klepon', 'category' => 'Dessert', 'price' => 8000, 'cost_price' => 3500, 'stock' => 50, 'description' => 'Kue klepon tepung ketan isi gula merah bertabur kelapa.'],
            ['name' => 'Getuk Lindri', 'category' => 'Dessert', 'price' => 9000, 'cost_price' => 4000, 'stock' => 45, 'description' => 'Getuk singkong warna-warni dengan taburan parutan kelapa.'],
            ['name' => 'Lapis Legit', 'category' => 'Dessert', 'price' => 14000, 'cost_price' => 6500, 'stock' => 35, 'description' => 'Kue lapis legit empuk berlapis dengan aroma rempah.'],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['name' => $product['name']],
                $product + ['image' => $product['image'] ?? null, 'is_active' => true]
            );
        }
    }
}
