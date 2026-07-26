<?php

namespace App\Enums;

/**
 * A named place within or near a city that movements/orders reference — a port,
 * a train station, a ziyarat site, a stopover, or a general tourist destination.
 * One table (`locations`) with this type column, rather than a separate table per
 * kind, since they're all "a named place, optionally in a city" with nothing else
 * that differs structurally.
 */
enum LocationType: string
{
    case AirPort = 'air_port';
    case LandPort = 'land_port';
    case SeaPort = 'sea_port';
    case TrainStation = 'train_station';
    case ZiyaratSite = 'ziyarat_site';
    case StopoverPoint = 'stopover_point';
    case TouristDestination = 'tourist_destination';

    public function label(): string
    {
        return match ($this) {
            self::AirPort => __('Air Port'),
            self::LandPort => __('Land Port'),
            self::SeaPort => __('Sea Port'),
            self::TrainStation => __('Train Station'),
            self::ZiyaratSite => __('Ziyarat Site'),
            self::StopoverPoint => __('Stopover Point'),
            self::TouristDestination => __('Tourist Destination'),
        };
    }

    /**
     * The port types, grouped by the trip mode they serve — used to filter the
     * port choice once an arrival/departure trip mode is picked.
     */
    public static function portsFor(TripMode $mode): self
    {
        return match ($mode) {
            TripMode::Air => self::AirPort,
            TripMode::Land => self::LandPort,
            TripMode::Sea => self::SeaPort,
        };
    }
}
