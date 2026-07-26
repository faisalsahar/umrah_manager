<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Umrah pricing (and most Umrah software, including the reference system this was
 * checked against) splits pilgrims into three fare bands by age at travel time.
 */
enum AgeBand: string
{
    case Adult = 'adult';
    case Child = 'child';
    case Infant = 'infant';

    public function label(): string
    {
        return match ($this) {
            self::Adult => __('Adult'),
            self::Child => __('Child'),
            self::Infant => __('Infant'),
        };
    }

    /**
     * The band a pilgrim of the given birth date falls into on the given day —
     * infant under 2, child under 12, adult otherwise. These thresholds are an
     * industry-standard default; operators with different fare rules can override
     * per pilgrim later if needed.
     */
    public static function forAge(CarbonInterface $dateOfBirth, ?CarbonInterface $asOf = null): self
    {
        $age = $dateOfBirth->diffInYears($asOf ?? now());

        return match (true) {
            $age < 2 => self::Infant,
            $age < 12 => self::Child,
            default => self::Adult,
        };
    }
}
