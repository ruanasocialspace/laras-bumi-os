<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class AccountsController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('manage', User::class);
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $users = User::query()->with('roles.permissions')->orderBy('name')->orderBy('id');
        if (isset($filters['q'])) {
            $term = '%'.addcslashes($filters['q'], '\\_%').'%';
            $users->where(fn ($query) => $query->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term));
        }

        return AccountResource::collection($users->paginate((int) ($filters['per_page'] ?? 25))->withQueryString());
    }

    public function show(string $user): AccountResource
    {
        Gate::authorize('manage', User::class);

        return new AccountResource(User::query()->findOrFail($user));
    }

    public function store(AccountRequest $request, Accounts $accounts): JsonResponse
    {
        return (new AccountResource($accounts->save($request->user(), null, $request->validated())))
            ->response()->setStatusCode(201);
    }

    public function update(AccountRequest $request, string $user, Accounts $accounts): AccountResource
    {
        return new AccountResource($accounts->save($request->user(), User::query()->findOrFail($user), $request->validated()));
    }

    public function roles(): JsonResponse
    {
        Gate::authorize('manage', User::class);

        return response()->json(['data' => Role::query()->with('permissions')->orderBy('code')->get()
            ->map(fn (Role $role): array => [
                'code' => $role->code,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('code')->all(),
            ])]);
    }
}
