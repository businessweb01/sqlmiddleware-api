<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Rider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class RateRiderController extends Controller
{
    public function RateRider(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'bookingId' => 'required|string|exists:bookings,bookingId',
                'passengerId' => 'required|string|exists:passengers,passengerId',
                'riderId' => 'required|string|exists:riders,riderId',
                'rating' => 'required|numeric|min:1|max:5',
                'comment' => 'nullable|string|max:1000', // Added comment validation
                'booked_date' => 'required|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Extract input values
            $bookingId = $request->input('bookingId');
            $passengerId = $request->input('passengerId');
            $riderId = $request->input('riderId');
            $rating = $request->input('rating');
            $comment = $request->input('comment'); // Added comment input
            $bookedDate = $request->input('booked_date');

            // Check for Authorization Header
            $authHeader = $request->header('Authorization');
            if (!$authHeader || !Str::startsWith($authHeader, 'Bearer ')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization header with Bearer token required'
                ], 401);
            }

            // Extract raw token from Authorization header
            $token = Str::after($authHeader, 'Bearer ');

            // Split the token to get the second part (the raw token)
            $parts = explode('|', $token);
            if (count($parts) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token format'
                ], 403);
            }

            $plainToken = $parts[1];
            $hashedToken = hash('sha256', $plainToken);

            // Verify token belongs to the passengerId
            $tokenRecord = DB::table('personal_access_tokens')
                ->where('tokenable_id', $passengerId)
                ->where('token', $hashedToken)
                ->orderByDesc('created_at')
                ->first();

            if (!$tokenRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access: Invalid token for this user'
                ], 401);
            }

            // Check if token is expired
            if ($tokenRecord->expires_at && Carbon::parse($tokenRecord->expires_at)->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access: Token expired'
                ], 401);
            }

            // Verify that the booking belongs to the authenticated passenger
            $booking = DB::table('bookings')
                ->where('bookingId', $bookingId)
                ->where('passengerId', $passengerId)
                ->where('riderId', $riderId)
                ->where('booked_date', $bookedDate)
                ->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found or you are not authorized to rate this booking'
                ], 404);
            }

            // Check if booking has already been rated
            if (!is_null($booking->ratings)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This booking has already been rated'
                ], 400);
            }

            // Start database transaction
            DB::beginTransaction();

            // Prepare update data
            $updateData = ['ratings' => $rating];
            if ($comment) {
                $updateData['comment'] = $comment;
            }

            // Update the ratings and comment columns in bookings table
            $bookingUpdated = DB::table('bookings')
                ->where('bookingId', $bookingId)
                ->where('passengerId', $passengerId)
                ->where('riderId', $riderId)
                ->where('booked_date', $bookedDate)
                ->update($updateData);

            if (!$bookingUpdated) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to update booking rating'
                ], 500);
            }

            // Calculate average rating for the rider
            $avgRating = DB::table('bookings')
                ->where('riderId', $riderId)
                ->whereNotNull('ratings')
                ->avg('ratings');

            // Update rider_ratings in riders table
            $riderUpdated = DB::table('riders')
                ->where('riderId', $riderId)
                ->update(['rider_ratings' => round($avgRating, 2)]);

            if (!$riderUpdated) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to update rider ratings'
                ], 500);
            }

            // Commit the transaction
            DB::commit();

            // Prepare response data
            $responseData = [
                'booking_id' => $bookingId,
                'passenger_id' => $passengerId,
                'rider_id' => $riderId,
                'rating' => $rating,
                'average_rating' => round($avgRating, 2)
            ];

            if ($comment) {
                $responseData['comment'] = $comment;
            }

            return response()->json([
                'success' => true,
                'message' => 'Rider rated successfully',
                'data' => $responseData
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while rating the rider',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}