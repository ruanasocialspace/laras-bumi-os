<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess;

use App\Modules\SharedInfrastructure\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AuthController
{
    public function login(Request $request, AuditLog $audit): AccountResource
    {
        abort_unless($request->hasSession(), 419);
        $input = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:72'],
        ]);

        $guard = Auth::guard('web');
        if (strlen($input['password']) > 72 || str_contains($input['password'], "\0") || !$guard->attempt([
            'email' => Str::lower($input['email']),
            'password' => $input['password'],
            'active' => true,
        ])) {
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak sesuai.']);
        }

        $actor = $guard->user();
        try {
            $audit->record($actor->id, 'identity.login', 'user', $actor->id);
        } catch (Throwable $error) {
            $this->clearSession($request);
            throw $error;
        }

        $request->session()->regenerate();
        $request->session()->put('identity_version', $actor->session_version);

        return new AccountResource($actor);
    }

    public function me(Request $request): AccountResource
    {
        return new AccountResource($request->user());
    }

    public function logout(Request $request, AuditLog $audit): Response
    {
        $actorId = $request->user()->id;
        $this->clearSession($request);
        $audit->record($actorId, 'identity.logout', 'user', $actorId);

        return response()->noContent();
    }

    private function clearSession(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
