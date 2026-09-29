<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Unduh foto produk nyata dari Openverse (bukan placeholder gambar bawaan).
 *
 * Sumber: Openverse API, yang mengagregasi Flickr, Wikimedia, dan repositori
 * Creative Commons lainnya. Hanya lisensi yang mengizinkan modifikasi sekaligus
 * penggunaan komersial yang dipakai: `cc0`, `pdm`, `by`, `by-sa`.
 *
 * Lisensi `by-nd` dikecualikan karena memotong gambar (crop) merupakan karya
 * turunan, dan `by-nc` karena dataset ini dipakai untuk keperluan komersial.
 * Atribusi lengkap disimpan di `manifest.json`.
 *
 * Semua gambar disimpan lokal di `public/images/products/<sku>.jpg` supaya
 * `migrate:fresh --seed` tidak bergantung pada koneksi jaringan.
 */
class GenerateProductImages extends Command
{
    /**
     * Jalankan `php artisan products:images` untuk mengunduh foto produk nyata
     * ke `public/images/products/` dan menautkannya ke setiap produk.
     *
     * Produk yang gambarnya sudah berupa URL remote tidak disentuh, sehingga
     * gambar unggahan manual tidak tertimpa.
     */
    protected $signature = 'products:images
                            {--force : Unduh ulang meski berkas sudah ada}
                            {--prune : Hapus berkas gambar yang tidak lagi dipakai produk}
                            {--only= : Batasi hanya SKU tertentu (pisahkan koma), untuk uji coba}';

    protected $description = 'Unduh foto produk nyata berlisensi bebas dari Openverse dan simpan lokal.';

    /**
     * Endpoint pencarian Openverse.
     */
    private const API = 'https://api.openverse.org/v1/images/';

    /**
     * Ukuran tepi gambar hasil (piksel, bujur sangkar).
     */
    private const SIZE = 800;

    /**
     * Kualitas JPEG hasil akhir.
     */
    private const QUALITY = 82;

    /**
     * Lisensi yang diizinkan: bebasderivat + komersial.
     *
     * @var array<string, int>
     */
    private const LICENSE_SCORE = [
        'cc0' => 40,
        'pdm' => 36,
        'by' => 30,
        'by-sa' => 24,
    ];

