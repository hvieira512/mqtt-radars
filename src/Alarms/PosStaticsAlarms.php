<?php

namespace App\Alarms;

class PosStaticsAlarms implements AlarmInterface
{
    public function evaluate(array $parsed): array
    {
        $alarms = [];

        if (($parsed['movement'] ?? 0) === 0) {
            $alarms[] = [
                'category' => 'alarm',
                'alarm_type' => 'no_activity',
                'level' => 'warning',
                'source' => 'minute_stats'
            ];
        }

        return $alarms;
    }
}
