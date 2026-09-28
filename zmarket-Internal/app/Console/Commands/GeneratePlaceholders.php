<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GeneratePlaceholders extends Command
{
    protected $signature = 'app:generate-placeholders';

    protected $description = 'Generate SVG placeholder images for seeded products';

    private const ICONS = [
        'monitor' => '<rect x="160" y="70" width="280" height="190" rx="14" fill="white" fill-opacity="0.25"/><rect x="190" y="90" width="220" height="150" rx="8" fill="white" fill-opacity="0.15"/><line x1="300" y1="260" x2="300" y2="310" stroke="white" stroke-opacity="0.3" stroke-width="6"/><line x1="250" y1="310" x2="350" y2="310" stroke="white" stroke-opacity="0.3" stroke-width="6" stroke-linecap="round"/>',
        'laptop' => '<rect x="170" y="80" width="260" height="170" rx="12" fill="white" fill-opacity="0.25"/><rect x="190" y="100" width="220" height="130" rx="6" fill="white" fill-opacity="0.15"/><path d="M130 260 L470 260 L450 290 L150 290 Z" fill="white" fill-opacity="0.2"/>',
        'keyboard' => '<rect x="140" y="120" width="320" height="140" rx="14" fill="white" fill-opacity="0.25"/><rect x="160" y="140" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="200" y="140" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="240" y="140" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="280" y="140" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="320" y="140" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="360" y="140" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="400" y="140" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="180" y="180" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="220" y="180" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="260" y="180" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="300" y="180" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="340" y="180" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="380" y="180" width="30" height="25" rx="4" fill="white" fill-opacity="0.15"/><rect x="210" y="220" width="180" height="20" rx="6" fill="white" fill-opacity="0.15"/>',
        'printer' => '<rect x="160" y="100" width="280" height="160" rx="16" fill="white" fill-opacity="0.25"/><rect x="190" y="130" width="220" height="80" rx="8" fill="white" fill-opacity="0.15"/><rect x="210" y="230" width="180" height="40" rx="6" fill="white" fill-opacity="0.12"/><circle cx="250" cy="165" r="8" fill="white" fill-opacity="0.2"/><circle cx="350" cy="165" r="8" fill="white" fill-opacity="0.2"/>',
        'mouse' => '<ellipse cx="300" cy="185" rx="75" ry="110" fill="white" fill-opacity="0.25"/><line x1="300" y1="100" x2="300" y2="210" stroke="white" stroke-opacity="0.2" stroke-width="3"/><ellipse cx="300" cy="145" rx="15" ry="25" fill="white" fill-opacity="0.15"/>',
        'desk' => '<rect x="120" y="150" width="360" height="14" rx="4" fill="white" fill-opacity="0.3"/><rect x="140" y="164" width="14" height="120" rx="3" fill="white" fill-opacity="0.2"/><rect x="446" y="164" width="14" height="120" rx="3" fill="white" fill-opacity="0.2"/>',
        'chair' => '<rect x="190" y="70" width="220" height="200" rx="24" fill="white" fill-opacity="0.25"/><rect x="230" y="270" width="140" height="16" rx="6" fill="white" fill-opacity="0.2"/><line x1="300" y1="286" x2="300" y2="310" stroke="white" stroke-opacity="0.25" stroke-width="6"/><ellipse cx="300" cy="320" rx="60" ry="10" fill="white" fill-opacity="0.15"/>',
        'cabinet' => '<rect x="180" y="60" width="240" height="260" rx="12" fill="white" fill-opacity="0.25"/><line x1="180" y1="140" x2="420" y2="140" stroke="white" stroke-opacity="0.15" stroke-width="3"/><line x1="180" y1="220" x2="420" y2="220" stroke="white" stroke-opacity="0.15" stroke-width="3"/><circle cx="300" cy="100" r="8" fill="white" fill-opacity="0.2"/><circle cx="300" cy="180" r="8" fill="white" fill-opacity="0.2"/><circle cx="300" cy="260" r="8" fill="white" fill-opacity="0.2"/>',
        'coffee' => '<ellipse cx="300" cy="210" rx="85" ry="60" fill="white" fill-opacity="0.25"/><ellipse cx="300" cy="180" rx="85" ry="40" fill="white" fill-opacity="0.15"/><path d="M385 185 Q430 185 430 220 Q430 255 385 255" fill="none" stroke="white" stroke-opacity="0.2" stroke-width="8" stroke-linecap="round"/><path d="M270 150 Q280 115 290 150" fill="none" stroke="white" stroke-opacity="0.15" stroke-width="5"/><path d="M300 145 Q310 105 320 145" fill="none" stroke="white" stroke-opacity="0.15" stroke-width="5"/>',
        'pen' => '<rect x="270" y="70" width="60" height="220" rx="12" fill="white" fill-opacity="0.25"/><polygon points="270,290 300,340 330,290" fill="white" fill-opacity="0.2"/><rect x="280" y="70" width="40" height="30" rx="6" fill="white" fill-opacity="0.15"/>',
        'paper' => '<rect x="170" y="60" width="260" height="280" rx="10" fill="white" fill-opacity="0.25"/><line x1="210" y1="120" x2="390" y2="120" stroke="white" stroke-opacity="0.15" stroke-width="4"/><line x1="210" y1="155" x2="370" y2="155" stroke="white" stroke-opacity="0.15" stroke-width="4"/><line x1="210" y1="190" x2="385" y2="190" stroke="white" stroke-opacity="0.15" stroke-width="4"/><line x1="210" y1="225" x2="350" y2="225" stroke="white" stroke-opacity="0.15" stroke-width="4"/><line x1="210" y1="260" x2="375" y2="260" stroke="white" stroke-opacity="0.15" stroke-width="4"/>',
        'shirt' => '<path d="M220 80 L260 60 L300 80 L340 60 L380 80 L420 130 L380 140 L380 310 L220 310 L220 140 L180 130 Z" fill="white" fill-opacity="0.25"/><line x1="300" y1="80" x2="300" y2="160" stroke="white" stroke-opacity="0.15" stroke-width="3"/>',
        'hardhat' => '<ellipse cx="300" cy="200" rx="120" ry="80" fill="white" fill-opacity="0.25"/><path d="M180 200 Q180 100 300 90 Q420 100 420 200" fill="white" fill-opacity="0.15"/><rect x="160" y="190" width="280" height="16" rx="6" fill="white" fill-opacity="0.2"/>',
        'wrench' => '<circle cx="230" cy="130" r="55" fill="none" stroke="white" stroke-opacity="0.25" stroke-width="16"/><line x1="265" y1="165" x2="380" y2="300" stroke="white" stroke-opacity="0.2" stroke-width="16" stroke-linecap="round"/>',
        'screen' => '<rect x="150" y="70" width="300" height="200" rx="12" fill="white" fill-opacity="0.25"/><rect x="170" y="90" width="260" height="160" rx="6" fill="white" fill-opacity="0.12"/><path d="M200 300 L400 300 L380 270 L220 270 Z" fill="white" fill-opacity="0.2"/>',
        'camera' => '<rect x="170" y="110" width="260" height="170" rx="16" fill="white" fill-opacity="0.25"/><circle cx="300" cy="195" r="55" fill="white" fill-opacity="0.15"/><circle cx="300" cy="195" r="35" fill="white" fill-opacity="0.1"/><rect x="320" y="95" width="50" height="20" rx="6" fill="white" fill-opacity="0.2"/>',
        'shield' => '<path d="M300 60 L420 110 L420 220 Q420 300 300 340 Q180 300 180 220 L180 110 Z" fill="white" fill-opacity="0.25"/><polyline points="250,200 290,240 360,170" fill="none" stroke="white" stroke-opacity="0.2" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/>',
        'cpu' => '<rect x="180" y="80" width="240" height="240" rx="16" fill="white" fill-opacity="0.25"/><rect x="220" y="120" width="160" height="160" rx="8" fill="white" fill-opacity="0.15"/><line x1="200" y1="110" x2="200" y2="290" stroke="white" stroke-opacity="0.12" stroke-width="4"/><line x1="400" y1="110" x2="400" y2="290" stroke="white" stroke-opacity="0.12" stroke-width="4"/><line x1="110" y1="200" x2="180" y2="200" stroke="white" stroke-opacity="0.12" stroke-width="4"/><line x1="110" y1="160" x2="180" y2="160" stroke="white" stroke-opacity="0.12" stroke-width="4"/><line x1="110" y1="240" x2="180" y2="240" stroke="white" stroke-opacity="0.12" stroke-width="4"/><line x1="420" y1="200" x2="490" y2="200" stroke="white" stroke-opacity="0.12" stroke-width="4"/><line x1="420" y1="160" x2="490" y2="160" stroke="white" stroke-opacity="0.12" stroke-width="4"/><line x1="420" y1="240" x2="490" y2="240" stroke="white" stroke-opacity="0.12" stroke-width="4"/>',
        'wifi' => '<circle cx="300" cy="260" r="14" fill="white" fill-opacity="0.3"/><path d="M220 210 Q300 130 380 210" fill="none" stroke="white" stroke-opacity="0.2" stroke-width="8" stroke-linecap="round"/><path d="M180 170 Q300 70 420 170" fill="none" stroke="white" stroke-opacity="0.15" stroke-width="8" stroke-linecap="round"/>',
        'cloud' => '<path d="M160 250 Q160 200 210 200 Q220 150 280 150 Q340 150 350 200 Q400 200 400 250 Q400 300 280 300 Q160 300 160 250 Z" fill="white" fill-opacity="0.25"/>',
        'megaphone' => '<path d="M180 140 L180 260 L230 260 L380 310 L380 90 L230 140 Z" fill="white" fill-opacity="0.25"/><ellipse cx="160" cy="200" rx="20" ry="50" fill="white" fill-opacity="0.2"/>',
    ];

    private const COLORS = [
        'elektronik-kantor' => '#1a56db',
        'perlengkapan-kerja' => '#d97706',
        'alat-tulis-kantor' => '#059669',
        'furniture-kantor' => '#7c3aed',
        'kebutuhan-pantry' => '#dc2626',
        'seragam-dan-atribut' => '#0891b2',
        'layanan-teknologi' => '#4f46e5',
        'layanan-umum' => '#ca8a04',
        'kesehatan-dan-keselamatan' => '#e11d48',
        'promosi-dan-dokumentasi' => '#9333ea',
    ];

    private const CATEGORY_ICONS = [
        'elektronik-kantor' => 'monitor',
        'perlengkapan-kerja' => 'desk',
        'alat-tulis-kantor' => 'pen',
        'furniture-kantor' => 'chair',
        'kebutuhan-pantry' => 'coffee',
        'seragam-dan-atribut' => 'shirt',
        'layanan-teknologi' => 'cpu',
        'layanan-umum' => 'wrench',
        'kesehatan-dan-keselamatan' => 'shield',
        'promosi-dan-dokumentasi' => 'megaphone',
    ];

    public function handle(): int
    {
        $dir = storage_path('app/public/products');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $count = 0;
        $products = $this->getProducts();

        foreach ($products as $product) {
            $filename = $product['file'];
            $svg = $this->generateSvg($product['name'], $product['icon'], $product['color']);
            $path = $dir.'/'.$filename;

            if (! file_exists($path)) {
                file_put_contents($path, $svg);
                $count++;
            }
        }

        $this->info("Generated {$count} placeholder images in storage/app/public/products/");

        return Command::SUCCESS;
    }

    private function getProducts(): array
    {
        $products = [];
        $number = 0;

        $categories = [
            'elektronik-kantor' => [
                ['name' => 'Laptop ASUS VivoBook 14', 'icon' => 'laptop'],
                ['name' => 'Monitor LG 24 inch IPS', 'icon' => 'monitor'],
                ['name' => 'Keyboard Mechanical Logitech', 'icon' => 'keyboard'],
                ['name' => 'Mouse Wireless Logitech M331', 'icon' => 'mouse'],
                ['name' => 'Printer HP LaserJet Pro', 'icon' => 'printer'],
                ['name' => 'Headset Jabra Evolve2 40', 'icon' => 'screen'],
                ['name' => 'Webcam Logitech C920 HD', 'icon' => 'camera'],
                ['name' => 'UPS APC 600VA', 'icon' => 'cpu'],
            ],
            'perlengkapan-kerja' => [
                ['name' => 'Pulpen Pilot G2 0.7mm', 'icon' => 'pen'],
                ['name' => 'Stabilo Boss Highlighter Set', 'icon' => 'pen'],
                ['name' => 'Map Folio Golden', 'icon' => 'paper'],
                ['name' => 'Binder Clips Set 24pcs', 'icon' => 'paper'],
                ['name' => 'Stapler Kenko Heavy Duty', 'icon' => 'wrench'],
                ['name' => 'Paper Shredder AO 600S', 'icon' => 'printer'],
            ],
            'alat-tulis-kantor' => [
                ['name' => 'Buku Tulis Sinar Dunia 38 lbr', 'icon' => 'paper'],
                ['name' => 'Spidol Snowman Board Marker', 'icon' => 'pen'],
                ['name' => 'Tinta Epson Black 003', 'icon' => 'printer'],
                ['name' => 'Kertas A4 Bibo 70gsm 500 lbr', 'icon' => 'paper'],
                ['name' => 'Pensil 2B Faber Castell', 'icon' => 'pen'],
                ['name' => 'Penggaris Stainless 30cm', 'icon' => 'wrench'],
            ],
            'furniture-kantor' => [
                ['name' => 'Meja Kerja Executive 120cm', 'icon' => 'desk'],
                ['name' => 'Kursi Ergonomis Ergotec', 'icon' => 'chair'],
                ['name' => 'Lemari Arsip 4 Laci', 'icon' => 'cabinet'],
                ['name' => 'Whiteboard 120x90cm', 'icon' => 'screen'],
                ['name' => 'Partisi Kantor Portable', 'icon' => 'desk'],
                ['name' => 'Rak Sepatu Kantor 5 Tier', 'icon' => 'cabinet'],
            ],
            'kebutuhan-pantry' => [
                ['name' => 'Kopi Kapal Api Special 48s', 'icon' => 'coffee'],
                ['name' => 'Gelas Keramik 350ml Set 6', 'icon' => 'coffee'],
                ['name' => 'Air Galon Le Minerale 19L', 'icon' => 'coffee'],
                ['name' => 'Tisu Wajah Paseo 250 sheets', 'icon' => 'paper'],
                ['name' => 'Sabun Cuci Piring Sunlight 800ml', 'icon' => 'coffee'],
            ],
            'seragam-dan-atribut' => [
                ['name' => 'Seragam Batik Kantor Pria', 'icon' => 'shirt'],
                ['name' => 'Seragam Batik Kantor Wanita', 'icon' => 'shirt'],
                ['name' => 'ID Card Holder Lanyard', 'icon' => 'shirt'],
                ['name' => 'Jas Hujan Poncho Universal', 'icon' => 'shirt'],
                ['name' => 'Topi Kantor Embroidered', 'icon' => 'hardhat'],
            ],
            'layanan-teknologi' => [
                ['name' => 'Maintenance Jaringan Bulanan', 'icon' => 'wifi'],
                ['name' => 'Instalasi Software Office', 'icon' => 'cpu'],
                ['name' => 'Backup Data Cloud Service', 'icon' => 'cloud'],
                ['name' => 'Perawatan Server Colocation', 'icon' => 'server'],
                ['name' => 'Pembuatan Website Company Profile', 'icon' => 'screen'],
            ],
            'layanan-umum' => [
                ['name' => 'Service AC Split 1PK', 'icon' => 'wrench'],
                ['name' => 'Cleaning Service Gedung', 'icon' => 'desk'],
                ['name' => 'Pengiriman Dokumen Ekspedisi', 'icon' => 'paper'],
                ['name' => 'Catering Rapat 20 Pax', 'icon' => 'coffee'],
            ],
            'kesehatan-dan-keselamatan' => [
                ['name' => 'APD Coverall Hazmat', 'icon' => 'shield'],
                ['name' => 'Hand Sanitizer 500ml', 'icon' => 'shield'],
                ['name' => 'Masker KN95 Box 50pcs', 'icon' => 'shield'],
                ['name' => 'Kotak P3K Lengkap', 'icon' => 'shield'],
            ],
            'promosi-dan-dokumentasi' => [
                ['name' => 'Banner Flexi 2x1m', 'icon' => 'megaphone'],
                ['name' => 'Brosur A5 Full Color 1000pcs', 'icon' => 'paper'],
                ['name' => 'Video Profil Perusahaan HD', 'icon' => 'camera'],
                ['name' => 'Design Logo Perusahaan', 'icon' => 'megaphone'],
            ],
        ];

        foreach ($categories as $categorySlug => $items) {
            foreach ($items as $item) {
                $number++;
                $products[] = [
                    'file' => 'prd-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT).'.svg',
                    'name' => $item['name'],
                    'icon' => self::ICONS[$item['icon']] ?? self::ICONS['monitor'],
                    'color' => self::COLORS[$categorySlug] ?? '#64748b',
                ];
            }
        }

        return $products;
    }

    private function generateSvg(string $name, string $iconPath, string $color): string
    {
        $escapedName = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $words = explode(' ', $escapedName);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $testLine = $currentLine ? $currentLine.' '.$word : $word;
            if (strlen($testLine) > 28) {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            } else {
                $currentLine = $testLine;
            }
        }
        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        $lines = array_slice($lines, 0, 3);
        $textCount = count($lines);
        $startY = $textCount === 1 ? 390 : ($textCount === 2 ? 375 : 362);

        $textElements = '';
        foreach ($lines as $i => $line) {
            $y = $startY + ($i * 18);
            $textElements .= "<text x=\"300\" y=\"{$y}\" font-family=\"Inter, -apple-system, sans-serif\" font-size=\"13\" font-weight=\"700\" fill=\"white\" text-anchor=\"middle\" fill-opacity=\"0.85\">{$line}</text>\n";
        }

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="600" height="420" viewBox="0 0 600 420">
            <defs>
                <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="{$color}"/>
                    <stop offset="100%" stop-color="{$color}" stop-opacity="0.75"/>
                </linearGradient>
            </defs>
            <rect width="600" height="420" rx="16" fill="url(#bg)"/>
            {$iconPath}
            {$textElements}
        </svg>
        SVG;
    }
}
