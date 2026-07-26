<?php

namespace App\Enums;

enum MovementStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Flagged = 'flagged'; // a violation/exception was reported against this movement

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Confirmed => __('Confirmed'),
            self::InProgress => __('In Progress'),
            self::Completed => __('Completed'),
            self::Flagged => __('Flagged'),
        };
    }
}