    /**
     * Pemetaan SKU ke kueri Openverse dan token yang harus muncul di judul/tag.
     *
     * Kueri diurutkan dari paling spesifik ke paling umum; kueri berikutnya
     * hanya dicoba bila kueri sebelumnya tidak menghasilkan kandidat yang
     * melewati penyaringan lisensi dan kecocokan token.
     *
     * @var array<string, array{queries: list<string>, match: list<string>}>
     */
    private const SEARCH_MAP = [
        // Makanan
        'MKN-001' => [
            ['q' => 'nasi goreng', 'match' => ['nasi goreng']],
        ],
        'MKN-002' => [
            ['q' => 'nasi goreng kampung', 'match' => ['nasi goreng']],
            ['q' => 'nasi goreng', 'match' => ['nasi goreng']],
        ],
        'MKN-003' => [
            ['q' => 'nasi goreng seafood', 'match' => ['seafood', 'udang', 'nasi goreng']],
            ['q' => 'nasi goreng', 'match' => ['seafood', 'udang', 'nasi goreng']],
        ],
        'MKN-004' => [
            ['q' => 'nasi uduk', 'match' => ['uduk']],
        ],
        'MKN-005' => [
            ['q' => 'nasi uduk ayam goreng', 'match' => ['ayam goreng']],
            ['q' => 'nasi uduk', 'match' => ['uduk']],
        ],
        'MKN-006' => [
            ['q' => 'nasi bakar ayam', 'match' => ['bakar', 'uduk']],
            ['q' => 'nasi uduk', 'match' => ['bakar', 'uduk']],
        ],
        'MKN-007' => [
            ['q' => 'nasi lemak', 'match' => ['lemak']],
        ],
        'MKN-008' => [
            ['q' => 'nasi campur', 'match' => ['campur']],
        ],
        'MKN-009' => [
            ['q' => 'nasi kapau', 'match' => ['kapau']],
            ['q' => 'kapau', 'match' => ['kapau']],
        ],
        'MKN-010' => [
            ['q' => 'mie goreng jawa', 'match' => ['mie goreng', 'mie']],
            ['q' => 'mie goreng', 'match' => ['mie goreng', 'mie']],
        ],
        'MKN-011' => [
            ['q' => 'mie goreng', 'match' => ['mie goreng', 'mie']],
        ],
        'MKN-012' => [
            ['q' => 'mie kuah bakso', 'match' => ['bakso', 'mie']],
            ['q' => 'bakso', 'match' => ['bakso', 'mie']],
        ],
        'MKN-013' => [
            ['q' => 'mie ayam bakso', 'match' => ['bakso', 'mie']],
            ['q' => 'bakso', 'match' => ['bakso', 'mie']],
        ],
        'MKN-014' => [
            ['q' => 'ayam geprek', 'match' => ['geprek']],
        ],
        'MKN-015' => [
            ['q' => 'ayam bakar madu', 'match' => ['bakar']],
            ['q' => 'ayam bakar', 'match' => ['bakar']],
        ],
        'MKN-016' => [
            ['q' => 'ayam bakar taliwang', 'match' => ['taliwang', 'bakar']],
            ['q' => 'ayam bakar', 'match' => ['taliwang', 'bakar']],
        ],
        'MKN-017' => [
            ['q' => 'ayam penyet', 'match' => ['penyet', 'geprek']],
            ['q' => 'ayam geprek', 'match' => ['penyet', 'geprek']],
        ],
        'MKN-018' => [
            ['q' => 'sate ayam', 'match' => ['sate ayam', 'chicken satay']],
            ['q' => 'chicken satay', 'match' => ['chicken satay', 'sate ayam']],
            ['q' => 'sate', 'match' => ['sate']],
        ],
        'MKN-019' => [
            ['q' => 'rendang sapi', 'match' => ['rendang']],
            ['q' => 'rendang', 'match' => ['rendang']],
        ],
        'MKN-020' => [
            ['q' => 'tongseng sapi', 'match' => ['tongseng', 'rendang']],
            ['q' => 'tongseng', 'match' => ['tongseng', 'rendang']],
            ['q' => 'rendang', 'match' => ['tongseng', 'rendang']],
        ],
        'MKN-021' => [
            ['q' => 'soto ayam lamongan', 'match' => ['soto']],
            ['q' => 'soto ayam', 'match' => ['soto']],
        ],
        'MKN-022' => [
            ['q' => 'soto ayam kampung', 'match' => ['soto']],
            ['q' => 'soto ayam', 'match' => ['soto']],
        ],
        'MKN-023' => [
            ['q' => 'bakso sapi', 'match' => ['bakso sapi', 'bakso']],
            ['q' => 'bakso daging', 'match' => ['bakso']],
            ['q' => 'bakso', 'match' => ['bakso']],
        ],
        'MKN-024' => [
            ['q' => 'rawon setan', 'match' => ['rawon']],
            ['q' => 'rawon', 'match' => ['rawon']],
        ],
        'MKN-025' => [
            ['q' => 'sup iga', 'match' => ['sup iga', 'soto iga', 'iga']],
            ['q' => 'soto iga', 'match' => ['soto iga', 'iga']],
            ['q' => 'iga bakar', 'match' => ['iga']],
        ],
        'MKN-026' => [
            ['q' => 'gurame goreng', 'match' => ['gurame', 'ikan']],
            ['q' => 'ikan gurame', 'match' => ['gurame', 'ikan']],
            ['q' => 'ikan goreng', 'match' => ['gurame', 'ikan']],
        ],
        'MKN-027' => [
            ['q' => 'cumi bakar', 'match' => ['cumi', 'squid']],
            ['q' => 'cumi goreng', 'match' => ['cumi', 'squid']],
            ['q' => 'squid', 'match' => ['cumi', 'squid']],
        ],
        'MKN-028' => [
            ['q' => 'iga bakar', 'match' => ['iga']],
        ],
        'MKN-029' => [
            ['q' => 'iga bakar sambal', 'match' => ['iga', 'sambal']],
            ['q' => 'iga bakar', 'match' => ['iga', 'sambal']],
        ],
        'MKN-030' => [
            ['q' => 'sayur lodeh', 'match' => ['lodeh']],
            ['q' => 'lodeh', 'match' => ['lodeh']],
        ],

        // Minuman
        'MNR-001' => [
            ['q' => 'es teh manis', 'match' => ['teh', 'tea']],
            ['q' => 'es teh', 'match' => ['teh', 'tea']],
            ['q' => 'iced tea', 'match' => ['teh', 'tea']],
        ],
        'MNR-002' => [
            ['q' => 'es jeruk', 'match' => ['jeruk', 'orange']],
            ['q' => 'orange juice', 'match' => ['jeruk', 'orange']],
        ],
        'MNR-003' => [
            ['q' => 'kopi susu gula aren', 'match' => ['kopi', 'coffee']],
            ['q' => 'kopi susu', 'match' => ['kopi', 'coffee']],
            ['q' => 'kopi', 'match' => ['kopi', 'coffee']],
        ],
        'MNR-004' => [
            ['q' => 'es kopi susu', 'match' => ['kopi', 'coffee']],
            ['q' => 'kopi susu', 'match' => ['kopi', 'coffee']],
            ['q' => 'iced coffee', 'match' => ['kopi', 'coffee']],
        ],
        'MNR-005' => [
            ['q' => 'kopi hitam', 'match' => ['kopi hitam', 'black coffee']],
            ['q' => 'black coffee', 'match' => ['black coffee', 'kopi hitam']],
            ['q' => 'kopi arabica', 'match' => ['kopi']],
        ],
        'MNR-006' => [
            ['q' => 'air mineral', 'match' => ['air mineral', 'mineral water', 'water bottle']],
            ['q' => 'mineral water', 'match' => ['air mineral', 'mineral water', 'water bottle']],
            ['q' => 'bottled water', 'match' => ['air mineral', 'mineral water', 'water bottle']],
        ],
        'MNR-007' => [
            ['q' => 'jus alpukat', 'match' => ['alpukat', 'avocado']],
            ['q' => 'avocado juice', 'match' => ['alpukat', 'avocado']],
        ],
        'MNR-008' => [
            ['q' => 'jus mangga', 'match' => ['jus mangga', 'mango juice']],
            ['q' => 'mango juice', 'match' => ['mango juice', 'jus mangga']],
        ],
        'MNR-009' => [
            ['q' => 'jus jambu', 'match' => ['jambu', 'guava']],
            ['q' => 'guava juice', 'match' => ['jambu', 'guava']],
        ],
        'MNR-010' => [
            ['q' => 'es cendol', 'match' => ['cendol']],
            ['q' => 'cendol', 'match' => ['cendol']],
        ],
        'MNR-011' => [
            ['q' => 'es campur', 'match' => ['es campur', 'campur']],
            ['q' => 'iced dessert', 'match' => ['es campur', 'campur']],
        ],
        'MNR-012' => [
            ['q' => 'teh tarik', 'match' => ['tarik']],
        ],
        'MNR-013' => [
            ['q' => 'chocolate milkshake', 'match' => ['milkshake']],
            ['q' => 'milkshake coklat', 'match' => ['milkshake']],
            ['q' => 'milkshake', 'match' => ['milkshake']],
        ],
        'MNR-014' => [
            ['q' => 'lemon tea', 'match' => ['lemon']],
            ['q' => 'iced lemon tea', 'match' => ['lemon']],
        ],

        // Snack
        'SNK-001' => [
            ['q' => 'pisang goreng', 'match' => ['pisang']],
            ['q' => 'pisang', 'match' => ['pisang']],
        ],
        'SNK-002' => [
            ['q' => 'kentang goreng', 'match' => ['kentang', 'fries']],
            ['q' => 'french fries', 'match' => ['kentang', 'fries']],
        ],
        'SNK-003' => [
            ['q' => 'bakso tahu', 'match' => ['tahu']],
            ['q' => 'tahu goreng', 'match' => ['tahu']],
        ],
        'SNK-004' => [
            ['q' => 'roti bakar', 'match' => ['roti', 'bread']],
            ['q' => 'roti cokelat', 'match' => ['roti', 'bread']],
        ],
        'SNK-005' => [
            ['q' => 'pastel goreng', 'match' => ['pastel']],
            ['q' => 'pastel', 'match' => ['pastel']],
        ],
        'SNK-006' => [
            ['q' => 'risol mayo', 'match' => ['risol']],
            ['q' => 'risol', 'match' => ['risol']],
        ],
        'SNK-007' => [
            ['q' => 'tahu goreng crispy', 'match' => ['tahu', 'tofu']],
            ['q' => 'crispy tofu', 'match' => ['tahu', 'tofu']],
            ['q' => 'tahu goreng', 'match' => ['tahu', 'tofu']],
        ],
        'SNK-008' => [
            ['q' => 'tempe mendoan', 'match' => ['mendoan', 'tempe']],
            ['q' => 'tempe goreng', 'match' => ['mendoan', 'tempe']],
        ],
        'SNK-009' => [
            ['q' => 'martabak telur', 'match' => ['martabak']],
            ['q' => 'martabak', 'match' => ['martabak']],
        ],
        'SNK-010' => [
            ['q' => 'cireng', 'match' => ['cireng', 'tahu']],
            ['q' => 'tahu goreng', 'match' => ['cireng', 'tahu']],
        ],
        'SNK-011' => [
            ['q' => 'perkedel kentang', 'match' => ['perkedel']],
            ['q' => 'perkedel', 'match' => ['perkedel']],
        ],
        'SNK-012' => [
            ['q' => 'tahu isi telur', 'match' => ['tahu']],
            ['q' => 'tahu goreng', 'match' => ['tahu']],
        ],

        // Dessert
        'DST-001' => [
            ['q' => 'ice cream sundae', 'match' => ['sundae', 'ice cream']],
            ['q' => 'sundae', 'match' => ['sundae', 'ice cream']],
        ],
        'DST-002' => [
            ['q' => 'chocolate pudding', 'match' => ['pudding', 'puding']],
            ['q' => 'puding cokelat', 'match' => ['pudding', 'puding']],
        ],
        'DST-003' => [
            ['q' => 'puding lumut', 'match' => ['lumut']],
            ['q' => 'pudding lumut', 'match' => ['lumut']],
            ['q' => 'puding lumut india', 'match' => ['puding']],
            ['q' => 'puding', 'match' => ['puding', 'pudding']],
        ],
        'DST-004' => [
            ['q' => 'klepon', 'match' => ['klepon']],
        ],
        'DST-005' => [
            ['q' => 'getuk lindri', 'match' => ['getuk']],
            ['q' => 'getuk', 'match' => ['getuk']],
        ],
        'DST-006' => [
            ['q' => 'lapis legit', 'match' => ['lapis', 'spekuk', 'spekkoek']],
            ['q' => 'spekkoek', 'match' => ['lapis', 'spekuk', 'spekkoek']],
        ],
    ];

