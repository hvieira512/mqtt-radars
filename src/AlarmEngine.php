<?php

namespace App;

use App\Alarms\AlarmInterface;
use App\Alarms\HeartBreathAlarms;
use App\Alarms\PositionAlarms;

class AlarmEngine
{
    /** @var AlarmInterface[] */
    private static array $alarms = [
        'vitals' => HeartBreathAlarms::class,
        'position' => PositionAlarms::class,
    ];

    public static function evaluate(array $parsed): array
    {
        $type = $parsed['type'] ?? null;
        if (!$type || !isset(self::$alarms[$type])) {
            return [];
        }

        $alarmClass = self::$alarms[$type];
        $instance = new $alarmClass();

        return $instance->evaluate($parsed);
    }
}
