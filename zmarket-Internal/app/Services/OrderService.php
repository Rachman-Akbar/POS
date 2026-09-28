<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function updateByAdmin(Order $order, array $data, User $actor): Order
    {
        return DB::transaction(function () use ($order, $data, $actor): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->load('items');

            $this->fillWhenPresent($locked, $data, 'customer_type');
            $this->fillWhenPresent($locked, $data, 'guest_email', fn ($value): string => mb_strtolower(trim((string) $value)));
            $this->fillWhenPresent($locked, $data, 'guest_name', fn ($value): string => trim((string) $value));
            $this->fillWhenPresent($locked, $data, 'guest_phone', fn ($value): string => trim((string) $value));
            $this->fillWhenPresent($locked, $data, 'guest_address', fn ($value): string => trim((string) $value));
            $this->fillWhenPresent($locked, $data, 'guest_nik', fn ($value): ?string => filled($value) ? trim((string) $value) : null);
            $this->fillWhenPresent($locked, $data, 'guest_npwp', fn ($value): ?string => filled($value) ? trim((string) $value) : null);
            $this->fillWhenPresent($locked, $data, 'guest_province', fn ($value): ?string => filled($value) ? trim((string) $value) : null);
            $this->fillWhenPresent($locked, $data, 'guest_city', fn ($value): ?string => filled($value) ? trim((string) $value) : null);
            $this->fillWhenPresent($locked, $data, 'guest_company_name', fn ($value): ?string => filled($value) ? trim((string) $value) : null);
            $this->fillWhenPresent($locked, $data, 'guest_postal_code', fn ($value): ?string => filled($value) ? trim((string) $value) : null);
            $this->fillWhenPresent($locked, $data, 'guest_country', fn ($value): ?string => filled($value) ? trim((string) $value) : null);
            $this->fillWhenPresent($locked, $data, 'guest_notes', fn ($value): ?string => filled($value) ? trim((string) $value) : null);

            if ($locked->customer_type === 'individual') {
                $locked->guest_nik = null;
                $locked->guest_npwp = null;
                $locked->guest_province = null;
                $locked->guest_city = null;
                $locked->guest_company_name = null;
                $locked->guest_postal_code = null;
                $locked->guest_country = null;
            }
            $this->fillWhenPresent($locked, $data, 'payment_method');
            $this->fillWhenPresent($locked, $data, 'payment_status');
            $this->fillWhenPresent($locked, $data, 'admin_notes', fn ($value): ?string => filled($value) ? trim((string) $value) : null);

            $this->syncCustomer($locked);

            $this->applyTransition(
                $locked,
                $data['status'] ?? $locked->status,
                $actor,
                $data['cancel_reason'] ?? null,
                $data['admin_notes'] ?? null
            );

            $locked->save();

            return $locked->fresh()->load(['items', 'statusHistories.user']);
        }, 3);
    }

    public function transition(
        Order $order,
        string $nextStatus,
        ?User $actor = null,
        ?string $reason = null,
        ?string $notes = null
    ): Order {
        return DB::transaction(function () use ($order, $nextStatus, $actor, $reason, $notes): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->load('items');
            $this->applyTransition($locked, $nextStatus, $actor, $reason, $notes);
            $locked->save();

            return $locked->fresh()->load(['items', 'statusHistories.user']);
        }, 3);
    }

    public function cancelGuest(Order $order, ?string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->load('items');

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Order hanya dapat dibatalkan ketika status masih pending.',
                ]);
            }

            $this->applyTransition($locked, 'cancelled', null, $reason, 'Dibatalkan oleh guest.');
            $locked->save();

            return $locked->fresh()->load(['items', 'statusHistories.user']);
        }, 3);
    }

    public function delete(Order $order, ?User $actor = null): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->load('items');

            if (in_array($locked->status, ['pending', 'confirmed', 'processing'], true)) {
                $this->applyTransition(
                    $locked,
                    'cancelled',
                    $actor,
                    'Order dihapus oleh admin.',
                    'Stok dikembalikan sebelum soft delete.'
                );
                $locked->save();
            }

            $locked->delete();
        }, 3);
    }

    private function syncCustomer(Order $order): void
    {
        $email = mb_strtolower(trim((string) $order->guest_email));

        if ($email === '') {
            return;
        }

        $customer = Customer::query()->updateOrCreate(
            ['email' => $email],
            [
                'customer_type' => $order->customer_type,
                'name' => trim((string) $order->guest_name),
                'phone' => trim((string) $order->guest_phone),
                'address' => trim((string) $order->guest_address),
                'nik' => $order->guest_nik,
                'npwp' => $order->guest_npwp,
                'province' => $order->guest_province,
                'city' => $order->guest_city,
                'company_name' => $order->guest_company_name,
                'postal_code' => $order->guest_postal_code,
                'country' => $order->guest_country,
            ]
        );

        $order->customer_id = $customer->id;
        $order->guest_email = $email;
    }

    private function applyTransition(
        Order $order,
        string $nextStatus,
        ?User $actor,
        ?string $reason,
        ?string $notes
    ): void {
        $fromStatus = $order->status;

        if ($fromStatus !== 'cancelled' && $nextStatus === 'cancelled') {
            $this->restoreStock($order);
            $order->cancelled_at = now();
            $order->cancel_reason = filled($reason) ? trim($reason) : null;
        }

        if ($fromStatus === 'cancelled' && $nextStatus !== 'cancelled') {
            $this->reserveStock($order);
            $order->cancelled_at = null;
            $order->cancel_reason = null;
        }

        if ($fromStatus === 'cancelled' && $nextStatus === 'cancelled' && $reason !== null) {
            $order->cancel_reason = filled($reason) ? trim($reason) : null;
        }

        $order->status = $nextStatus;

        if ($fromStatus !== $nextStatus) {
            $order->statusHistories()->create([
                'user_id' => $actor?->id,
                'from_status' => $fromStatus,
                'to_status' => $nextStatus,
                'notes' => filled($notes) ? trim($notes) : (filled($reason) ? trim($reason) : null),
            ]);
        }
    }

    private function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = Product::query()->lockForUpdate()->find($item->product_id);

            if ($product?->track_stock) {
                $product->increment('stock', $item->quantity);
            }
        }
    }

    private function reserveStock(Order $order): void
    {
        $products = [];

        foreach ($order->items as $item) {
            $product = Product::query()->lockForUpdate()->find($item->product_id);

            if (! $product || ! $product->is_active || $product->status !== 'published') {
                throw ValidationException::withMessages([
                    'status' => "Produk {$item->product_name} sudah tidak tersedia.",
                ]);
            }

            if ($product->track_stock && (int) ($product->stock ?? 0) < $item->quantity) {
                throw ValidationException::withMessages([
                    'status' => "Stok {$item->product_name} tidak mencukupi untuk membuka kembali order.",
                ]);
            }

            $products[$item->id] = $product;
        }

        foreach ($order->items as $item) {
            $product = $products[$item->id];

            if ($product->track_stock) {
                $product->decrement('stock', $item->quantity);
            }
        }
    }

    private function fillWhenPresent(Order $order, array $data, string $key, ?callable $transform = null): void
    {
        if (! array_key_exists($key, $data)) {
            return;
        }

        $order->{$key} = $transform ? $transform($data[$key]) : $data[$key];
    }
}
