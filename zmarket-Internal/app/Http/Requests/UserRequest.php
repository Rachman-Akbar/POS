<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('email')) {
            $merge['email'] = mb_strtolower(trim((string) $this->input('email')));
        }

        if ($this->has('is_active')) {
            $merge['is_active'] = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : null;
        $presence = $userId ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:255'],
            'email' => [$presence, 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$userId ? 'sometimes' : 'required', 'nullable', 'string', 'min:8', 'max:255'],
            'role' => [$presence, Rule::in(['admin', 'seller'])],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'department' => ['sometimes', 'nullable', 'string', 'max:150'],
            'is_active' => [$presence, 'boolean'],
        ];
    }
}
