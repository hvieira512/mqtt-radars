<?php

namespace App\Parsers;

use App\Parsers\ParserInterface;

class HeartBreathParser implements ParserInterface
{
    public function parse(string $base64, ?string $deviceCode): ?array
    {
        $raw = base64_decode($base64);
        if (strlen($raw) !== 16) return null;

        $sleep_states = [
            0b00 => "Undefined",
            0b01 => "Light Sleep",
            0b10 => "Deep Sleep",
            0b11 => "Awake"
        ];

        $status_byte = ord($raw[13]);
        $sleep_state_bits = ($status_byte & 0b11000000) >> 6;

        return [
            "type"        => "vitals",
            "device_code" => $deviceCode,
            "breathing"   => ord($raw[1]),
            "heart_rate"  => ord($raw[2]),
            "sleep_state" => $sleep_states[$sleep_state_bits]
        ];
    }
}
