# High Performance User Registration API - Code Documentation

## 1. Controller Class (UserRegistrationController)

### Rate Limiting Properties
```php
protected $maxAttempts = 1000; // Max attempts per minute
protected $decayMinutes = 1;   // Time window in minutes
```
These properties define the rate limiting configuration:
- `$maxAttempts`: Maximum allowed registration attempts per IP address in one minute
- `$decayMinutes`: The time window for tracking attempts (1 minute in this case)

### Register Method Components

#### 1. Rate Limiting Implementation
```php
$ip = $request->ip();
$key = 'registration_attempts:' . $ip;
$attempts = Redis::get($key) ?? 0;
```
- Gets the client's IP address for tracking
- Creates a unique Redis key combining 'registration_attempts:' with the IP
- Retrieves current attempt count (defaults to 0 if not set)

#### 2. Rate Limit Check
```php
if ($attempts > $this->maxAttempts) {
    return response()->json([
        'status' => 'error',
        'message' => 'Too many registration attempts. Please try again later.'
    ], 429);
}
```
- Checks if attempts exceed the limit
- Returns 429 (Too Many Requests) if limit exceeded
- Includes error message for client feedback

#### 3. Rate Limit Counter Update
```php
Redis::incr($key);
Redis::expire($key, 60 * $this->decayMinutes);
```
- Increments the attempt counter in Redis
- Sets expiration time for the counter (60 seconds * decay minutes)
- Auto-cleanup of old counters

#### 4. Request Validation
```php
$validator = Validator::make($request->all(), [
    'name' => 'required|string|max:255',
    'email' => 'required|string|email|max:255|unique:users',
    'phone' => 'required|string|unique:users',
    'gender' => 'required|in:male,female,other'
]);
```
Validates incoming data with rules:
- Name: Required, string, max 255 chars
- Email: Required, valid email format, unique in users table
- Phone: Required, unique in users table
- Gender: Required, must be one of: male, female, other

#### 5. Request Processing
```php
$requestId = uniqid('reg_', true);
$userData = [
    'name' => $request->name,
    'email' => $request->email,
    'phone' => $request->phone,
    'gender' => $request->gender,
    'status' => 'pending'
];
```
- Generates unique request ID with 'reg_' prefix
- Prepares user data array for storage
- Sets initial status as 'pending'

#### 6. Redis Data Storage
```php
Redis::setex('registration:' . $requestId, 3600, json_encode($userData));
```
- Stores user data in Redis with key 'registration:{requestId}'
- Sets 1-hour expiration (3600 seconds)
- Data is JSON encoded for storage

#### 7. Queue Job Dispatch
```php
ProcessUserRegistration::dispatch($requestId)
    ->onQueue('registrations')
    ->delay(now()->addSeconds(1));
```
- Dispatches job to process registration
- Uses dedicated 'registrations' queue
- Adds 1-second delay to prevent race conditions

## 2. Job Class (ProcessUserRegistration)

### Class Properties
```php
protected $requestId;
public $tries = 3;
public $timeout = 30;
```
- `$requestId`: Stores the registration request identifier
- `$tries`: Number of retry attempts for failed jobs
- `$timeout`: Maximum execution time in seconds

### Handle Method Components

#### 1. Data Retrieval
```php
$userData = Redis::get('registration:' . $this->requestId);
if (!$userData) {
    return;
}
$userData = json_decode($userData, true);
```
- Retrieves stored user data from Redis
- Returns early if data not found
- Decodes JSON data to array

#### 2. Database Transaction
```php
DB::beginTransaction();

$user = User::create([
    'name' => $userData['name'],
    'email' => $userData['email'],
    'phone' => $userData['phone'],
    'gender' => $userData['gender']
]);

DB::commit();
```
- Wraps user creation in transaction
- Creates new user record
- Commits transaction on success

#### 3. Status Update
```php
$userData['status'] = 'completed';
$userData['user_id'] = $user->id;
Redis::setex('registration:' . $this->requestId, 3600, json_encode($userData));
```
- Updates registration status to 'completed'
- Stores created user ID
- Updates Redis data with new status

#### 4. Error Handling
```php
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
```
- Logs failed registration attempts
- Updates registration status to 'failed'
- Stores error message
- Maintains Redis data for status checking

## Performance Optimizations

### 1. Redis Usage
- Rate limiting using Redis prevents database overhead
- Temporary data storage reduces database load
- Status tracking without database queries
- Efficient data expiration handling

### 2. Queue System
- Asynchronous processing improves response times
- Retry mechanism handles temporary failures
- Separate queue for registration processing
- Configurable timeout and retry attempts

### 3. Database Operations
- Transaction usage ensures data consistency
- Error handling prevents partial operations
- Proper indexing for email and phone fields
- Efficient unique constraint checking

### 4. Error Handling
- Comprehensive try-catch blocks
- Detailed error logging
- User-friendly error messages
- Failed job handling with status updates

## Security Considerations

### 1. Rate Limiting
- IP-based request limiting prevents abuse
- Configurable thresholds for different scenarios
- Redis-backed tracking for efficiency
- Automatic cleanup of old tracking data

### 2. Data Validation
- Input validation prevents invalid data
- Email format verification
- Unique constraint enforcement
- Required field validation

### 3. Error Handling
- No sensitive information in error messages
- Proper logging of errors
- Failed attempt tracking
- Secure status checking