    /**
     * Kalimat pada judul yang menandakan gambar bukan foto makanan (iklan, teks
     * resep, poster, dan sejenisnya). Kandidat seperti ini diberi penalti.
     *
     * @var list<string>
     */
    private const JUNK_TERMS = [
        'resep', 'poster', 'banner', 'spanduk', 'brosur', 'iklan', 'promo',
        'menu ', 'daftar', 'harga', 'diskon', 'logo', 'katalog',
        'pameran', 'exhibit', 'sign', 'plakat', 'papan', 'label', 'kemasan',
        'mesin', 'penggiling', 'machine', 'toko', 'box', 'paket', 'package',
        'kompor', 'oven', 'panetone', 'varietas', 'pohon', 'kebun', 'tanaman',
        // Subjek non-foto: hasil seni, peta, dan benda yang hanya berbagi kata.
        'abstract', 'pattern', 'tileable', 'silhouette', 'illustration',
        'painting', 'drawing', 'sketch', 'stamp', 'coin', 'banknote',
        'quinceanos', 'wallpaper', 'gradient', 'texture',
    ];

    public function handle(): int
    {
        $directory = public_path('images/products');
        File::ensureDirectoryExists($directory);

        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $force = (bool) $this->option('force');

        $manifestPath = $directory.'/manifest.json';
        $manifest = File::exists($manifestPath)
            ? (json_decode((string) File::get($manifestPath), true) ?: [])
            : [];

        $products = Product::query()
            ->when($only !== [], fn ($query) => $query->whereIn('sku', $only))
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            $this->warn('Produk tidak ditemukan. Jalankan migrate:fresh --seed lebih dulu.');

            return self::FAILURE;
        }

