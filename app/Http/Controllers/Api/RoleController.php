<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Daftar kelompok hak akses beserta jumlah user dan permission.
     */
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->with('permissions:id,name')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_active' => $role->is_active,
                'is_system' => $role->is_system,
                'is_super_admin' => $role->isSuperAdmin(),
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'permissions' => $role->permissions->pluck('name')->all(),
            ]);

        return response()->json(['data' => $roles]);
    }

    /**
     * Buat kelompok hak akses baru.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::names())],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_system' => false,
        ]);

        $role->permissions()->sync($this->permissionIds($data['permissions'] ?? []));

        $this->audit->log($request, 'create', 'role', $role, "Membuat kelompok hak akses {$role->name}.");

        return response()->json(['data' => $this->present($role)], Response::HTTP_CREATED);
    }

    /**
     * Ubah nama, deskripsi, status, dan permission sebuah role.
     */
    public function update(Request $request, Role $role): JsonResponse|Response
    {
        if ($role->isSuperAdmin() && ! $request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Hanya Super Admin yang dapat mengubah role Super Admin.',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionCatalog::names())],
        ]);

        $old = $role->only(['name', 'description', 'is_active']);

        if (array_key_exists('name', $data)) {
            $role->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $role->description = $data['description'];
        }

        // Role Super Admin selalu aktif dan implicit punya semua permission.
        if (! $role->isSuperAdmin() && array_key_exists('is_active', $data)) {
            $role->is_active = (bool) $data['is_active'];
        }

        $role->save();

        if (array_key_exists('permissions', $data) && ! $role->isSuperAdmin()) {
            $role->permissions()->sync($this->permissionIds($data['permissions']));
        }

        $this->audit->log(
            $request,
            'update',
            'role',
            $role,
            "Memperbarui kelompok hak akses {$role->name}.",
            $old,
            $role->only(['name', 'description', 'is_active']),
        );

        return response()->json(['data' => $this->present($role)]);
    }

    /**
     * Hapus role. Role sistem dan role yang masih dipakai user tidak boleh dihapus.
     */
    public function destroy(Request $request, Role $role): Response|JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'message' => 'Role bawaan sistem tidak dapat dihapus. Nonaktifkan bila tidak dipakai.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $used = $role->users()->count();

        if ($used > 0) {
            return response()->json([
                'message' => "Role masih dipakai {$used} user. Pindahkan user terlebih dahulu.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = $role->name;
        $role->delete();

        $this->audit->log($request, 'delete', 'role', null, "Menghapus kelompok hak akses {$name}.");

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Role $role): array
    {
        $role->loadMissing('permissions:id,name')->loadCount('users');

        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'is_active' => $role->is_active,
            'is_system' => $role->is_system,
            'is_super_admin' => $role->isSuperAdmin(),
            'users_count' => $role->users_count,
            'permissions_count' => $role->permissions->count(),
            'permissions' => $role->permissions->pluck('name')->all(),
        ];
    }

    /**
     * Ubah daftar nama permission menjadi id yang siap disimpan di pivot.
     * Permission yang belum ter-seed diabaikan agar sinkronisasi tidak gagal.
     *
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    private function permissionIds(array $names): array
    {
        return Permission::query()
            ->whereIn('name', $names)
            ->pluck('id')
            ->all();
    }

    /**
     * Slug dari nama role, ditambah sufiks bila nama serupa sudah dipakai.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $suffix = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
