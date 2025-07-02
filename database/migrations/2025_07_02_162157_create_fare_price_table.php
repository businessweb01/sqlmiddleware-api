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
        Schema::create('fare_price', function (Blueprint $table) {
            $table->string('fareId', 20)->primary();
            $table->decimal('fare', 8, 2);
            
            // Match adminId (string) type
            $table->string('updated_by');
            $table->timestamps();

            // Foreign key referencing adminId (string)
            $table->foreign('updated_by')->references('adminId')->on('admin')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fare_price');
    }
};
