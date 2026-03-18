<?php

namespace App;

class EventTypes
{
    public const POSITION      = 1;
    public const MINUTE_STATS  = 2;
    public const VITALS        = 3;
    public const HBSTATICS     = 4;
    public const ALARM         = 5;

    // Alarms
    public const FALL_DETECTION = 10;
    public const HEART_RATE_HIGH = 11;
    public const HEART_RATE_LOW = 12;
    public const APNEA = 13;
    public const NO_ACTIVITY = 14;
    public const EMPTY_ROOM = 15;
    public const PRESENCE_DETECTED = 16;

    public static function fromString(string $type): int

    {
        return match ($type) {
            'position' => self::POSITION,
            'minute_stats' => self::MINUTE_STATS,
            'vitals' => self::VITALS,
            'hbstatics' => self::HBSTATICS,

            'fall_detection' => self::FALL_DETECTION,
            'heart_rate_high' => self::HEART_RATE_HIGH,
            'heart_rate_low' => self::HEART_RATE_LOW,
            'apnea' => self::APNEA,
            'no_activity' => self::NO_ACTIVITY,
            'empty_room' => self::EMPTY_ROOM,
            'presence_detected' => self::PRESENCE_DETECTED,
            default => throw new \InvalidArgumentException("Unknown event type: $type")
        };
    }
}