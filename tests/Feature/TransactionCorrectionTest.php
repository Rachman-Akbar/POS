<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\ItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesReceipt;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Koreksi transaksi oleh admin: pembatalan (void) dan retur.
 *
 * Fokus test: uang yang keluar dari kas karena kesalahan kasir selalu bisa
 * ditelusuri (siapa, kapan, berapa, alasan), pembukuan tetap seimbang setelah
 * pembatalan, dan kasir tidak bisa menyentuh endpoint koreksi meski ia tahu
 * URL-nya.
 */
class TransactionCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ChartOfAccountSeeder::class, PaymentMethodSeeder::class, ProductSeeder::class]);
    }

    public function test_anonymous_request_cannot_void_a_transaction(): void
    {
        $order = $this->paidOrder();

        // `Sanctum::actingAs` di helper di atas meninggalkan user aktif untuk
        // seluruh test, jadi guard dibuang dulu agar request ini benar-benar
        // tanpa sesi — persis seperti Postman tanpa token.
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/orders/{$order->id}/void", ['reason' => 'Input kasir salah'])
            ->assertUnauthorized();

        $this->assertSame(PaymentStatus::Paid->value, $order->fresh()->payment_status);
    }

    public function test_cashier_cannot_void_a_transaction(): void
    {
        $order = $this->paidOrder();

        Sanctum::actingAs($this->staff($this->cashierPermissions(), ['role' => 'cashier']));

        $this->postJson("/api/orders/{$order->id}/void", ['reason' => 'Input kasir salah'])
            ->assertForbidden()
            ->assertJsonPath('missing_permission', 'transaction.void');

        $this->assertSame(PaymentStatus::Paid->value, $order->fresh()->payment_status);
    }

    public function test_cashier_cannot_refund_a_transaction(): void
    {
        $order = $this->paidOrder();

        Sanctum::actingAs($this->staff($this->cashierPermissions(), ['role' => 'cashier']));

        $this->postJson("/api/orders/{$order->id}/refunds/{$order->invoice->receipts()->first()->id}", [
            'reason' => 'Kasir mau kembalikan duit',
        ])->assertForbidden()->assertJsonPath('missing_permission', 'transaction.refund');
    }

    public function test_void_requires_a_reason(): void
    {
        $order = $this->paidOrder();

        Sanctum::actingAs($this->staff(['transaction.void', 'transaction.view.all'], ['role' => 'admin']));

        $this->postJson("/api/orders/{$order->id}/void", ['reason' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertSame(InvoiceStatus::Paid->value, $order->invoice->fresh()->status);
    }

    public function test_admin_can_void_a_paid_transaction(): void
    {
        $order = $this->paidOrder();
        $receipt = $order->invoice->receipts()->firstOrFail();
        $stock = $order->items()->first()->product->stock;

        Sanctum::actingAs($this->staff(['transaction.void', 'transaction.view.all'], ['role' => 'admin']));

        // `assertJsonPath` membandingkan dengan `assertSame`, dan PHP
        // menyerialisasi 50000.0 sebagai `50000` — jadi nominal integer yang
        // selalu bulat dipakai di sini, bukan hasil `/ 2`.
        $this->postJson("/api/orders/{$order->id}/void", ['reason' => 'Barang tidak dikirim, retur pelanggan'])
            ->assertOk()
            ->assertJsonPath('data.refunded', 50000);

        $order = $order->fresh();

        $this->assertSame(OrderStatus::Void->value, $order->status);
        $this->assertSame(PaymentStatus::Refunded->value, $order->payment_status);
        $this->assertSame(InvoiceStatus::Void->value, $order->invoice->status);
        $this->assertSame('Barang tidak dikirim, retur pelanggan', $order->void_reason);
        $this->assertNotNull($order->voided_at);

        // Stok barang yang sudah ditarik dari gudang harus kembali.
        $this->assertSame(
            $stock + $order->items()->first()->qty,
            $order->items()->first()->product->fresh()->stock,
        );

        // Barang yang dibatalkan tidak boleh lagi dihitungطبخ di dapur.
        $this->assertSame(ItemStatus::Cancelled->value, $order->items()->first()->fresh()->status);
    }

    public function test_void_reverses_the_journal_and_keeps_the_books_balanced(): void
    {
        $baseline = $this->journalTotals();
        $order = $this->paidOrder();
        $sold = $this->journalTotals();

        Sanctum::actingAs($this->staff(['transaction.void', 'transaction.view.all'], ['role' => 'admin']));
        $this->postJson("/api/orders/{$order->id}/void", ['reason' => 'Salah input harga'])->assertOk();

        $after = $this->journalTotals();

        // Penjualan harus benar-benar memindahkan angka: pendapatan naik, kas
        // naik, persediaan turun. Kalau ini nol semua, pembatalan yang diuji
        // tidak berarti apa-apa karena tidak ada yang dibatalkan.
        $this->assertNotSame($baseline['revenue'], $sold['revenue']);
        $this->assertNotSame($baseline['cash'], $sold['cash']);
        $this->assertNotSame($baseline['inventory'], $sold['inventory']);

        // Setiap jurnal harus seimbang: total debit = total kredit.
        $this->assertSame(0.0, $after['unbalanced']);
        $this->assertSame(0.0, $sold['unbalanced']);

        // Setelah void, buku harus persis seperti sebelum ada penjualan.
        foreach (['revenue', 'receivable', 'cash', 'inventory'] as $account) {
            $this->assertSame($baseline[$account], $after[$account], "Saldo {$account} tidak kembali ke kondisi awal.");
        }

        // Satu entri pembatalan saja: kalau dibukukan dua entri, sisi piutang
        // ikut dibalik dua kali dan saldo piutang berakhir negatif.
        $this->assertSame(1, JournalEntry::where('type', 'sales_invoice_reversal')->count());
        $this->assertSame(0, JournalEntry::where('type', 'sales_refund')->count());
        $this->assertSame(0.0, $after['receivable']);
    }

    public function test_void_is_recorded_in_the_audit_log(): void
    {
        $order = $this->paidOrder();
        $admin = $this->staff(['transaction.void', 'transaction.view.all'], ['role' => 'admin']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/orders/{$order->id}/void", ['reason' => 'Salah ketik nomor meja'])
            ->assertOk();

        $log = AuditLog::where('module', 'transaction')->where('action', 'void')->firstOrFail();

        // Spec retur: who / what / when / reason harus tersimpan semua.
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Order', $log->record_type);
        $this->assertSame((string) $order->id, $log->record_id);
        $this->assertStringContainsString('Salah ketik nomor meja', (string) $log->description);
        $this->assertSame($order->status, $log->old_values['status']);
        $this->assertSame(OrderStatus::Void->value, $log->new_values['status']);
        $this->assertNotNull($log->created_at);
    }

    public function test_a_voided_transaction_cannot_be_voided_again(): void
    {
        $order = $this->paidOrder();

        Sanctum::actingAs($this->staff(['transaction.void', 'transaction.view.all'], ['role' => 'admin']));
        $this->postJson("/api/orders/{$order->id}/void", ['reason' => 'Dibatalkan sekali'])->assertOk();

        $this->postJson("/api/orders/{$order->id}/void", ['reason' => 'Dibatalkan dua kali'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pesanan ini sudah dibatalkan.');
    }

    public function test_a_voided_transaction_cannot_receive_payment(): void
    {
        $order = $this->paidOrder();
        $cashier = $this->staff($this->cashierPermissions(), ['role' => 'cashier']);

        Sanctum::actingAs($this->staff(['transaction.void', 'transaction.view.all'], ['role' => 'admin']));
        $this->postJson("/api/orders/{$order->id}/void", ['reason' => 'Pesanan salah'])->assertOk();

        // Kasir tetap boleh mencoba, tapi kas tidak boleh menerima uang untuk
        // transaksi yang sudah dibatalkan: itu akan hilang tanpa pembukuan.
        $order = $order->fresh();
        $order->update(['payment_status' => PaymentStatus::Unpaid->value]);

        Sanctum::actingAs($cashier);
        $this->postJson("/api/payments/orders/{$order->id}/settle", ['payment_method' => 'cash'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Transaksi ini sudah dibatalkan.');

        $this->assertSame(0, (int) SalesReceipt::whereHas(
            'invoice',
            fn ($query) => $query->where('order_id', $order->id)
        )->where('payment_method', 'cash')->where('gross_amount', '>', 0)->where('refund_amount', 0)->count());
    }

    public function test_admin_can_refund_part_of_a_payment(): void
    {
        $order = $this->paidOrder();
        $receipt = $order->invoice->receipts()->firstOrFail();
        $gross = (float) $receipt->gross_amount;

        Sanctum::actingAs($this->staff(['transaction.refund', 'transaction.view.all'], ['role' => 'admin']));

        $this->postJson("/api/orders/{$order->id}/refunds/{$receipt->id}", [
            'amount' => 25000,
            'reason' => 'Pelanggan mengembalikan 1 porsi',
        ])->assertOk()->assertJsonPath('data.amount', 25000);

        $order = $order->fresh();

        $this->assertSame(25000.0, (float) $receipt->fresh()->refund_amount);
        $this->assertSame(25000.0, (float) $order->paid_amount);
        $this->assertSame(PaymentStatus::Partial->value, $order->payment_status);
        $this->assertSame($order->total_amount, $order->invoice->fresh()->total_amount);
    }

    public function test_refunding_the_whole_payment_marks_the_order_as_refunded(): void
    {
        $order = $this->paidOrder();
        $receipt = $order->invoice->receipts()->firstOrFail();
        $gross = (float) $receipt->gross_amount;

        Sanctum::actingAs($this->staff(['transaction.refund', 'transaction.view.all'], ['role' => 'admin']));

        $this->postJson("/api/orders/{$order->id}/refunds/{$receipt->id}", [
            'reason' => 'Pelanggan batal seluruh pesanan',
        ])->assertOk()->assertJsonPath('data.amount', 50000);

        $this->assertSame(PaymentStatus::Refunded->value, $order->fresh()->payment_status);
        $this->assertSame(0.0, (float) $order->fresh()->paid_amount);
    }

    public function test_refund_cannot_exceed_the_money_that_was_actually_received(): void
    {
        $order = $this->paidOrder();
        $receipt = $order->invoice->receipts()->firstOrFail();
        $gross = (float) $receipt->gross_amount;

        Sanctum::actingAs($this->staff(['transaction.refund', 'transaction.view.all'], ['role' => 'admin']));

        $this->postJson("/api/orders/{$order->id}/refunds/{$receipt->id}", [
            'amount' => $gross + 1000,
            'reason' => 'Terlalu banyak',
        ])->assertUnprocessable()->assertJsonPath(
            'message',
            'Nominal retur melebihi sisa pembayaran yang bisa diretur.'
        );

        $this->assertSame(0.0, (float) $receipt->fresh()->refund_amount);
    }

    public function test_repeated_partial_refunds_accumulate_instead_of_overwriting(): void
    {
        $order = $this->paidOrder();
        $receipt = $order->invoice->receipts()->firstOrFail();
        $gross = (float) $receipt->gross_amount;

        Sanctum::actingAs($this->staff(['transaction.refund', 'transaction.view.all'], ['role' => 'admin']));

        foreach ([10000, 15000] as $chunk) {
            $this->postJson("/api/orders/{$order->id}/refunds/{$receipt->id}", [
                'amount' => $chunk,
                'reason' => 'Retur sebagian',
            ])->assertOk();
        }

        $this->assertSame(25000.0, (float) $receipt->fresh()->refund_amount);
        $this->assertSame(round($gross - 25000, 2), (float) $order->fresh()->paid_amount);
    }

    public function test_refund_is_recorded_in_the_audit_log_with_the_actor(): void
    {
        $order = $this->paidOrder();
        $receipt = $order->invoice->receipts()->firstOrFail();
        $admin = $this->staff(['transaction.refund', 'transaction.view.all'], ['role' => 'admin']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/orders/{$order->id}/refunds/{$receipt->id}", [
            'amount' => 10000,
            'reason' => 'Pelanggan kirim kurang satu item',
        ])->assertOk();

        $log = AuditLog::where('module', 'transaction')->where('action', 'refund')->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertStringContainsString('Rp. 10.000', (string) $log->description);
        $this->assertStringContainsString('satu item', (string) $log->description);
    }

    public function test_refund_posted_a_balanced_journal_entry(): void
    {
        $order = $this->paidOrder();
        $receipt = $order->invoice->receipts()->firstOrFail();
        $before = $this->journalTotals();

        Sanctum::actingAs($this->staff(['transaction.refund', 'transaction.view.all'], ['role' => 'admin']));
        $this->postJson("/api/orders/{$order->id}/refunds/{$receipt->id}", [
            'amount' => 10000,
            'reason' => 'Retur sebagian',
        ])->assertOk();

        $after = $this->journalTotals();

        $this->assertSame(0.0, $after['unbalanced']);
        $this->assertSame(
            round($before['cash'] - 10000, 2),
            $after['cash'],
            'Retur harus mengurangi kas sebesar nominal retur.',
        );
        $this->assertSame(
            round($before['revenue'] + 10000, 2),
            $after['revenue'],
            'Retur harus mengurangi pendapatan sebesar nominal retur.',
        );

        // Pembayarannya sudah masuk kas saat diterima, jadi piutang tidak boleh
        // ikut berubah — kalau didebet di sini saldo piutang jadi negatif.
        $this->assertSame(
            $before['receivable'],
            $after['receivable'],
            'Retur setelah pembayaran tidak boleh mengubah saldo piutang.',
        );

        $this->assertDatabaseHas('journal_entries', ['type' => 'sales_refund']);
    }

    public function test_refund_cannot_target_a_receipt_from_another_order(): void
    {
        $order = $this->paidOrder();
        $other = $this->paidOrder();
        $otherReceipt = $other->invoice->receipts()->firstOrFail();

        Sanctum::actingAs($this->staff(['transaction.refund', 'transaction.view.all'], ['role' => 'admin']));

        $this->postJson("/api/orders/{$order->id}/refunds/{$otherReceipt->id}", [
            'reason' => 'Mencoba retur dari pesanan lain',
        ])->assertUnprocessable()->assertJsonPath(
            'message',
            'Penerimaan pembayaran tidak ditemukan pada pesanan ini.',
        );
    }

    /**
     * Permission koreksi tidak otomatis memberi akses ke semua transaksi:
     * admin yang sedang shift di kasir hanya boleh mengoreksi shiftnya sendiri
     * kecuali ia memegang `transaction.view.all`.
     */
    public function test_correction_is_limited_to_the_reachable_data_scope(): void
    {
        $foreign = $this->paidOrder();

        // Role `admin` warisan tidak boleh otomatis menjadikan orang ini Super
        // Admin; ia cuma memegang `transaction.void`.
        $shiftAdmin = $this->staff(['transaction.void'], ['role' => 'admin']);
        $this->assertFalse($shiftAdmin->isSuperAdmin());

        $own = $this->paidOrderAs($shiftAdmin);

        $this->postJson("/api/orders/{$foreign->id}/void", ['reason' => 'Di luar shift saya'])
            ->assertForbidden();

        $this->postJson("/api/orders/{$own->id}/void", ['reason' => 'Shift saya sendiri'])
            ->assertOk();
    }

    public function test_voided_orders_disappear_from_the_kitchen_queue(): void
    {
        $order = $this->paidOrder();
        $item = $order->items()->first();

        Sanctum::actingAs($this->staff($this->kitchenPermissions(), ['role' => 'kitchen']));
        $this->getJson('/api/kitchen/items')->assertOk()->assertJsonCount(1, 'data.waiting');

        Sanctum::actingAs($this->staff(['transaction.void', 'transaction.view.all'], ['role' => 'admin']));
        $this->postJson("/api/orders/{$order->id}/void", ['reason' => 'Pesanan dibatalkan admin'])->assertOk();

        Sanctum::actingAs($this->staff($this->kitchenPermissions(), ['role' => 'kitchen']));
        $this->getJson('/api/kitchen/items')->assertOk()->assertJsonCount(0, 'data.waiting');

        // Dapur tidak boleh mengubah tahap produksi barang yang sudah dibatalkan.
        $this->patchJson("/api/kitchen/items/{$item->id}/status", ['status' => 'cooking'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pesanan ini sudah dibatalkan, tidak bisa diproses dapur.');
    }

    public function test_seeded_roles_can_and_cannot_correct_transactions(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $granted = fn (string $slug): array => Role::query()
            ->where('slug', $slug)
            ->firstOrFail()
            ->permissions()
            ->pluck('name')
            ->all();

        // Admin dan Supervisor boleh mengoreksi kesalahan kasir.
        $this->assertContains('transaction.void', $granted('admin'));
        $this->assertContains('transaction.refund', $granted('admin'));
        $this->assertContains('transaction.void', $granted('supervisor'));
        $this->assertContains('transaction.refund', $granted('supervisor'));

        // Admin melihat seluruh transaksi, kasir tidak.
        $this->assertContains('transaction.view.all', $granted('admin'));
        $this->assertContains('transaction.view.all', $granted('supervisor'));

        // Kasir, pelayan, dan dapur tidak boleh mengoreksi apa pun.
        foreach (['kasir', 'pelayan', 'dapur', 'manager'] as $slug) {
            $permissions = $granted($slug);

            $this->assertNotContains('transaction.void', $permissions, "Role {$slug} tidak boleh void.");
            $this->assertNotContains('transaction.refund', $permissions, "Role {$slug} tidak boleh retur.");
        }

        $this->assertNotContains('transaction.view.all', $granted('kasir'));

        // Role `admin` warisan tidak boleh memberi jalur pintas Super Admin,
        // kalau tidak `transaction.view.all` jadi tidak berarti apa-apa.
        $legacyAdmin = User::factory()->create(['role' => 'admin']);
        $legacyAdmin->syncRoles([Role::query()->where('slug', 'admin')->firstOrFail()->id]);

        $this->assertFalse($legacyAdmin->fresh()->isSuperAdmin());
        $this->assertFalse(
            $legacyAdmin->fresh()->hasPermission('role.delete'),
            'Role Admin tidak memegang `role.delete`, jadi harus tetap ditolak walau kolom `role` warisan berisi `admin`.',
        );
    }

    /**
     * Order `pay_now` yang sudah lunas, dibuat oleh kasir.
     */
    private function paidOrder(?User $creator = null): Order
    {
        $cashier = $creator ?? $this->staff($this->cashierPermissions(), ['role' => 'cashier']);

        Sanctum::actingAs($cashier);

        $this->postJson('/api/orders', [
            'payment_type' => 'pay_now',
            'payment_method' => 'cash',
            'items' => [['product_id' => Product::firstOrFail()->id, 'qty' => 2]],
        ])->assertCreated();

        return Order::latest('id')->firstOrFail()->load('invoice.receipts');
    }

    /**
     * Order lunas milik user tertentu, untuk menguji batas data (scope).
     */
    private function paidOrderAs(User $owner): Order
    {
        return $this->paidOrder($this->grant($owner, $this->cashierPermissions()));
    }

    /**
     * Saldo per akun kunci plus selisih debit-kredit, untuk membuktikan
     * pembatalan tidak merusak pembukuan.
     *
     * @return array<string, float>
     */
    private function journalTotals(): array
    {
        $totals = JournalEntry::query()
            ->join('journal_details', 'journal_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_details.account_id')
            ->groupBy('chart_of_accounts.code')
            ->selectRaw('chart_of_accounts.code, SUM(journal_details.debit) as debit, SUM(journal_details.credit) as credit')
            ->pluck('debit', 'code');

        $credits = JournalEntry::query()
            ->join('journal_details', 'journal_details.journal_entry_id', '=', 'journal_entries.id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_details.account_id')
            ->groupBy('chart_of_accounts.code')
            ->selectRaw('chart_of_accounts.code, SUM(journal_details.credit) as credit')
            ->pluck('credit', 'code');

        $balance = fn (string $code): float => round(
            (float) ($totals[$code] ?? 0) - (float) ($credits[$code] ?? 0),
            2
        );

        return [
            'revenue' => $balance('4000'),
            'receivable' => $balance('1100'),
            'cash' => $balance('1300'),
            'inventory' => $balance('1200'),
            'unbalanced' => round(
                (float) JournalDetail::query()->sum('debit') - (float) JournalDetail::query()->sum('credit'),
                2
            ),
        ];
    }
}
