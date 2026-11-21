<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess;

use App\Modules\SharedInfrastructure\AuditLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Accounts
{
    public function __construct(private readonly AuditLog $audit)
    {
    }

    public function save(User $actor, ?User $target, array $input): User
    {
        return DB::transaction(function () use ($actor, $target, $input): User {
            // A shared lock anchor serializes grants and the last-admin invariant.
            $this->lockRoleCatalog();
            $currentActor = $actor->fresh();
            abort_unless($currentActor?->session_version === $actor->session_version, 401);
            abort_unless($currentActor->hasPermission('identity.manage'), 403);

            $user = $target === null ? new User(['active' => true]) : User::query()->lockForUpdate()->findOrFail($target->id);
            $previousRoles = $user->exists ? $user->roles()->pluck('code')->all() : [];
            $roles = $input['role_codes'] ?? $previousRoles;
            sort($roles);

            $email = $input['email'] ?? $user->email;
            if (User::query()->where('email', $email)->where('id', '!=', $user->id ?? '00000000-0000-0000-0000-000000000000')->exists()) {
                throw ValidationException::withMessages(['email' => 'Email sudah digunakan.']);
            }

            $wasActive = $user->active;
            $user->fill(Arr::only($input, ['name', 'email', 'password', 'active']));
            if (
                $wasActive && in_array(RoleCatalog::SUPER_ADMIN, $previousRoles, true)
                && (!$user->active || !in_array(RoleCatalog::SUPER_ADMIN, $roles, true))
            ) {
                abort_unless($this->activeAdmins()->where('users.id', '!=', $user->id)->exists(), 409);
            }

            if ($user->exists && $user->isDirty(['password', 'active'])) {
                $user->session_version++;
            }

            $changes = array_values(array_intersect(array_keys($user->getDirty()), ['name', 'email', 'password', 'active']));
            $creating = !$user->exists;
            $user->save();
            $this->syncRoles($user, $roles);

            if ($creating || $changes !== [] || $previousRoles !== $roles) {
                $this->audit->record($actor->id, $creating ? 'identity.user_created' : 'identity.user_updated', 'user', $user->id, [
                    'fields' => $changes,
                    'active' => $user->active,
                    'roles_before' => $previousRoles,
                    'roles_after' => $roles,
                ]);
            }

            return $user->refresh()->load('roles');
        }, 3);
    }

    public function bootstrap(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            $this->lockRoleCatalog();
            if ($this->activeAdmins()->exists()) {
                throw new \DomainException('Administrator utama aktif sudah tersedia.');
            }

            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'active' => true]);
            $this->syncRoles($user, [RoleCatalog::SUPER_ADMIN]);
            $this->audit->record(null, 'identity.admin_bootstrapped', 'user', $user->id);

            return $user;
        }, 3);
    }

    public function seedDemo(string $name, string $email, string $password, string $role): void
    {
        if (!app()->environment('local') || !config('laras.demo_accounts')) {
            throw new \DomainException('Akun pengembangan hanya tersedia saat diaktifkan di lingkungan lokal.');
        }

        DB::transaction(function () use ($name, $email, $password, $role): void {
            $this->lockRoleCatalog();
            if (User::query()->where('email', $email)->exists()) {
                return;
            }

            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'active' => true]);
            $this->syncRoles($user, [$role]);
            $this->audit->record(null, 'identity.demo_user_created', 'user', $user->id);
        }, 3);
    }

    private function lockRoleCatalog(): void
    {
        Role::query()->where('code', RoleCatalog::SUPER_ADMIN)->lockForUpdate()->firstOrFail();
    }

    private function activeAdmins(): \Illuminate\Database\Eloquent\Builder
    {
        return User::query()->where('active', true)->whereHas(
            'roles',
            fn ($query) => $query->where('code', RoleCatalog::SUPER_ADMIN),
        );
    }

    /** Preserve existing grants and their identifiers on repeat. */
    private function syncRoles(User $user, array $codes): void
    {
        $roleIds = Role::query()->whereIn('code', $codes)->pluck('id');
        DB::table('user_roles')->where('user_id', $user->id)->whereNotIn('role_id', $roleIds)->delete();

        foreach ($roleIds as $id) {
            DB::table('user_roles')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'role_id' => $id,
            ]);
        }
    }
}
