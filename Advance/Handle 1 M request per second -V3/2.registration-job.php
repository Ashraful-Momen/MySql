<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessUserRegistration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $requestId;
    public $tries = 3;  // Number of retry attempts
    public $timeout = 30; // Job timeout in seconds

    public function __construct($requestId)
    {
        $this->requestId = $requestId;
    }

    public function handle()
    {
        try {
            // Get registration data from Redis
            $userData = Redis::get('registration:' . $this->requestId);
            
            if (!$userData) {
                return;
            }

            $userData = json_decode($userData, true);

            DB::beginTransaction();

            // Create user
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'gender' => $userData['gender']
            ]);

            // Update cache with success status
            $userData['status'] = 'completed';
            $userData['user_id'] = $user->id;
            Redis::setex('registration:' . $this->requestId, 3600, json_encode($userData));

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            
            // Update cache with error status
            $userData['status'] = 'failed';
            $userData['error'] = $e->getMessage();
            Redis::setex('registration:' . $this->requestId, 3600, json_encode($userData));

            // Throw exception to trigger job retry
            throw $e;
        }
    }

    public function failed(\Exception $e)
    {
        \Log::error('User registration failed: ' . $e->getMessage());
        
        $userData = Redis::get('registration:' . $this->requestId);
        if ($userData) {
            $userData = json_decode($userData, true);
            $userData['status'] = 'failed';
            $userData['error'] = $e->getMessage();
            Redis::setex('registration:' . $this->requestId, 3600, json_encode($userData));
        }
    }
}
