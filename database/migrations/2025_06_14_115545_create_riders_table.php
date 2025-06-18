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
        Schema::create('riders', function (Blueprint $table) {
            $table->string('riderId',20)->primary();
            $table->string('rider_fname');
            $table->string('rider_lname');
            $table->string('rider_cont_num');
            $table->string('rider_addr');
            $table->date('rider_birthdate');
            $table->integer('rider_age');
            $table->string('plate_number');
            $table->date('date_register');
            $table->string('rider_psswrd');
            $table->boolean('rider_status')->default(0); // Unverified by default
            $table->boolean('isLoggedin')->default(0);
            $table->boolean('isOnline')->default(0);
            $table->string('profile_pic_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};
