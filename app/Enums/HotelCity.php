<?php

namespace App\Enums;

enum HotelCity: string
{
    case Makkah = 'makkah';
    case Madinah = 'madinah';
    case Jeddah = 'jeddah';

    public function label(): string
    {
        return match ($this) {
            self::Makkah => __('Makkah'),
            self::Madinah => __('Madinah'),
            self::Jeddah => __('Jeddah'),
        };
    }
}