        foreach ($manifest as $entry) {
            if (isset($entry['source'])) {
                // URL aset disimpan di manifest; sumber asli dicatat terpisah.
                $usedUrls[] = (string) ($entry['asset_url'] ?? $entry['source']);
            }
        }

        $this->line("<info>Mencari foto di Openverse untuk {$products->count()} produk...</>");
        $this->line('  <comment>Licenses: cc0, pdm, by, by-sa (dimodifikasi & komersial)</comment>');

        $downloaded = 0;
        $linked = 0;
        $skipped = 0;
        $failed = 0;
        $usedFiles = [];
        $usedUrls = [];

        foreach ($products as $product) {
            $existing = trim((string) $product->image);

            if ($existing !== '' && preg_match('#^https?://#i', $existing) === 1) {
                $this->line("  <comment>skip</comment> {$this->pad($product->sku)} gambar sudah remote");
                $skipped++;

                continue;
            }

            $sku = $this->slugFor($product);
            $fileName = "{$sku}.jpg";
            $relativePath = "/images/products/{$fileName}";
            $absolute = $directory.'/'.$fileName;
            $usedFiles[$fileName] = true;

            if (! $force && File::exists($absolute)) {
                // Berkas sudah ada: pastikan tautan produk tetap sinkron.
                if ($existing !== $relativePath) {
                    $product->forceFill(['image' => $relativePath])->saveQuietly();
                    $linked++;
                }
                $this->line("  <comment>ada</comment>  {$this->pad($product->sku)} {$product->name}");
                $skipped++;

                continue;
            }

            $spec = self::SEARCH_MAP[$product->sku] ?? null;

            if ($spec === null) {
                $this->line("  <fg=red>lewati</>  {$this->pad($product->sku)} tidak ada kueri untuk SKU ini");
                $this->forgetImage($product);
                $failed++;

                continue;
            }

            try {
                $result = $this->resolve($spec, $usedUrls);
            } catch (RuntimeException $e) {
                $this->line("  <fg=red>gagal</>   {$this->pad($product->sku)} {$e->getMessage()}");
                $this->forgetImage($product);
                $failed++;

                continue;
            }

            if ($result === null) {
                $this->line("  <fg=red>lewati</>  {$this->pad($product->sku)} tidak ada kandidat cocok");
                $this->forgetImage($product);
                $failed++;

                continue;
            }

            try {
                File::put($absolute, $this->render($result));
            } catch (RuntimeException $e) {
                $this->line("  <fg=red>gagal</>   {$this->pad($product->sku)} unduhan gagal: {$e->getMessage()}");
                File::delete($absolute);
                $this->forgetImage($product);
                $failed++;

                continue;
            }

            $manifest[$fileName] = [
                'sku' => $product->sku,
                'product' => $product->name,
                'title' => $result['title'],
                'creator' => $result['creator'],
                'creator_url' => $result['creator_url'],
                'license' => $result['license'].' '.$result['license_version'],
                'license_url' => $result['license_url'],
                'source' => $result['foreign_landing_url'],
                'asset_url' => $result['url'],
            ];

            if ($existing !== $relativePath) {
                $product->forceFill(['image' => $relativePath])->saveQuietly();
                $linked++;
            }

            // Jeda singkat agar CDN sumber (Flickr/Wikimedia) tidak rate-limit.
            usleep(350_000);

            $usedUrls[] = $result['url'];

            $downloaded++;
            $this->line(sprintf(
                '  <info>ok</info>    %s %s <comment>(%s · %s)</comment>',
                $this->pad($product->sku),
                Str::limit((string) $product->name, 28),
                $result['license'].' '.$result['license_version'],
                Str::limit((string) $result['title'], 40),
            ));
        }

