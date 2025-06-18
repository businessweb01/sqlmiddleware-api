<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Passenger;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PassengerProfileController extends Controller
{
    public function update(Request $request)
    {
        // Check Authorization header
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !Str::startsWith($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Authorization header with Bearer token required'], 401);
        }

        // Extract and validate token
        $token = Str::after($authHeader, 'Bearer ');
        $parts = explode('|', $token);

        if (count($parts) !== 2) {
            return response()->json(['message' => 'Invalid token format'], 403);
        }

        $plainToken = $parts[1];
        $hashedToken = hash('sha256', $plainToken);

        // Get token record
        $tokenRecord = DB::table('personal_access_tokens')
            ->where('token', $hashedToken)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', Carbon::now());
            })
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Invalid or expired token'], 403);
        }

        // Get the Passenger using tokenable_id
        $passenger = Passenger::find($tokenRecord->tokenable_id);
        if (!$passenger) {
            return response()->json(['message' => 'Passenger not found'], 404);
        }

        // Validate incoming request data
        $validated = $request->validate([
            'passenger_fname' => 'sometimes|required|string|max:50',
            'passenger_lname' => 'sometimes|required|string|max:50',
            'pass_cont_num' => 'sometimes|required|string|max:15',
            'pass_email' => 'sometimes|required|email|unique:passengers,pass_email,' . $passenger->passengerId . ',passengerId',
            'pass_age' => 'sometimes|required|integer',
            'pass_addr' => 'sometimes|required|string|max:255',
            'pass_birthdate' => 'sometimes|required|date',
            'pass_pswrd' => 'sometimes|required|string|min:6|confirmed',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        // Update password if provided
        if (isset($validated['pass_pswrd'])) {
            $validated['pass_pswrd'] = bcrypt($validated['pass_pswrd']);
        }

        // ✅ PROCESS AND STORE PROFILE PICTURE
        if ($request->hasFile('image')) {
            try {
                $imageFile = $request->file('image');
                
                // Log for debugging
                Log::info('Processing image upload for passenger: ' . $passenger->passengerId);
                Log::info('Image original name: ' . $imageFile->getClientOriginalName());
                Log::info('Image size: ' . $imageFile->getSize());
                
                $extension = $imageFile->getClientOriginalExtension();
                $filename = $passenger->passengerId . '.' . $extension;
                $filePath = "ProfilePictures/{$filename}";
                $fileSizeMB = $imageFile->getSize() / 1024 / 1024;

                // Delete old profile picture if exists
                if ($passenger->profile_pic_url) {
                    // If URL is in new format (ProfilePictures/filename), use it directly
                    // If URL is in old format (storage/ProfilePictures/filename), strip the storage/
                    $oldPath = Str::startsWith($passenger->profile_pic_url, 'storage/') 
                        ? str_replace('storage/', '', $passenger->profile_pic_url)
                        : $passenger->profile_pic_url;
                        
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                        Log::info('Deleted old profile picture: ' . $oldPath);
                    }
                }

                // Check if compression is needed
                if ($fileSizeMB > 4) {
                    Log::info('Compressing image, size: ' . $fileSizeMB . 'MB');
                    
                    $compressedImage = Image::make($imageFile)
                        ->resize(1024, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        })
                        ->encode($extension, 75);

                    $stored = Storage::disk('public')->put($filePath, $compressedImage);
                } else {
                    Log::info('Storing image without compression');
                    $stored = $imageFile->storeAs('ProfilePictures', $filename, 'public');
                }

                if ($stored) {
                    // ✅ UPDATE PROFILE PIC URL IN DB
                    $validated['profile_pic_url'] = "ProfilePictures/{$filename}";
                    Log::info('Image stored successfully at: ' . $filePath);
                } else {
                    Log::error('Failed to store image');
                    return response()->json(['message' => 'Failed to store profile picture'], 500);
                }

            } catch (\Exception $e) {
                Log::error('Image upload error: ' . $e->getMessage());
                return response()->json(['message' => 'Error processing image: ' . $e->getMessage()], 500);
            }
        }

        // ✅ UPDATE PASSENGER DATA
        try {
            $passenger->update($validated);
            Log::info('Passenger profile updated successfully');
        } catch (\Exception $e) {
            Log::error('Database update error: ' . $e->getMessage());
            return response()->json(['message' => 'Error updating profile: ' . $e->getMessage()], 500);
        }

        // Refresh the model to get updated data
        $passenger->refresh();

        return response()->json([
            'message' => 'Profile updated successfully',
            'passenger' => $passenger,
            'profile_picture_url' => $passenger->profile_pic_url ? asset('storage/' . $passenger->profile_pic_url) : null,
        ]);
    }
}