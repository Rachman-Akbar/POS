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
use Illuminate\Http\Response;

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
     * Kitchen moves a single order item along the production flow.
     */
    public function updateItemStatus(Request $request, OrderItem $item): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:cooking,sent,done'],
        ]);

        $current = ItemStatus::from($item->status);
        $next = ItemStatus::from($data['status']);

        if ($current === ItemStatus::Done || $next !== $current->next()) {
            return response()->json(['message' => 'Transisi status tidak valid untuk item ini.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $previous = $item->status;
        $item->update(['status' => $next->value]);

        $this->safeBroadcast(new ItemStatusUpdated($item->fresh(), $previous));

        return response()->json(['data' => $item->fresh(['product', 'order'])]);
    }
}
