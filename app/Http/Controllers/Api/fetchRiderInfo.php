<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class fetchRiderInfo extends Controller
{
    public function fetchRiderInfo(Request $request)
    {
        try {
            // Get riderId and passengerId from query parameters
            $riderId = $request->query('riderId');
            $passengerId = $request->query('passengerId');

            if (!$riderId) {
                return response()->json(['message' => 'riderId is required'], 400);
            }

            if (!$passengerId) {
                return response()->json(['message' => 'passengerId is required'], 400);
            }

            // Check for Authorization Header
            $authHeader = $request->header('Authorization');
            if (!$authHeader || !Str::startsWith($authHeader, 'Bearer ')) {
                return response()->json(['message' => 'Authorization header with Bearer token required'], 401);
            }

            // Extract raw token from Authorization header (no hashing)
            $token = Str::after($authHeader, 'Bearer ');

            // Split the token to get the second part (the raw token)
            $parts = explode('|', $token);
            if (count($parts) !== 2) {
                return response()->json(['message' => 'Invalid token format'], 403);
            }

            $plainToken = $parts[1];
            $hashedToken = hash('sha256', $plainToken);

            // Verify token belongs to the userId
            $tokenRecord = DB::table('personal_access_tokens')
                ->where('tokenable_id', $passengerId)
                ->where('token', $hashedToken) // Laravel Sanctum hashes tokens
                ->orderByDesc('created_at')
                ->first();

            if (!$tokenRecord) {
                return response()->json(['message' => 'Unauthorized access: Invalid token for this user'], 401);
            }

            // Check if token is expired
            if ($tokenRecord->expires_at && Carbon::parse($tokenRecord->expires_at)->isPast()) {
                return response()->json(['message' => 'Unauthorized access: Token expired'], 401);
            }

            // Fetch rider information
            $rider = Rider::select(
                'rider_fname',
                'rider_lname',
                'rider_cont_num',
                'profile_pic_url',
                'plate_number'
            )->where('riderId', $riderId)->first();

            if (!$rider) {
                return response()->json(['message' => 'Rider not found'], 404);
            }

            // Return rider information
            return response()->json([
                'success' => true,
                'message' => 'Rider information retrieved successfully',
                'data' => [
                    'rider_fullname' => $rider->rider_fname . ' ' . $rider->rider_lname,
                    'rider_cont_num' => $rider->rider_cont_num,
                    'profile_pic_url' => $rider->profile_pic_url,
                    'plate_number' => $rider->plate_number
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching rider information',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}