<?php

namespace App\Alarms;

use App\Logger;

class PositionAlarms implements AlarmInterface
{
    public function evaluate(array $parsed): array
    {
        $alarms = [];
        $people = $parsed['people'] ?? [];

        foreach ($people as $person) {
            $personIndex = $person['person_index'] ?? 0;
            $posture     = $person['posture_state'] ?? '';
            $lastEvent   = $person['last_event'] ?? '';
            $regionId    = $person['region_id'] ?? null;

            $x = $person['x_position_dm'] ?? '?';
            $y = $person['y_position_dm'] ?? '?';
            $z = $person['z_position_cm'] ?? '?';
            $timeLeft = $person['time_left_s'] ?? '?';

            if ($regionId === 5) {
                $alarms[] = $this->makeAlarm(
                    'event',
                    'test',
                    'success',
                    $personIndex,
                    $regionId,
                    'O Marcus está a trabalhar'
                    );
                break;
            }

            $postureAlarms = [
                'Fall Confirmation' => ['fall_confirmed', 'danger', "Queda confirmada em ({$x}, {$y}, {$z} cm)! Tempo restante: {$timeLeft}s."],
                'Confirmed Sitting on Ground' => ['sitting_confirmed', 'warning', "Pessoa sentada no chão em ({$x}, {$y}, {$z} cm)."]
            ];

            if (isset($postureAlarms[$posture])) {
                [$type, $level, $message] = $postureAlarms[$posture];
                $alarms[] = $this->makeAlarm('alarm', $type, $level, $personIndex, $regionId, $message);
            }

            $eventMap = [
                'Enter Room' => 'room_entry',
                'Leave Room' => 'room_exit',
                'Enter Area' => 'area_entry',
                'Leave Area' => 'area_exit'
            ];

            if (isset($eventMap[$lastEvent])) {
                $alarms[] = $this->makeAlarm('event', $eventMap[$lastEvent], 'info', $personIndex, $regionId);
            }
        }

        return $alarms;
    }

    private function makeAlarm(
        string $category,
        string $alarmType,
        string $level,
        int $personIndex,
        ?int $regionId,
        ?string $message = null
    ): array {
        $alarm = [
            'category'     => $category,
            'alarm_type'   => $alarmType,
            'level'        => $level,
            'source'       => 'position',
            'person_index' => $personIndex,
            'region_id'    => $regionId
        ];

        if ($message !== null) {
            $alarm['message'] = $message;
        }

        return $alarm;
    }
}