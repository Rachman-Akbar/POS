<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentProcessed;
use App\Http\Controllers\Api\Concerns\SafeBroadcasts;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\SalesInvoice;
use App\Models\SalesReceipt;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    use SafeBroadcasts;

    public function __construct(private readonly SalesService $salesService) {}

    /**
     * Cashier view — outstanding Pay Later invoices awaiting settlement.
     */
    public function pending(): JsonResponse
    {
        $invoices = SalesInvoice::where('status', InvoiceStatus::Issued->value)
            ->with(['order' => fn ($query) => $query->with('items.product')])
            ->orderByDesc('issued_at')
            ->get();

        return response()->json(['data' => $invoices]);
    }

    /**
     * Cashier settles a Pay Later invoice (final payment/receipt).
     */
    public function settle(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', 'string', 'max:25'],
        ]);

        try {
            $method = PaymentMethod::where('code', $data['payment_method'])->where('is_active', true)->first()
                ?? throw new \DomainException('Metode pembayaran tidak ditemukan.');
            $receipt = $this->salesService->settlePayment($order, $method);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->safeBroadcast(new PaymentProcessed($order->fresh(), $receipt));

        return response()->json([
            'data' => [
                'receipt' => $receipt,
                'order' => $order->fresh('invoice.receipts'),
            ],
        ]);
    }

    /**
     * Today's settled payments for the cashier dashboard.
     */
    public function today(): JsonResponse
    {
        $receipts = SalesReceipt::with('invoice.order')
            ->whereDate('payment_date', now()->toDateString())
            ->orderByDesc('payment_date')
            ->get();

        $total = (float) $receipts->sum('net_amount');
        $cash = (float) $receipts->where('payment_method', 'cash')->sum('net_amount');

        return response()->json([
            'data' => $receipts,
            'summary' => [
                'total_net' => round($total, 2),
                'cash' => round($cash, 2),
                'count' => $receipts->count(),
            ],
        ]);
    }

    /**
     * Orders still awaiting payment that the cashier can process.
     */
    public function unpaidOrders(): JsonResponse
    {
        $orders = Order::where('payment_status', PaymentStatus::Unpaid->value)
            ->where('status', '!=', 'completed')
            ->with(['items.product'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $orders]);
    }
}
