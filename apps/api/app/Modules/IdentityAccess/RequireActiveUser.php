<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RequireActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        abort_unless($request->hasSession(), 401);

        if (!$actor->active || $actor->session_version !== $request->session()->get('identity_version')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(401);
        }

        return $next($request);
    }
}
