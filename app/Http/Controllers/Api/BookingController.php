<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Rider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BookingController extends Controller
{
     public function fetchMyBookings(Request $request)
    {
        try {
            // Get userId from request body
             $userId = $request->query('userId'); // Works for GET, POST, etc.

            if (!$userId) {
                return response()->json(['message' => 'userId is required'], 400);
            }

             // Check for Authorization header
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
                ->where('tokenable_id', $userId)
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

            // Auto-detect user type by checking which table contains the userId
            $userType = $this->detectUserType($userId);
            
            if (!$userType) {
                return response()->json(['message' => 'User not found in passengers or riders table'], 404);
            }

            // Fetch bookings based on user type
            if ($userType === 'passenger') {
                $result = DB::table('bookings')
                    ->join('riders', 'bookings.riderId', '=', 'riders.riderId')
                    ->where('bookings.passengerId', $userId)
                    ->orderByDesc('bookings.booked_date')
                    ->limit(10)
                    ->select([
                        'bookings.bookingId',
                        'bookings.pickupDisplay_name as pickup_location',
                        'bookings.dropoffDisplay_name as dropoff_location',
                        'bookings.fare',
                        'bookings.booked_date as ride_date',
                        'bookings.plate_number',
                        'bookings.no_of_luggage',
                        'bookings.no_of_passenger',
                        'bookings.booking_status',
                        'bookings.updated_at',
                        DB::raw("CONCAT(riders.rider_fname, ' ', riders.rider_lname) as riderFullname")
                    ])
                    ->get();
            } else {
                $result = DB::table('bookings')
                    ->where('bookings.riderId', $userId)
                    ->orderByDesc('booked_date')
                    ->limit(10)
                    ->select([
                        'bookingId',
                        'pickupDisplay_name as pickup_location',
                        'dropoffDisplay_name as dropoff_location',
                        'fare',
                        'no_of_luggage',
                        'no_of_passenger as passenger_count',
                        'booked_date as ride_date',
                        'booking_status'
                    ])
                    ->get();
            }

            return response()->json([
                'success' => true,
                'user_type' => $userType,
                'ride_history' => $result,
                'total_rides' => $result->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in fetchMyBookings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ride history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-detect if userId belongs to passenger or rider
     */
    private function detectUserType($userId)
    {
        // Check if userId exists in passengers table
        $passengerExists = DB::table('passengers')
            ->where('passengerId', $userId)
            ->exists();
            
        if ($passengerExists) {
            return 'passenger';
        }

        // Check if userId exists in riders table
        $riderExists = DB::table('riders')
            ->where('riderId', $userId)
            ->exists();
            
        if ($riderExists) {
            return 'rider';
        }

        return null; // User not found in either table
    }


    public function insertBookings(Request $request)
        {
            $data = $request->all();

            $booking = [
                'bookingId' => $data['bookingId'],
                'riderId' => $data['assignedRider'],
                'passengerId' => $data['passengerId'],
                'pickupDisplay_name' => $data['pickupCoordinates']['display_name'],
                'pickupCoords' => json_encode([
                    'lat' => $data['pickupCoordinates']['lat'],
                    'lng' => $data['pickupCoordinates']['lng'],
                ]),
                'dropoffDisplay_name' => $data['destinationCoordinates']['display_name'],
                'dropoffCoords' => json_encode([
                    'lat' => $data['destinationCoordinates']['lat'],
                    'lng' => $data['destinationCoordinates']['lng'],
                ]),
                'plate_number' => $data['plate_number'] ?? null,
                'ratings' => $data['ratings'] ?? null,
                'fare' => $data['fare'],
                'no_of_luggage' => $data['luggageCount'],
                'no_of_passenger' => (int) $data['numberofPassengers'],

                // ✅ Set the `booked_date` field properly:
                'booked_date' => isset($data['booked_at_timestamp']) 
                    ? Carbon::createFromTimestampMs($data['booked_at_timestamp'])
                    : now(),

                'booking_status' => $data['booking_status'],

                'created_at' => isset($data['booked_at_timestamp']) 
                    ? Carbon::createFromTimestampMs($data['booked_at_timestamp']) 
                    : now(),

                'updated_at' => isset($data['completed_at_timestamp']) 
                    ? Carbon::createFromTimestampMs($data['completed_at_timestamp'])
                    : (isset($data['cancelled_at_timestamp']) 
                        ? Carbon::createFromTimestampMs($data['cancelled_at_timestamp']) 
                        : now()),
            ];
            
            Booking::create($booking);

            return response()->json(['message' => 'Booking inserted']);

        }


//         public function testGet()
// {
//     try {
//         // Test 1: Basic response
//         $response = [
//             'status' => 'success',
//             'message' => 'GET endpoint is working',
//             'timestamp' => now()->toISOString(),
//             'server_info' => [
//                 'php_version' => PHP_VERSION,
//                 'memory_limit' => ini_get('memory_limit'),
//                 'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB'
//             ]
//         ];

//         // Test 2: Database connection
//         try {
//             $bookingCount = DB::table('bookings')->count();
//             $response['database'] = [
//                 'status' => 'connected',
//                 'total_bookings' => $bookingCount
//             ];
//         } catch (\Exception $e) {
//             $response['database'] = [
//                 'status' => 'error',
//                 'message' => $e->getMessage()
//             ];
//         }

//         // Test 3: Sample booking data (minimal query)
//         try {
//             $sampleBooking = DB::table('bookings')
//                 ->select(['bookingId', 'booked_date'])
//                 ->orderBy('booked_date', 'desc')
//                 ->first();
            
//             $response['sample_data'] = $sampleBooking ? [
//                 'latest_booking_id' => $sampleBooking->bookingId,
//                 'latest_booking_date' => $sampleBooking->booked_date
//             ] : 'No bookings found';
            
//         } catch (\Exception $e) {
//             $response['sample_data'] = [
//                 'error' => $e->getMessage()
//             ];
//         }

//         return response()->json($response);
        
//     } catch (\Exception $e) {
//         return response()->json([
//             'status' => 'error',
//             'message' => 'Test failed',
//             'error' => $e->getMessage(),
//             'line' => $e->getLine(),
//             'file' => basename($e->getFile())
//         ], 500);
//     }
// }


}
