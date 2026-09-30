<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Field yang hanya relevan untuk badan usaha.
     */
    private const BUSINESS_FIELDS = ['company_name', 'nik', 'npwp', 'province', 'city', 'postal_code', 'country'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Master pelanggan: perorangan maupun badan usaha.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'customer_type' => ['nullable', Rule::in(CustomerType::values())],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $customers = Customer::query()
            ->withCount('orders')
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($validated['customer_type'] ?? null, fn ($query, string $type) => $query->where('customer_type', $type))
            ->orderBy('name')
            ->paginate((int) ($validated['per_page'] ?? 20))
            ->withQueryString();

        return response()->json([
            'data' => $customers->items(),
            'meta' => [
                'total' => $customers->total(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
            ],
        ]);
    }

    /**
     * Pencarian pelanggan untuk layar kasir.
     *
     * Berbeda dengan `index` (master data admin), endpoint ini Ringkas: hanya
     * pelanggan aktif, tanpa pagination meta, dan tanpa `orders_count` yang
     * tidak dibutuhkan kasir.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $customers = Customer::query()
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit((int) ($validated['limit'] ?? 10))
            ->get(['id', 'customer_type', 'name', 'company_name', 'email', 'phone', 'nik', 'npwp', 'address', 'province', 'city', 'postal_code', 'country']);

        return response()->json(['data' => $customers]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = Customer::create($this->validated($request));

        return response()->json(['data' => $customer], Response::HTTP_CREATED);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $customer->update($this->validated($request, $customer));

        return response()->json(['data' => $customer->fresh()]);
    }

    /**
     * Pelanggan yang sudah punya transaksi tidak boleh dihapus.
     */
    public function destroy(Request $request, Customer $customer): Response|JsonResponse
    {
        if ($customer->orders()->exists()) {
            return response()->json([
                'message' => 'Pelanggan sudah punya transaksi. Nonaktifkan saja agar tidak dipakai lagi.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = $customer->name;
        $customer->delete();

        $this->audit->log($request, 'delete', 'customer', null, "Menghapus pelanggan {$name}.");

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Customer $customer = null): array
    {
        $this->prepareForValidation($request);

        $type = CustomerType::tryFrom((string) $request->input('customer_type')) ?? CustomerType::Individual;

        $data = $request->validate([
            'customer_type' => ['required', Rule::in(CustomerType::values())],
            'name' => ['required', 'string', 'max:120'],
            // Satu-satunya field wajib adalah nama. Form pelanggan di kasir
            // sengaja tidak memaksa apa pun yang lain: banyak pelanggan dine-in
            // hanya diketahui namanya, dan data yang belum ada lebih mudah
            // dilengkapi belakangan daripada menghambat transaksi.
            'email' => ['nullable', 'string', 'email:rfc', 'max:190', Rule::unique('customers', 'email')->ignore($customer?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]+$/'],
            'address' => ['nullable', 'string', 'max:2000'],
            'company_name' => ['nullable', 'string', 'max:180'],
            'nik' => ['nullable', 'string', 'regex:/^[0-9]{16}$/'],
            'npwp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9.\-]+$/'],
            'province' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:16', 'regex:/^[0-9A-Za-z\-\s]{3,16}$/'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.regex' => 'Nomor HP hanya boleh berisi angka, spasi, dan tanda + ( ) - .',
            'nik.regex' => 'NIK harus 16 digit angka.',
            'npwp.regex' => 'NPWP hanya boleh berisi angka, titik, dan tanda hubung.',
        ]);

        $data['customer_type'] = $type->value;
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) ($customer?->is_active ?? true);

        // Field badan usaha dikosongkan untuk perorangan; alamat dan catatan tetap dipakai semua tipe.
        foreach ([...self::BUSINESS_FIELDS, 'address', 'notes'] as $field) {
            $value = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
            $data[$field] = $type === CustomerType::Individual && in_array($field, self::BUSINESS_FIELDS, true) ? null : $value;
        }

        return $data;
    }

    /**
     * Normalkan nilai sebelum validasi agar email unik tidak lolos karena beda kapital.
     */
    private function prepareForValidation(Request $request): void
    {
        $request->merge([
            'customer_type' => (CustomerType::tryFrom((string) $request->input('customer_type')) ?? CustomerType::Individual)->value,
            'email' => filled($request->input('email')) ? Str::lower(trim((string) $request->input('email'))) : null,
            'name' => filled($request->input('name')) ? trim((string) $request->input('name')) : null,
        ]);
    }
}
