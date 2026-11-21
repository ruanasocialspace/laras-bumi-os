<?php

use App\Modules\IdentityAccess\AccountsController;
use App\Modules\IdentityAccess\AuthController;
use App\Modules\IdentityAccess\User;
use App\Modules\SharedInfrastructure\InfrastructureController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->group(function (): void {
    Route::get('health/live', [InfrastructureController::class, 'liveness']);
    Route::get('health/ready', [InfrastructureController::class, 'readiness']);
});
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'active', 'throttle:identity'])->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('reference', [InfrastructureController::class, 'reference']);
    Route::get('audit-logs', [InfrastructureController::class, 'audits'])->middleware('can:audit.read');

    require __DIR__.'/customers.php';
    require __DIR__.'/contracts.php';

    Route::middleware('can:manage,'.User::class)->group(function (): void {
        Route::get('roles', [AccountsController::class, 'roles']);
        Route::get('users', [AccountsController::class, 'index']);
        Route::post('users', [AccountsController::class, 'store']);
        Route::get('users/{user}', [AccountsController::class, 'show'])->whereUuid('user');
        Route::patch('users/{user}', [AccountsController::class, 'update'])->whereUuid('user');
    });
});
