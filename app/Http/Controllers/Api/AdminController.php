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
     * Cash & Bank account list for the admin panel, with balance.
     */
    public function cashBankAccounts(): JsonResponse
    {
        $accounts = CashBankAccount::with('paymentMethod', 'receipts')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $accounts]);
    }

    /**
     * Create a new Cash & Bank account.
     */
    public function storeCashBankAccount(Request $request): JsonResponse
    {
        $account = CashBankAccount::create($this->validatedAccount($request));
        $this->syncDefault($account);

        return response()->json(['data' => $this->presentAccount($account)], Response::HTTP_CREATED);
    }

    /**
     * Update a Cash & Bank account.
     */
    public function updateCashBankAccount(Request $request, CashBankAccount $cashBankAccount): JsonResponse
    {
        $cashBankAccount->update($this->validatedAccount($request));
        $this->syncDefault($cashBankAccount);

        return response()->json(['data' => $this->presentAccount($cashBankAccount)]);
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
     * Mutation history (incoming payments) for a Cash & Bank account.
     */
    public function accountMutations(CashBankAccount $cashBankAccount): JsonResponse
    {
        $receipts = $cashBankAccount->receipts()
            ->with(['invoice.order'])
            ->orderByDesc('payment_date')
            ->get();

        return response()->json(['data' => $receipts]);
    }

    /**
     * Payment method list for the admin panel, with linked accounts.
     */
    public function paymentMethods(): JsonResponse
    {
        return response()->json(['data' => PaymentMethod::with('accounts')->orderBy('id')->get()]);
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
            'type' => ['required', Rule::in(['kas', 'bank', 'qris', 'ewallet'])],
            'name' => ['required', 'string', 'max:120'],
            'mdr_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['kas', 'bank'])],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'is_default' => ['sometimes', 'boolean'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAccount(Request $request): array
    {
        $data = $request->validate($this->accountRules());

        $data['payment_method_id'] = $data['payment_method_id'] ?? null;
        $data['is_default'] = (bool) ($data['is_default'] ?? false);

        return $data;
    }

    /**
     * Ensure only one default account per payment method.
     */
    private function syncDefault(CashBankAccount $account): void
    {
        if (! $account->is_default || ! $account->payment_method_id) {
            return;
        }

        CashBankAccount::query()
            ->where('payment_method_id', $account->payment_method_id)
            ->where('id', '!=', $account->id)
            ->update(['is_default' => false]);
    }

    /**
     * Reload the account with its balance and method for the response.
     */
    private function presentAccount(CashBankAccount $account): CashBankAccount
    {
        return $account->fresh(['paymentMethod', 'receipts']);
    }

    private function isCashierFlag(string $key): bool
    {
        return in_array($key, array_keys(Setting::cashierFlags()), true);
    }
}
