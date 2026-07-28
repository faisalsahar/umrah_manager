<?php

namespace App\Enums;

/**
 * What an agent's own sale represents — kept to two broad buckets rather than a
 * catalog, since an agent's non-ticket sales vary too much to enumerate.
 */
enum AgentSaleType: string
{
    case Ticket = 'ticket';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ticket => __('Ticket'),
            self::Other => __('Other'),
        };
    }
}
