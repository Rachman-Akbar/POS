<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Pemeriksaan role lama, dipertahankan agar kode yang masih memakai
     * `users.role` tidak langsung rusak.
     */
    public function hasRole(string $role): bool
    {
        if ($this->role === $role) {
            return true;
        }

        $roles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->get();

        return $roles->contains(
            fn (Role $assigned): bool => $assigned->slug === $role || $assigned->name === $role
        );
    }

    /**
     * Seluruh permission milik user, digabung dari setiap role aktif.
     *
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        $roles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->with('permissions')->get();

        return $roles
            ->filter(fn (Role $role): bool => $role->is_active)
            ->flatMap(fn (Role $role): Collection => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    /**
     * Super Admin selalu lolos, baik dari role baru maupun dari `role` lama
     * supaya akun admin existing tidak terkunci saat transisi.
     */
    /**
     * Super Admin ditentukan oleh role, bukan oleh kolom `role` warisan.
     *
     * Kolom `role` lama (`admin`) hanya dipakai sebagai jaring pengaman untuk
     * user lama yang belum punya role sama sekali. Begitu sebuah role
     * ditugaskan, kolom lama harus kehilangan kewenangannya: kalau tidak,
     * setiap Admin/Supervisor otomatis jadi Super Admin dan batas
     * `transaction.view.all` tidak berlaku.
     */
    public function isSuperAdmin(): bool
    {
        $roles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->get();

        if ($roles->isNotEmpty()) {
            return $roles->contains(fn (Role $role): bool => $role->isSuperAdmin());
        }

        return $this->role === 'admin';
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isSuperAdmin() || $this->permissionNames()->contains($permission);
    }

    /**
     * Ganti seluruh role user dengan yang diberikan (id, slug, atau nama role).
     *
     * @param  array<int, int|string>  $roleKeys
     */
    public function syncRoles(array $roleKeys): void
    {
        $ids = Role::query()
            ->whereIn('id', array_filter($roleKeys, 'is_numeric'))
            ->orWhereIn('slug', $roleKeys)
            ->orWhereIn('name', $roleKeys)
            ->pluck('id')
            ->all();

        $this->roles()->sync($ids);
        $this->unsetRelation('roles');
    }

    public function markLogin(): void
    {
        $this->forceFill(['last_login_at' => now()])->save();
    }
}
