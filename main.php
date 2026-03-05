<?php

require('vendor/autoload.php');

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

$server   = 'localhost';
$port     = 1883;
$username = 'havicare';
$password = 'hitCare';

$subscribeTopic = "radar/+/1/null";
$apiEndpoint = "http://localhost:8000/api/telemetry";

$connectionSettings = (new ConnectionSettings)
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(60);

$mqtt = new MqttClient($server, $port, 'php-radar-client');
$mqtt->connect($connectionSettings, true);
echo "[" . date('H:i:s') . "] Client connected\n";

$mqtt->subscribe($subscribeTopic, function ($topic, $message) use () {

    $timestamp = date('H:i:s');
    echo "[$timestamp] Received message on [$topic]\n";

    $data = json_decode($message, true);
    if (!$data || !isset($data['payload'])) {
        echo "[$timestamp] Invalid payload\n";
        return;
    }

    $payload = $data['payload'];

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

        // Enviar para a API
        // try {
        //     $ch = curl_init($apiEndpoint);
        //     curl_setopt($ch, CURLOPT_POST, true);
        //     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($minuteStats));
        //     curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        //     curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        //     curl_exec($ch);
        //     curl_close($ch);
        // } catch (Exception $e) {
        //     echo "[$timestamp] Error sending minute_stats: " . $e->getMessage() . "\n";
        // }
    }

    // --- Heart/breath vitals ---
    if (isset($payload['heartbreath'])) {
        $raw = base64_decode($payload['heartbreath']);
        if (strlen($raw) !== 16) {
            echo "[$timestamp] Invalid heartbreath length\n";
            return;
        }

        $vitals = [
            "type"         => "vitals",
            "device_code"  => $payload['deviceCode'] ?? null,
            "respiracao"   => $raw[1],
            "rpm"          => $raw[2],
            "pessoas"      => $raw[15],
            "postura_code" => $raw[10],
        ];

        print_r($vitals);

        // Enviar para a API
        // try {
        //     $ch = curl_init($apiEndpoint);
        //     curl_setopt($ch, CURLOPT_POST, true);
        //     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($vitals));
        //     curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        //     curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        //     curl_exec($ch);
        //     curl_close($ch);
        // } catch (Exception $e) {
        //     echo "[$timestamp] Error sending vitals: " . $e->getMessage() . "\n";
        // }
    }
}, 0);

// ---------------- START LOOP ----------------
while (true) {
    $mqtt->loop(false); // non-blocking loop, processa mensagens em tempo real
    usleep(500000);     // 0.5s de espera para não consumir CPU em excesso
}
