<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends Model
{
    use HasFactory;
    
    protected $table = 'bookings';
    protected $primaryKey = 'bookingId';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'bookingId',
        'riderId',
        'passengerId',
        'pickupDisplay_name',
        'pickupCoords',
        'dropoffDisplay_name',
        'dropoffCoords',
        'no_of_luggage',
        'no_of_passenger',
        'booked_date',
        'fare',
        'booking_status',
        'plate_number',
        'created_at',
        'updated_at',
        'ratings'
    ];

    protected $casts = [
        'pickupCoords' => 'array',
        'dropoffCoords' => 'array',
        'booked_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function passenger()
    {
        return $this->belongsTo(Passenger::class, 'passengerId');
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class, 'riderId');
    }
}
