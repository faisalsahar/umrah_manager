<?php

namespace App\Enums;

/**
 * How an operator holds inventory with a hotel. See docs/PLAN.md §4 — the release
 * deadline on an allotment is a real, money-losing date and must never be optional.
 */
enum HotelContractType: string
{
    case Allotment = 'allotment';
    case FreeSale = 'free_sale';
    case OnRequest = 'on_request';

    public function label(): string
    {
        return match ($this) {
            self::Allotment => __('Allotment'),
            self::FreeSale => __('Free Sale'),
            self::OnRequest => __('On Request'),
        };
    }
}
