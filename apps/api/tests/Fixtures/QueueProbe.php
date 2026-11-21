<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

final class QueueProbe implements ShouldQueue
{
    public function __construct(public readonly string $key)
    {
    }

    public function handle(): void
    {
        Cache::put($this->key, 'processed', 60);
    }
}
