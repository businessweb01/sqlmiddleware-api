<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
   public function uploadProfilePicture(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
            'userId' => 'required|string',
        ]);

        $userId = $request->input('userId');

        // Validate userId format
        if (!preg_match('/^(SPQ|SRQ)/', $userId)) {
            return response()->json(['message' => 'Valid userId (SPQ... or SRQ...) is required'], 400);
        }

        // Check for Authorization header
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !Str::startsWith($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Authorization header with Bearer token required'], 401);
        }

        // Extract raw token
        $token = Str::after($authHeader, 'Bearer ');
        $parts = explode('|', $token);
        if (count($parts) !== 2) {
            return response()->json(['message' => 'Invalid token format'], 403);
        }

        $plainToken = $parts[1];
        $hashedToken = hash('sha256', $plainToken);

        // Verify token matches and not expired
        $tokenRecord = DB::table('personal_access_tokens')
            ->where('tokenable_id', $userId)
            ->where('token', $hashedToken)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$tokenRecord) {
            return response()->json([
                'message' => 'Invalid or expired token for this userId',
            ], 403);
        }

        // Process image
        $imageFile = $request->file('image');
        $extension = $imageFile->getClientOriginalExtension();
        $filename = $userId . '.' . $extension;
        $filePath = "ProfilePictures/{$filename}";
        $publicUrl = asset("storage/{$filePath}");

        $fileSizeMB = $imageFile->getSize() / 1024 / 1024;

        if ($fileSizeMB > 4) {
            $compressedImage = Image::make($imageFile)
                ->resize(1024, null, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode($extension, 75);
            Storage::disk('public')->put($filePath, $compressedImage);
        } else {
            $imageFile->storeAs('ProfilePictures', $filename, 'public');
        }

        // Update DB: determine rider or passenger and update profile_pic_url
        if (Str::startsWith($userId, 'SRQ')) {
            DB::table('riders')->where('riderId', $userId)->update([
                'profile_pic_url' => $filePath
            ]);
        } elseif (Str::startsWith($userId, 'SPQ')) {
            DB::table('passengers')->where('passengerId', $userId)->update([
                'profile_pic_url' => $filePath
            ]);
        }

        return response()->json([
            'message' => 'Image uploaded and saved successfully',
            'file_path' => $filePath,
            'public_url' => $publicUrl,
        ]);
    }

}
