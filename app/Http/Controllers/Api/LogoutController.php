<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LogoutController extends Controller
{
    public function logout(Request $request)
    {
        // Check Authorization header
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !Str::startsWith($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Authorization header with Bearer token required'], 401);
        }

        // Extract the raw token from "Bearer x|xxxxxxxx..."
        $token = Str::after($authHeader, 'Bearer ');
        $parts = explode('|', $token);

        if (count($parts) !== 2) {
            return response()->json(['message' => 'Invalid token format'], 403);
        }

        $plainToken = $parts[1];
        $hashedToken = hash('sha256', $plainToken);

        // Get the token record
        $tokenRecord = DB::table('personal_access_tokens')
            ->where('token', $hashedToken)
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Token not found or already invalidated'], 403);
        }

        $tokenableId = $tokenRecord->tokenable_id;

        // Check if tokenable_id exists in passengers
        $isPassenger = DB::table('passengers')->where('passengerId', $tokenableId)->exists();

        // Check if tokenable_id exists in riders
        $isRider = DB::table('riders')->where('riderId', $tokenableId)->exists();

        if ($isRider) {
            // Set isLoggedin = 1 on logout (as per your request)
            DB::table('riders')
                ->where('riderId', $tokenableId)
                ->update(['isLoggedin' => 0]);
        }

        // Expire the token
        DB::table('personal_access_tokens')
            ->where('id', $tokenRecord->id)
            ->update([
                'expires_at' => Carbon::now()->subMinute(), // set to expired
                'last_used_at' => Carbon::now()
            ]);

        return response()->json([
            'message' => 'Logged out successfully. Token expired.',
            'type' => $isRider ? 'rider' : ($isPassenger ? 'passenger' : 'unknown'),
            'id' => $tokenableId
        ]);
    }
}
