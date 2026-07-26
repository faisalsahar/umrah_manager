<?php

namespace App\Enums;

/**
 * The extensible "other services" catalogue (visa, ziyarat, catering, …). These are
 * unconstrained: sold per head with a workflow, no date/capacity inventory to manage —
 * unlike hotels and transport. See docs/PLAN.md §6.
 */
enum ServiceType: string
{
    case Visa = 'visa';
    case Ziyarat = 'ziyarat';
    case Catering = 'catering';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Visa => __('Visa'),
            self::Ziyarat => __('Ziyarat'),
            self::Catering => __('Catering'),
            self::Other => __('Other'),
        };
    }
}
