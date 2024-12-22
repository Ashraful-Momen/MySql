<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessUserRegistration;
use Illuminate\Support\Facades\Validator;

class UserRegistrationController extends Controller
{
    // Rate limiting configuration
    protected $maxAttempts = 1000; // Max attempts per minute
    protected $decayMinutes = 1;   // Time window in minutes

    /**
     * Handle user registration request
     */
    public function register(Request $request)
    {
        try {
            // Check rate limiting using Redis
            $ip = $request->ip();
            $key = 'registration_attempts:' . $ip;
            
            $attempts = Redis::get($key) ?? 0;
            
            if ($attempts > $this->maxAttempts) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Too many registration attempts. Please try again later.'
                ], 429);
            }
            
            Redis::incr($key);
            Redis::expire($key, 60 * $this->decayMinutes);

            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'phone' => 'required|string|unique:users',
                'gender' => 'required|in:male,female,other'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate unique request ID
            $requestId = uniqid('reg_', true);

            // Store validated data in Redis cache
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'gender' => $request->gender,
                'status' => 'pending'
            ];

            Redis::setex('registration:' . $requestId, 3600, json_encode($userData));

            // Dispatch job to process registration
            ProcessUserRegistration::dispatch($requestId)
                ->onQueue('registrations')
                ->delay(now()->addSeconds(1));

            return response()->json([
                'status' => 'success',
                'message' => 'Registration request received',
                'request_id' => $requestId
            ], 202);

        } catch (\Exception $e) {
            \Log::error('Registration error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during registration'
            ], 500);
        }
    }

    /**
     * Check registration status
     */
    public function checkStatus($requestId)
    {
        $status = Redis::get('registration:' . $requestId);
        
        if (!$status) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid request ID'
            ], 404);
        }

        $userData = json_decode($status, true);

        return response()->json([
            'status' => 'success',
            'data' => $userData
        ]);
    }
}
