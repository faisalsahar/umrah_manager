<?php

namespace App\Enums;

enum AgentSaleStatus: string
{
    case Issued = 'issued';
    case Refunded = 'refunded';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Issued => __('Issued'),
            self::Refunded => __('Refunded'),
            self::Voided => __('Voided'),
        };
    }
}
