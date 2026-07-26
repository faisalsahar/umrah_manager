<?php

namespace App\Enums;

/**
 * The functional kind a movement type represents, used for filtering, badge
 * colours, and reporting. Movement TYPES themselves are operator-defined (see
 * App\Models\MovementType) — an operator can create as many named types as they
 * like ("Airport shuttle", "VIP transfer", …), but each one is tagged with exactly
 * one of these categories so the rest of the app can still reason about "is this
 * an arrival leg" without caring about the operator's own naming.
 */
enum MovementCategory: string
{
    case Arrival = 'arrival';
    case Internal = 'internal';
    case Ziyarat = 'ziyarat';
    case Permit = 'permit';
    case Departure = 'departure';

    public function label(): string
    {
        return match ($this) {
            self::Arrival => __('Arrival'),
            self::Internal => __('Internal'),
            self::Ziyarat => __('Ziyarat'),
            self::Permit => __('Permit'),
            self::Departure => __('Departure'),
        };
    }
}
