<?php

declare(strict_types=1);

namespace SeatLayer;

final class EventHostingRegion
{
    public const WESTERN_EUROPE = 'western-europe';
    public const EASTERN_EUROPE = 'eastern-europe';
    public const NORTH_AMERICA_EAST = 'north-america-east';
    public const NORTH_AMERICA_WEST = 'north-america-west';
    public const SOUTH_AMERICA = 'south-america';
    public const ASIA_PACIFIC = 'asia-pacific';
    public const NORTHEAST_ASIA = 'northeast-asia';
    public const SOUTHEAST_ASIA = 'southeast-asia';
    public const OCEANIA = 'oceania';
    public const AFRICA = 'africa';
    public const MIDDLE_EAST = 'middle-east';

    private const ALL = [
        self::WESTERN_EUROPE, self::EASTERN_EUROPE,
        self::NORTH_AMERICA_EAST, self::NORTH_AMERICA_WEST,
        self::SOUTH_AMERICA, self::ASIA_PACIFIC, self::NORTHEAST_ASIA,
        self::SOUTHEAST_ASIA, self::OCEANIA, self::AFRICA, self::MIDDLE_EAST,
    ];

    public static function assert(?string $value): void
    {
        if ($value !== null && !in_array($value, self::ALL, true)) {
            throw new \InvalidArgumentException('Unsupported SeatLayer Event region.');
        }
    }
}
