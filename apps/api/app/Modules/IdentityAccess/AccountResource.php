<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'active' => $this->active,
            'roles' => $this->roles->map(fn (Role $role): array => ['code' => $role->code, 'name' => $role->name]),
            'permissions' => $this->permissionCodes(),
        ];
    }
}
