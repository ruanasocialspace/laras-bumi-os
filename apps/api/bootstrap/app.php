<?php

use App\Modules\IdentityAccess\RequireActiveUser;
use App\Modules\SharedInfrastructure\ApiErrors;
use App\Modules\SharedInfrastructure\RequestContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', apiPrefix: 'api/v1')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequestContext::class);
        $middleware->statefulApi();
        $middleware->trimStrings(except: ['password']);
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->alias(['active' => RequireActiveUser::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => ApiErrors::applies($request) || $request->expectsJson());
        $exceptions->render(ApiErrors::render(...));
        $exceptions->report(function (QueryException $error): void {
            Log::error('database.query_failed', ['sqlstate' => (string) $error->getCode()]);
        })->stop();
    })
    ->create();
