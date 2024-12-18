<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ShardingService
{
    private const USERS_PER_SHARD = 10000;
    private const CACHE_TTL = 3600; // 1 hour
    private const BATCH_SIZE = 1000;

    private $redis;

    public function __construct()
    {
        $this->redis = Redis::connection();
    }

    /**
     * Handle user registration with sharding and caching
     */
    public function handleUserRegistration($userData)
    {
        // Generate request ID for tracking
        $requestId = uniqid('reg_', true);

        // Cache the request data
        $this->redis->setex(
            "reg_request:{$requestId}", 
            300, // 5 minutes
            json_encode($userData)
        );

        // Add to registration queue
        $this->redis->lpush('registration_queue', $requestId);

        // Process batch if needed
        $this->processBatchIfNeeded('registration_queue');

        return $requestId;
    }

    /**
     * Handle balance check with caching
     */
    public function handleBalanceCheck($userId)
    {
        $cacheKey = "balance:{$userId}";
        $cached = $this->redis->get($cacheKey);

        if ($cached) {
            return json_decode($cached, true);
        }

        // Get balance from appropriate shard
        $balance = $this->getBalanceFromShard($userId);

        // Cache the result
        $this->redis->setex($cacheKey, self::CACHE_TTL, json_encode($balance));

        return $balance;
    }

    /**
     * Process registration batch
     */
    private function processBatchIfNeeded($queue)
    {
        if ($this->redis->llen($queue) >= self::BATCH_SIZE) {
            $lock = $this->redis->lock('batch_processing_lock', 10);

            if ($lock->get()) {
                try {
                    $batch = [];
                    for ($i = 0; $i < self::BATCH_SIZE; $i++) {
                        $requestId = $this->redis->rpop($queue);
                        if (!$requestId) break;

                        $data = $this->redis->get("reg_request:{$requestId}");
                        if ($data) {
                            $batch[] = json_decode($data, true);
                            $this->redis->del("reg_request:{$requestId}");
                        }
                    }

                    if (!empty($batch)) {
                        $this->processBatch($batch);
                    }
                } finally {
                    $lock->release();
                }
            }
        }
    }

    /**
     * Process batch of registrations
     */
    private function processBatch($batch)
    {
        foreach ($batch as $userData) {
            $shard = $this->getOrCreateUserShard($userData['country']);
            
            try {
                // Try primary shard
                $this->insertUser($shard->primary_table, $userData);
            } catch (\Exception $e) {
                // Failover to replica 1
                try {
                    $this->insertUser($shard->replica1_table, $userData);
                    $this->promoteReplica($shard->id, 1);
                } catch (\Exception $e2) {
                    // Failover to replica 2
                    $this->insertUser($shard->replica2_table, $userData);
                    $this->promoteReplica($shard->id, 2);
                }
            }
        }
    }

    /**
     * Get or create user shard
     */
    private function getOrCreateUserShard($country)
    {
        $shard = DB::table('shard_maps')
            ->where('country', $country)
            ->where('status', 'active')
            ->first();

        if (!$shard || $this->isShardFull($shard)) {
            $shard = $this->createNewShard($country);
        }

        return $shard;
    }

    /**
     * Create new shard
     */
    private function createNewShard($country)
    {
        DB::beginTransaction();
        try {
            $shardId = DB::table('shard_maps')->max('id') + 1;
            $primaryTable = "users_shard_{$shardId}";
            $replica1Table = "{$primaryTable}_replica1";
            $replica2Table = "{$primaryTable}_replica2";

            // Create tables
            $this->createShardTables([
                $primaryTable, 
                $replica1Table, 
                $replica2Table
            ]);

            // Add to shard map
            $shardId = DB::table('shard_maps')->insertGetId([
                'country' => $country,
                'primary_table' => $primaryTable,
                'replica1_table' => $replica1Table,
                'replica2_table' => $replica2Table,
                'status' => 'active',
                'user_count' => 0
            ]);

            DB::commit();
            return DB::table('shard_maps')->find($shardId);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create shard tables
     */
    private function createShardTables($tables)
    {
        $schema = "
            CREATE TABLE IF NOT EXISTS {TABLE} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255),
                gender ENUM('male', 'female', 'other'),
                phone VARCHAR(20),
                email VARCHAR(255),
                country VARCHAR(2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_email (email),
                KEY idx_country (country)
            )
        ";

        foreach ($tables as $table) {
            DB::statement(str_replace('{TABLE}', $table, $schema));
        }
    }

    /**
     * Check if shard is full
     */
    private function isShardFull($shard)
    {
        return DB::table($shard->primary_table)->count() >= self::USERS_PER_SHARD;
    }

    /**
     * Insert user into table
     */
    private function insertUser($table, $userData)
    {
        $id = DB::table($table)->insertGetId($userData);
        
        // Update user count in shard map
        DB::table('shard_maps')
            ->where('primary_table', $table)
            ->orWhere('replica1_table', $table)
            ->orWhere('replica2_table', $table)
            ->increment('user_count');

        return $id;
    }

    /**
     * Promote replica to primary
     */
    private function promoteReplica($shardId, $replicaNumber)
    {
        $shard = DB::table('shard_maps')->find($shardId);
        $replicaTable = "replica{$replicaNumber}_table";

        DB::table('shard_maps')
            ->where('id', $shardId)
            ->update([
                'primary_table' => $shard->$replicaTable,
                $replicaTable => $shard->primary_table
            ]);
    }

    /**
     * Get balance from appropriate shard
     */
    private function getBalanceFromShard($userId)
    {
        try {
            return $this->getBalanceFromTable('balance_primary', $userId);
        } catch (\Exception $e) {
            try {
                // Try replica 1
                return $this->getBalanceFromTable('balance_replica1', $userId);
            } catch (\Exception $e2) {
                // Try replica 2
                return $this->getBalanceFromTable('balance_replica2', $userId);
            }
        }
    }

    /**
     * Get balance from specific table
     */
    private function getBalanceFromTable($table, $userId)
    {
        return DB::table($table)
            ->where('user_id', $userId)
            ->first();
    }
}