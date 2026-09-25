<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentType;
use App\Events\OrderCreated;
use App\Http\Controllers\Api\Concerns\SafeBroadcasts;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    use SafeBroadcasts;

    public function __construct(private readonly SalesService $salesService) {}

    /**
     * List orders visible to the waiter dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['items' => fn ($query) => $query->with('product'), 'invoice'])
            ->when($request->boolean('active_only'), function ($query) use ($request) {
                $status = $request->query('status');
                $query->where('status', '!=', 'completed');
                if ($status) {
                    $query->where('status', $status);
                }
            })
            ->when($request->boolean('ready'), function ($query) {
                $query->where('status', '!=', 'completed')
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
            'payment_type' => ['required', 'in:pay_now,pay_later'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:25'],
            'payment_account_id' => ['nullable', 'integer', 'exists:cash_bank_accounts,id'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = $this->salesService->createOrder(
                user: null,
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
                ],
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->safeBroadcast(new OrderCreated($order));

        return response()->json(['data' => $order], Response::HTTP_CREATED);
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
     * Show a single order with full detail.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['items.product', 'invoice.receipts', 'user']);

        return response()->json(['data' => $order]);
    }
}
