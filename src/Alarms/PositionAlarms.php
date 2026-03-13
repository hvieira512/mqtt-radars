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

            $posture    = $person['posture_state'] ?? '';
            $x          = $person['x_position_dm'] ?? '?';
            $y          = $person['y_position_dm'] ?? '?';
            $z          = $person['z_position_cm'] ?? '?';
            $timeLeft   = $person['time_left_s'] ?? '?';
            $lastEvent  = $person['last_event'] ?? 'Desconhecido';

            switch ($posture) {

                case 'Fall Confirmation':
                    $alarms[] = [
                        'category'   => 'alarm',
                        'alarm_type' => 'fall_confirmed',
                        'level'      => 'critical',
                        'source'     => 'position',
                        'message'    => "Queda confirmada em ({$x}, {$y}, {$z} cm)! Tempo restante: {$timeLeft}s. Último evento: {$lastEvent}."
                    ];
                    break;

                case 'Confirmed Sitting on Ground':
                    $alarms[] = [
                        'category'   => 'alarm',
                        'alarm_type' => 'sitting_confirmed',
                        'level'      => 'warning',
                        'source'     => 'position',
                        'message'    => "Pessoa confirmada sentada no chão em ({$x}, {$y}, {$z} cm). Último evento: {$lastEvent}."
                    ];
                    break;

                case 'Enter Room':
                    $alarms[] = [
                        'category'   => 'event',
                        'alarm_type' => 'room_entry',
                        'level'      => 'info',
                        'source'     => 'position',
                        'message'    => "Pessoa entrou na sala em ({$x}, {$y}, {$z} cm)."
                    ];
                    break;

                case 'Leave Room':
                    $alarms[] = [
                        'category'   => 'event',
                        'alarm_type' => 'room_exit',
                        'level'      => 'info',
                        'source'     => 'position',
                        'message'    => "Pessoa saiu da sala de ({$x}, {$y}, {$z} cm)."
                    ];
                    break;

                case 'Enter Bed':
                    $alarms[] = [
                        'category'   => 'event',
                        'alarm_type' => 'bed_entry',
                        'level'      => 'info',
                        'source'     => 'position',
                        'message'    => "Pessoa deitou-se na cama monitorizada em ({$x}, {$y}, {$z} cm)."
                    ];
                    break;

                case 'Leave Bed':
                    $alarms[] = [
                        'category'   => 'event',
                        'alarm_type' => 'bed_exit',
                        'level'      => 'info',
                        'source'     => 'position',
                        'message'    => "Pessoa levantou-se da cama monitorizada em ({$x}, {$y}, {$z} cm)."
                    ];
                    break;
            }
        }

        return $alarms;
    }
}
