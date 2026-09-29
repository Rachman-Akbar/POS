<?php

namespace Database\Seeders;

use App\Enums\CustomerType;
use App\Enums\OrderStatus;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\SalesService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Membuat riwayat transaksi penjualan yang realistis.
 *
 * Setiap order dibuat melalui SalesService agar item, stok, faktur, receipt,
 * dan jurnal akuntansi tetap konsisten. Tanggal dibuat relatif terhadap
 * `now()` (30 hari terakhir) memakai Carbon::setTestNow() supaya faktur,
 * receipt, dan jurnal ikut bertanggal sama dengan ordernya.
 *
 * Stok produk dinaikkan lebih dulu sebesar total konsumsi rencana, lalu
 * dikembalikan oleh createOrder — sehingga stok akhir di DB sama persis dengan
 * nilai yang ditetapkan ProductSeeder.
 */
class SalesTransactionSeeder extends Seeder
{
    /**
     * Total order yang dibuat.
     */
    private const TOTAL_ORDERS = 120;

    /**
     * Jumlah hari ke belakang yang dipakai.
     */
    private const TOTAL_DAYS = 30;

    /**
     * Distribusi metode pembayaran (kode => bobot).
     *
     * @var array<string, int>
     */
    private const METHOD_WEIGHTS = [
        'cash' => 38,
        'qris' => 20,
        'bank' => 14,
        'gopay' => 9,
        'dana' => 6,
        'ovo' => 5,
        'bca' => 4,
        'bni' => 2,
        'bri' => 2,
    ];

    /**
     * @var Collection<int, Product>
     */
    private Collection $food;

    /**
     * @var Collection<int, Product>
     */
    private Collection $drink;

    /**
     * @var Collection<int, Product>
     */
    private Collection $snack;

    /**
     * @var Collection<int, Product>
     */
    private Collection $dessert;

    public function run(): void
    {
        mt_srand(20260929);

        $this->food = Product::query()->where('category', 'Makanan')->where('is_active', true)->get();
        $this->drink = Product::query()->where('category', 'Minuman')->where('is_active', true)->get();
        $this->snack = Product::query()->where('category', 'Snack')->where('is_active', true)->get();
        $this->dessert = Product::query()->where('category', 'Dessert')->where('is_active', true)->get();

        if ($this->food->isEmpty()) {
            $this->command?->warn('Produk belum ada, transaksi dilewati.');

            return;
        }

        $customers = Customer::query()->where('is_active', true)->get();
        $cashiers = User::query()->whereIn('role', ['cashier', 'waiter'])->get();

        $plan = $this->buildPlan($customers);
        $this->reserveStockFor($plan);

        $sales = app(SalesService::class);
        $created = 0;

        foreach ($plan as $spec) {
            Carbon::setTestNow($spec['at']);
            DB::transaction(function () use ($sales, $spec, $cashiers): void {
                // `unit_price` hanya dipakai untuk menghitung diskon, kirim sisanya.
                $items = array_map(
                    fn (array $item): array => ['product_id' => $item['product_id'], 'qty' => $item['qty']],
                    $spec['items'],
                );

                $order = $sales->createOrder(
                    user: $cashiers->isNotEmpty() ? $cashiers->random() : null,
                    tableNumber: $spec['table_number'] ?? '',
                    paymentType: $spec['payment_type'],
                    items: $items,
                    options: [
                        'discount' => $spec['discount'],
                        'payment_method' => $spec['payment_type'] === PaymentType::PayNow ? $spec['payment_method'] : null,
                        'notes' => $spec['notes'],
                        'customer_id' => $spec['customer_id'],
                    ],
                );

                $this->finalizeOrder($order, $spec);
            });
            Carbon::setTestNow();
            $created++;

            if ($created % 20 === 0) {
                $this->command?->info("  {$created} order dibuat...");
            }
        }

        $this->command?->info("Transaksi selesai: {$created} order dalam ".self::TOTAL_DAYS.' hari terakhir.');
    }

