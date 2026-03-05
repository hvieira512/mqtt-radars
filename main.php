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

            $x = $bytes[1]; // -127 ~ 127 decímetros
            if ($x > 127) $x -= 256; // interpretar como signed

            $y = $bytes[2];
            if ($y > 127) $y -= 256;

            $z = $bytes[3]; // 0 ~ 255 cm

            $time_left = $bytes[12]; // seconds
            $posture_code = $bytes[13];

            $event_code = $bytes[14];
            $region_id  = $bytes[15];

            $postures = [
                0 => "initialization",
                1 => "walking",
                2 => "suspected_fall",
                3 => "squatting",
                4 => "standing",
                5 => "fall_confirmation",
                6 => "lying_down",
                7 => "suspected_sitting_on_ground",
                8 => "confirmed_sitting_on_ground",
                9 => "sitting_up_bed",
                10 => "suspected_sitting_up_bed",
                11 => "confirmed_sitting_up_bed"
            ];

            $events = [
                0 => "no_event",
                1 => "enter_room",
                2 => "leave_room",
                3 => "enter_area",
                4 => "leave_area"
            ];

            $people[] = [
                "target_id"   => $target_id,
                "x_dm"        => $x,
                "y_dm"        => $y,
                "z_cm"        => $z,
                "time_left_s" => $time_left,
                "posture"     => $postures[$posture_code] ?? "unknown",
                "event"       => $events[$event_code] ?? "unknown",
                "region_id"   => $region_id
            ];
        }

        $result = [
            "type"        => "position",
            "device_code" => $payload['deviceCode'] ?? null,
            "people"      => $people
        ];

        print_r($result);
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

        print_r($minuteStats);
    }

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
            "breathing"   => $breathing,
            "heart_rate"  => $heart_rate,
            "sleep_state" => $sleep_states[$sleep_state_bits],
        ];

        print_r($vitals);
    }
}, 0);

$mqtt->loop(true);
$mqtt->disconnect();
