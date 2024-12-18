<?php

// app/Jobs/ProcessWriteBatch.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\RedisSharding;

class ProcessWriteBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $batch;

    public function __construct(array $batch)
    {
        $this->batch = $batch;
    }

    public function handle(RedisSharding $sharding)
    {
        $sharding->handle('batch', $this->batch);
    }
}

// app/Console/Commands/MonitorShards.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RedisSharding;
use App\Services\CacheService;

class MonitorShards extends Command
{
    protected $signature = 'shards:monitor';
    protected $description = 'Monitor shard health and process queues';

    private $sharding;
    private $cache;

    public function __construct(RedisSharding $sharding, CacheService $cache)
    {
        parent::__construct();
        $this->sharding = $sharding;
        $this->cache = $cache;
    }

    public function handle()
    {
        $this->info('Starting shard monitoring...');

        while (true) {
            $this->processQueues();
            $this->checkShardHealth();
            sleep(1);
        }
    }

    private function processQueues()
    {
        $lock = $this->cache->lock('queue_processing', 10);

        if ($lock->get()) {
            try {
                $this->sharding->handle('batch', []);
            } finally {
                $lock->release();
            }
        }
    }

    private function checkShardHealth()
    {
        // Implement health checks
        // Add monitoring logic here
    }
}