<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Passenger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Helpers\IdGenerator;

class AuthController extends Controller

{
  public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'pass_fname'     => 'required|string|max:50',
                'pass_lname'     => 'required|string|max:50',
                'pass_email'     => 'required|email|unique:passengers,pass_email',
                'pass_pswrd'     => 'required|string|min:6|confirmed',
                'pass_cont_num'  => 'required|string|max:15',
                'pass_birthdate' => 'required|date',
                'pass_addr'      => 'required|string|max:255',
            ]);

            // Auto-compute age
            $birthdate = Carbon::parse($validated['pass_birthdate']);
            $validated['pass_age'] = $birthdate->age;

            // Encrypt password
            $validated['pass_pswrd'] = bcrypt($validated['pass_pswrd']);

            // Generate passengerId using helper
            $validated['passengerId'] = IdGenerator::generatePassengerId();

            // Save passenger
            $passenger = Passenger::create($validated);

            // Generate token
            $accessToken = $passenger->createToken('auth_token');
            $plainTextToken = $accessToken->plainTextToken;

            // Hash the token manually to match what Laravel stores in DB
            $hashedToken = hash('sha256', explode('|', $plainTextToken)[1]);

            // Update token expiration and usage
            DB::table('personal_access_tokens')
                ->where('token', $hashedToken)
                ->update([
                    'expires_at' => now()->addDays(7),
                    'last_used_at' => now(),
                ]);

            return response()->json([
                'message' => 'Passenger registered successfully',
                'token' => $plainTextToken,
                'user' => $passenger
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }


   public function login(Request $request)
    {
        $passenger = Passenger::where('pass_email', $request->pass_email)->first();

        if (!$passenger || !Hash::check($request->pass_pswrd, $passenger->pass_pswrd)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $accessToken = $passenger->createToken('passenger_token');
        $plainTextToken = $accessToken->plainTextToken;
        $hashedToken = hash('sha256', explode('|', $plainTextToken)[1]);

        DB::table('personal_access_tokens')
            ->where('token', $hashedToken)
            ->update([
                'expires_at' => now()->addDays(7),
                'last_used_at' => now(),
            ]);
            
        return response()->json([
             'access_token' => $plainTextToken,
            'token_type' => 'Bearer',
            'passenger' => $passenger,
        ]);
    }

}
