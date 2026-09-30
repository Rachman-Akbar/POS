<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentProcessed;
use App\Http\Controllers\Api\Concerns\SafeBroadcasts;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\SalesInvoice;
use App\Models\SalesReceipt;
use App\Services\SalesService;
use App\Services\TransactionScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    use SafeBroadcasts;

    public function __construct(
        private readonly SalesService $salesService,
        private readonly TransactionScope $scope,
    ) {}

    /**
     * Cashier view — outstanding Pay Later invoices awaiting settlement.
     *
     * Pending tidak pernah disembunyikan: setiap kasir di kasir yang sama harus
     * bisa menerima pembayaran untuk order yang dibuat pelayan atau kasir lain.
     */
    public function pending(): JsonResponse
    {
        $invoices = SalesInvoice::where('status', InvoiceStatus::Issued->value)
            ->with(['receipts', 'order' => fn ($query) => $query->with(['items.product', 'customer'])])
            ->orderByDesc('issued_at')
            ->get();

        return response()->json(['data' => $invoices]);
    }

    /**
     * Cashier view — every invoice transaction, not just the ones still
     * awaiting settlement. Feeds the "Pesanan" table which pairs the amount
     * paid against the remaining balance and the kitchen process stage.
     *
     * Transaksi yang sudah lunas milik kasir lain disembunyikan; user dengan
     * `transaction.view.all` (admin, supervisor) tetap melihat semuanya.
     */
    public function invoices(Request $request): JsonResponse
    {
        $invoices = $this->scope->invoices(
            SalesInvoice::query()->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::Paid->value]),
            $request->user()
        )
            ->with(['receipts', 'order' => fn ($query) => $query->with(['items.product', 'customer'])])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
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
            'payment_account_id' => [
                'nullable',
                'integer',
                Rule::exists('cash_bank_accounts', 'id')->where(
                    fn (Builder $query) => $query
                        ->where('is_active', true)
                        ->whereIn('payment_method_id', PaymentMethod::query()
                            ->where('code', $request->input('payment_method'))
                            ->select('id'))
                ),
            ],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ], [
            'payment_account_id.exists' => 'Rekening tidak sesuai dengan metode pembayaran yang dipilih.',
        ]);

        try {
            $method = PaymentMethod::where('code', $data['payment_method'])->where('is_active', true)->first()
                ?? throw new \DomainException('Metode pembayaran tidak ditemukan.');
            $settlement = $this->salesService->settlePayment(
                $order,
                $method,
                isset($data['amount']) ? (float) $data['amount'] : null,
                isset($data['payment_account_id']) ? (int) $data['payment_account_id'] : null
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $receipt = $settlement['receipt'];

        $this->safeBroadcast(new PaymentProcessed($order->fresh(), $receipt));

        return response()->json([
            'data' => [
                'receipt' => $receipt,
                'applied' => $settlement['applied'],
                'change' => $settlement['change'],
                'order' => $order->fresh('invoice.receipts'),
            ],
        ]);
    }

    /**
     * Today's settled payments for the cashier dashboard.
     *
     * Ringkasan kas harian hanya menghitung penerimaan milik user yang sedang
     * login, supaya angka penjualan di layar kasir tidak bercampur dengan
     * shift kasir lain. User dengan `transaction.view.all` melihat semuanya.
     */
    public function today(Request $request): JsonResponse
    {
        $receipts = $this->scope->receipts(
            SalesReceipt::with(['account', 'invoice.order'])
                ->whereDate('payment_date', now()->toDateString()),
            $request->user()
        )
            ->orderByDesc('payment_date')
            ->get();

        $total = (float) $receipts->sum('net_amount');
        $cash = (float) $receipts->where('payment_method', 'cash')->sum('net_amount');
        $refunded = (float) $receipts->sum('refund_amount');

        return response()->json([
            'data' => $receipts,
            'summary' => [
                'total_net' => round($total, 2),
                'cash' => round($cash, 2),
                'refunded' => round($refunded, 2),
                'count' => $receipts->count(),
            ],
        ]);
    }

    /**
     * Orders still awaiting payment that the cashier can process.
     */
    public function unpaidOrders(): JsonResponse
    {
        // Drafts are excluded: an unprocessed draft has no invoice to settle,
        // so it is not an "unpaid order" in the settlement sense.
        $orders = Order::whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::Partial->value])
            ->whereIn('status', [OrderStatus::Pending->value, OrderStatus::Completed->value])
            ->with(['items.product', 'customer'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $orders]);
    }
}
