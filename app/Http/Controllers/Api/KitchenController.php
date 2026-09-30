<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemStatus;
use App\Enums\OrderStatus;
use App\Events\ItemStatusUpdated;
use App\Http\Controllers\Api\Concerns\SafeBroadcasts;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class KitchenController extends Controller
{
    use SafeBroadcasts;

    /**
     * Kitchen display — all order items still being produced, grouped by status.
     * Drafts are excluded: a draft is an unprocessed order that has not been
     * finalized, so it has not reached the kitchen. Item berstatus Draft juga
     * dikecualikan secara eksplisit supaya papan dapur tetap bersih walau
     * ada item draft yang somehow menempel pada pesanan non-draft.
     */
    public function index(): JsonResponse
    {
        $items = OrderItem::whereHas('order', fn ($query) => $query->where('status', OrderStatus::Pending->value))
            ->where('status', '!=', ItemStatus::Draft->value)
            ->with(['product', 'order'])
            ->orderBy('created_at')
            ->get();

        $grouped = $items->groupBy(fn (OrderItem $item) => $item->status);

        return response()->json([
            'data' => [
                'waiting' => $grouped->get(ItemStatus::Pending->value, collect())->values(),
                'cooking' => $grouped->get(ItemStatus::Cooking->value, collect())->values(),
                'sent' => $grouped->get(ItemStatus::Sent->value, collect())->values(),
                'done' => $grouped->get(ItemStatus::Done->value, collect())->values(),
            ],
        ]);
    }

    /**
     * Kitchen sets a single order item's status manually
     * (any allowed status can be selected, including going back).
     */
    public function updateItemStatus(Request $request, OrderItem $item): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ItemStatus::class)],
        ]);

        // Draft belum diproses, jadi itemnya tidak boleh diubah tahap produksi.
        if ($item->order?->status === OrderStatus::Draft->value) {
            return response()->json(
                ['message' => 'Item draft belum diproses dan tidak bisa diubah tahapnya.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        // Pesanan yang sudah dibatalkan admin keluar dari antrean dapur. Tanpa
        // penjaga ini, dapur masih bisa menandai "selesai" untuk barang yang
        // tidak pernah dibayar.
        if ($item->order?->isVoided()) {
            return response()->json(
                ['message' => 'Pesanan ini sudah dibatalkan, tidak bisa diproses dapur.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $previous = $item->status;
        $item->update(['status' => $data['status']]);

        $this->safeBroadcast(new ItemStatusUpdated($item->fresh(), $previous));

        return response()->json(['data' => $item->fresh(['product', 'order'])]);
    }
}
