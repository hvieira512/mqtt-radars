<?php

namespace App\Parsers;

use App\Parsers\ParserInterface;

class PositionParser implements ParserInterface
{
    public function parse(string $base64, ?string $deviceCode): ?array
    {
        $raw = base64_decode($base64);
        if (strlen($raw) % 16 !== 0) return null;

        $people = [];
        $count = strlen($raw) / 16;

        $postures = [
            0 => "Initialization",
            1 => "Walking",
            2 => "Suspected Fall",
            3 => "Squatting",
            4 => "Standing",
            5 => "Fall Confirmation",
            6 => "Lying Down",
            7 => "Suspected Sitting on Ground",
            8 => "Confirmed Sitting on Ground",
            9 => "Sitting Up Bed",
            10 => "Suspected Sitting Up Bed",
            11 => "Confirmed Sitting Up Bed"
        ];

        $events = [
            0 => "No Event",
            1 => "Enter Room",
            2 => "Leave Room",
            3 => "Enter Area",
            4 => "Leave Area"
        ];

        for ($i = 0; $i < $count; $i++) {
            $bytes = array_values(unpack('C*', substr($raw, $i * 16, 16)));
            $x = $bytes[1] > 127 ? $bytes[1] - 256 : $bytes[1];
            $y = $bytes[2] > 127 ? $bytes[2] - 256 : $bytes[2];

            $people[] = [
                "person_index"  => $bytes[0],
                "x_position_dm" => $x,
                "y_position_dm" => $y,
                "z_position_cm" => $bytes[3],
                "time_left_s"   => $bytes[12],
                "posture_state" => $postures[$bytes[13]] ?? "Unknown",
                "last_event"    => $events[$bytes[14]] ?? "Unknown",
                "region_id"     => $bytes[15]
            ];
        }

        return ["type" => "position", "device_code" => $deviceCode, "people" => $people];
    }
}
