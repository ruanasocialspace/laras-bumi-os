<?php

declare(strict_types=1);

namespace App\Modules\SharedInfrastructure;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class RequestContext
{
    public static function id(): string
    {
        return request()->attributes->get('request_id') ?? Str::uuid()->toString();
    }

    public function handle(Request $request, Closure $next): Response
    {
        $id = Str::uuid()->toString();
        $request->attributes->set('request_id', $id);
        Log::withContext(['request_id' => $id]);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $id);
            if (ApiErrors::applies($request)) {
                $response->headers->set('Cache-Control', 'no-store');
            }

            return $response;
        } finally {
            Log::withoutContext(['request_id']);
        }
    }
}
