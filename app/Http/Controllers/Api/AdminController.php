<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashBankAccount;
use App\Models\PaymentMethod;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Current value of every cashier flag.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => Setting::cashierFlags()]);
    }

    /**
     * Persist the cashier flags toggled by the admin.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flags' => ['required', 'array'],
            'flags.*' => ['sometimes', 'boolean'],
        ]);

        foreach ($validated['flags'] as $key => $value) {
            if (! $this->isCashierFlag($key)) {
                continue;
            }

            Setting::query()->updateOrCreate(
                ['key' => 'pos.'.$key],
                [
                    'group' => 'pos',
                    'value' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
                    'is_active' => true,
                ]
            );
        }

        return response()->json(['data' => Setting::cashierFlags()]);
    }

    /**
     * Cash & Bank account list for the admin panel.
     */
    public function cashBankAccounts(): JsonResponse
    {
        return response()->json(['data' => CashBankAccount::orderBy('type')->orderBy('name')->get()]);
    }

    /**
     * Create a new Cash & Bank account.
     */
    public function storeCashBankAccount(Request $request): JsonResponse
    {
        $account = CashBankAccount::create($request->validate($this->accountRules()));

        return response()->json(['data' => $account], Response::HTTP_CREATED);
    }

    /**
     * Update a Cash & Bank account.
     */
    public function updateCashBankAccount(Request $request, CashBankAccount $cashBankAccount): JsonResponse
    {
        $cashBankAccount->update($request->validate($this->accountRules()));

        return response()->json(['data' => $cashBankAccount->fresh()]);
    }

    /**
     * Delete a Cash & Bank account.
     */
    public function destroyCashBankAccount(CashBankAccount $cashBankAccount): Response
    {
        $cashBankAccount->delete();

        return response()->noContent();
    }

    /**
     * Payment method list for the admin panel.
     */
    public function paymentMethods(): JsonResponse
    {
        return response()->json(['data' => PaymentMethod::orderBy('id')->get()]);
    }

    /**
     * Create a new payment method (kas / bank / qris, etc.).
     */
    public function storePaymentMethod(Request $request): JsonResponse
    {
        $method = PaymentMethod::create($request->validate($this->methodRules()));

        return response()->json(['data' => $method], Response::HTTP_CREATED);
    }

    /**
     * Update an existing payment method.
     */
    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update($request->validate($this->methodRules($paymentMethod->id)));

        return response()->json(['data' => $paymentMethod->fresh()]);
    }

    /**
     * Delete a payment method.
     */
    public function destroyPaymentMethod(PaymentMethod $paymentMethod): Response
    {
        $paymentMethod->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function methodRules(?int $ignoreId = null): array
    {
        return [
            'code' => ['required', 'string', 'max:25', Rule::unique('payment_methods', 'code')->ignore($ignoreId)],
            'type' => ['required', Rule::in(['kas', 'bank', 'qris'])],
            'name' => ['required', 'string', 'max:120'],
            'mdr_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function isCashierFlag(string $key): bool
    {
        return in_array($key, array_keys(Setting::cashierFlags()), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['kas', 'bank'])],
            'account_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
