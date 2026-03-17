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

            $alarmsMap = [
                'Fall Confirmation' => ['fall_confirmed', 'danger', "Queda confirmada"],
                'Confirmed Sitting on Ground' => ['sitting_confirmed', 'danger', "Pessoa sentada no chão"],
            ];

            if (isset($alarmsMap[$posture])) {
                [$type, $level, $message] = $alarmsMap[$posture];
                $alarms[] = $this->makeAlarm($type, $level, $personIndex, $regionId, $message);
            }

            $eventsMap = [
                'Enter Room' => ['room_entry', "Entrada no quarto"],
                'Leave Room' => ['room_exit', "Saída no quarto"],
                'Enter Area' => ['area_entry', "Entrada na zona"],
                'Leave Area' => ['area_exit', "Saída na zona"],
            ];

            if (isset($eventsMap[$lastEvent])) {
                [$type, $message] = $eventsMap[$lastEvent];
                $alarms[] = $this->makeEvent($type, $personIndex, $regionId, $message);
            }
        }

        return $alarms;
    }

    private function makeAlarm(string $type, string $level, int $personIndex, ?int $regionId, ?string $message = null): array
    {
        return $this->makeEntry('alarm', $type, $level, $personIndex, $regionId, $message);
    }

    private function makeEvent(string $type, int $personIndex, ?int $regionId, ?string $message = null): array
    {
        return $this->makeEntry('event', $type, 'info', $personIndex, $regionId, $message);
    }

    private function makeEntry(
        string $category,
        string $type,
        string $level,
        int $personIndex,
        ?int $regionId,
        ?string $message = null
    ): array {
        $entry = [
            'category'     => $category,
            'alarm_type'   => $type,
            'level'        => $level,
            'source'       => 'position',
            'person_index' => $personIndex,
            'region_id'    => $regionId
        ];

        if ($message !== null) {
            $entry['message'] = $message;
        }

        return $entry;
    }
}