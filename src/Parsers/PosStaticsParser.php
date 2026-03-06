<?php

namespace App\Parsers;

use App\Parsers\ParserInterface;

class PosStaticsParser implements ParserInterface
{
    public function parse(string $base64, ?string $deviceCode): ?array
    {
        $raw = base64_decode($base64);
        if (strlen($raw) !== 16) return null;

        $bytes = array_values(unpack('C*', $raw));
        $breathingActive = ($bytes[1] >= 2) ? (($bytes[10] & 0b00000001) !== 0) : false;

        return [
            "type"             => "minute_stats",
            "device_code"      => $deviceCode,
            "version"          => $bytes[1],
            "people"           => $bytes[2],
            "walking_distance" => ($bytes[3] << 8) + $bytes[4],
            "walking_time"     => $bytes[5],
            "meditation_time"  => $bytes[6],
            "in_bed_time"      => $bytes[7],
            "standing_time"    => $bytes[8],
            "multiplayer_time" => $bytes[9],
            "breathing_active" => $breathingActive
        ];
    }
}
