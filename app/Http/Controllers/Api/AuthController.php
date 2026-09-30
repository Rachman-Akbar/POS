<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Maksimal percobaan login per identitas + alamat IP dalam satu menit.
     *
     * POS biasanya dibuka di satu perangkat untuk banyak shift, jadi kuncinya
     * tidak boleh terlalu rapat. Kunci dibuat per email/username, bukan hanya
     * per IP: tanpa itu, satu penyerang bisa mengunci seluruh kasir di outlet.
     */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identity' => ['required_without:email', 'nullable', 'string', 'max:255'],
            'email' => ['required_without:identity', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'identity.required_without' => 'Email atau nama pengguna wajib diisi.',
            'email.required_without' => 'Email atau nama pengguna wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        // `identity` adalah nama field baru; `email` tetap diterima supaya
        // klien lama yang belum sempat migrasi tidak langsung terlempar 422.
        $identity = trim((string) ($data['identity'] ?? $data['email'] ?? ''));
        $throttleKey = $this->throttleKey($request, $identity);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $message = "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.";

            // Bentuk respons dibuat manual, bukan lewat ValidationException,
            // supaya header `Retry-After` ikut terbawa dan frontend bisa
            // menghitung mundur. Bentuk `errors.identity` tetap dipertahankan
            // supaya pemetaan error di form login tidak ikut berubah.
            return response()->json([
                'message' => $message,
                'errors' => ['identity' => [$message]],
            ], Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $seconds]);
        }

        $user = $this->findByIdentity($identity);

        // Pesan salah kredensial sengaja sama untuk email yang tidak terdaftar
        // maupun password yang salah, supaya halaman login tidak bisa dipakai
        // untuk menebak akun mana yang ada.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'identity' => ['Email, nama pengguna, atau kata sandi salah.'],
            ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'identity' => ['Akun ini dinonaktifkan. Hubungi administrator.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user->markLogin();

        return response()->json([
            'token' => $user->createToken('pos-token')->plainTextToken,
            'user' => $this->presentUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->presentUser($user),
        ]);
    }

    /**
     * Logout mencabut token yang sedang dipakai, bukan hanya membersihkan
     * storage di browser. Setelah ini, request dengan token lama dijawab 401.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Berhasil logout.']);
    }

    /**
     * Cari user berdasarkan nama pengguna, atau email bila isinya berbentuk
     * email. Nama pengguna lebih cepat diketik di kasir daripada email lengkap.
     */
    private function findByIdentity(string $identity): ?User
    {
        $normalized = Str::lower($identity);

        $user = User::query()->where('username', $normalized)->first();

        if ($user) {
            return $user;
        }

        if (! str_contains($identity, '@')) {
            return null;
        }

        return User::query()->where('email', $normalized)->first();
    }

    private function throttleKey(Request $request, string $identity): string
    {
        return 'login:'.Str::transliterate(Str::lower($identity)).'|'.$request->ip();
    }

    /**
     * Bentuk user yang dikirim ke client: tanpa password, lengkap dengan role
     * dan daftar permission supaya frontend bisa menyembunyikan menu.
     *
     * @return array<string, mixed>
     */
    private function presentUser(User $user): array
    {
        $user->loadMissing('roles.permissions');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
            'is_super_admin' => $user->isSuperAdmin(),
            'roles' => $user->roles->map(fn ($role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ])->all(),
            'permissions' => $user->isSuperAdmin()
                ? PermissionCatalog::names()
                : $user->permissionNames()->all(),
        ];
    }
}
