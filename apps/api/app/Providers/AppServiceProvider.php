<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\IdentityAccess\User;
use App\Modules\IdentityAccess\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::define('audit.read', fn (User $user): bool => $user->hasPermission('audit.read'));
        foreach ([
            'customers.read', 'customers.write', 'customers.categories.manage',
            'contracts.read', 'contracts.write', 'contracts.activate', 'contracts.pricing.write',
            'finance.read', 'finance.bill',
        ] as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
        RateLimiter::for('login', function (Request $request): array {
            $input = $request->input('email');
            $email = is_string($input) ? Str::lower(substr($input, 0, 254)) : '';
            $ip = $request->ip();

            return [
                Limit::perMinute(25)->by('ip:'.$ip),
                Limit::perMinute(5)->by('account:'.hash('sha256', $ip.'|'.$email)),
            ];
        });
        RateLimiter::for('identity', fn (Request $request): Limit => Limit::perMinute(120)->by($request->user()->id));
    }
}
