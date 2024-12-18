<?php

// database/migrations/create_shard_maps_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShardMapsTable extends Migration
{
    public function up()
    {
        Schema::create('shard_maps', function (Blueprint $table) {
            $table->id();
            $table->string('country', 2);
            $table->string('primary_table');
            $table->string('replica1_table');
            $table->string('replica2_table');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('user_count')->default(0);
            $table->timestamps();
            
            $table->index('country');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shard_maps');
    }
}

// database/migrations/create_balance_tables.php
class CreateBalanceTables extends Migration
{
    public function up()
    {
        // Create primary and replica tables
        foreach (['primary', 'replica1', 'replica2'] as $suffix) {
            Schema::create("balance_{$suffix}", function (Blueprint $table) {
                $table->id();
                $table->bigInteger('user_id');
                $table->decimal('amount', 10, 2)->default(0);
                $table->timestamps();
                
                $table->index('user_id');
            });
        }
    }

    public function down()
    {
        foreach (['primary', 'replica1', 'replica2'] as $suffix) {
            Schema::dropIfExists("balance_{$suffix}");
        }
    }
}

// routes/api.php
use App\Http\Controllers\Api\UserController;

Route::post('/user_reg', [UserController::class, 'register']);
Route::get('/show_balance', [UserController::class, 'showBalance']);