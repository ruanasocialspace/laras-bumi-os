<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    use HasUuids;

    protected $fillable = ['name', 'email', 'password', 'active'];

    protected $hidden = ['password', 'session_version'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'password' => 'hashed', 'session_version' => 'integer'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->orderBy('roles.code');
    }

    public function permissionCodes(): array
    {
        return $this->roles->loadMissing('permissions')->pluck('permissions')->collapse()
            ->pluck('code')->unique()->sort()->values()->all();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->active && in_array($permission, $this->permissionCodes(), true);
    }
}
