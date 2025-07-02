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
        Schema::create('reports', function (Blueprint $table) {
            $table->string('reportId', 20)->primary();
            $table->string('riderId')->nullable();
            $table->string('rider_name')->nullable();
            $table->string('plate_number');
            $table->string('reported_by');
            $table->text('remarks'); // Passenger complaint
            $table->date('date_submitted');
            $table->timestamps();

            // Optional: If you have a riders table, you can add foreign key
            // $table->foreign('rider_id')->references('id')->on('riders')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