        ksort($manifest);
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

        if ($this->option('prune')) {
            $removed = 0;

            foreach (File::files($directory) as $file) {
                if ($file->getExtension() === 'jpg' && ! isset($usedFiles[$file->getFilename()])) {
                    File::delete($file->getPathname());
                    unset($manifest[$file->getFilename()]);
                    $removed++;
                }
            }

            if ($removed > 0) {
                $this->line("  <info>{$removed}</info> berkas gambar tak terpakai dihapus.");
            }
        }

        $this->newLine();
        $this->info("Selesai: {$downloaded} diunduh, {$linked} ditautkan, {$skipped} dilewati, {$failed} gagal.");
        $this->line("Lokasi: {$directory}");
        $this->line("Atribusi: {$manifestPath}");

        return $failed > 0 && $downloaded === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Cari kandidat terbaik untuk satu produk dari daftar kueri.
     *
     * Setiap entri kueri membawa token pencocokannya sendiri, sehingga fallback
     * bisa dibuat lebih ketat (mis. "jus mangga" boleh jatuh ke "mangga Wani",
     * sedangkan kueri kedua mensyaratkan kata "juice").
     *
     * @param  list<array{q: string, match: list<string>}>  $spec
     * @param  list<string>  $used
     * @return array<string, mixed>|null
     */
    private function resolve(array $spec, array $used = []): ?array
    {
        foreach ($spec as $attempt) {
            $best = $this->search($attempt['q'], $attempt['match'], $used);

            if ($best !== null) {
                return $best;
            }
        }

        return null;
    }

