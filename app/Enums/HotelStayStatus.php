<?php

namespace App\Enums;

enum HotelStayStatus: string
{
    case Booked = 'booked';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Booked => __('Booked'),
            self::CheckedIn => __('Checked In'),
            self::CheckedOut => __('Checked Out'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
