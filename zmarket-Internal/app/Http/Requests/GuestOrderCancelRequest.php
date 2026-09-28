<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuestOrderCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email', $this->input('buyer_email', '')))),
            'cancel_reason' => $this->input('cancel_reason', $this->input('reason')),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'cancel_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
