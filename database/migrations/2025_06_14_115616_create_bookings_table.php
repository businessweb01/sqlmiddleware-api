<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->string('bookingId', 20)->primary(); // max 20 characters
            $table->string('riderId', 20);
            $table->string('passengerId', 20);

            $table->json('pickupCoords');
            $table->string('pickupDisplay_name');
            $table->json('dropoffCoords');
            $table->string('dropoffDisplay_name');

            $table->decimal('fare', 8, 2);
            $table->integer('no_of_luggage');
            $table->integer('no_of_passenger');
            $table->dateTime('booked_date');
            $table->string('booking_status')->default('pending');
            $table->string('plate_number');
            $table->decimal('ratings', 3, 2)->nullable();
            $table->timestamps();

            // Optional FK if related tables have matching 20-char string keys
            // $table->foreign('riderId')->references('riderId')->on('riders')->onDelete('cascade');
            // $table->foreign('passengerId')->references('passengerId')->on('passengers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
