<?php

namespace App\Enums;

/**
 * Mirrors HotelStaySource: transport can be arranged by the operator or brought by
 * the agent (their own contracted bus/driver), same as a hotel stay can be sold by
 * the operator or provided by the agent. See docs/PLAN.md §3.
 */
enum MovementSource: string
{
    case OperatorProvided = 'operator_provided';
    case AgentProvided = 'agent_provided';

    public function label(): string
    {
        return match ($this) {
            self::OperatorProvided => __('Provided by Operator'),
            self::AgentProvided => __('Provided by Agent'),
        };
    }
}
