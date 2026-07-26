<?php

namespace App\Enums;

/**
 * How a pilgrim group physically enters/leaves the Kingdom — air, land, or sea.
 * Drives which ports are valid choices for the arrival/departure leg.
 */
enum TripMode: string
{
    case Air = 'air';
    case Land = 'land';
    case Sea = 'sea';

    public function label(): string
    {
        return match ($this) {
            self::Air => __('Air'),
            self::Land => __('Land'),
            self::Sea => __('Sea'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Air => 'paper-airplane',
            self::Land => 'truck',
            self::Sea => 'lifebuoy',
        };
    }
}
