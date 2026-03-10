<?php

require __DIR__ . '/bootstrap.php';

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use App\RadarRepository;
use App\Logger;
use App\Parsers\PositionParser;
use App\Parsers\PosStaticsParser;
use App\Parsers\HeartBreathParser;
use App\Parsers\HbStaticsParser;
use App\WebLogger;
use WebSocket\Client;

$repo = new RadarRepository();

$server   = $_ENV['MQTT_SERVER'];
$port     = isset($_ENV['MQTT_PORT']) && $_ENV['MQTT_PORT'] !== '' ? (int) $_ENV['MQTT_PORT'] : 1883;
$username = $_ENV['MQTT_USERNAME'];
$password = $_ENV['MQTT_PASSWORD'];
$topic    = $_ENV['MQTT_TOPIC'];

$connectionSettings = (new ConnectionSettings)
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(60);

$mqtt = new MqttClient($server, $port, 'php-radar-client');
$mqtt->connect($connectionSettings, true);
Logger::info("[" . date('H:i:s') . "] MQTT client connected\n");

$wsClient = new Client("ws://127.0.0.1:8080");
WebLogger::initWebSocket($wsClient, true);

$parsers = [
    'position'     => new PositionParser(),
    'posstatics'   => new PosStaticsParser(),
    'heartbreath'  => new HeartBreathParser(),
    'hbstatics'    => new HbStaticsParser(),
];

$mqtt->subscribe($topic, function ($_topic, $message) use ($parsers, $repo) {
    $timestamp = date('H:i:s');
    $data = json_decode($message, true);

    if (!$data || !isset($data['payload'])) {
        Logger::warn("[$timestamp] Invalid payload\n");
        return;
    }

    $payload = $data['payload'];
    $deviceCode = $payload['deviceCode'] ?? null;

    foreach ($parsers as $key => $parser) {
        if (!isset($payload[$key])) continue;

        $parsed = $parser->parse($payload[$key], $deviceCode);
        if (!$parsed) continue;

        WebLogger::send($parsed);

        $deviceId = $repo->getDeviceId($deviceCode);
        $eventId = $repo->createEvent($deviceId, $parsed['type']);

        match ($parsed['type']) {
            'position'      => $repo->insertPosition($eventId, $parsed['people']),
            'minute_stats'  => $repo->insertMinuteStats($eventId, $parsed),
            'vitals'        => $repo->insertVitals($eventId, $parsed),
            'hbstatics'     => $repo->insertHbStatics($eventId, $parsed),
            default         => null,
        };
    }
}, 0);

// Start MQTT loop
$mqtt->loop(true);
$mqtt->disconnect();
