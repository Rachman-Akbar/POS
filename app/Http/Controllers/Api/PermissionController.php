<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    /**
     * Seluruh permission dikelompokkan per modul, lengkap dengan label modul,
     * agar UI kelompok hak akses tidak perlu tahu daftar modul secara manual.
     */
    public function index(): JsonResponse
    {
        $known = Permission::query()
            ->orderBy('module')
            ->orderBy('id')
            ->get()
            ->keyBy('name');

        $modules = [];

        foreach (PermissionCatalog::definitions() as $module => $permissions) {
            $items = [];

            foreach ($permissions as $name => $label) {
                $permission = $known->get($name);

                $items[] = [
                    'id' => $permission?->id,
                    'name' => $name,
                    'label' => $label,
                    'description' => $permission?->description,
                ];
            }

            $modules[] = [
                'key' => $module,
                'label' => PermissionCatalog::MODULE_LABELS[$module] ?? $module,
                'permissions' => $items,
            ];
        }

        return response()->json([
            'data' => $modules,
            'meta' => [
                'total' => array_sum(array_map(fn (array $module): int => count($module['permissions']), $modules)),
            ],
        ]);
    }
}
