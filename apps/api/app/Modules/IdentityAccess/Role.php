<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Role extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['code', 'name'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->orderBy('permissions.code');
    }
}
