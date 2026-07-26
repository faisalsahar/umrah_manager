<?php

namespace App\Enums;

/**
 * Where a stay came from. This is the field that makes the "agent brings their own
 * hotel booking" intake path representable at all — see docs/PLAN.md §3: a stay can
 * exist with no operating order behind it, because the operator still has to know
 * where the pilgrim sleeps even when it sold them nothing.
 */
enum HotelStaySource: string
{
    case OperatorSold = 'operator_sold';
    case AgentProvided = 'agent_provided';

    public function label(): string
    {
        return match ($this) {
            self::OperatorSold => __('Sold by Operator'),
            self::AgentProvided => __('Provided by Agent'),
        };
    }
}
