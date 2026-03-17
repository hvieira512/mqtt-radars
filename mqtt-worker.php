<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use App\AlarmEngine;
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
    ->setKeepAliveInterval(120);

$mqtt = new MqttClient($server, (int)$port, 'php-radar-worker');

$parsers = [
    'position' => new PositionParser(),
    'heartbreath' => new HeartBreathParser(),
    'hbstatics' => new HbStaticsParser(),
    'posstatics' => new PosStaticsParser(),
];

// Track last alarms per person to prevent repeated alerts
$lastAlarms = [];

/**
 * Handles incoming MQTT messages
 */
function handleMqttMessage(string $message): void
{
    global $parsers, $repo, $lastAlarms;

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

        // Save event
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

        // Evaluate alarms
        $alarms = AlarmEngine::evaluate($parsed);
        foreach ($alarms as $alarm) {
            $alarmKey = "{$deviceCode}_{$alarm['person_index']}_{$alarm['alarm_type']}";

            // Only send alarm if level changed or new
            if (($lastAlarms[$alarmKey] ?? null) !== $alarm['level']) {
                $alarm['device_code'] = $deviceCode;
                if (!isset($alarm['message'])) {
                    $alarm['message'] = "Evento detectado: {$alarm['alarm_type']}";
                }
                $broadcast[] = $alarm;

                // Update last alarm state
                $lastAlarms[$alarmKey] = $alarm['level'];
            }
        }

        // Reset last alarms if posture/event is back to normal
        if (isset($parsed['people'])) {
            foreach ($parsed['people'] as $person) {
                $personIndex = $person['person_index'] ?? 0;
                $posture = $person['posture_state'] ?? '';
                $normalPostures = ['Standing', 'Walking', 'Sitting'];
                if (in_array($posture, $normalPostures)) {
                    foreach ($alarms as $a) {
                        if ($a['person_index'] === $personIndex) {
                            $key = "{$deviceCode}_{$personIndex}_{$a['alarm_type']}";
                            unset($lastAlarms[$key]);
                        }
                    }
                }
            }
        }

        // Always broadcast raw parsed data
        $parsed['device_code'] = $deviceCode;
        $broadcast[] = $parsed;
    }

    if (!empty($broadcast)) {
        $ch = curl_init("http://127.0.0.1:8081/broadcast");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($broadcast),
        ]);
        curl_exec($ch);
    }
}

/**
 * Connects and subscribes to MQTT
 */
function connectAndSubscribe(MqttClient $mqtt, ConnectionSettings $settings, string $topic)
{
    $mqtt->connect($settings, true);
    Logger::info("MQTT connected");

    $mqtt->subscribe($topic, function ($topic, $message) {
        handleMqttMessage($message);
    }, 0);
}

/**
 * Broadcast helper
 */
function broadcastToWebsocket(array $payload): void
{
    $ch = curl_init("http://127.0.0.1:8081/broadcast");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    curl_exec($ch);
}

Logger::info("MQTT Worker started");
while (true) {
    try {
        if (!$mqtt->isConnected()) {
            connectAndSubscribe($mqtt, $settings, $topic);
        }
        $mqtt->loop(true);
    } catch (DataTransferException $e) {
        Logger::error("MQTT connection lost: {$e->getMessage()}, reconnecting...");
        broadcastToWebsocket(['message' => 'MQTT connection lost, attempting reconnect']);
        sleep(3);
        try {
            connectAndSubscribe($mqtt, $settings, $topic);
        } catch (\Exception $e2) {
            Logger::error("MQTT reconnect failed: {$e2->getMessage()}");
            broadcastToWebsocket(['message' => 'MQTT reconnect attempt failed']);
            sleep(5);
        }
    } catch (\Exception $e) {
        Logger::error("Unexpected MQTT loop error: {$e->getMessage()}");
        broadcastToWebsocket(['message' => "Unexpected MQTT error: {$e->getMessage()}"]);
        sleep(5);
    }
}