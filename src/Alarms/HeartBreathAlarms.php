<?php

namespace App\Alarms;

class HeartBreathAlarms implements AlarmInterface
{
    public function evaluate(array $parsed): array
    {
        $alarms = [];
        $hr = $parsed['heart_rate'] ?? null;
        $br = $parsed['breathing'] ?? null;

        if ($hr !== null && $hr > 110) {
            $alarms[] = [
                'category' => 'alarm',
                'alarm_type' => 'heart_rate_high',
                'level' => 'warning',
                'source' => 'vitals'
            ];
        }

        if ($hr !== null && $hr < 40) {
            $alarms[] = [
                'category' => 'alarm',
                'alarm_type' => 'heart_rate_low',
                'level' => 'warning',
                'source' => 'vitals'
            ];
        }

        if ($br !== null && $br === 0) {
            $alarms[] = [
                'category' => 'alarm',
                'alarm_type' => 'apnea',
                'level' => 'alert',
                'source' => 'vitals'
            ];
        }

        return $alarms;
    }
}