    /**
     * Satu permintaan pencarian Openverse, disaring dan diberi skor.
     *
     * @param  list<string>  $match
     * @param  list<string>  $used  URL sumber yang sudah dipakai produk lain
     * @return array<string, mixed>|null
     */
    private function search(string $query, array $match, array $used = []): ?array
    {
        $response = Http::withHeaders($this->userAgent())
            ->timeout(30)
            ->retry(2, 400, throw: false)
            ->get(self::API, [
                'q' => $query,
                'page_size' => 20,
                'license_type' => 'all',
            ]);

        if (! $response->successful()) {
            return null;
        }

        $results = $response->json('results') ?? [];
        $best = null;
        $bestScore = -1;

        foreach ($results as $item) {
            $license = strtolower((string) ($item['license'] ?? ''));

            if (! isset(self::LICENSE_SCORE[$license])) {
                continue;
            }

            // Satu foto tidak boleh dipakai dua produk, Menu jadi repetitif.
            if (in_array((string) ($item['url'] ?? ''), $used, true)) {
                continue;
            }

            $title = (string) ($item['title'] ?? '');
            $haystack = mb_strtolower(trim($title.' '.$this->tagNames($item)));
            $hits = 0;

            foreach ($match as $token) {
                if (str_contains($haystack, mb_strtolower($token))) {
                    $hits++;
                }
            }

            // Judul harus menyebut hidangan yang dicari, kalau tidak foto bisa
            // sama sekali tidak relevan (mis. "Bakso Super - Es Teler").
            if ($hits === 0) {
                continue;
            }

            // Buang kandidat yang jelas bukan foto makanan (iklan, poster, teks resep).
            $penalty = $this->junkPenalty(mb_strtolower($title));

            if ($penalty >= 30) {
                continue;
            }

            $width = (int) ($item['width'] ?? 0);
            $height = (int) ($item['height'] ?? 0);

            if ($width < 480 || $height < 480) {
                continue;
            }

            $score = self::LICENSE_SCORE[$license]
                + ($hits * 6)
                + $this->resolutionScore($width, $height)
                + $this->squareScore($width, $height)
                - $penalty;

            if ($score <= $bestScore) {
                continue;
            }

            $bestScore = $score;
            $best = [
                'title' => (string) ($item['title'] ?? $query),
                'creator' => (string) ($item['creator'] ?? 'Tidak diketahui'),
                'creator_url' => (string) ($item['creator_url'] ?? ''),
                'license' => $license,
                'license_version' => (string) ($item['license_version'] ?? ''),
                'license_url' => (string) ($item['license_url'] ?? ''),
                'foreign_landing_url' => (string) ($item['foreign_landing_url'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'thumbnail' => (string) ($item['thumbnail'] ?? ''),
            ];
        }

        return $best;
    }

    /**
     * Penalti untuk judul yang menandakan bukan foto makanan.
     */
    private function junkPenalty(string $title): int
    {
        $penalty = 0;

        foreach (self::JUNK_TERMS as $term) {
            if (str_contains($title, $term)) {
                $penalty += 15;
            }
        }

        return min($penalty, 30);
    }

    /**
     * Skor resolusi: makin besar makin baik, dengan batas atas agar dimensi
     * raksasa tidak mendominasi skor kecocokan judul.
     */
    private function resolutionScore(int $width, int $height): int
    {
        $shortest = min($width, $height);

        return (int) min(20, round($shortest / 120));
    }

    /**
     * Skor rasio aspek: potong bujur sangkar, semakin dekat 1:1 semakin bagus
     * karena pemotongan membuang lebih sedikit piksel.
     */
    private function squareScore(int $width, int $height): float
    {
        $ratio = $width / max($height, 1);
        $distance = abs(1 - min($ratio, 1 / max($ratio, 0.0001)));

        return max(0.0, 8.0 - ($distance * 8.0));
    }

    /**
     * Gabungkan nama tag dari hasil Openverse.
     *
     * @param  array<string, mixed>  $item
     */
    private function tagNames(array $item): string
    {
        $names = [];

        foreach ((array) ($item['tags'] ?? []) as $tag) {
            if (is_array($tag) && isset($tag['name'])) {
                $names[] = (string) $tag['name'];
            }
        }

        return implode(' ', $names);
    }

    /**
     * Unduh gambar, potong bujur sangkar di tengah, resize, dan simpan sebagai JPEG.
     *
     * @param  array<string, mixed>  $result
     */
    private function render(array $result): string
    {
        $sources = array_filter([$result['url'] ?? '', $result['thumbnail'] ?? '']);
        $errors = [];

        foreach ($sources as $source) {
            try {
                return $this->encode($this->download((string) $source));
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        throw new RuntimeException(implode('; ', $errors) ?: 'tidak ada sumber gambar');
    }

    /**
     * Unduh bytes gambar, mengikuti redirect, dan kembalikan error sebagai nilai
     * balik agar bisa dicoba dengan URL cadangan.
     */
    private function download(string $url): string
    {
        $response = Http::withHeaders($this->userAgent())
            ->timeout(45)
            ->retry(4, 1200, throw: false)
            ->withOptions(['allow_redirects' => ['max' => 5]])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("HTTP {$response->status()} dari sumber gambar");
        }

        $binary = $response->body();

        if ($binary === '' || strlen($binary) < 1024) {
            throw new RuntimeException('berkas gambar kosong atau terlalu kecil');
        }

        return $binary;
    }

    /**
     * Potong bujur sangkar di tengah, resize ke SIZE, dan encode sebagai JPEG.
     */
    private function encode(string $binary): string
    {
        $size = @getimagesizefromstring($binary);

        if ($size === false) {
            throw new RuntimeException('bukan berkas gambar yang valid');
        }

        [$width, $height, $type] = $size;

        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException("format gambar tidak didukung (tipe {$type})");
        }

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw new RuntimeException('gambar gagal didekode GD');
        }

        // Potong bujur sangkar dari tengah gambar.
        $edge = min($width, $height);
        $sourceX = (int) (($width - $edge) / 2);
        $sourceY = (int) (($height - $edge) / 2);

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            self::SIZE,
            self::SIZE,
            $edge,
            $edge,
        );

        ob_start();
        imagejpeg($canvas, null, self::QUALITY);
        $output = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $output;
    }

