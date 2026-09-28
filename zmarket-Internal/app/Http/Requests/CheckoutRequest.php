<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);

        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = json_last_error() === JSON_ERROR_NONE ? $decoded : $items;
        }

        if (is_array($items)) {
            $items = collect($items)
                ->filter(fn ($item): bool => is_array($item))
                ->map(function (array $item): array {
                    return [
                        'product_id' => $item['product_id'] ?? $item['id'] ?? null,
                        'quantity' => $item['quantity'] ?? $item['qty'] ?? 1,
                    ];
                })
                ->values()
                ->all();
        }

        $customerType = mb_strtolower(trim((string) $this->firstFilled([
            'customer_type',
            'buyer_type',
            'checkout_type',
        ])));

        $this->merge([
            'customer_type' => in_array($customerType, ['individual', 'business'], true)
                ? $customerType
                : 'individual',
            'name' => $this->firstFilled(['name', 'buyer_name', 'guest_name', 'contact_name']),
            'email' => mb_strtolower(trim((string) $this->firstFilled(['email', 'buyer_email', 'guest_email']))),
            'phone' => $this->firstFilled(['phone', 'buyer_phone', 'guest_phone', 'telephone']),
            'address' => $this->firstFilled(['address', 'buyer_address', 'guest_address']),
            'nik' => $this->firstFilled(['nik', 'buyer_nik', 'guest_nik']),
            'npwp' => $this->firstFilled(['npwp', 'buyer_npwp', 'guest_npwp']),
            'province' => $this->firstFilled(['province', 'buyer_province', 'guest_province']),
            'city' => $this->firstFilled(['city', 'buyer_city', 'guest_city']),
            'company_name' => $this->firstFilled(['company_name', 'company', 'buyer_company', 'guest_company_name']),
            'postal_code' => $this->firstFilled(['postal_code', 'zip_code', 'buyer_postal_code', 'guest_postal_code']),
            'country' => $this->firstFilled(['country', 'buyer_country', 'guest_country']),
            'notes' => $this->firstFilled(['notes', 'buyer_notes', 'guest_notes']),
            'payment_method' => $this->firstFilled(['payment_method']) ?: 'internal_billing',
            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_type' => ['required', Rule::in(['individual', 'business'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'address' => ['required', 'string', 'max:3000'],
            'nik' => ['nullable', 'required_if:customer_type,business', 'string', 'regex:/^[0-9]{16}$/'],
            'npwp' => ['nullable', 'required_if:customer_type,business', 'string', 'max:32', 'regex:/^[0-9.\-]+$/'],
            'province' => ['nullable', 'required_if:customer_type,business', 'string', 'max:120'],
            'city' => ['nullable', 'required_if:customer_type,business', 'string', 'max:120'],
            'company_name' => ['nullable', 'required_if:customer_type,business', 'string', 'max:180'],
            'postal_code' => ['nullable', 'required_if:customer_type,business', 'string', 'max:16', 'regex:/^[0-9A-Za-z\-\s]{3,16}$/'],
            'country' => ['nullable', 'required_if:customer_type,business', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'payment_method' => ['sometimes', Rule::in(['cod', 'bank_transfer', 'internal_billing'])],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    private function firstFilled(array $keys): mixed
    {
        foreach ($keys as $key) {
            if ($this->filled($key)) {
                return $this->input($key);
            }
        }

        return null;
    }
}
