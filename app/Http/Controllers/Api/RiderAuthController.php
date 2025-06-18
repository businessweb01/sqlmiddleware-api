<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rider;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Helpers\IdGenerator;
use Illuminate\Support\Facades\DB;

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
        $rider = Rider::where('rider_cont_num', $request->rider_cont_num)->first();

        if (!$rider || !Hash::check($request->rider_psswrd, $rider->rider_psswrd)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $accessToken = $rider->createToken('rider_token'); // this gives the full token object
        $plainTextToken = $accessToken->plainTextToken;
        $tokenId = $accessToken->accessToken->id;

        // Set expiration
        DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->update([
                'expires_at' => now()->addDays(7),
                'last_used_at' => now(),
            ]);

        // Set isLoggedin = 1 using rider_cont_num
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
