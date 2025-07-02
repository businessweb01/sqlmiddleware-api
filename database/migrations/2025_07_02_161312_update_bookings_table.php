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
    Schema::table('bookings', function (Blueprint $table) {
        // Only add columns that don't exist in your current table
        // For example, if you're missing some columns:
        $table->string('comment')->nullable();
        // Add other missing columns here
    });
}

public function down(): void
{
    Schema::table('bookings', function (Blueprint $table) {
        $table->dropColumn(['new_column']);
    });
}
};
