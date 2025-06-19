<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rider;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Helpers\IdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;

class RiderAuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'rider_fname' => 'required|string',
            'rider_lname' => 'required|string',
            'rider_cont_num' => 'required|string',
            'rider_addr' => 'required|string',
            'rider_birthdate' => 'required|date',
            'plate_number' => 'required|string',
            'date_register' => 'required|date',
            'rider_psswrd' => 'required|string|min:6|confirmed',
        ]);

        // Auto-compute age
        $birthdate = Carbon::parse($validated['rider_birthdate']);
        $validated['rider_age'] = $birthdate->age;

        // Encrypt password
        $validated['rider_psswrd'] = bcrypt($validated['rider_psswrd']);
        $validated['rider_status'] = 0; // default to unverified
        $validated['isLoggedin'] = 0;
        $validated['isOnline'] = 0;

        // Generate passengerId using helper
        $validated['riderId'] = IdGenerator:: generateRiderId();

        $rider = Rider::create($validated);
        $accessToken = $rider->createToken('rider_token'); // this gives the full token object
        $plainTextToken = $accessToken->plainTextToken;
        $tokenId = $accessToken->accessToken->id;

        // Set expiration
        DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->update(['expires_at' => now()->addDays(7)]);

        return response()->json([
            'access_token' => $plainTextToken,
            'token_type' => 'Bearer',
            'rider' => $rider,
        ]);

    }

    public function login(Request $request)
    {
        $request->validate([
            'rider_cont_num' => 'required|string',
            'rider_psswrd'   => 'required|string',
        ]);

        $key = 'rider_login:' . $request->ip() . ':' . $request->rider_cont_num;

        $defaultAttempts = 3;
        $defaultPenalty = 30;

        $attemptsLeft = Cache::get($key . ':attempts', $defaultAttempts);
        $penalty = Cache::get($key . ':penalty', $defaultPenalty);

        // Check lockout
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Locked out. Try again in {$seconds} seconds.",
                'retry_after' => $seconds,
                'unlock_time' => now()->addSeconds($seconds)->toIso8601String()
            ], 429);
        }

        $rider = Rider::where('rider_cont_num', $request->rider_cont_num)->first();

        if (!$rider || !Hash::check($request->rider_psswrd, $rider->rider_psswrd)) {
            $attemptsLeft--;

            if ($attemptsLeft <= 0) {
                RateLimiter::hit($key, $penalty);
                $nextPenalty = min($penalty + 30, 300); // Cap at 5 minutes
                Cache::put($key . ':penalty', $nextPenalty, now()->addMinutes(10));
                Cache::put($key . ':attempts', 1, now()->addMinutes(10)); // Only 1 attempt after lockout

                return response()->json([
                    'message' => "Too many failed attempts. Locked out for {$penalty} seconds.",
                    'retry_after' => $penalty,
                    'unlock_time' => now()->addSeconds($penalty)->toIso8601String()
                ], 429);
            } else {
                Cache::put($key . ':attempts', $attemptsLeft, now()->addMinutes(10));

                return response()->json([
                    'message' => 'Invalid credentials',
                    'attempts_left' => $attemptsLeft
                ], 401);
            }
        }

        // ✅ Login success — reset throttle
        RateLimiter::clear($key);
        Cache::forget($key . ':attempts');
        Cache::forget($key . ':penalty');

        // Generate token
        $accessToken = $rider->createToken('rider_token');
        $plainTextToken = $accessToken->plainTextToken;
        $tokenId = $accessToken->accessToken->id;

        // Set expiration
        DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->update([
                'expires_at' => now()->addDays(7),
                'last_used_at' => now(),
            ]);

        // Mark as logged in
        DB::table('riders')
            ->where('rider_cont_num', $request->rider_cont_num)
            ->update(['isLoggedin' => 1]);

        return response()->json([
            'access_token' => $plainTextToken,
            'token_type' => 'Bearer',
            'rider' => $rider,
        ]);
    }


}
