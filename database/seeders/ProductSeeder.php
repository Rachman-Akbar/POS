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
            ['name' => 'Nasi Uduk Komplit', 'category' => 'Makanan', 'price' => 20000, 'cost_price' => 9500, 'stock' => 35, 'image' => 'https://live.staticflickr.com/3270/2718880897_b2ddee7e90.jpg', 'description' => 'Nasi uduk dengan ayam goreng, tempe orek, telur dadar, dan sambal.'],
            ['name' => 'Rendang Sapi', 'category' => 'Makanan', 'price' => 32000, 'cost_price' => 16000, 'stock' => 25, 'image' => 'https://live.staticflickr.com/3430/3212668888_b905c99642_b.jpg', 'description' => 'Daging sapi dimasak dengan santan dan rempah khas Minang.'],
            ['name' => 'Soto Ayam Lamongan', 'category' => 'Makanan', 'price' => 18000, 'cost_price' => 8000, 'stock' => 35, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/6/69/Soto_Ayam_home-made.JPG', 'description' => 'Soto ayam bening dengan koya, toge, dan sambal jeruk.'],
            ['name' => 'Bakso Sapi Jumbo', 'category' => 'Makanan', 'price' => 20000, 'cost_price' => 9000, 'stock' => 40, 'image' => 'https://live.staticflickr.com/49/139637646_c181c33824_b.jpg', 'description' => 'Bakso sapi ukuran jumbo dengan kuah kaldu gurih dan bawang goreng.'],
            ['name' => 'Mie Ayam Bakso', 'category' => 'Makanan', 'price' => 18000, 'cost_price' => 8000, 'stock' => 40, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/e/e4/Wonogiri-style_noodle_soup_mie_ayam.jpg', 'description' => 'Mie ayam dengan pangsit goreng, bakso, dan kuah kaldu.'],
            ['name' => 'Ayam Bakar Madu', 'category' => 'Makanan', 'price' => 27000, 'cost_price' => 13000, 'stock' => 30, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/4/4c/Ayam_bakar_khas_Taliwang_2.JPG', 'description' => 'Ayam bakar dengan saus madu kecap dan sambal pencit.'],
            ['name' => 'Ikan Gurame Goreng', 'category' => 'Makanan', 'price' => 35000, 'cost_price' => 17000, 'stock' => 20, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/e/ec/Gurame_bakar_kecap_2.JPG', 'description' => 'Gurame goreng renyah dengan sambal kecap dan jeruk limau.'],
            ['name' => 'Iga Bakar', 'category' => 'Makanan', 'price' => 45000, 'cost_price' => 22000, 'stock' => 15, 'image' => 'https://live.staticflickr.com/1367/865133782_7f05773de6.jpg', 'description' => 'Iga sapi bakar empuk dengan saus barbekyu rumahan.'],
            ['name' => 'Nasi Goreng Seafood', 'category' => 'Makanan', 'price' => 30000, 'cost_price' => 15000, 'stock' => 25, 'image' => 'https://live.staticflickr.com/2341/2056323956_f7b0b52b00.jpg', 'description' => 'Nasi goreng dengan udang, cumi, dan ikan asin.'],
            ['name' => 'Tongseng Sapi', 'category' => 'Makanan', 'price' => 32000, 'cost_price' => 16000, 'stock' => 20, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/e/eb/Tongseng_Dish.jpg', 'description' => 'Tongseng kambing dengan kuah santan, kol, dan tomat.'],
            ['name' => 'Capcay Goreng', 'category' => 'Makanan', 'price' => 17000, 'cost_price' => 7500, 'stock' => 30, 'image' => 'https://live.staticflickr.com/4132/5026495305_448268553e_b.jpg', 'description' => 'Sayuran segar ditumis dengan bakso dan saus tiram.'],
            ['name' => 'Cumi Bakar Tepung', 'category' => 'Makanan', 'price' => 29000, 'cost_price' => 14000, 'stock' => 20, 'image' => 'https://live.staticflickr.com/163/332778972_992d231d2d_b.jpg', 'description' => 'Cumi goreng tepung kriuk disajikan dengan sambal matah.'],
            ['name' => 'Rawon Setan', 'category' => 'Makanan', 'price' => 24000, 'cost_price' => 12000, 'stock' => 20, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/7/7e/Rawon_Setan.jpg', 'description' => 'Rawon daging sapi dengan kuah hitam kluwek dan telur asin.'],
            ['name' => 'Nasi Campur Bali', 'category' => 'Makanan', 'price' => 26000, 'cost_price' => 12500, 'stock' => 25, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Nasi_Campur_Bali_Sate_Lilit.jpg', 'description' => 'Nasi dengan ayam betutu, sate lilit, dan sambal matah.'],

            // Minuman
            ['name' => 'Es Teh Manis', 'category' => 'Minuman', 'price' => 6000, 'cost_price' => 1500, 'stock' => 100, 'image' => 'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=400&h=400&fit=crop', 'is_favorite' => true, 'description' => 'Teh hitam dingin dengan gula, disajikan dengan es batu.'],
            ['name' => 'Es Jeruk', 'category' => 'Minuman', 'price' => 8000, 'cost_price' => 2500, 'stock' => 100, 'image' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?w=400&h=400&fit=crop', 'is_favorite' => true, 'description' => 'Perasan jeruk segar dingin dengan tambahan es.'],
            ['name' => 'Kopi Susu Gula Aren', 'category' => 'Minuman', 'price' => 15000, 'cost_price' => 6000, 'stock' => 60, 'image' => 'https://images.unsplash.com/photo-1541167760496-1628856ab772?w=400&h=400&fit=crop', 'is_favorite' => true, 'description' => 'Espresso dengan susu segar dan gula aren asli.'],
            ['name' => 'Air Mineral', 'category' => 'Minuman', 'price' => 5000, 'cost_price' => 2500, 'stock' => 120, 'image' => 'https://images.unsplash.com/photo-1616118132534-381148898bb4?w=400&h=400&fit=crop', 'description' => 'Air mineral kemasan botol 600 ml.'],
            ['name' => 'Es Kopi Susu', 'category' => 'Minuman', 'price' => 18000, 'cost_price' => 7000, 'stock' => 60, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/5/54/Es_kopi_susu_kekinian_di_Yogyakarta%2C_Indonesia.jpg', 'description' => 'Kopi susu dingin yang creamy dengan gula aren.'],
            ['name' => 'Jus Alpukat', 'category' => 'Minuman', 'price' => 16000, 'cost_price' => 7000, 'stock' => 50, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/2/2c/Jus_alpukat_Bandung.JPG', 'description' => 'Jus alpukat kental dengan cokelat dan susu kental manis.'],
            ['name' => 'Jus Mangga', 'category' => 'Minuman', 'price' => 15000, 'cost_price' => 6500, 'stock' => 50, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/a/a4/Mangga_Wani.jpg', 'description' => 'Jus mangga segar tanpa tambahan gula.'],
            ['name' => 'Es Cendol', 'category' => 'Minuman', 'price' => 14000, 'cost_price' => 6000, 'stock' => 40, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/e/e5/Cendol_in_a_Glass.JPG', 'description' => 'Cendol dengan santan, gula merah, dan es serut.'],
            ['name' => 'Es Campur', 'category' => 'Minuman', 'price' => 15000, 'cost_price' => 6500, 'stock' => 40, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/d/d8/Es_Campur.jpg', 'description' => 'Campuran buah segar dengan sirup, susu, dan es serut.'],
            ['name' => 'Teh Tarik', 'category' => 'Minuman', 'price' => 12000, 'cost_price' => 5000, 'stock' => 60, 'image' => 'https://live.staticflickr.com/1394/1139626060_743ad7f06d_b.jpg', 'description' => 'Teh susu kental manis yang dipulas, disajikan hangat atau dingin.'],
            ['name' => 'Milkshake Cokelat', 'category' => 'Minuman', 'price' => 19000, 'cost_price' => 8500, 'stock' => 40, 'image' => 'https://live.staticflickr.com/7159/6389039119_7d45ee9985.jpg', 'description' => 'Milkshake cokelat dengan es krim vanila dan topping wafer.'],
            ['name' => 'Lemon Tea', 'category' => 'Minuman', 'price' => 10000, 'cost_price' => 4000, 'stock' => 80, 'image' => 'https://live.staticflickr.com/7142/6641522097_ac53e29872_b.jpg', 'description' => 'Teh dingin dengan perasan lemon segar.'],

            // Snack
            ['name' => 'Pisang Goreng', 'category' => 'Snack', 'price' => 10000, 'cost_price' => 4000, 'stock' => 60, 'image' => 'https://images.unsplash.com/photo-1611485988300-b7530defb8e2?w=400&h=400&fit=crop', 'description' => 'Pisang raja goreng gurih dengan taburan gula.'],
            ['name' => 'Kentang Goreng', 'category' => 'Snack', 'price' => 15000, 'cost_price' => 7000, 'stock' => 60, 'image' => 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=400&h=400&fit=crop', 'description' => 'Kentang goreng renyah dengan saus sambal dan saus keju.'],
            ['name' => 'Baso Tahu', 'category' => 'Snack', 'price' => 12000, 'cost_price' => 5000, 'stock' => 50, 'image' => 'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?w=400&h=400&fit=crop', 'description' => 'Tahu goreng isi bakso dengan saus kacang pedas.'],
            ['name' => 'Roti Bakar Cokelat', 'category' => 'Snack', 'price' => 13000, 'cost_price' => 6000, 'stock' => 50, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/1/1a/Roti_bakar_kekinian.jpg', 'description' => 'Roti bakar dengan olesan cokelat dan keju yang meleleh.'],
            ['name' => 'Pastel Goreng', 'category' => 'Snack', 'price' => 9000, 'cost_price' => 4000, 'stock' => 50, 'image' => 'https://live.staticflickr.com/6003/5892645995_d79a5d33ca_b.jpg', 'description' => 'Pastel ayam wortel kentang dengan kulit renyah.'],
            ['name' => 'Risol Mayo', 'category' => 'Snack', 'price' => 11000, 'cost_price' => 5000, 'stock' => 50, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Kue_risoles.JPG', 'description' => 'Risol dengan isian mayones dan telur, digoreng hingga kuning.'],
            ['name' => 'Tahu Crispy', 'category' => 'Snack', 'price' => 10000, 'cost_price' => 4500, 'stock' => 60, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/9/93/Tahu_Goreng.jpg', 'description' => 'Tahu goreng tepung kriuk dengan sambal kecap.'],
            ['name' => 'Tempe Mendoan', 'category' => 'Snack', 'price' => 8000, 'cost_price' => 3500, 'stock' => 60, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/f/ff/Tempe_mendoan_fried_tempeh.jpg', 'description' => 'Tempe goreng tepung setengah matang dengan sambal kecap.'],
            ['name' => 'Martabak Telur', 'category' => 'Snack', 'price' => 22000, 'cost_price' => 11000, 'stock' => 30, 'image' => 'https://live.staticflickr.com/2315/2181397189_1c00e349ae_b.jpg', 'description' => 'Martabak telur dengan daging cincang, daun bawang, dan acar.'],
            ['name' => 'Cireng Keju', 'category' => 'Snack', 'price' => 12000, 'cost_price' => 5500, 'stock' => 45, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/4/45/Cireng_isi.jpg', 'description' => 'Cireng isi keju mozzarella dengan bumbu rujak.'],

            // Dessert
            ['name' => 'Es Krim Sundae', 'category' => 'Dessert', 'price' => 15000, 'cost_price' => 7000, 'stock' => 40, 'image' => 'https://live.staticflickr.com/8014/7575345032_cd046cb152_b.jpg', 'description' => 'Es krim sundae vanila dengan siraman cokelat.'],
            ['name' => 'Puding Cokelat', 'category' => 'Dessert', 'price' => 12000, 'cost_price' => 5500, 'stock' => 40, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/7/7c/Puding_Coklat.jpg', 'description' => 'Puding cokelat lembut dengan saus cokelat.'],
            ['name' => 'Puding Lumut', 'category' => 'Dessert', 'price' => 10000, 'cost_price' => 4500, 'stock' => 40, 'image' => 'https://live.staticflickr.com/2099/2049255601_d990e78560_b.jpg', 'description' => 'Puding lumut pandan dengan saus santan gula merah.'],
            ['name' => 'Klepon', 'category' => 'Dessert', 'price' => 8000, 'cost_price' => 3500, 'stock' => 50, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/3/34/Klepon.JPG', 'description' => 'Kue klepon tepung ketan isi gula merah bertabur kelapa.'],
            ['name' => 'Getuk Lindri', 'category' => 'Dessert', 'price' => 9000, 'cost_price' => 4000, 'stock' => 45, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/a/af/Getuk_lindri.jpg', 'description' => 'Getuk singkong warna-warni dengan taburan parutan kelapa.'],
            ['name' => 'Lapis Legit', 'category' => 'Dessert', 'price' => 14000, 'cost_price' => 6500, 'stock' => 35, 'image' => 'https://upload.wikimedia.org/wikipedia/commons/e/eb/Indonesian_kue_lapis_legit_-_20130217.jpg', 'description' => 'Kue lapis legit empuk berlapis dengan aroma rempah.'],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['name' => $product['name']],
                $product + ['image' => $product['image'] ?? null, 'is_active' => true]
            );
        }
    }
}
