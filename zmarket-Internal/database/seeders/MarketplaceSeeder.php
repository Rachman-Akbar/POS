<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MarketplaceSeeder extends Seeder
{
    private array $categorySlugs = [];

    public function run(): void
    {
        Artisan::call('app:generate-placeholders');

        DB::transaction(function (): void {
            $this->seedUsers();
            $categories = $this->seedCategories();

            Order::withTrashed()->forceDelete();
            Customer::query()->delete();
            Product::query()->delete();

            $products = $this->seedProducts($categories);
            $this->seedOrders($products);
        });
    }

    private function seedUsers(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@company.local'],
            [
                'name' => 'Marketplace Admin',
                'password' => Hash::make('12345678'),
                'role' => 'admin',
                'phone' => '081200000001',
                'department' => 'Information Technology',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'seller@company.local'],
            [
                'name' => 'Marketplace Seller',
                'password' => Hash::make('12345678'),
                'role' => 'seller',
                'phone' => '081200000002',
                'department' => 'Procurement',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }

    private function seedCategories()
    {
        $categoriesData = [
            ['name' => 'Elektronik Kantor', 'desc' => 'Perangkat elektronik untuk kebutuhan operasional kantor sehari-hari.'],
            ['name' => 'Perlengkapan Kerja', 'desc' => 'Alat dan perlengkapan pendukung produktivitas kerja.'],
            ['name' => 'Alat Tulis Kantor', 'desc' => 'Kebutuhan alat tulis menulis untuk aktivitas kantor.'],
            ['name' => 'Furniture Kantor', 'desc' => 'Furniture dan perlengkapan ruang kerja yang ergonomis.'],
            ['name' => 'Kebutuhan Pantry', 'desc' => 'Kebutuhan makanan, minuman, dan perlengkapan pantry kantor.'],
            ['name' => 'Seragam dan Atribut', 'desc' => 'Seragam kerja dan atribut perusahaan resmi.'],
            ['name' => 'Layanan Teknologi', 'desc' => 'Layanan teknologi informasi dan dukungan sistem.'],
            ['name' => 'Layanan Umum', 'desc' => 'Layanan pendukung operasional kantor umum.'],
            ['name' => 'Kesehatan dan Keselamatan', 'desc' => 'Perlengkapan APD dan kesehatan keselamatan kerja.'],
            ['name' => 'Promosi dan Dokumentasi', 'desc' => 'Layanan promosi, cetak, dan dokumentasi perusahaan.'],
        ];

        $this->categorySlugs = [];

        return collect($categoriesData)->map(function (array $data, int $index): Category {
            $slug = Str::slug($data['name']);
            $this->categorySlugs[$data['name']] = $slug;

            return Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'description' => $data['desc'],
                    'image' => null,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        });
    }

    private function seedProducts($categories): Collection
    {
        $productsData = [
            // Elektronik Kantor
            ['cat' => 'Elektronik Kantor', 'name' => 'Laptop ASUS VivoBook 14 X1404ZA', 'sku' => 'ELK-0001', 'brand' => 'ASUS', 'price' => 8499000, 'stock' => 24, 'desc' => 'Laptop 14 inch dengan prosesor Intel Core i5-1235U, RAM 8GB, SSD 512GB. Cocok untuk produktivitas harian.', 'featured' => true],
            ['cat' => 'Elektronik Kantor', 'name' => 'Monitor LG 24MP400-B IPS Full HD', 'sku' => 'ELK-0002', 'brand' => 'LG', 'price' => 1875000, 'stock' => 36, 'desc' => 'Monitor 24 inch IPS panel, resolusi 1920x1080, refresh rate 75Hz. Tampilan tajam untuk semua kebutuhan.', 'featured' => true],
            ['cat' => 'Elektronik Kantor', 'name' => 'Keyboard Mechanical Logitech G413', 'sku' => 'ELK-0003', 'brand' => 'Logitech', 'price' => 1150000, 'stock' => 45, 'desc' => 'Keyboard mekanikal dengan switch Romer-G, backlight LED, bodi aluminium. Daya tahan tinggi untuk penggunaan intensif.', 'featured' => true],
            ['cat' => 'Elektronik Kantor', 'name' => 'Mouse Wireless Logitech M331 Silent', 'sku' => 'ELK-0004', 'brand' => 'Logitech', 'price' => 375000, 'stock' => 60, 'desc' => 'Mouse wireless silent click, DPI 1000, baterai hingga 24 bulan. Nyaman dan senyap.', 'featured' => true],
            ['cat' => 'Elektronik Kantor', 'name' => 'Printer HP LaserJet Pro M404dn', 'sku' => 'ELK-0005', 'brand' => 'HP', 'price' => 5800000, 'stock' => 12, 'desc' => 'Printer laser monokrom, duplex otomatis, kecepatan 38 ppm. Ideal untuk kantor dengan volume cetak tinggi.', 'featured' => true],
            ['cat' => 'Elektronik Kantor', 'name' => 'Headset Jabra Evolve2 40', 'sku' => 'ELK-0006', 'brand' => 'Jabra', 'price' => 1625000, 'stock' => 30, 'desc' => 'Headset kabel profesional dengan noise isolating microphone. Dirancang untuk panggilan conference.', 'featured' => true],
            ['cat' => 'Elektronik Kantor', 'name' => 'Webcam Logitech C920s HD Pro', 'sku' => 'ELK-0007', 'brand' => 'Logitech', 'price' => 1050000, 'stock' => 28, 'desc' => 'Webcam Full HD 1080p dengan auto-focus dan privacy shutter. Cocok untuk video meeting.', 'featured' => true],
            ['cat' => 'Elektronik Kantor', 'name' => 'UPS APC Back-UPS BX1100LI 660W', 'sku' => 'ELK-0008', 'brand' => 'APC', 'price' => 2150000, 'stock' => 18, 'desc' => 'UPS 1100VA/660W denganAutomatic Voltage Regulator. Perlindungan daya untuk perangkat kantor.'],

            // Perlengkapan Kerja
            ['cat' => 'Perlengkapan Kerja', 'name' => 'Pulpen Pilot G2 0.7mm Hitam', 'sku' => 'PKR-0009', 'brand' => 'Pilot', 'price' => 32500, 'stock' => 200, 'desc' => 'Pulpen gel berenergi, ujung 0.7mm, tinta hitam pekat. Garis stabil dan tidak putus.'],
            ['cat' => 'Perlengkapan Kerja', 'name' => 'Stabilo Boss Highlighter Set 6 Warna', 'sku' => 'PKR-0010', 'brand' => 'Stabilo', 'price' => 87500, 'stock' => 80, 'desc' => 'Set 6 warna highlighter dengan ujung tip 1.5mm dan 5.0mm. Tidak menembus kertas.'],
            ['cat' => 'Perlengkapan Kerja', 'name' => 'Map Folio Golden 10 pcs', 'sku' => 'PKR-0011', 'brand' => 'Golden', 'price' => 65000, 'stock' => 120, 'desc' => 'Map folio transparan, ukuran F4. Cocok untuk menyimpan dokumen penting.'],
            ['cat' => 'Perlengkapan Kerja', 'name' => 'Binder Clips Set 24 pcs Assorted', 'sku' => 'PKR-0012', 'brand' => 'Kenko', 'price' => 45000, 'stock' => 150, 'desc' => 'Set 24 clip binder berbagai ukuran (19mm, 25mm, 32mm, 51mm). Daya jepit kuat.'],
            ['cat' => 'Perlengkapan Kerja', 'name' => 'Stapler Kenko Heavy Duty No.10', 'sku' => 'PKR-0013', 'brand' => 'Kenko', 'price' => 125000, 'stock' => 40, 'desc' => 'Stapler heavy duty kapasitas 80 lembar, ringan dan ergonomis.'],
            ['cat' => 'Perlengkapan Kerja', 'name' => 'Paper Shredder AO 600S 12L', 'sku' => 'PKR-0014', 'brand' => 'AO', 'price' => 1350000, 'stock' => 15, 'desc' => 'Mesin penghancur kertas 12L, kapasitas 6 lembar sekaligus, keamanan P-3.'],

            // Alat Tulis Kantor
            ['cat' => 'Alat Tulis Kantor', 'name' => 'Buku Tulis Sinar Dunia 38 lembar', 'sku' => 'ATK-0015', 'brand' => 'Sinar Dunia', 'price' => 5500, 'stock' => 500, 'desc' => 'Buku tulis 38 lembar, ukuran A5, kertas HVS 70gsm. Tulisan tidak tembus.'],
            ['cat' => 'Alat Tulis Kantor', 'name' => 'Spidol Snowman Board Marker Set', 'sku' => 'ATK-0016', 'brand' => 'Snowman', 'price' => 48000, 'stock' => 90, 'desc' => 'Set 4 warna spidol papan tulis. Mudah dihapus, tidak meninggalkan bekas.'],
            ['cat' => 'Alat Tulis Kantor', 'name' => 'Tinta Printer Epson 003 Black', 'sku' => 'ATK-0017', 'brand' => 'Epson', 'price' => 135000, 'stock' => 70, 'desc' => 'Tinta asli Epson 003 warna hitam, kapasitas cetak hingga 4500 halaman.'],
            ['cat' => 'Alat Tulis Kantor', 'name' => 'Kertas A4 Bibo 70gsm 500 lembar', 'sku' => 'ATK-0018', 'brand' => 'Bibo', 'price' => 62000, 'stock' => 300, 'desc' => 'Kertas HVS A4 70gsm isi 500 lembar. Cocok untuk pencetakan dokumen sehari-hari.'],
            ['cat' => 'Alat Tulis Kantor', 'name' => 'Pensil 2B Faber Castell 12 pcs', 'sku' => 'ATK-0019', 'brand' => 'Faber Castell', 'price' => 72000, 'stock' => 100, 'desc' => 'Pensil 2B isi 12 batang. Tulisan halus, tidak mudah patah, nyaman digenggam.'],
            ['cat' => 'Alat Tulis Kantor', 'name' => 'Penggaris Stainless 30cm', 'sku' => 'ATK-0020', 'brand' => 'Butterfly', 'price' => 28000, 'stock' => 80, 'desc' => 'Penggaris stainless steel 30cm, tahan karat, skala presisi.'],

            // Furniture Kantor
            ['cat' => 'Furniture Kantor', 'name' => 'Meja Kerja Executive 120x60cm', 'sku' => 'FRN-0021', 'brand' => 'Olympic', 'price' => 2250000, 'stock' => 10, 'desc' => 'Meja kerja kayu particle board, lapis HPL, ukuran 120x60x75cm. Kuat dan elegan.', 'featured' => true],
            ['cat' => 'Furniture Kantor', 'name' => 'Kursi Ergonomis Ergotec Hips 100', 'sku' => 'FRN-0022', 'brand' => 'Ergotec', 'price' => 3750000, 'stock' => 15, 'desc' => 'Kursi ergonomis dengan lumbar support, adjustable armrest, dan tilt control.', 'featured' => true],
            ['cat' => 'Furniture Kantor', 'name' => 'Lemari Arsip 4 Laci Bantex', 'sku' => 'FRN-0023', 'brand' => 'Bantex', 'price' => 2875000, 'stock' => 8, 'desc' => 'Lemari arsip logam 4 laci, kunci sentral, kapasitas 200 folder. Tahan lama.'],
            ['cat' => 'Furniture Kantor', 'name' => 'Whiteboard Magnetik 120x90cm', 'sku' => 'FRN-0024', 'brand' => 'Sakana', 'price' => 675000, 'stock' => 20, 'desc' => 'Papan tulis magnetik 120x90cm, frame aluminium, permukaan enamel.'],
            ['cat' => 'Furniture Kantor', 'name' => 'Partisi Kantor Portable 4 Panel', 'sku' => 'FRN-0025', 'brand' => 'Generic', 'price' => 1850000, 'stock' => 6, 'desc' => 'Partisi lipat 4 panel tinggi 180cm, bahan fabric, mudah dipindahkan.'],

            // Kebutuhan Pantry
            ['cat' => 'Kebutuhan Pantry', 'name' => 'Kopi Kapal Api Special 48 sachet', 'sku' => 'PTR-0026', 'brand' => 'Kapal Api', 'price' => 68000, 'stock' => 100, 'desc' => 'Kopi bubuk mix gula, 48 sachet. Rasa kopi robusta khas Indonesia.'],
            ['cat' => 'Kebutuhan Pantry', 'name' => 'Gelas Keramik 350ml Set 6 pcs', 'sku' => 'PTR-0027', 'brand' => 'Luminarc', 'price' => 175000, 'stock' => 30, 'desc' => 'Gelas keramik putih 350ml isi 6. Aman untuk mesin cuci piring.'],
            ['cat' => 'Kebutuhan Pantry', 'name' => 'Air Galon Le Minerale 19L', 'sku' => 'PTR-0028', 'brand' => 'Le Minerale', 'price' => 18000, 'stock' => 200, 'desc' => 'Air mineral galon 19 liter, kandungan mineral alami, tanpa pengawet.'],
            ['cat' => 'Kebutuhan Pantry', 'name' => 'Tisu Paseo Facial 250 sheets', 'sku' => 'PTR-0029', 'brand' => 'Paseo', 'price' => 32000, 'stock' => 120, 'desc' => 'Tisu wajah lembut 250 lembar, 3 lapis, hypoallergenic.'],
            ['cat' => 'Kebutuhan Pantry', 'name' => 'Sabun Cuci Piring Sunlight 800ml', 'sku' => 'PTR-0030', 'brand' => 'Sunlight', 'price' => 28500, 'stock' => 60, 'desc' => 'Sabun cuci piring ekstrak jeruk nipis 800ml. Ampuh hilangkan lemak membandel.'],

            // Seragam dan Atribut
            ['cat' => 'Seragam dan Atribut', 'name' => 'Seragam Batik Kantor Pria Lengan Panjang', 'sku' => 'SRG-0031', 'brand' => 'SeragamKu', 'price' => 275000, 'stock' => 40, 'desc' => 'Bahan katun premium, motif batik nasional, tersedia ukuran M-XXL.', 'featured' => true],
            ['cat' => 'Seragam dan Atribut', 'name' => 'Seragam Batik Kantor Wanita Lengan Panjang', 'sku' => 'SRG-0032', 'brand' => 'SeragamKu', 'price' => 275000, 'stock' => 40, 'desc' => 'Bahan katun premium, potongan feminim elegan, ukuran S-XXL.'],
            ['cat' => 'Seragam dan Atribut', 'name' => 'ID Card Holder Lanyard Custom', 'sku' => 'SRG-0033', 'brand' => 'Custom', 'price' => 15000, 'stock' => 200, 'desc' => 'Tali lanyard ID card lebar 2cm, bahan satin, bisa custom logo.'],
            ['cat' => 'Seragam dan Atribut', 'name' => 'Jas Hujan Poncho Universal', 'sku' => 'SRG-0034', 'brand' => 'Generic', 'price' => 45000, 'stock' => 50, 'desc' => 'Jas hujan poncho lipat, ringan, waterproof, cocok untuk perjalanan dinas.'],
            ['cat' => 'Seragam dan Atribut', 'name' => 'Topi Kantor Embroidered Logo', 'sku' => 'SRG-0035', 'brand' => 'Custom', 'price' => 65000, 'stock' => 80, 'desc' => 'Topi baseball cap dengan bordir logo perusahaan, bahan twill.'],

            // Layanan Teknologi
            ['cat' => 'Layanan Teknologi', 'name' => 'Maintenance Jaringan Bulanan', 'sku' => 'LTK-0036', 'brand' => 'KishaIT', 'price' => 3500000, 'stock' => null, 'desc' => 'Pemeliharaan jaringan LAN/WiFi bulanan, monitoring 24/7, laporan berkala.', 'featured' => true],
            ['cat' => 'Layanan Teknologi', 'name' => 'Instalasi Software Office & OS', 'sku' => 'LTK-0037', 'brand' => 'KishaIT', 'price' => 500000, 'stock' => null, 'desc' => 'Instalasi dan konfigurasi sistem operasi serta software produktivitas.'],
            ['cat' => 'Layanan Teknologi', 'name' => 'Backup Data Cloud Service 100GB', 'sku' => 'LTK-0038', 'brand' => 'KishaIT', 'price' => 750000, 'stock' => null, 'desc' => 'Layanan backup data cloud 100GB bulanan, enkripsi AES-256, redundant.'],
            ['cat' => 'Layanan Teknologi', 'name' => 'Perawatan Server Colocation', 'sku' => 'LTK-0039', 'brand' => 'KishaIT', 'price' => 5000000, 'stock' => null, 'desc' => 'Pemeliharaan server fisik di data center, uptime 99.9%, laporan bulanan.'],
            ['cat' => 'Layanan Teknologi', 'name' => 'Pembuatan Website Company Profile', 'sku' => 'LTK-0040', 'brand' => 'KishaIT', 'price' => 15000000, 'stock' => null, 'desc' => 'Pembuatan website company profile profesional, responsive, SEO-friendly.'],

            // Layanan Umum
            ['cat' => 'Layanan Umum', 'name' => 'Service AC Split 1PK', 'sku' => 'LKU-0041', 'brand' => 'KishaService', 'price' => 350000, 'stock' => null, 'desc' => 'Servis AC split 1PK, termasuk cuci indoor, cek freon, dan general check-up.'],
            ['cat' => 'Layanan Umum', 'name' => 'Cleaning Service Gedung 1 Hari', 'sku' => 'LKU-0042', 'brand' => 'KishaService', 'price' => 2500000, 'stock' => null, 'desc' => 'Layanan kebersihan gedung 1 hari, termasuk 3 personel dan peralatan lengkap.'],
            ['cat' => 'Layanan Umum', 'name' => 'Pengiriman Dokumen Ekspedisi', 'sku' => 'LKU-0043', 'brand' => 'KishaService', 'price' => 75000, 'stock' => null, 'desc' => 'Pengiriman dokumen dalam kota, estimasi sampai hari yang sama.'],
            ['cat' => 'Layanan Umum', 'name' => 'Catering Rapat 20 Pax', 'sku' => 'LKU-0044', 'brand' => 'KishaService', 'price' => 4000000, 'stock' => null, 'desc' => 'Paket catering rapat untuk 20 orang, termasuk makanan, snack, dan minuman.'],

            // Kesehatan dan Keselamatan
            ['cat' => 'Kesehatan dan Keselamatan', 'name' => 'APD Coverall Hazmat Type 5/6', 'sku' => 'KES-0045', 'brand' => 'DuPont', 'price' => 125000, 'stock' => 100, 'desc' => 'Coverall pelindung diri dari partikel dan cairan. Sertifikasi CE.'],
            ['cat' => 'Kesehatan dan Keselamatan', 'name' => 'Hand Sanitizer Antiseptik 500ml', 'sku' => 'KES-0046', 'brand' => 'Antis', 'price' => 35000, 'stock' => 200, 'desc' => 'Hand sanitizer antiseptik 500ml, kandungan alkohol 70%, cepat kering.'],
            ['cat' => 'Kesehatan dan Keselamatan', 'name' => 'Masker KN95 50 pcs Box', 'sku' => 'KES-0047', 'brand' => ' generic', 'price' => 185000, 'stock' => 80, 'desc' => 'Masker KN99 5 lapis, kapasitas filtrasi 95%, 50 pcs per box.'],
            ['cat' => 'Kesehatan dan Keselamatan', 'name' => 'Kotak P3K Lengkap Standar', 'sku' => 'KES-0048', 'brand' => 'Onemed', 'price' => 425000, 'stock' => 25, 'desc' => 'Kotak P3K standar berisi perban, plester, antiseptik, dan perlengkapan darurat.'],

            // Promosi dan Dokumentasi
            ['cat' => 'Promosi dan Dokumentasi', 'name' => 'Banner Flexi 2x1m Full Color', 'sku' => 'PRM-0049', 'brand' => 'PrintPro', 'price' => 175000, 'stock' => null, 'desc' => 'Banner flexi 2x1m, cetak full color resolusi tinggi, bahan flexi korea.'],
            ['cat' => 'Promosi dan Dokumentasi', 'name' => 'Brosur A5 Full Color 1000 lembar', 'sku' => 'PRM-0050', 'brand' => 'PrintPro', 'price' => 850000, 'stock' => null, 'desc' => 'Cetak brosur A5 full color 2 sisi, kertas art paper 150gsm, 1000 lembar.'],
        ];

        $productNumber = 0;
        $allCategories = $categories->keyBy('name');

        return collect($productsData)->map(function (array $data) use (&$productNumber, $allCategories) {
            $productNumber++;
            $category = $allCategories[$data['cat']];
            $isService = $data['stock'] === null;
            $filename = 'prd-'.str_pad((string) $productNumber, 3, '0', STR_PAD_LEFT).'.svg';
            $thumbnailPath = 'products/'.$filename;
            $imageUrl = url('/api/media/products/'.$filename);

            $product = Product::query()->create([
                'primary_category_id' => $category->id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'sku' => $data['sku'],
                'type' => $isService ? 'service' : 'product',
                'description' => $data['desc'],
                'brand' => $data['brand'],
                'thumbnail' => $thumbnailPath,
                'price' => $data['price'],
                'track_stock' => ! $isService,
                'stock' => $isService ? null : $data['stock'],
                'status' => 'published',
                'is_featured' => $data['featured'] ?? false,
                'is_active' => true,
            ]);

            $product->categories()->sync([
                $category->id => ['is_primary' => true],
            ]);

            $product->images()->create([
                'url' => $thumbnailPath,
                'alt_text' => $data['name'],
                'is_primary' => true,
                'sort_order' => 0,
            ]);

            return $product;
        });
    }

    private function seedOrders(Collection $products): void
    {
        $availableProducts = $products
            ->filter(fn (Product $p): bool => $p->status === 'published' && $p->is_active)
            ->values();

        $statuses = ['pending', 'confirmed', 'processing', 'completed', 'cancelled'];

        $customers = collect([
            ['email' => 'buyer01@company.local', 'type' => 'individual', 'name' => 'Ahmad Pratama', 'phone' => '081300000001', 'address' => 'Lantai 3, Gedung Utama'],
            ['email' => 'buyer02@company.local', 'type' => 'business', 'name' => 'Dewi Kusuma', 'phone' => '081300000002', 'address' => 'Lantai 5, Gedung B', 'company' => 'PT Mitra Solusiindo', 'nik' => '317400000001', 'npwp' => '01.234.567.1-001.000', 'province' => 'DKI Jakarta', 'city' => 'Jakarta Selatan', 'postal' => '12950'],
            ['email' => 'buyer03@company.local', 'type' => 'individual', 'name' => 'Rizky Aditya', 'phone' => '081300000003', 'address' => 'Lantai 2, Gedung Timur'],
            ['email' => 'buyer04@company.local', 'type' => 'business', 'name' => 'Sari Wulandari', 'phone' => '081300000004', 'address' => 'Lantai 7, Gedung Utama', 'company' => 'PT Nusantara Jaya', 'nik' => '317400000002', 'npwp' => '01.234.567.2-002.000', 'province' => 'Jawa Barat', 'city' => 'Bandung', 'postal' => '40111'],
            ['email' => 'buyer05@company.local', 'type' => 'individual', 'name' => 'Fajar Nugroho', 'phone' => '081300000005', 'address' => 'Lantai 1, Gedung Barat'],
            ['email' => 'buyer06@company.local', 'type' => 'business', 'name' => 'Maya Anggraeni', 'phone' => '081300000006', 'address' => 'Lantai 4, Gedung Selatan', 'company' => 'PT Berkah Digital', 'nik' => '317400000003', 'npwp' => '01.234.567.3-003.000', 'province' => 'Jawa Tengah', 'city' => 'Semarang', 'postal' => '50132'],
            ['email' => 'buyer07@company.local', 'type' => 'individual', 'name' => 'Andi Setiawan', 'phone' => '081300000007', 'address' => 'Lantai 6, Gedung Utama'],
            ['email' => 'buyer08@company.local', 'type' => 'individual', 'name' => 'Lestari Dewi', 'phone' => '081300000008', 'address' => 'Lantai 3, Gedung Timur'],
        ])->map(function (array $c): Customer {
            return Customer::query()->create([
                'email' => $c['email'],
                'customer_type' => $c['type'],
                'name' => $c['name'],
                'phone' => $c['phone'],
                'address' => $c['address'],
                'nik' => $c['nik'] ?? null,
                'npwp' => $c['npwp'] ?? null,
                'province' => $c['province'] ?? null,
                'city' => $c['city'] ?? null,
                'company_name' => $c['company'] ?? null,
                'postal_code' => $c['postal'] ?? null,
                'country' => isset($c['company']) ? 'Indonesia' : null,
            ]);
        });

        foreach (range(1, 20) as $number) {
            $product = $availableProducts[($number - 1) % $availableProducts->count()];
            $quantity = ($number % 3) + 1;
            $subtotal = round((float) $product->price * $quantity, 2);
            $status = $statuses[($number - 1) % count($statuses)];
            $orderDate = now()->subDays($number % 10);
            $customer = $customers[($number - 1) % $customers->count()];

            $order = Order::query()->create([
                'order_number' => 'INV-'.$orderDate->format('Ymd').'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'customer_type' => $customer->customer_type,
                'guest_email' => $customer->email,
                'guest_name' => $customer->name,
                'guest_phone' => $customer->phone,
                'guest_address' => $customer->address,
                'guest_nik' => $customer->nik,
                'guest_npwp' => $customer->npwp,
                'guest_province' => $customer->province,
                'guest_city' => $customer->city,
                'guest_company_name' => $customer->company_name,
                'guest_postal_code' => $customer->postal_code,
                'guest_country' => $customer->country,
                'guest_notes' => $number % 2 === 0 ? 'Mohon diproses pada jam kerja (08:00 - 17:00).' : null,
                'subtotal' => $subtotal,
                'total_amount' => $subtotal,
                'status' => $status,
                'payment_method' => 'internal_billing',
                'payment_status' => $status === 'completed' ? 'paid' : 'unpaid',
                'cancelled_at' => $status === 'cancelled' ? $orderDate->copy()->addHour() : null,
                'cancel_reason' => $status === 'cancelled' ? 'Kebutuhan sudah tidak diperlukan.' : null,
                'admin_notes' => null,
                'created_at' => $orderDate,
                'updated_at' => $orderDate,
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'product_type' => $product->type,
                'price' => $product->price,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ]);

            $order->statusHistories()->create([
                'user_id' => null,
                'from_status' => null,
                'to_status' => 'pending',
                'notes' => 'Order berhasil dibuat.',
                'created_at' => $orderDate,
                'updated_at' => $orderDate,
            ]);

            if ($status !== 'pending') {
                $order->statusHistories()->create([
                    'user_id' => null,
                    'from_status' => 'pending',
                    'to_status' => $status,
                    'notes' => 'Status diperbarui oleh admin.',
                    'created_at' => $orderDate->copy()->addHour(),
                    'updated_at' => $orderDate->copy()->addHour(),
                ]);
            }
        }
    }
}
