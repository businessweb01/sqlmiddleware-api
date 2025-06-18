<?php

namespace App\Helpers;

use App\Models\Passenger;
use App\Models\Rider; // Make sure you have this model
use Illuminate\Support\Carbon;

class IdGenerator
{
    /**
     * Generate a unique ID with prefix and incremented sequence.
     */
    private static function generateId(string $prefixBase, string $column, string $modelClass): string
    {
        $now = Carbon::now();
        $shortYear = substr($now->year, -2);
        $month = strtoupper($now->format('M'));
        $quarter = ceil($now->month / 3);
        $prefix = "{$prefixBase}Q{$quarter}{$month}{$shortYear}";

        $latest = $modelClass::where($column, 'LIKE', "$prefix%")
            ->orderBy($column, 'desc')
            ->first();

        if ($latest) {
            $lastNumber = intval(substr($latest->$column, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $newNumber;
    }

    /**
     * Generate a unique passenger ID.
     */
    public static function generatePassengerId(): string
    {
        return self::generateId('SP', 'passengerId', Passenger::class);
    }

    /**
     * Generate a unique rider ID.
     */
    public static function generateRiderId(): string
    {
        return self::generateId('SR', 'riderId', Rider::class);
    }
}
