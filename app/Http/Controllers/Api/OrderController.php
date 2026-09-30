<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentType;
use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Api\Concerns\AuthorizesDiscount;
use App\Http\Controllers\Api\Concerns\SafeBroadcasts;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\AuditLogger;
use App\Services\SalesService;
use App\Services\TransactionScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use AuthorizesDiscount;
    use SafeBroadcasts;

    public function __construct(
        private readonly SalesService $salesService,
        private readonly TransactionScope $scope,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * List orders visible to the waiter dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['items' => fn ($query) => $query->with('product'), 'invoice', 'customer'])
            ->when($request->boolean('active_only'), function ($query) use ($request) {
                $status = $request->query('status');
                $query->where('status', OrderStatus::Pending->value);
                if ($status) {
                    $query->where('status', $status);
                }
            })
            ->when($request->boolean('ready'), function ($query) {
                $query->where('status', OrderStatus::Pending->value)
                    ->whereHas('items', fn ($q) => $q->where('status', 'done'))
                    ->whereDoesntHave('items', fn ($q) => $q->where('status', '!=', 'done'));
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * Create a new order (Step 2-3 of the flow).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'table_number' => ['nullable', 'string', 'max:50'],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('is_active', true),
            ],
            'payment_type' => ['required', 'in:pay_now,pay_later'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:25'],
            'payment_account_id' => [
                'nullable',
                'integer',
                Rule::exists('cash_bank_accounts', 'id')->where(
                    fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->whereIn('payment_method_id', PaymentMethod::query()
                            ->where('code', $request->input('payment_method', 'cash'))
                            ->select('id'))
                ),
            ],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ], [
            'payment_account_id.exists' => 'Rekening tidak sesuai dengan metode pembayaran yang dipilih.',
            'customer_id.exists' => 'Pelanggan tidak ditemukan atau sudah tidak aktif.',
        ]);

        $this->authorizeDiscount($request, (float) ($data['discount'] ?? 0));

        try {
            $order = $this->salesService->createOrder(
                user: $request->user(),
                tableNumber: $data['table_number'] ?? '',
                paymentType: PaymentType::from($data['payment_type']),
                items: $data['items'],
                options: [
                    'discount' => (float) ($data['discount'] ?? 0),
                    'tax_rate' => isset($data['tax_rate']) ? (float) $data['tax_rate'] : null,
                    'payment_method' => $data['payment_method'] ?? null,
                    'payment_account_id' => $data['payment_account_id'] ?? null,
                    'paid_amount' => isset($data['paid_amount']) ? (float) $data['paid_amount'] : null,
                    'notes' => $data['notes'] ?? null,
                    'customer_id' => $data['customer_id'] ?? null,
                ],
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->safeBroadcast(new OrderCreated($order));

        return response()->json(['data' => $order->load('customer')], Response::HTTP_CREATED);
    }

    /**
     * Mark an order as completed (food served to customer).
     */
    public function complete(Order $order): JsonResponse
    {
        $order = $this->salesService->completeOrder($order);

        return response()->json(['data' => $order]);
    }

    /**
     * Every order the cashier tracks, including drafts. Unlike the invoice
     * list this also returns orders that have no invoice yet, because a draft
     * is a stored order that has not been issued an invoice.
     *
     * Transaksi yang sudah dilunasi milik user lain disembunyikan, sedangkan
     * pesanan aktif dan draft milik siapa pun tetap terlihat supaya kasir bisa
     * melanjutkan pekerjaan yang ditinggalkan shift sebelumnya.
     */
    public function transactions(Request $request): JsonResponse
    {
        $orders = $this->scope->orders(
            Order::query()->with([
                'items.product',
                'customer',
                'invoice.receipts',
            ]),
            $request->user()
        )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * Save the current checkout as a draft. A draft is a stored, unprocessed
     * order: no invoice, no stock reservation, no kitchen. It is finalized
     * later from the cashier's Pesanan list.
     */
    public function storeDraft(Request $request): JsonResponse
    {
        $data = $this->validateItems($request);

        $this->authorizeDiscount($request, (float) ($data['discount'] ?? 0));

        $order = $this->salesService->saveDraft(
            user: $request->user(),
            tableNumber: $data['table_number'] ?? '',
            items: $data['items'],
            options: [
                'discount' => (float) ($data['discount'] ?? 0),
                'tax_rate' => isset($data['tax_rate']) ? (float) $data['tax_rate'] : null,
                'notes' => $data['notes'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
            ],
        );

        return response()->json(['data' => $order], Response::HTTP_CREATED);
    }

    /**
     * Edit isi draft dari halaman detail pesanan.
     *
     * Hanya draft yang bisa diubah isinya (menu, jumlah, meja, diskon, PPN).
     * Transaksi yang sudah diproses dikoreksi lewat endpoint koreksi, yang
     * permission-nya terpisah dan tercatat di audit log.
     */
    public function update(Request $request, Order $order): JsonResponse
    {
        $data = $this->validateItems($request);

        $this->authorizeDiscount($request, (float) ($data['discount'] ?? 0));

        try {
            $order = $this->salesService->updateDraft(
                order: $order,
                tableNumber: $data['table_number'] ?? '',
                items: $data['items'],
                options: [
                    'discount' => (float) ($data['discount'] ?? 0),
                    'tax_rate' => isset($data['tax_rate']) ? (float) $data['tax_rate'] : null,
                    'notes' => $data['notes'] ?? null,
                    'customer_id' => $data['customer_id'] ?? null,
                ],
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->audit->log(
            $request,
            'update',
            'transaction',
            null,
            sprintf('Mengedit draft %s menjadi %d item.', $order->order_number, count($data['items'])),
            ['status' => $order->status],
            null,
        );

        return response()->json(['data' => $order]);
    }

    /**
     * Continue a draft: reserve stock, issue the invoice, optionally record
     * the payment, and move the order into the normal kitchen flow.
     */
    public function finalizeDraft(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:25'],
            'payment_account_id' => [
                'nullable',
                'integer',
                Rule::exists('cash_bank_accounts', 'id')->where(
                    fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->whereIn('payment_method_id', PaymentMethod::query()
                            ->where('code', $request->input('payment_method', 'cash'))
                            ->select('id'))
                ),
            ],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ], [
            'payment_account_id.exists' => 'Rekening tidak sesuai dengan metode pembayaran yang dipilih.',
        ]);

        try {
            $order = $this->salesService->finalizeDraft($order, [
                'payment_method' => $data['payment_method'] ?? null,
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'paid_amount' => isset($data['paid_amount']) ? (float) $data['paid_amount'] : null,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->safeBroadcast(new OrderCreated($order));

        return response()->json(['data' => $order]);
    }

    /**
     * Shared validation for order creation and draft creation.
     */
    private function validateItems(Request $request): array
    {
        return $request->validate([
            'table_number' => ['nullable', 'string', 'max:50'],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('is_active', true),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ], [
            'customer_id.exists' => 'Pelanggan tidak ditemukan atau sudah tidak aktif.',
        ]);
    }

    /**
     * Hapus draft sebelum diproses.
     *
     * Draft tidak menyentuh invoice, jurnal, atau stok, jadi penghapusan aman
     * dan langsung. Transaksi yang sudah jadi tetap tidak bisa dihapus — mana
     * pun yang salah input, koreksi dilakukan lewat void/refund yang tercatat.
     */
    public function destroy(Request $request, Order $order): JsonResponse|Response
    {
        try {
            $this->salesService->deleteDraft($order);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->audit->log(
            $request,
            'delete',
            'transaction',
            null,
            sprintf('Menghapus draft %s.', $order->order_number),
            ['status' => $order->status],
            null,
        );

        $this->safeBroadcast(new OrderStatusUpdated($order, OrderStatus::Draft->value));

        return response()->noContent();
    }

    /**
     * Show a single order with full detail.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['items.product', 'invoice.receipts', 'user']);

        return response()->json(['data' => $order]);
    }
}
