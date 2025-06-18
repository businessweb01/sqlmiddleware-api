<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RiderOnlineController extends Controller
{
    public function setOnline(Request $request)
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

        // Get the token record (only if not expired)
        $tokenRecord = DB::table('personal_access_tokens')
            ->where('token', $hashedToken)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', Carbon::now());
            })
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Token not found or expired'], 403);
        }

        $tokenableId = $tokenRecord->tokenable_id;

        // Check if rider exists
        $isRider = DB::table('riders')->where('riderId', $tokenableId)->exists();

        if (!$isRider) {
            return response()->json(['message' => 'Rider not found'], 404);
        }

        // Set isOnline = 1 for rider
        DB::table('riders')
            ->where('riderId', $tokenableId)
            ->update(['isOnline' => 1]);

        return response()->json([
            'message' => 'Rider is now online.',
            'riderId' => $tokenableId
        ]);
    }

      public function goOffline(Request $request)
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
        
        // Get the token record (only if not expired)
        $tokenRecord = DB::table('personal_access_tokens')
            ->where('token', $hashedToken)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', Carbon::now());
            })
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Token not found or expired'], 403);
        }

        $tokenableId = $tokenRecord->tokenable_id;

        // Check if rider exists
        $isRider = DB::table('riders')->where('riderId', $tokenableId)->exists();

        if (!$isRider) {
            return response()->json(['message' => 'Rider not found'], 404);
        }

        // Set isOnline = 0 for rider
        DB::table('riders')
            ->where('riderId', $tokenableId)
            ->update(['isOnline' => 0]);

        return response()->json([
            'message' => 'Rider is now offline.',
            'riderId' => $tokenableId
        ]);
    }
}
