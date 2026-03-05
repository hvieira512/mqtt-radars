<?php

require('vendor/autoload.php');

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

$server   = '192.168.3.250';
$port     = 1883;
$username = 'havicare';
$password = 'hitCare';

$subscribeTopic = "radar/+/1/null";

$connectionSettings = (new ConnectionSettings)
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(60);

$mqtt = new MqttClient($server, $port, 'php-radar-client');
$mqtt->connect($connectionSettings, true);
echo "[" . date('H:i:s') . "] Client connected\n";

$mqtt->subscribe($subscribeTopic, function ($_topic, $message) {
    $timestamp = date('H:i:s');

    $data = json_decode($message, true);
    if (!$data || !isset($data['payload'])) {
        echo "[$timestamp] Invalid payload\n";
        return;
    }

    $payload = $data['payload'];

    // --- Position data ---
    if (isset($payload['position'])) {
        $raw = base64_decode($payload['position']);
        $totalLength = strlen($raw);

        if ($totalLength % 16 !== 0) {
            echo "[$timestamp] Invalid position length: $totalLength\n";
            return;
        }

        $num_people = $totalLength / 16;
        $people = [];

        for ($i = 0; $i < $num_people; $i++) {
            $chunk = substr($raw, $i * 16, 16);
            $bytes = array_values(unpack('C*', $chunk));

            $target_id = $bytes[0];

            $x = $bytes[1];
            if ($x > 127) $x -= 256;
            $y = $bytes[2];
            if ($y > 127) $y -= 256;
            $z = $bytes[3];

            $time_left = $bytes[12];
            $posture_code = $bytes[13];
            $event_code = $bytes[14];
            $region_id  = $bytes[15];

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

            $people[] = [
                "target_id"   => $target_id,
                "x_dm"        => $x,
                "y_dm"        => $y,
                "z_cm"        => $z,
                "time_left_s" => $time_left,
                "posture"     => $postures[$posture_code] ?? "Unknown",
                "event"       => $events[$event_code] ?? "Unknown",
                "region_id"   => $region_id
            ];
        }

        $result = [
            "type"        => "position",
            "device_code" => $payload['deviceCode'] ?? null,
            "people"      => $people
        ];

        echo "[" . $timestamp . "] " . json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    // --- Minute-level stats (posstatics) ---
    if (isset($payload['posstatics'])) {
        $raw = base64_decode($payload['posstatics']);
        if (strlen($raw) !== 16) {
            echo "[$timestamp] Invalid posstatics length\n";
            return;
        }

        $bytes = array_values(unpack('C*', $raw));

        $version = $bytes[1];
        $people  = $bytes[2];
        $walkingDistance = ($bytes[3] << 8) + $bytes[4];
        $walkingTime     = $bytes[5];
        $meditationTime  = $bytes[6];
        $inBedTime       = $bytes[7];
        $standingTime    = $bytes[8];
        $multiplayerTime = $bytes[9];
        $breathingActive = ($version >= 2) ? (($bytes[10] & 0b00000001) !== 0) : false;

        $minuteStats = [
            "type"             => "minute_stats",
            "device_code"      => $payload['deviceCode'] ?? null,
            "version"          => $version,
            "people"           => $people,
            "walking_distance" => $walkingDistance,
            "walking_time"     => $walkingTime,
            "meditation_time"  => $meditationTime,
            "in_bed_time"      => $inBedTime,
            "standing_time"    => $standingTime,
            "multiplayer_time" => $multiplayerTime,
            "breathing_active" => $breathingActive,
        ];

        echo "[" . $timestamp . "] " . json_encode($minuteStats, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    // --- Heartbreath data ---
    if (isset($payload['heartbreath'])) {
        $raw = base64_decode($payload['heartbreath']);
        if (strlen($raw) !== 16) {
            echo "[$timestamp] Invalid heartbreath length\n";
            return;
        }

        $breathing = ord($raw[1]);
        $heart_rate = ord($raw[2]);

        $status_byte = ord($raw[13]);
        $sleep_state_bits = ($status_byte & 0b11000000) >> 6;

        $sleep_states = [
            0b00 => "Undefined",
            0b01 => "Light Sleep",
            0b10 => "Deep Sleep",
            0b11 => "Awake",
        ];

        $vitals = [
            "type"        => "vitals",
            "device_code" => $payload['deviceCode'] ?? null,
            "breathing"   => $breathing,
            "heart_rate"  => $heart_rate,
            "sleep_state" => $sleep_states[$sleep_state_bits],
        ];

        echo "[" . $timestamp . "] " . json_encode($vitals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    // --- Minute-Level Sleep & Breathing Statistics
    if (isset($payload['hbstatics'])) {
        $raw = base64_decode($payload['hbstatics']);

        if (strlen($raw) !== 16) {
            echo "[$timestamp] Invalid hbstatics length\n";
            return;
        }

        $bytes = array_values(unpack('C*', $raw));

        // Simple values
        $breathing_realtime = $bytes[1];
        $heart_rate_realtime = $bytes[2];
        $breathing_avg = $bytes[5];
        $heart_rate_avg = $bytes[6];

        // Byte 13: status/events
        $status_byte = $bytes[13];

        // Bits 1-0: breathing status
        $breathing_status_bits = $status_byte & 0b00000011;
        $breathing_status_map = [
            0b00 => "Normal",
            0b01 => "Hypopnea",
            0b10 => "Hyperpnea",
            0b11 => "Apnea"
        ];
        $breathing_status = $breathing_status_map[$breathing_status_bits] ?? "unknown";

        // Bits 3-2: heart rate status
        $heart_rate_status_bits = ($status_byte & 0b00001100) >> 2;
        $heart_rate_status_map = [
            0b00 => "Normal",
            0b01 => "Low",
            0b10 => "High",
            0b11 => "Undefined"
        ];
        $heart_rate_status = $heart_rate_status_map[$heart_rate_status_bits] ?? "unknown";

        // Bits 5-4: vital signs
        $vital_signs_bits = ($status_byte & 0b00110000) >> 4;
        $vital_signs_map = [
            0b00 => "Normal",
            0b01 => "Undefined",
            0b10 => "Undefined",
            0b11 => "Weak"
        ];
        $vital_signs = $vital_signs_map[$vital_signs_bits] ?? "unknown";

        // Bits 7-6: sleep state
        $sleep_state_bits = ($status_byte & 0b11000000) >> 6;
        $sleep_states_map = [
            0b00 => "Undefined",
            0b01 => "Light_sleep",
            0b10 => "Deep_sleep",
            0b11 => "Awake"
        ];
        $sleep_state = $sleep_states_map[$sleep_state_bits] ?? "unknown";

        $hbstatics_result = [
            "type"                 => "hbstatics",
            "device_code"          => $payload['deviceCode'] ?? null,
            "breathing_realtime"   => $breathing_realtime,
            "heart_rate_realtime"  => $heart_rate_realtime,
            "breathing_avg"        => $breathing_avg,
            "heart_rate_avg"       => $heart_rate_avg,
            "breathing_status"     => $breathing_status,
            "heart_rate_status"    => $heart_rate_status,
            "vital_signs"          => $vital_signs,
            "sleep_state"          => $sleep_state
        ];

        echo "[" . $timestamp . "] " . json_encode($hbstatics_result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}, 0);
$mqtt->loop(true);
$mqtt->disconnect();
