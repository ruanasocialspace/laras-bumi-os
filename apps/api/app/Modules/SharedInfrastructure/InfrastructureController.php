<?php

declare(strict_types=1);

namespace App\Modules\SharedInfrastructure;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class InfrastructureController
{
    public function liveness(): JsonResponse
    {
        return response()->json(['data' => ['status' => 'ok']]);
    }

    public function readiness(): JsonResponse
    {
        try {
            DB::table('users')->limit(1)->exists();
            Redis::connection('default')->ping();
            Redis::connection(config('queue.connections.redis.connection'))->ping();
        } catch (Throwable $error) {
            report($error);
            abort(503);
        }

        return response()->json(['data' => ['status' => 'ok']]);
    }

    public function reference(): JsonResponse
    {
        return response()->json(['data' => DB::table('reference_values')->orderBy('code')->pluck('value', 'code')]);
    }

    public function audits(Request $request, AuditLog $audit): JsonResponse
    {
        $input = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $page = DB::table('audit_logs')->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate((int) ($input['per_page'] ?? 25))->withQueryString();
        $audit->record($request->user()->id, 'audit.viewed', 'audit_log');
        $entries = $page->getCollection()->map(function (object $entry): object {
            $entry->details = json_decode($entry->details, false, 512, JSON_THROW_ON_ERROR);
            $entry->occurred_at = \Illuminate\Support\Carbon::parse($entry->occurred_at)->toISOString();

            return $entry;
        });

        return response()->json([
            'data' => $entries,
            'links' => ['first' => $page->url(1), 'last' => $page->url($page->lastPage()), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }
}