    /**
     * Susun rencana order (tanpa menyentuh DB) lalu urutkan kronologis.
     *
     * @param  Collection<int, Customer>  $customers
     * @return array<int, array<string, mixed>>
     */
    private function buildPlan(Collection $customers): array
    {
        $plan = [];

        foreach ($this->dailyCounts() as $dayIndex => $count) {
            $day = Carbon::now()->subDays(self::TOTAL_DAYS - 1 - $dayIndex)->startOfDay();

            for ($i = 0; $i < $count; $i++) {
                $at = $this->timeOnDay($day);
                $plan[] = $this->makeSpec($at, $customers);
            }
        }

        usort($plan, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return $plan;
    }

    /**
     * Jumlah order per hari: dasar 3/hari + satu_order tambahan yang disebar acak.
     *
     * @return array<int, int> indeks hari => jumlah order
     */
    private function dailyCounts(): array
    {
        $base = 3;
        $extra = self::TOTAL_ORDERS - ($base * self::TOTAL_DAYS);
        $counts = array_fill(0, self::TOTAL_DAYS, $base);

        for ($i = 0; $i < $extra; $i++) {
            $counts[mt_rand(0, self::TOTAL_DAYS - 1)]++;
        }

        return $counts;
    }

    /**
     * Acak jam buka (10:00-21:00) dengan puncak makan siang & malam.
     */
    private function timeOnDay(Carbon $day): Carbon
    {
        $slots = [
            10, 11,
            12, 12, 12,
            13, 13, 13,
            14,
            15, 15,
            16, 16,
            17, 17,
            18, 18, 18,
            19, 19, 19, 19,
            20, 20, 20,
            21,
        ];

        $at = $day->copy()->setTime(mt_rand(10, 21), mt_rand(0, 59), mt_rand(0, 59));
        $hour = $slots[mt_rand(0, count($slots) - 1)];
        $at->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));

