<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminOrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'seller'], true);
    }

    protected function prepareForValidation(): void
    {
        $aliases = [
            'customer_type' => ['buyer_type'],
            'guest_email' => ['buyer_email', 'email'],
            'guest_name' => ['buyer_name', 'name'],
            'guest_phone' => ['buyer_phone', 'phone'],
            'guest_address' => ['buyer_address', 'address'],
            'guest_nik' => ['buyer_nik', 'nik'],
            'guest_npwp' => ['buyer_npwp', 'npwp'],
            'guest_province' => ['buyer_province', 'province'],
            'guest_city' => ['buyer_city', 'city'],
            'guest_company_name' => ['buyer_company', 'company_name'],
            'guest_postal_code' => ['buyer_postal_code', 'postal_code'],
            'guest_country' => ['buyer_country', 'country'],
            'guest_notes' => ['buyer_notes', 'notes'],
        ];

        $merge = [];

        foreach ($aliases as $target => $sources) {
            if ($this->has($target)) {
                continue;
            }

            foreach ($sources as $source) {
                if ($this->has($source)) {
                    $merge[$target] = $this->input($source);
                    break;
                }
            }
        }

        if (array_key_exists('guest_email', $merge) || $this->has('guest_email')) {
            $merge['guest_email'] = mb_strtolower(trim((string) ($merge['guest_email'] ?? $this->input('guest_email'))));
        }

        if (array_key_exists('customer_type', $merge) || $this->has('customer_type')) {
            $merge['customer_type'] = mb_strtolower(trim((string) ($merge['customer_type'] ?? $this->input('customer_type'))));
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'customer_type' => ['sometimes', 'required', Rule::in(['individual', 'business'])],
            'guest_email' => ['sometimes', 'required', 'string', 'email:rfc', 'max:190'],
            'guest_name' => ['sometimes', 'required', 'string', 'max:120'],
            'guest_phone' => ['sometimes', 'required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'guest_address' => ['sometimes', 'required', 'string', 'max:3000'],
            'guest_nik' => ['sometimes', 'nullable', 'required_if:customer_type,business', 'string', 'regex:/^[0-9]{16}$/'],
            'guest_npwp' => ['sometimes', 'nullable', 'required_if:customer_type,business', 'string', 'max:32', 'regex:/^[0-9.\-]+$/'],
            'guest_province' => ['sometimes', 'nullable', 'required_if:customer_type,business', 'string', 'max:120'],
            'guest_city' => ['sometimes', 'nullable', 'required_if:customer_type,business', 'string', 'max:120'],
            'guest_company_name' => ['sometimes', 'nullable', 'required_if:customer_type,business', 'string', 'max:180'],
            'guest_postal_code' => ['sometimes', 'nullable', 'required_if:customer_type,business', 'string', 'max:16', 'regex:/^[0-9A-Za-z\-\s]{3,16}$/'],
            'guest_country' => ['sometimes', 'nullable', 'required_if:customer_type,business', 'string', 'max:100'],
            'guest_notes' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'status' => ['sometimes', 'required', Rule::in(['pending', 'confirmed', 'processing', 'completed', 'cancelled'])],
            'payment_status' => ['sometimes', 'required', Rule::in(['unpaid', 'paid'])],
            'payment_method' => ['sometimes', 'required', Rule::in(['cod', 'bank_transfer', 'internal_billing'])],
            'cancel_reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
