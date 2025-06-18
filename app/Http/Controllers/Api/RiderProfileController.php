<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Models\Rider;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class RiderProfileController extends Controller
{
    public function update(Request $request)
    {
        // Authorization header check
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !Str::startsWith($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Authorization header with Bearer token required'], 401);
        }

        $token = Str::after($authHeader, 'Bearer ');
        $parts = explode('|', $token);
        if (count($parts) !== 2) {
            return response()->json(['message' => 'Invalid token format'], 403);
        }

        $plainToken = $parts[1];
        $hashedToken = hash('sha256', $plainToken);

        $tokenRecord = DB::table('personal_access_tokens')
            ->where('token', $hashedToken)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            })
            ->first();

        if (!$tokenRecord) {
            return response()->json(['message' => 'Invalid or expired token'], 403);
        }

        $rider = Rider::find($tokenRecord->tokenable_id);
        if (!$rider) {
            return response()->json(['message' => 'Rider not found'], 404);
        }

        $validated = $request->validate([
            'rider_fname' => 'sometimes|required|string|max:50',
            'rider_lname' => 'sometimes|required|string|max:50',
            'rider_cont_num' => 'sometimes|required|string|max:15',
            'rider_addr' => 'sometimes|required|string|max:255',
            'rider_birthdate' => 'sometimes|required|date',
            'rider_psswrd' => 'sometimes|required|string|min:6|confirmed',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if (isset($validated['rider_psswrd'])) {
            $validated['rider_psswrd'] = bcrypt($validated['rider_psswrd']);
        }

        // ✅ PROCESS AND STORE PROFILE PICTURE
        if ($request->hasFile('image')) {
            try {
                $imageFile = $request->file('image');
                
                // Log for debugging
                Log::info('Processing image upload for rider: ' . $rider->riderId);
                Log::info('Image original name: ' . $imageFile->getClientOriginalName());
                Log::info('Image size: ' . $imageFile->getSize());
                
                $extension = $imageFile->getClientOriginalExtension();
                $filename = $rider->riderId . '.' . $extension;
                $filePath = "ProfilePictures/{$filename}";
                $fileSizeMB = $imageFile->getSize() / 1024 / 1024;

                // Delete old profile picture if exists
                if ($rider->profile_pic_url) {
                    // If URL is in new format (ProfilePictures/filename), use it directly
                    // If URL is in old format (storage/ProfilePictures/filename), strip the storage/
                    $oldPath = Str::startsWith($rider->profile_pic_url, 'storage/') 
                        ? str_replace('storage/', '', $rider->profile_pic_url)
                        : $rider->profile_pic_url;
                        
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

        // ✅ UPDATE RIDER DATA
        try {
            $rider->update($validated);
            Log::info('Rider profile updated successfully');
        } catch (\Exception $e) {
            Log::error('Database update error: ' . $e->getMessage());
            return response()->json(['message' => 'Error updating profile: ' . $e->getMessage()], 500);
        }

        // Refresh the model to get updated data
        $rider->refresh();

        return response()->json([
            'message' => 'Profile updated successfully',
            'rider' => $rider,
            'profile_picture_url' => $rider->profile_pic_url ? asset('storage/' . $rider->profile_pic_url) : null,
        ]);
    }
}