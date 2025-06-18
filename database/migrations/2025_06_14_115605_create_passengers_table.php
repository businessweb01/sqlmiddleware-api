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
        Schema::create('passengers', function (Blueprint $table) {
            $table->string('passengerId', 20)->primary();
            $table->string('pass_fname');
            $table->string('pass_lname');
            $table->string('pass_addr');
            $table->date('pass_birthdate');
            $table->integer('pass_age');
            $table->string('pass_email')->unique();
            $table->string('pass_pswrd');
            $table->string('pass_cont_num');
            $table->string('profile_pic_url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passengers');
    }
};
