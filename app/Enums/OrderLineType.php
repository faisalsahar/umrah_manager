<?php

namespace App\Enums;

/**
 * What a single sold line on an operating order represents. Exactly one of the
 * matching foreign keys on OrderLine is set, depending on this value.
 */
enum OrderLineType: string
{
    case Hotel = 'hotel';
    case Transport = 'transport';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Hotel => __('Hotel'),
            self::Transport => __('Transport'),
            self::Service => __('Service'),
        };
    }
}
