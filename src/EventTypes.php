<?php

namespace App;

class EventTypes
{
    public const POSITION      = 1;
    public const MINUTE_STATS  = 2;
    public const VITALS        = 3;
    public const HBSTATICS     = 4;
    public const ALARM         = 5;

    public static function fromString(string $type): int
    {
        return match ($type) {
            'position' => self::POSITION,
            'minute_stats' => self::MINUTE_STATS,
            'vitals' => self::VITALS,
            'hbstatics' => self::HBSTATICS,
            'alarm' => self::ALARM,
            default => throw new \InvalidArgumentException("Unknown event type: $type")
        };
    }
}