<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->ensureAdmin($request);

        $users = User::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->input('search'));

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->input('role')))
            ->latest('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, User $user): UserResource
    {
        $this->ensureAdmin($request);

        return new UserResource($user);
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $nextRole = $data['role'] ?? $user->role;
        $nextActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) $user->is_active;

        if ($request->user()->is($user) && (! $nextActive || $nextRole !== 'admin')) {
            abort(422, 'Admin tidak dapat menonaktifkan atau menurunkan role akun sendiri.');
        }

        if ($user->role === 'admin' && $user->is_active && (! $nextActive || $nextRole !== 'admin')) {
            abort_if(
                User::query()->where('role', 'admin')->where('is_active', true)->count() <= 1,
                422,
                'Minimal harus ada satu admin aktif.'
            );
        }

        $user->update($data);

        return new UserResource($user->fresh());
    }

    public function destroy(Request $request, User $user): Response
    {
        $this->ensureAdmin($request);

        abort_if($request->user()->is($user), 422, 'Anda tidak dapat menghapus akun sendiri.');

        abort_if(
            $user->role === 'admin'
                && $user->is_active
                && User::query()->where('role', 'admin')->where('is_active', true)->count() <= 1,
            422,
            'Minimal harus ada satu admin aktif.'
        );

        $user->delete();

        return response()->noContent();
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Hanya admin yang dapat mengelola user.');
    }
}
