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

class KitchenController extends Controller
{
    use SafeBroadcasts;

    /**
     * Kitchen display — all order items still being produced, grouped by status.
     */
    public function index(): JsonResponse
    {
        $items = OrderItem::whereHas('order', fn ($query) => $query->where('status', '!=', OrderStatus::Completed->value))
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
            'status' => ['required', 'in:pending,cooking,sent,done'],
        ]);

        $previous = $item->status;
        $item->update(['status' => $data['status']]);

        $this->safeBroadcast(new ItemStatusUpdated($item->fresh(), $previous));

        return response()->json(['data' => $item->fresh(['product', 'order'])]);
    }
}