    /**
     * User-Agent deskriptif. Wikimedia dan sebagian besar CDN menolak request
     * tanpa UA yang jelas, jadi nama alat dan kontak ikut disertakan.
     *
     * @return array<string, string>
     */
    private function userAgent(): array
    {
        return [
            'User-Agent' => 'POS-Product-Image-Seeder/1.0 (Laravel; seed data untuk demo POS; +https://github.com/)',
        ];
    }

    /**
     * Kosongkan path gambar produk agar tidak menunjuk berkas yang tidak ada.
     * Frontend sudah menampilkan ikon pengganti saat gambar kosong.
     */
    private function forgetImage(Product $product): void
    {
        if (trim((string) $product->image) !== '') {
            $product->forceFill(['image' => null])->saveQuietly();
        }
    }

    /**
     * Nama berkas gambar: pakai SKU, jatuh ke slug nama bila SKU kosong.
     */
    private function slugFor(Product $product): string
    {
        $sku = trim((string) $product->sku);

        if ($sku !== '') {
            return preg_replace('/[^A-Za-z0-9_-]+/', '-', $sku) ?? 'product';
        }

        $slug = Str($product->name)->slug()->value();

        return $slug !== '' ? $slug : 'product-'.$product->id;
    }

    /**
     * Padding SKU agar kolom output sejajar.
     */
    private function pad(string $sku): string
    {
        return str_pad($sku, 8);
    }
}