        // Jangan membuat order di masa depan (untuk hari ini).
        return $at->isFuture() ? Carbon::now()->subMinutes(mt_rand(5, 240)) : $at;
    }

    /**
     * Rancang satu order lengkap.
     *
     * @param  Collection<int, Customer>  $customers
     * @return array<string, mixed>
     */
    private function makeSpec(Carbon $at, Collection $customers): array
    {
        $items = $this->makeItems();
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += $item['qty'] * (float) $item['unit_price'];
        }

        $discount = $this->makeDiscount($subtotal);
        $paymentType = $this->makePaymentType();
        $isRecent = $at->isAfter(Carbon::now()->subDay());
        $completed = $isRecent ? mt_rand(0, 100) < 35 : mt_rand(0, 100) < 94;

        return [
            'at' => $at,
            'payment_type' => $paymentType,
            'payment_method' => $paymentType === PaymentType::PayNow ? $this->pickMethod() : null,
            'customer_id' => $this->pickCustomer($customers, $subtotal),
            'discount' => $discount,
            'table_number' => mt_rand(0, 100) < 60 ? 'Meja '.mt_rand(1, 20) : null,
            'notes' => $this->makeNotes($completed),
            'items' => $items,
            'status' => $completed ? OrderStatus::Completed : OrderStatus::Pending,
        ];
    }

    /**
     * Susun baris item unik per order dengan gaya belanja yang wajar.
     *
     * @return array<int, array{product: Product, qty: int}>
     */
    private function makeItems(): array
    {
        $style = $this->weightedPick(['main' => 6, 'combo' => 2, 'light' => 2]);
        $picked = [];

        $add = function (Collection $pool, int $max) use (&$picked): void {
            $count = mt_rand(0, $max);
            for ($i = 0; $i < $count; $i++) {
                $available = $pool->reject(fn (Product $p): bool => isset($picked[$p->id]))->values();
                if ($available->isEmpty()) {
                    return;
                }

                $product = $available->random();
                $picked[$product->id] = $product;
            }
        };

        match ($style) {
            'main' => (function () use ($add): void {
                $add($this->food, 2);
                $add($this->drink, 1);
            })(),
            'combo' => (function () use ($add): void {
                $add($this->food, 1);
                $add($this->snack, 1);
                $add($this->drink, 1);
            })(),
            default => (function () use ($add): void {
                $pool = [$this->drink, $this->snack, $this->dessert][mt_rand(0, 2)];
                $add($pool, 1);
            })(),
        };

        // Pastikan minimal satu item food/drink agar order tidak kosong.
        if ($picked === []) {
            $product = $this->food->random();
            $picked[$product->id] = $product;
        }

        $items = [];
        foreach ($picked as $product) {
            $items[] = [
                'product_id' => $product->id,
                'qty' => mt_rand(0, 100) < 75 ? 1 : mt_rand(2, 3),
                'unit_price' => (float) $product->price,
            ];
        }

        return $items;
    }

    /**
     * Diskon sesekali: persen atau nominal tetap.
     */
    private function makeDiscount(float $subtotal): float
    {
        if (mt_rand(0, 100) >= 20 || $subtotal <= 0) {
            return 0.0;
        }

        $discount = mt_rand(0, 100) < 60
            ? $subtotal * (mt_rand(5, 15) / 100)
            : mt_rand(3, 8) * 1000;

        return round(min($discount, $subtotal), 2);
    }

    /**
     * Sebagian besar bayar di muka, sisanya faktur gantung (pay_later).
     */
    private function makePaymentType(): PaymentType
    {
        return mt_rand(0, 100) < 84 ? PaymentType::PayNow : PaymentType::PayLater;
    }

    /**
     * Pilih metode pembayaran berdasarkan bobot.
     */
    private function pickMethod(): string
    {
        $total = array_sum(self::METHOD_WEIGHTS);
        $roll = mt_rand(1, $total);

        foreach (self::METHOD_WEIGHTS as $code => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $code;
            }
        }

        return 'cash';
    }

    /**
     * Pilih pelanggan untuk sebuah order.
     *
     * Sekitar 55% order terkait pelanggan, sisanya guest. Korelasi dengan nilai
     * order dibuat realistis: badan usaha (kantor, gathering) jauh lebih sering
     * muncul di order bernilai besar, sedangkan perorangan mendominasi
     * transaksi kecil harian.
     *
     * @param  Collection<int, Customer>  $customers
     */
    private function pickCustomer(Collection $customers, float $subtotal): ?int
    {
        if ($customers->isEmpty() || mt_rand(0, 100) >= 55) {
            return null;
        }

        // Order besar -> condong ke badan usaha; order kecil -> perorangan.
        $businessChance = match (true) {
            $subtotal >= 70000 => 85,
            $subtotal >= 45000 => 60,
            default => 12,
        };

        $wantsBusiness = mt_rand(0, 100) < $businessChance;

        $pool = $customers->filter(
            fn (Customer $c): bool => ($c->customer_type === CustomerType::Business) === $wantsBusiness
        );

        // Fallback ke semua pelanggan bila tidak ada yang cocok (mis. hanya ada
        // satu tipe pelanggan di database).
        $pool = $pool->isNotEmpty() ? $pool : $customers;

        return $pool->random()->id;
    }

    /**
     * Catatan singkat untuk beberapa order.
     */
    private function makeNotes(bool $completed): ?string
    {
        $notes = [
            'Packing untuk dibawa pulang.',
            'Pedas ekstra, tidak pakai sambal kacang.',
            'Bisa ganti teh jadi lemon tea?',
            'Untuk takeaway, mohon dibungkus terpisah.',
            'Kirim ke meja, ya.',
        ];

        if (mt_rand(0, 100) < 12) {
            return $notes[mt_rand(0, count($notes) - 1)];
        }

        return null;
    }

    /**
     * Naikkan stok produk sebesar total konsumsi rencana agar stok akhir
     * kembali persis ke nilai ProductSeeder setelah order dibuat.
     *
     * @param  array<int, array<string, mixed>>  $plan
     */
    private function reserveStockFor(array $plan): void
    {
        $consumption = [];

        foreach ($plan as $spec) {
            foreach ($spec['items'] as $item) {
                $consumption[$item['product_id']] = ($consumption[$item['product_id']] ?? 0) + $item['qty'];
            }
        }

        foreach ($consumption as $productId => $qty) {
            Product::whereKey($productId)->increment('stock', $qty);
        }
    }

    /**
     * Terapkan status order dan status item dapur setelah order dibuat.
     *
     * @param  array<string, mixed>  $spec
     */
    private function finalizeOrder(Order $order, array $spec): void
    {
        $completed = $spec['status'] === OrderStatus::Completed;

        $order->update(['status' => $spec['status']->value]);

        if ($completed) {
            $order->items()->update(['status' => 'done']);

            return;
        }

        // Order aktif: sebar status item supaya layar dapur terisi realistis.
        $pool = ['pending', 'pending', 'cooking', 'sent', 'done'];
        foreach ($order->items as $item) {
            $item->update(['status' => $pool[mt_rand(0, count($pool) - 1)]]);
        }
    }

    /**
     * Pilih kunci berdasarkan bobot.
     *
     * @param  array<string, int>  $weights
     */
    private function weightedPick(array $weights): string
    {
        $total = array_sum($weights);
        $roll = mt_rand(1, $total);

        foreach ($weights as $key => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $key;
            }
        }

        return (string) array_key_first($weights);
    }
}
