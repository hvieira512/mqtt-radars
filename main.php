<?php

require('vendor/autoload.php');

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$server   = $_ENV['MQTT_SERVER'];
$port     = (int) $_ENV['MQTT_PORT'];
$username = $_ENV['MQTT_USERNAME'];
$password = $_ENV['MQTT_PASSWORD'];
$subscribeTopic = $_ENV['MQTT_TOPIC'];

$connectionSettings = (new ConnectionSettings)
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(60);

$mqtt = new MqttClient($server, $port, 'php-radar-client');
$mqtt->connect($connectionSettings, true);
echo "[" . date('H:i:s') . "] Client connected\n";

// --- Helper functions for parsing each payload type ---

function parsePosition(string $base64, ?string $deviceCode)
{
    $raw = base64_decode($base64);
    $totalLength = strlen($raw);
    if ($totalLength % 16 !== 0) return null;

    $people = [];
    for ($i = 0; $i < $totalLength / 16; $i++) {
        $bytes = array_values(unpack('C*', substr($raw, $i * 16, 16)));
        $x = $bytes[1] > 127 ? $bytes[1] - 256 : $bytes[1];
        $y = $bytes[2] > 127 ? $bytes[2] - 256 : $bytes[2];

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
            "person_index"   => $bytes[0],
            "x_position_dm"        => $x,
            "y_position_dm"        => $y,
            "z_position_cm"        => $bytes[3],
            "time_left_s" => $bytes[12],
            "posture_state"     => $postures[$bytes[13]] ?? "Unknown",
            "last_event"       => $events[$bytes[14]] ?? "Unknown",
            "region_id"   => $bytes[15]
        ];
    }

    return ["type" => "position", "device_code" => $deviceCode, "people" => $people];
}

function parsePosstatics(string $base64, ?string $deviceCode)
{
    $raw = base64_decode($base64);
    if (strlen($raw) !== 16) return null;
    $bytes = array_values(unpack('C*', $raw));

    $breathingActive = ($bytes[1] >= 2) ? (($bytes[10] & 0b00000001) !== 0) : false;

    return [
        "type" => "minute_stats",
        "device_code" => $deviceCode,
        "version" => $bytes[1],
        "people" => $bytes[2],
        "walking_distance" => ($bytes[3] << 8) + $bytes[4],
        "walking_time" => $bytes[5],
        "meditation_time" => $bytes[6],
        "in_bed_time" => $bytes[7],
        "standing_time" => $bytes[8],
        "multiplayer_time" => $bytes[9],
        "breathing_active" => $breathingActive
    ];
}

function parseHeartbreath(string $base64, ?string $deviceCode)
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
        "type" => "vitals",
        "device_code" => $deviceCode,
        "breathing" => ord($raw[1]),
        "heart_rate" => ord($raw[2]),
        "sleep_state" => $sleep_states[$sleep_state_bits]
    ];
}

function parseHbstatics(string $base64, ?string $deviceCode)
{
    $raw = base64_decode($base64);
    if (strlen($raw) !== 16) return null;

    $bytes = array_values(unpack('C*', $raw));

    $status_byte = $bytes[13];
    $breathing_status_map = [0b00 => "Normal", 0b01 => "Hypopnea", 0b10 => "Hyperpnea", 0b11 => "Apnea"];
    $heart_rate_status_map = [0b00 => "Normal", 0b01 => "Low", 0b10 => "High", 0b11 => "Undefined"];
    $vital_signs_map = [0b00 => "Normal", 0b01 => "Undefined", 0b10 => "Undefined", 0b11 => "Weak"];
    $sleep_states_map = [0b00 => "Undefined", 0b01 => "Light Sleep", 0b10 => "Deep Sleep", 0b11 => "Awake"];

    return [
        "type" => "hbstatics",
        "device_code" => $deviceCode,
        "real_time_breathing" => $bytes[1],
        "real_time_heart_rate" => $bytes[2],
        "avg_breathing_per_minute" => $bytes[5],
        "avg_heart_rate_per_minute" => $bytes[6],
        "breathing_status_per_minute" => $breathing_status_map[$status_byte & 0b00000011] ?? "unknown",
        "heart_rate_status_per_minute" => $heart_rate_status_map[($status_byte & 0b00001100) >> 2] ?? "unknown",
        "vital_signs_status" => $vital_signs_map[($status_byte & 0b00110000) >> 4] ?? "unknown",
        "sleep_state_status" => $sleep_states_map[($status_byte & 0b11000000) >> 6] ?? "unknown"
    ];
}

// --- Single callback with unified output ---
$mqtt->subscribe($subscribeTopic, function ($_topic, $message) {
    $timestamp = date('H:i:s');
    $data = json_decode($message, true);

    if (!$data || !isset($data['payload'])) {
        echo "[$timestamp] Invalid payload\n";
        return;
    }

    $payload = $data['payload'];
    $results = [];

    if (isset($payload['position'])) $results[] = parsePosition($payload['position'], $payload['deviceCode'] ?? null);
    if (isset($payload['posstatics'])) $results[] = parsePosstatics($payload['posstatics'], $payload['deviceCode'] ?? null);
    if (isset($payload['heartbreath'])) $results[] = parseHeartbreath($payload['heartbreath'], $payload['deviceCode'] ?? null);
    if (isset($payload['hbstatics'])) $results[] = parseHbstatics($payload['hbstatics'], $payload['deviceCode'] ?? null);

    foreach ($results as $r) {
        if ($r) echo "[" . $timestamp . "] " . json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}, 0);

$mqtt->loop(true);
$mqtt->disconnect();
