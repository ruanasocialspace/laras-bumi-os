<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixtures\QueueProbe;
use Tests\TestCase;

final class RedisQueueTest extends TestCase
{
    public function test_redis_worker_processes_a_real_queued_job(): void
    {
        $queue = 'foundation_test_'.Str::uuid();
        $connection = Queue::connection('redis');

        try {
            $connection->push(new QueueProbe($queue), '', $queue);
            $this->assertSame(1, $connection->size($queue));

            $this->artisan('queue:work', [
                'connection' => 'redis',
                '--queue' => $queue,
                '--once' => true,
            ])->assertExitCode(0);

            $this->assertSame('processed', Cache::get($queue));
            $this->assertSame(0, $connection->size($queue));
        } finally {
            $connection->clear($queue);
        }
    }
}
