<?php

namespace App\Alarms;

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

            switch ($posture) {

                case 'Fall Confirmation':
                    $alarms[] = [
                        'category'     => 'alarm',
                        'alarm_type'   => 'fall_confirmed',
                        'level'        => 'critical',
                        'source'       => 'position',
                        'person_index' => $personIndex,
                        'region_id'    => $regionId,
                        'message'      => "Queda confirmada em ({$x}, {$y}, {$z} cm)! Tempo restante: {$timeLeft}s."
                    ];
                    break;

                case 'Confirmed Sitting on Ground':
                    $alarms[] = [
                        'category'     => 'alarm',
                        'alarm_type'   => 'sitting_confirmed',
                        'level'        => 'warning',
                        'source'       => 'position',
                        'person_index' => $personIndex,
                        'region_id'    => $regionId,
                        'message'      => "Pessoa sentada no chão em ({$x}, {$y}, {$z} cm)."
                    ];
                    break;
            }

            switch ($lastEvent) {

                case 'Enter Room':
                    $alarms[] = [
                        'category'     => 'event',
                        'alarm_type'   => 'room_entry',
                        'level'        => 'info',
                        'source'       => 'position',
                        'person_index' => $personIndex,
                        'region_id'    => $regionId
                    ];
                    break;

                case 'Leave Room':
                    $alarms[] = [
                        'category'     => 'event',
                        'alarm_type'   => 'room_exit',
                        'level'        => 'info',
                        'source'       => 'position',
                        'person_index' => $personIndex,
                        'region_id'    => $regionId
                    ];
                    break;

                case 'Enter Area':
                    $alarms[] = [
                        'category'     => 'event',
                        'alarm_type'   => 'area_entry',
                        'level'        => 'info',
                        'source'       => 'position',
                        'person_index' => $personIndex,
                        'region_id'    => $regionId
                    ];
                    break;

                case 'Leave Area':
                    $alarms[] = [
                        'category'     => 'event',
                        'alarm_type'   => 'area_exit',
                        'level'        => 'info',
                        'source'       => 'position',
                        'person_index' => $personIndex,
                        'region_id'    => $regionId
                    ];
                    break;
            }
        }

        return $alarms;
    }
}