<?php

namespace App\Enums;

/**
 * The lifecycle of an operating order (أمر التشغيل) — the document that ties a
 * client/agent, a group of pilgrims, hotels and transport together. See
 * OperatingOrder model and docs/PLAN.md §3.
 */
enum OperatingOrderStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingReview => __('Pending Review'),
            self::Approved => __('Approved'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
