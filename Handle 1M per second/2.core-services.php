<?php

// app/Services/CacheService.php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class CacheService
{
    private $redis;
    private $prefix;

    public function __construct()
    {
        $this->redis = Redis::connection();
        $this->prefix = config('sharding.redis.prefix');
    }

    public function remember($key, $ttl, $callback)
    {
        $value = $this->get($key);
        
        if (!is_null($value)) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    public function get($key)
    {
        $value = $this->redis->get($this->prefix . $key);
        return $value ? json_decode($value, true) : null;
    }

    public function set($key, $value, $ttl = null)
    {
        $key = $this->prefix . $key;
        $value = json_encode($value);
        
        if ($ttl) {
            $this->redis->setex($key, $ttl, $value);
        } else {
            $this->redis->set($key, $value);
        }
    }

    public function lock($name, $seconds)
    {
        return $this->redis->lock($this->prefix . $name, $seconds);
    }

    public function queue($queue, $data)
    {
        return $this->redis->lpush($this->prefix . $queue, json_encode($data));
    }

    public function dequeue($queue)
    {
        $data = $this->redis->rpop($this->prefix . $queue);
        return $data ? json_decode($data, true) : null;
    }
}

// app/Services/RedisSharding.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessWriteBatch;
use Exception;

class RedisSharding
{
    private $cache;
    private $config;

    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
        $this->config = config('sharding');
    }

    public function handle($operation, $data = [], $key = null)
    {
        try {
            switch ($operation) {
                case 'write':
                    return $this->handleWrite($data, $key);
                case 'read':
                    return $this->handleRead($key);
                case 'batch':
                    return $this->processBatch($data);
                default:
                    throw new Exception("Invalid operation");
            }
        } catch (Exception $e) {
            \Log::error("Sharding error: " . $e->getMessage());
            throw $e;
        }
    }

    private function handleWrite($data, $key)
    {
        $requestId = uniqid('req_', true);
        
        // Store write request
        $this->cache->set("write:{$requestId}", [
            'data' => $data,
            'key' => $key
        ], 300);

        // Queue for processing
        $this->cache->queue('write_queue', $requestId);

        // Process batch if size reached
        $this->checkAndProcessBatch();

        return $requestId;
    }

    private function handleRead($key)
    {
        return $this->cache->remember("user:{$key}", $this->config['cache_ttl'], function () use ($key) {
            $shard = $this->getShardInfo($key);
            return DB::table($shard->table_name)
                ->where('user_id', $key)
                ->orWhere('country', $key)
                ->first();
        });
    }

    private function checkAndProcessBatch()
    {
        $lock = $this->cache->lock('batch_processing', 10);

        if ($lock->get()) {
            try {
                $batch = [];
                while (count($batch) < $this->config['batch_size']) {
                    $item = $this->cache->dequeue('write_queue');
                    if (!$item) break;
                    $batch[] = $item;
                }

                if (!empty($batch)) {
                    ProcessWriteBatch::dispatch($batch);
                }
            } finally {
                $lock->release();
            }
        }
    }

    private function getShardInfo($key)
    {
        return $this->cache->remember("shard:{$key}", $this->config['cache_ttl'], function () use ($key) {
            return $this->getOrCreateShard($key);
        });
    }

    private function getOrCreateShard($key)
    {
        $lock = $this->cache->lock('shard_creation', 5);

        if ($lock->get()) {
            try {
                return $this->findOrCreateShard($key);
            } finally {
                $lock->release();
            }
        }

        throw new Exception("Could not acquire lock for shard creation");
    }

    private function findOrCreateShard($key)
    {
        $shard = DB::table('shards')
            ->where('key_from', '<=', $key)
            ->where('key_to', '>=', $key)
            ->where('status', 'active')
            ->first();

        if (!$shard) {
            $shard = $this->createNewShard($key);
        }

        return $shard;
    }

    private function createNewShard($key)
    {
        DB::beginTransaction();
        try {
            $shardId = DB::table('shards')->max('id') + 1;
            $tableName = $this->config['shard_prefix'] . $shardId;
            $backupTable = $tableName . '_backup';

            $this->createShardTables($tableName, $backupTable);

            $shardId = DB::table('shards')->insertGetId([
                'table_name' => $tableName,
                'backup_table' => $backupTable,
                'key_from' => $key,
                'key_to' => $key + $this->config['max_shard_size'],
                'status' => 'active',
                'created_at' => now()
            ]);

            DB::commit();
            return DB::table('shards')->find($shardId);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function createShardTables($primary, $backup)
    {
        $schema = "
            CREATE TABLE IF NOT EXISTS {TABLE} (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT,
                name VARCHAR(255),
                email VARCHAR(255),
                country VARCHAR(2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_user (user_id),
                KEY idx_country (country)
            )
        ";

        DB::statement(str_replace('{TABLE}', $primary, $schema));
        DB::statement(str_replace('{TABLE}', $backup, $schema));
    }
}