<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use App\EventTypes;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\DataTransferException;
use App\RadarRepository;
use App\Logger;
use App\Parsers\PositionParser;
use App\Parsers\HeartBreathParser;
use App\Parsers\HbStaticsParser;
use App\Parsers\PosStaticsParser;

$repo = new RadarRepository();

$server   = $_ENV['MQTT_SERVER'] ?? '127.0.0.1';
$port     = $_ENV['MQTT_PORT'] ?? 1883;
$username = $_ENV['MQTT_USERNAME'] ?? '';
$password = $_ENV['MQTT_PASSWORD'] ?? '';
$topic    = $_ENV['MQTT_TOPIC'] ?? 'radar/frontend';

$settings = (new ConnectionSettings())
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(120); // Increase keepalive for stability

$mqtt = new MqttClient($server, (int)$port, 'php-radar-worker');

$parsers = [
    'position' => new PositionParser(),
    'heartbreath' => new HeartBreathParser(),
    'hbstatics' => new HbStaticsParser(),
    'posstatics' => new PosStaticsParser(),
];

/**
 * Handles incoming MQTT messages
 *
 * @param [type] $message
 * @return void
 */
function handleMqttMessage($message)
{
    global $parsers, $repo;

    $data = json_decode($message, true);
    if (!$data || !isset($data['payload'])) return;

    $payload = $data['payload'];
    $deviceCode = $payload['deviceCode'] ?? null;

    if (!$deviceCode) return;

    $deviceId = $repo->getDeviceId($deviceCode);

    $broadcast = [];

    foreach ($parsers as $key => $parser) {

        if (!isset($payload[$key])) continue;

        $parsed = $parser->parse($payload[$key], $deviceCode);
        if (!$parsed) continue;

        Logger::logData($parsed);

        $eventTypeId = EventTypes::fromString($parsed['type']);
        $eventId = $repo->createEvent($deviceId, $eventTypeId);

        switch ($parsed['type']) {
            case 'position':
                $repo->insertPosition($eventId, $parsed['people']);
                break;

            case 'vitals':
                $repo->insertVitals($eventId, $parsed);
                break;

            case 'minute_stats':
                $repo->insertMinuteStats($eventId, $parsed);
                break;

            case 'hbstatics':
                $repo->insertHbStatics($eventId, $parsed);
                break;
        }

        $parsed['device_code'] = $deviceCode;
        $broadcast[] = $parsed;
    }

    if (!empty($broadcast)) {

        $ch = curl_init("http://127.0.0.1:8081/broadcast");

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($broadcast)
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}

/**
 * Connects and subscribes to the MQTT broker
 *
 * @param MqttClient $mqtt
 * @param ConnectionSettings $settings
 * @param string $topic
 * @return void
 */
function connectAndSubscribe(MqttClient $mqtt, ConnectionSettings $settings, string $topic) {
    $mqtt->connect($settings, true);
    Logger::info("MQTT connected");

    $mqtt->subscribe($topic, function ($topic, $message) {
        handleMqttMessage($message);
    }, 0);
}

/**
 * Main loop with reconnect logic
 */
while (true) {
    try {
        if (!$mqtt->isConnected()) {
            connectAndSubscribe($mqtt, $settings, $topic);
        }

        $mqtt->loop(true); // blocking
    } catch (DataTransferException $e) {
        Logger::error("MQTT connection lost: {$e->getMessage()}, reconnecting...");
        sleep(3); // brief pause before reconnect
        try {
            connectAndSubscribe($mqtt, $settings, $topic);
        } catch (\Exception $e2) {
            Logger::error("MQTT reconnect failed: {$e2->getMessage()}");
            sleep(5); // wait longer if reconnect fails
        }
    } catch (\Exception $e) {
        Logger::error("Unexpected error in MQTT loop: {$e->getMessage()}");
        sleep(5);
    }
}