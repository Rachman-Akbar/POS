<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashBankAccount;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Current value of every cashier flag, plus the app appearance.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Setting::cashierFlags(),
            'appearance' => Setting::appearance(),
        ]);
    }

    /**
     * Persist the cashier flags toggled by the admin.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'flags' => ['sometimes', 'array'],
            'flags.*' => ['sometimes', 'boolean'],
            'appearance' => ['sometimes', 'array'],
            'appearance.mode' => ['sometimes', Rule::in(Setting::THEME_MODES)],
            'appearance.accent' => ['sometimes', Rule::in(Setting::THEME_ACCENTS)],
        ]);

        foreach ($validated['flags'] ?? [] as $key => $value) {
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

        foreach (['mode' => 'app.theme_mode', 'accent' => 'app.theme_accent'] as $field => $settingKey) {
            $value = $validated['appearance'][$field] ?? null;

            if ($value === null) {
                continue;
            }

            Setting::query()->updateOrCreate(
                ['key' => $settingKey],
                [
                    'group' => 'app',
                    'value' => $value,
                    'label' => $field === 'mode' ? 'Mode Tampilan' : 'Warna Aksen',
                    'is_active' => true,
                ]
            );
        }

        return response()->json([
            'data' => Setting::cashierFlags(),
            'appearance' => Setting::appearance(),
        ]);
    }

    /**
     * Every product category with its product count, in the admin display order.
     */
    public function categories(): JsonResponse
    {
        $counts = Product::query()
            ->selectRaw("COALESCE(category, 'Lainnya') as name, COUNT(*) as total")
            ->groupByRaw("COALESCE(category, 'Lainnya')")
            ->pluck('total', 'name')
            ->all();

        $order = Setting::categoryOrder();
        $position = fn (string $name): int => ($index = array_search($name, $order, true)) === false ? PHP_INT_MAX : $index;

        $sorted = collect(array_keys($counts))
            ->sort(fn (string $a, string $b): int => $position($a) <=> $position($b) ?: strnatcasecmp($a, $b))
            ->values();

        return response()->json([
            'data' => $sorted->map(fn (string $name): array => [
                'name' => $name,
                'total' => (int) $counts[$name],
            ])->all(),
        ]);
    }

    /**
     * Store the display order of the product categories.
     */
    public function updateCategoryOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'string', 'distinct', 'max:100'],
        ]);

        $order = array_values($validated['order']);
        $known = $this->categoryNames();

        if (array_diff($order, $known) || array_diff($known, $order)) {
            return response()->json([
                'message' => 'Urutan kategori harus memuat seluruh kategori yang ada.',
                'errors' => ['order' => ['Urutan kategori tidak lengkap atau tidak dikenal.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        Setting::saveCategoryOrder($order);

        return $this->categories();
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

    /**
     * Every category name currently used by a product.
     *
     * @return array<int, string>
     */
    private function categoryNames(): array
    {
        return Product::query()
            ->selectRaw('COALESCE(category, ?) as name', ['Lainnya'])
            ->distinct()
            ->pluck('name')
            ->all();
    }
}
