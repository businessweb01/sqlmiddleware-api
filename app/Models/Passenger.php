<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable; // Needed for Sanctum auth
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Passenger extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'passengerId'; // Tell Laravel your primary key field
    public $incrementing = false;          // Since it's not auto-increment
    protected $keyType = 'string';         // Because IDs like 'SPQ2JUN250001' are strings

    protected $fillable = [
        'passengerId',
        'pass_fname',
        'pass_lname',
        'pass_email',
        'pass_pswrd',
        'pass_addr',
        'pass_birthdate',
        'pass_age',
        'pass_cont_num',
        'profile_pic_url'
    ];

    protected $hidden = ['pass_pswrd'];
}
