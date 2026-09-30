<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Nilai kolom `users.role` lama yang diwakili tiap role, supaya kode lama
     * yang masih membaca `role` tetap konsisten dengan role baru.
     *
     * @var array<string, string>
     */
    private const LEGACY_ROLE_MAP = [
        'super-admin' => 'admin',
        'admin' => 'admin',
        'supervisor' => 'admin',
        'manager' => 'admin',
        'kasir' => 'cashier',
        'pelayan' => 'waiter',
        'dapur' => 'kitchen',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Daftar user dengan role, status, dan jejak login terakhir.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'role' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $query = User::query()->with('roles:id,name,slug');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if ($roleSlug = ($filters['role'] ?? null)) {
            $query->whereHas('roles', fn ($builder) => $builder->where('slug', $roleSlug));
        }

        $users = $query->orderBy('name')->paginate((int) ($filters['per_page'] ?? 15));

        return response()->json([
            'data' => collect($users->items())->map(fn (User $user): array => $this->present($user))->all(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse|Response
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9._-]+$/i', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->usernameMessages());

        $roles = $this->resolveRoles($data['role_ids'] ?? [], $request);

        $user = User::create([
            'name' => $data['name'],
            'username' => Str::lower($data['username']),
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->syncRoles($roles->pluck('id')->all());
        $user->forceFill(['role' => $this->legacyRoleFor($roles)])->save();

        $this->audit->log(
            $request,
            'create',
            'user',
            $user,
            "Membuat user {$user->email}.",
            null,
            ['roles' => $roles->pluck('name')->all(), 'is_active' => $user->is_active],
        );

        return response()->json(['data' => $this->present($user)], Response::HTTP_CREATED);
    }

    public function update(Request $request, User $user): JsonResponse|Response
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9._-]+$/i', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->usernameMessages());

        $actor = $request->user();
        $isSelf = $actor->id === $user->id;

        if ($isSelf && array_key_exists('is_active', $data) && ! $data['is_active']) {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan akun sendiri.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $actor->isSuperAdmin() && $user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Hanya Super Admin yang dapat mengubah user Super Admin.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $this->isLastActiveSuperAdmin($user)) {
            return response()->json([
                'message' => 'Tidak dapat menonaktifkan Super Admin terakhir yang aktif.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $old = $user->only(['name', 'username', 'email', 'is_active']);

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        if (array_key_exists('username', $data)) {
            $user->username = Str::lower($data['username']);
        }

        if (array_key_exists('email', $data)) {
            $user->email = $data['email'];
        }

        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'];
        }

        $user->save();

        if (array_key_exists('role_ids', $data)) {
            $roles = $this->resolveRoles($data['role_ids'], $request);
            $user->syncRoles($roles->pluck('id')->all());
            $user->forceFill(['role' => $this->legacyRoleFor($roles)])->save();
        }

        if ($user->wasChanged('is_active') && ! $user->is_active) {
            $user->tokens()->delete();
        }

        $this->audit->log(
            $request,
            'update',
            'user',
            $user,
            "Memperbarui user {$user->email}.",
            $old,
            ['is_active' => $user->is_active, 'roles' => $user->roles()->pluck('name')->all()],
        );

        return response()->json(['data' => $this->present($user)]);
    }

    /**
     * Reset password user. Password tidak pernah dikembalikan ke client.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->forceFill(['password' => $data['password']])->save();
        $user->tokens()->delete();

        $this->audit->log($request, 'reset_password', 'user', $user, "Reset password user {$user->email}.");

        return response()->json([
            'data' => $this->present($user),
            'message' => 'Password diperbarui. Semua sesi user ini dikeluarkan.',
        ]);
    }

    public function destroy(Request $request, User $user): Response|JsonResponse
    {
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun sendiri.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return response()->json([
                'message' => 'Hanya Super Admin yang dapat menghapus user Super Admin.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->isSuperAdmin() && $this->activeSuperAdminCount() <= 1) {
            return response()->json([
                'message' => 'Tidak dapat menghapus Super Admin terakhir.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $email = $user->email;
        $user->delete();

        $this->audit->log($request, 'delete', 'user', null, "Menghapus user {$email}.");

        return response()->noContent();
    }

    /**
     * Pastikan actor berhak memberikan role tersebut. Hanya Super Admin boleh
     * memberikan role Super Admin, sehingga tidak ada eskalasi hak akses lewat
     * halaman user management.
     *
     * @param  array<int, int|string>  $roleIds
     * @return Collection<int, Role>
     */
    private function resolveRoles(array $roleIds, Request $request): Collection
    {
        $roles = Role::query()->whereIn('id', $roleIds)->get();

        if ($roles->contains(fn (Role $role): bool => $role->isSuperAdmin()) && ! $request->user()->isSuperAdmin()) {
            abort(Response::HTTP_FORBIDDEN, 'Hanya Super Admin yang dapat memberikan role Super Admin.');
        }

        if ($roles->contains(fn (Role $role): bool => ! $role->is_active)) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Tidak dapat memberikan role yang sedang dinonaktifkan.');
        }

        return $roles;
    }

    private function legacyRoleFor(Collection $roles): string
    {
        $primary = $roles->first(fn (Role $role): bool => isset(self::LEGACY_ROLE_MAP[$role->slug]))
            ?? $roles->first();

        if (! $primary) {
            return 'cashier';
        }

        return self::LEGACY_ROLE_MAP[$primary->slug] ?? $primary->slug;
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        if (! $user->isSuperAdmin() || ! $user->is_active) {
            return false;
        }

        return $this->activeSuperAdminCount() <= 1;
    }

    private function activeSuperAdminCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($builder) => $builder->where('slug', 'super-admin'))
            ->count();
    }

    /**
     * Pesan validasi nama pengguna yang bisa dibaca kasir, bukan aturan regex
     * Laravel yang berbahasa Inggris.
     *
     * @return array<string, string>
     */
    private function usernameMessages(): array
    {
        return [
            'username.required' => 'Nama pengguna wajib diisi untuk login.',
            'username.min' => 'Nama pengguna minimal 3 karakter.',
            'username.regex' => 'Nama pengguna hanya boleh huruf, angka, titik, garis, dan garis bawah.',
            'username.unique' => 'Nama pengguna ini sudah dipakai user lain.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        $user->loadMissing('roles:id,name,slug');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
            'is_super_admin' => $user->isSuperAdmin(),
            'roles' => $user->roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->all(),
            'created_at' => $user->created_at?->toDateTimeString(),
            'last_login_at' => $user->last_login_at?->toDateTimeString(),
        ];
    }
}
