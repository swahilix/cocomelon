<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_admin', 'role_id'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_admin' => 'boolean'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permissionSlugs(): array
    {
        if (! $this->is_admin || ! $this->role_id) {
            return [];
        }

        if ($this->relationLoaded('role') && $this->role?->relationLoaded('permissions')) {
            return $this->role->permissions->pluck('slug')->values()->all();
        }

        return Permission::query()
            ->join('permission_role', 'permission_role.permission_id', '=', 'permissions.id')
            ->where('permission_role.role_id', $this->role_id)
            ->pluck('permissions.slug')
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_admin || ! $this->role_id) {
            return false;
        }

        return in_array($permission, $this->permissionSlugs(), true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        $granted = $this->permissionSlugs();
        return (bool) array_intersect($permissions, $granted);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
