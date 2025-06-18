<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable; // Needed for Sanctum auth
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rider extends Model
{
  use HasApiTokens, HasFactory, Notifiable;
    protected $primaryKey = 'riderId'; // Tell Laravel your primary key field
    public $incrementing = false;          // Since it's not auto-increment
    protected $keyType = 'string';         // Because IDs like 'SPQ2JUN250001' are strings

    protected $fillable = [
        'riderId',
        'rider_fname',
        'rider_lname',
        'rider_cont_num',
        'rider_addr',
        'rider_birthdate',
        'rider_age',
        'plate_number',
        'date_register',
        'rider_psswrd',
        'rider_status',
        'isLoggedin',
        'isOnline',
        'profile_pic_url',

    ];

    protected $hidden = ['rider_psswrd'];
}
