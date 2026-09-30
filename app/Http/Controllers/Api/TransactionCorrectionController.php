<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Api\Concerns\SafeBroadcasts;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SalesReceipt;
use App\Services\AuditLogger;
use App\Services\SalesService;
use App\Services\TransactionScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Koreksi transaksi oleh admin.
 *
 * Kasir salah input tidak boleh dihapus diam-diam, jadi setiap koreksi di sini
 * wajib menyebut alasan dan langsung tercatat di audit log. Permission-nya
 * terpisah: `transaction.void` untuk pembatalan total, `transaction.refund`
 * untuk pengembalian uang. Role kasir tidak diberi keduanya.
 */
class TransactionCorrectionController extends Controller
{
    use SafeBroadcasts;

    public function __construct(
        private readonly SalesService $salesService,
        private readonly TransactionScope $scope,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Pembatalan total: kembalikan stok, batalkan faktur, kembalikan seluruh
     * pembayaran yang sudah masuk, dan reversal jurnal.
     */
    public function void(Request $request, Order $order): JsonResponse
    {
        $this->guardScope($request, $order);

        $data = $this->validateReason($request);

        $before = $this->snapshot($order);

        try {
            $result = $this->salesService->voidOrder($order, $request->user(), $data['reason']);
        } catch (\DomainException $e) {
            return $this->rejected($e->getMessage());
        }

        $this->audit->log(
            $request,
            'void',
            'transaction',
            $result['order'],
            sprintf(
                'Membatalkan transaksi %s. Dikembalikan ke kas: %s. Alasan: %s',
                $result['order']->order_number,
                $this->rupiah($result['refunded']),
                $data['reason'],
            ),
            $before,
            $this->snapshot($result['order']),
        );

        $this->safeBroadcast(new OrderStatusUpdated($result['order'], $before['status']));

        return response()->json([
            'message' => 'Transaksi dibatalkan.',
            'data' => [
                'order' => $result['order'],
                'refunded' => $result['refunded'],
            ],
        ]);
    }

    /**
     * Retur: kembalikan sebagian atau seluruh pembayaran pada satu penerimaan.
     */
    public function refund(Request $request, Order $order, SalesReceipt $receipt): JsonResponse
    {
        $this->guardScope($request, $order);

        $data = $this->validateReason($request, [
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $before = $this->snapshot($order);

        try {
            $result = $this->salesService->refundPayment(
                $order,
                $receipt,
                isset($data['amount']) ? (float) $data['amount'] : null,
                $request->user(),
                $data['reason'],
            );
        } catch (\DomainException $e) {
            return $this->rejected($e->getMessage());
        }

        $this->audit->log(
            $request,
            'refund',
            'transaction',
            $result['order'],
            sprintf(
                'Retur %s pada transaksi %s. Alasan: %s',
                $this->rupiah($result['amount']),
                $result['order']->order_number,
                $data['reason'],
            ),
            $before,
            $this->snapshot($result['order']),
        );

        return response()->json([
            'message' => 'Retur berhasil dicatat.',
            'data' => [
                'order' => $result['order'],
                'receipt' => $result['receipt'],
                'amount' => $result['amount'],
            ],
        ]);
    }

    /**
     * Permission koreksi tidak otomatis memberi akses ke semua transaksi.
     * Admin area yang memegang `transaction.void` karenaugas di kasir tetap
     * tidak boleh membatalkan penjualan shift orang lain tanpa
     * `transaction.view.all`.
     */
    private function guardScope(Request $request, Order $order): void
    {
        if ($this->scope->canCorrect($request->user(), $order)) {
            return;
        }

        abort(
            Response::HTTP_FORBIDDEN,
            'Transaksi ini di luar jangkauan Anda. Hubungi supervisor atau admin.',
        );
    }

    /**
     * Alasan wajib diisi. Tanpa alasan, koreksi jadi tidak bisa ditelusuri
     * saat terjadi selisih kas di akhir shift.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validateReason(Request $request, array $extra = []): array
    {
        return $request->validate($extra + [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan koreksi wajib diisi.',
            'reason.min' => 'Alasan koreksi minimal 5 karakter.',
        ]);
    }

    /**
     * Nilai yang berubah karena koreksi, disimpan apa adanya sebagai jejak
     * "apa yang diubah".
     *
     * @return array<string, mixed>
     */
    private function snapshot(Order $order): array
    {
        $order->loadMissing('invoice.receipts');

        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => (float) $order->total_amount,
            'paid' => (float) $order->paid_amount,
            'outstanding' => $order->outstandingAmount(),
            'refunded' => round((float) $order->invoice?->receipts()->sum('refund_amount'), 2),
            'void_reason' => $order->void_reason,
        ];
    }

    private function rejected(string $message): JsonResponse
    {
        return response()->json(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function rupiah(float $value): string
    {
        return 'Rp. '.number_format($value, 0, ',', '.');
    }
}
