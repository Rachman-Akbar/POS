<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function checkout(array $payload): Order
    {
        return DB::transaction(fn (): Order => $this->performCheckout($payload), 3);
    }

    private function performCheckout(array $payload): Order
    {
        $requestedItems = collect($payload['items'])
            ->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ])
            ->groupBy('product_id')
            ->map(fn ($rows): array => [
                'product_id' => (int) $rows->first()['product_id'],
                'quantity' => (int) $rows->sum('quantity'),
            ])
            ->values();

        $products = Product::query()
            ->whereIn('id', $requestedItems->pluck('product_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $orderItems = [];
        $subtotal = 0.0;

        foreach ($requestedItems as $index => $item) {
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => 'Produk tidak ditemukan.',
                ]);
            }

            if (! $product->is_active || $product->status !== 'published') {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => "{$product->name} sudah tidak tersedia.",
                ]);
            }

            if ($product->track_stock && (int) ($product->stock ?? 0) < $item['quantity']) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => "Stok {$product->name} tidak mencukupi.",
                ]);
            }

            $line = round((float) $product->price * $item['quantity'], 2);
            $subtotal = round($subtotal + $line, 2);

            $orderItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'product_type' => $product->type,
                'price' => (float) $product->price,
                'quantity' => $item['quantity'],
                'subtotal' => $line,
            ];
        }

        if ($orderItems === []) {
            throw ValidationException::withMessages(['items' => 'Data keranjang tidak valid.']);
        }

        $customerType = $payload['customer_type'];
        $email = mb_strtolower(trim((string) $payload['email']));
        $customerData = [
            'customer_type' => $customerType,
            'name' => trim((string) $payload['name']),
            'phone' => trim((string) $payload['phone']),
            'address' => trim((string) $payload['address']),
            'nik' => $this->nullableString($payload['nik'] ?? null),
            'npwp' => $this->nullableString($payload['npwp'] ?? null),
            'province' => $this->nullableString($payload['province'] ?? null),
            'city' => $this->nullableString($payload['city'] ?? null),
            'company_name' => $this->nullableString($payload['company_name'] ?? null),
            'postal_code' => $this->nullableString($payload['postal_code'] ?? null),
            'country' => $this->nullableString($payload['country'] ?? null),
        ];

        if ($customerType === 'individual') {
            $customerData = [
                ...$customerData,
                'nik' => null,
                'npwp' => null,
                'province' => null,
                'city' => null,
                'company_name' => null,
                'postal_code' => null,
                'country' => null,
            ];
        }

        $customer = Customer::query()->updateOrCreate(['email' => $email], $customerData);

        $order = Order::query()->create([
            'order_number' => $this->nextOrderNumber(),
            'customer_id' => $customer->id,
            'customer_type' => $customerType,
            'guest_email' => $email,
            'guest_name' => $customerData['name'],
            'guest_phone' => $customerData['phone'],
            'guest_address' => $customerData['address'],
            'guest_nik' => $customerData['nik'],
            'guest_npwp' => $customerData['npwp'],
            'guest_province' => $customerData['province'],
            'guest_city' => $customerData['city'],
            'guest_company_name' => $customerData['company_name'],
            'guest_postal_code' => $customerData['postal_code'],
            'guest_country' => $customerData['country'],
            'guest_notes' => $this->nullableString($payload['notes'] ?? null),
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
            'status' => 'pending',
            'payment_method' => $payload['payment_method'] ?? 'internal_billing',
            'payment_status' => 'unpaid',
        ]);

        $order->items()->createMany($orderItems);
        $order->statusHistories()->create([
            'user_id' => null,
            'from_status' => null,
            'to_status' => 'pending',
            'notes' => 'Order dibuat.',
        ]);

        foreach ($requestedItems as $item) {
            $product = $products->get($item['product_id']);

            if ($product?->track_stock) {
                $product->decrement('stock', $item['quantity']);
            }
        }

        return $order->load(['items', 'statusHistories.user']);
    }

    private function nextOrderNumber(): string
    {
        $prefix = 'INV-'.now()->format('Ymd').'-';
        $latest = Order::withTrashed()
            ->where('order_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('order_number')
            ->value('order_number');
        $sequence = $latest ? ((int) last(explode('-', $latest))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    private function nullableString(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }
}
