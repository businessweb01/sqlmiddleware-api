<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RiderAuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\RiderProfileController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PassengerProfileController;
use App\Http\Controllers\Api\LogoutController;
use App\Http\Controllers\Api\RiderOnlineController;
use App\Http\Controllers\Api\fetchRiderInfo;

// Passenger
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::match(['post', 'put'], '/updatePassData',[PassengerProfileController::class, 'update']);
Route::get('/fetch-rider-info',[fetchRiderInfo::class, 'fetchRiderInfo']);

// Rider
Route::post('/rider/register', [RiderAuthController::class, 'register']);
Route::post('/rider/login', [RiderAuthController::class, 'login']);
Route::match(['post', 'put'], '/rider/updateRiderData', [RiderProfileController::class, 'update']);
Route::match(['post', 'put'], '/rider/Go-Online', [RiderOnlineController::class, 'setOnline']);
Route::match(['post', 'put'], '/rider/Go-Offline', [RiderOnlineController::class, 'goOffline']);

//Fetch Bookings
Route::get('/my-bookings', [BookingController::class, 'fetchMyBookings']);

//For Inserting from firebase to MySql
Route::post('/insert-booking', [BookingController::class, 'insertBookings']);

//Upload Profile Both for Rider and Passenger
Route::post('/upload-profile-picture', [ProfileController::class, 'uploadProfilePicture']);

//Logout Users
Route::match(['post','put'], '/logout', [LogoutController::class, 'logout']);