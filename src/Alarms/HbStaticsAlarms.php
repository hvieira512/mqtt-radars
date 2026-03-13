<?php

namespace App\Alarms;

class HbStaticsAlarms implements AlarmInterface
{
    public function evaluate(array $parsed): array
    {
        $alarms = [];

        if (($parsed['fall'] ?? false) === true) {
            $alarms[] = [
                'category' => 'alarm',
                'alarm_type' => 'fall_detection',
                'level' => 'alert',
                'source' => 'hbstatics'
            ];
        }

        return $alarms;
    }
}
