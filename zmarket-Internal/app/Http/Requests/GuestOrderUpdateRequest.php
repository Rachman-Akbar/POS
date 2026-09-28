<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuestOrderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $customerType = mb_strtolower(trim((string) $this->input(
            'customer_type',
            $this->input('buyer_type', 'individual')
        )));

        $this->merge([
            'email_verification' => mb_strtolower(trim((string) $this->input('email_verification', $this->input('verification_email', '')))),
            'customer_type' => in_array($customerType, ['individual', 'business'], true)
                ? $customerType
                : 'individual',
            'email' => mb_strtolower(trim((string) $this->input('email', $this->input('buyer_email', '')))),
            'name' => $this->input('name', $this->input('buyer_name')),
            'phone' => $this->input('phone', $this->input('buyer_phone')),
            'address' => $this->input('address', $this->input('buyer_address')),
            'nik' => $this->input('nik', $this->input('buyer_nik')),
            'npwp' => $this->input('npwp', $this->input('buyer_npwp')),
            'province' => $this->input('province', $this->input('buyer_province')),
            'city' => $this->input('city', $this->input('buyer_city')),
            'company_name' => $this->input('company_name', $this->input('buyer_company')),
            'postal_code' => $this->input('postal_code', $this->input('buyer_postal_code')),
            'country' => $this->input('country', $this->input('buyer_country')),
            'notes' => $this->input('notes', $this->input('buyer_notes')),
        ]);
    }

    public function rules(): array
    {
        return [
            'email_verification' => ['required', 'string', 'email:rfc', 'max:190'],
            'customer_type' => ['required', Rule::in(['individual', 'business'])],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'name' => ['required', 'string', 'max:120'],
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
        ];
    }
}
