<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminOrderUpdateRequest;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\GuestOrderCancelRequest;
use App\Http\Requests\GuestOrderLookupRequest;
use App\Http\Requests\GuestOrderUpdateRequest;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function checkout(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        return (new OrderResource($checkout->checkout($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function track(GuestOrderLookupRequest $request): AnonymousResourceCollection
    {
        $email = $this->normalizeEmail($request->validated('email'));

        $orders = Order::query()
            ->where(function ($query) use ($email): void {
                $query->where('guest_email', $email)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('email', $email));
            })
            ->with(['items', 'statusHistories.user'])
            ->latest()
            ->get();

        return OrderResource::collection($orders);
    }

    public function updateGuest(GuestOrderUpdateRequest $request, string $orderNumber): OrderResource
    {
        $owned = $this->findOwnedOrder($orderNumber, $request->validated('email_verification'));

        $order = DB::transaction(function () use ($request, $owned): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($owned->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Data buyer hanya dapat diubah ketika order masih pending.',
                ]);
            }

            $data = $request->validated();
            $customerType = $data['customer_type'];
            $email = $this->normalizeEmail($data['email']);
            $businessData = $customerType === 'business'
                ? [
                    'nik' => $this->nullableString($data['nik'] ?? null),
                    'npwp' => $this->nullableString($data['npwp'] ?? null),
                    'province' => $this->nullableString($data['province'] ?? null),
                    'city' => $this->nullableString($data['city'] ?? null),
                    'company_name' => $this->nullableString($data['company_name'] ?? null),
                    'postal_code' => $this->nullableString($data['postal_code'] ?? null),
                    'country' => $this->nullableString($data['country'] ?? null),
                ]
                : [
                    'nik' => null,
                    'npwp' => null,
                    'province' => null,
                    'city' => null,
                    'company_name' => null,
                    'postal_code' => null,
                    'country' => null,
                ];

            $customer = Customer::query()->updateOrCreate(
                ['email' => $email],
                [
                    'customer_type' => $customerType,
                    'name' => trim((string) $data['name']),
                    'phone' => trim((string) $data['phone']),
                    'address' => trim((string) $data['address']),
                    ...$businessData,
                ]
            );

            $locked->update([
                'customer_id' => $customer->id,
                'customer_type' => $customerType,
                'guest_email' => $email,
                'guest_name' => trim((string) $data['name']),
                'guest_phone' => trim((string) $data['phone']),
                'guest_address' => trim((string) $data['address']),
                'guest_nik' => $businessData['nik'],
                'guest_npwp' => $businessData['npwp'],
                'guest_province' => $businessData['province'],
                'guest_city' => $businessData['city'],
                'guest_company_name' => $businessData['company_name'],
                'guest_postal_code' => $businessData['postal_code'],
                'guest_country' => $businessData['country'],
                'guest_notes' => $this->nullableString($data['notes'] ?? null),
            ]);

            return $locked->fresh()->load(['items', 'statusHistories.user']);
        }, 3);

        return new OrderResource($order);
    }

    public function cancelGuest(
        GuestOrderCancelRequest $request,
        string $orderNumber,
        OrderService $orders
    ): OrderResource {
        $order = $this->findOwnedOrder($orderNumber, $request->validated('email'));

        return new OrderResource($orders->cancelGuest($order, $request->validated('cancel_reason')));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureOrderAccess($request->user());
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'processing', 'completed', 'cancelled'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'with_deleted' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $orders = Order::query()
            ->with('items')
            ->when((bool) ($validated['with_deleted'] ?? false), fn ($query) => $query->withTrashed())
            ->when(filled($validated['status'] ?? null), fn ($query) => $query->where('status', $validated['status']))
            ->when(filled($validated['date_from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $validated['date_from']))
            ->when(filled($validated['date_to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $validated['date_to']))
            ->when(filled($validated['search'] ?? null), function ($query) use ($validated): void {
                $search = trim((string) $validated['search']);

                $query->where(fn ($query) => $query
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%")
                    ->orWhere('guest_phone', 'like', "%{$search}%")
                    ->orWhere('guest_company_name', 'like', "%{$search}%")
                    ->orWhere('guest_npwp', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function store(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        return $this->checkout($request, $checkout);
    }

    public function show(int $order): OrderResource
    {
        $this->ensureOrderAccess(request()->user());
        $model = Order::withTrashed()->findOrFail($order);

        return new OrderResource($model->load(['items', 'statusHistories.user']));
    }

    public function update(
        AdminOrderUpdateRequest $request,
        Order $order,
        OrderService $orders
    ): OrderResource {
        $this->ensureOrderAccess($request->user());

        return new OrderResource($orders->updateByAdmin($order, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, Order $order, OrderService $orders): Response
    {
        $this->ensureOrderAccess($request->user());
        $orders->delete($order, $request->user());

        return response()->noContent();
    }

    private function findOwnedOrder(string $orderNumber, string $email): Order
    {
        $normalizedEmail = $this->normalizeEmail($email);

        return Order::query()
            ->where('order_number', mb_strtoupper(trim($orderNumber)))
            ->where(function ($query) use ($normalizedEmail): void {
                $query->where('guest_email', $normalizedEmail)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('email', $normalizedEmail));
            })
            ->with(['items', 'statusHistories.user'])
            ->firstOrFail();
    }

    private function ensureOrderAccess(?User $user): void
    {
        abort_unless($user && in_array($user->role, ['admin', 'seller'], true), 403);
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function nullableString(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }
}
