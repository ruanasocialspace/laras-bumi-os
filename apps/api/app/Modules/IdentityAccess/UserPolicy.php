<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess;

final class UserPolicy
{
    public function manage(User $actor): bool
    {
        return $actor->hasPermission('identity.manage');
    }
}
