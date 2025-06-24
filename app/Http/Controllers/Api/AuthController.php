<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Passenger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Helpers\IdGenerator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller

{
  public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'pass_fname'     => 'required|string|max:50',
                'pass_lname'     => 'required|string|max:50',
                'pass_email'     => 'required|email|unique:passengers,pass_email',
                'pass_pswrd'     => 'required|string|min:6|confirmed',
                'pass_cont_num'  => 'required|string|max:15',
                'pass_birthdate' => 'required|date',
                'pass_addr'      => 'required|string|max:255',
            ]);

            // Auto-compute age
            $birthdate = Carbon::parse($validated['pass_birthdate']);
            $validated['pass_age'] = $birthdate->age;

            // Encrypt password
            $validated['pass_pswrd'] = bcrypt($validated['pass_pswrd']);

            // Generate passengerId using helper
            $validated['passengerId'] = IdGenerator::generatePassengerId();

            // Save passenger
            $passenger = Passenger::create($validated);

            // Generate token
            $accessToken = $passenger->createToken('auth_token');
            $plainTextToken = $accessToken->plainTextToken;

            // Hash the token manually to match what Laravel stores in DB
            $hashedToken = hash('sha256', explode('|', $plainTextToken)[1]);

            // Update token expiration and usage
            DB::table('personal_access_tokens')
                ->where('token', $hashedToken)
                ->update([
                    'expires_at' => now()->addDays(7),
                    'last_used_at' => now(),
                ]);

            return response()->json([
                'message' => 'Passenger registered successfully',
                'token' => $plainTextToken,
                'user' => $passenger
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }


    public function login(Request $request)
    {
        $request->validate([
            'login_id'   => 'required|string', // can be email or contact number
            'pass_pswrd' => 'required|string',
        ]);

        $loginId = $request->login_id;
        $key = 'login:' . $request->ip() . ':' . $loginId;

        $defaultAttempts = 3;
        $defaultPenalty = 30;

        // Fetch current values or defaults
        $attemptsLeft = Cache::get($key . ':attempts', $defaultAttempts);
        $penalty = Cache::get($key . ':penalty', $defaultPenalty);

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Locked out. Try again in {$seconds} seconds.",
                'retry_after' => $seconds,
                'unlock_time' => now()->addSeconds($seconds)->toIso8601String(),
            ], 429);
        }

        // Search by email OR contact number
        $passenger = Passenger::where('pass_email', $loginId)
            ->orWhere('pass_cont_num', $loginId)
            ->first();

        if (!$passenger || !Hash::check($request->pass_pswrd, $passenger->pass_pswrd)) {
            $attemptsLeft--;

            if ($attemptsLeft <= 0) {
                RateLimiter::hit($key, $penalty);
                $nextPenalty = min($penalty + 30, 300); // max 5 mins
                Cache::put($key . ':penalty', $nextPenalty, now()->addMinutes(10));
                Cache::put($key . ':attempts', 1, now()->addMinutes(10));

                return response()->json([
                    'message' => "Too many failed attempts. Locked out for {$penalty} seconds.",
                    'retry_after' => $penalty,
                    'unlock_time' => now()->addSeconds($penalty)->toIso8601String(),
                ], 429);
            } else {
                Cache::put($key . ':attempts', $attemptsLeft, now()->addMinutes(10));

                return response()->json([
                    'message' => 'Invalid credentials.',
                    'attempts_left' => $attemptsLeft
                ], 401);
            }
        }

        // ✅ SUCCESS
        RateLimiter::clear($key);
        Cache::forget($key . ':attempts');
        Cache::forget($key . ':penalty');

        $accessToken = $passenger->createToken('passenger_token');
        $plainTextToken = $accessToken->plainTextToken;
        $hashedToken = hash('sha256', explode('|', $plainTextToken)[1]);

        DB::table('personal_access_tokens')
            ->where('token', $hashedToken)
            ->update([
                'expires_at' => now()->addDays(7),
                'last_used_at' => now(),
            ]);

        return response()->json([
            'access_token' => $plainTextToken,
            'token_type' => 'Bearer',
            'passenger' => $passenger,
        ]);
    }


}
