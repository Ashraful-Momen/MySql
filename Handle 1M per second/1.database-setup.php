// database/migrations/2024_01_01_000000_create_shards_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShardsTable extends Migration
{
    public function up()
    {
        Schema::create('shards', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->string('backup_table');
            $table->bigInteger('key_from');
            $table->bigInteger('key_to');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_failover')->nullable();
            $table->timestamps();
            
            $table->index(['key_from', 'key_to']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shards');
    }
}

// config/sharding.php
<?php

return [
    'max_shard_size' => env('MAX_SHARD_SIZE', 1000000),
    'shard_prefix' => env('SHARD_PREFIX', 'users_shard_'),
    'cache_ttl' => env('CACHE_TTL', 3600),
    'batch_size' => env('BATCH_SIZE', 1000),
    'redis' => [
        'prefix' => env('REDIS_PREFIX', 'shard:'),
        'queue' => env('REDIS_QUEUE', 'default'),
        'connection' => env('REDIS_CONNECTION', 'default'),
    ],
];

// config/queue.php
<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
        ],
    ],
];

// .env additions
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_PREFIX=shard:
MAX_SHARD_SIZE=1000000
BATCH_SIZE=1000
CACHE_TTL=3600