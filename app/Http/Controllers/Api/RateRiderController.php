<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Rider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class RateRiderController extends Controller
{
    public function RateRider(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'bookingId' => 'required|string|exists:bookings,bookingId',
                'riderId' => 'required|string|exists:riders,riderId',
                'rating' => 'required|numeric|min:1|max:5',
                'booked_date' => 'required|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bookingId = $request->input('bookingId');
            $passengerId = $request->input('passengerId'); // Added passengerId as you mentioned
            $riderId = $request->input('riderId');
            $rating = $request->input('rating');
            $bookedDate = $request->input('booked_date');

            // Start database transaction
            DB::beginTransaction();

            // Update the ratings column in bookings table
            $bookingUpdated = DB::table('bookings')
                ->where('bookingId', $bookingId)
                ->where('riderId', $riderId)
                ->where('booked_date', $bookedDate)
                ->update(['ratings' => $rating]);

            if (!$bookingUpdated) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found or unable to update rating'
                ], 404);
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

            return response()->json([
                'success' => true,
                'message' => 'Rider rated successfully',
                'data' => [
                    'booking_id' => $bookingId,
                    'rider_id' => $riderId,
                    'rating' => $rating,
                    'average_rating' => round($avgRating, 2)
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while rating the rider',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}