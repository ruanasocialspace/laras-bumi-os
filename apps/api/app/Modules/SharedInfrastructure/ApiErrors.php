<?php

declare(strict_types=1);

namespace App\Modules\SharedInfrastructure;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiErrors
{
    public static function applies(Request $request): bool
    {
        return $request->is('api/*', 'sanctum/csrf-cookie');
    }

    public static function render(Throwable $error, Request $request): ?JsonResponse
    {
        if (!self::applies($request)) {
            return null;
        }

        $status = match (true) {
            $error instanceof ValidationException => 422,
            $error instanceof AuthenticationException => 401,
            $error instanceof ThrottleRequestsException => 429,
            $error instanceof HttpExceptionInterface => $error->getStatusCode(),
            default => 500,
        };
        [$code, $message] = match ($status) {
            400 => ['bad_request', 'Permintaan tidak valid.'],
            401 => ['unauthenticated', 'Silakan masuk untuk melanjutkan.'],
            403 => ['forbidden', 'Anda tidak memiliki izin untuk tindakan ini.'],
            404 => ['not_found', 'Data tidak ditemukan.'],
            405 => ['method_not_allowed', 'Metode permintaan tidak didukung.'],
            409 => ['state_conflict', 'Perubahan bertentangan dengan status data saat ini.'],
            413 => ['payload_too_large', 'Ukuran permintaan terlalu besar.'],
            415 => ['unsupported_media_type', 'Format data tidak didukung.'],
            419 => ['csrf_expired', 'Sesi keamanan telah berakhir. Silakan coba lagi.'],
            422 => ['validation_failed', 'Periksa kembali data yang Anda masukkan.'],
            429 => ['rate_limited', 'Terlalu banyak percobaan. Silakan coba lagi nanti.'],
            503 => ['unavailable', 'Layanan belum siap. Silakan coba lagi nanti.'],
            default => ['request_failed', 'Permintaan tidak dapat diproses.'],
        };

        $body = ['code' => $code, 'message' => $message, 'request_id' => RequestContext::id()];
        if ($error instanceof ValidationException) {
            $body['fields'] = $error->errors();
        }
        if ($error instanceof RevisionConflict) {
            $body['current_revision'] = $error->currentRevision;
        }

        $headers = $error instanceof HttpExceptionInterface ? $error->getHeaders() : [];
        $headers['Cache-Control'] = 'no-store';
        $headers['X-Request-ID'] = $body['request_id'];

        return response()->json(['error' => $body], $status, $headers);
    }
}
