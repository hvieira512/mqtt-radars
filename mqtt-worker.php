<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\Logger;
use App\QueueItem;
use Predis\Client as RedisClient;

$server   = $_ENV['MQTT_SERVER'] ?? '127.0.0.1';
$port     = $_ENV['MQTT_PORT'] ?? 1883;
$username = ($_ENV['MQTT_USERNAME'] ?? '') !== '' ? $_ENV['MQTT_USERNAME'] : null;
$password = ($_ENV['MQTT_PASSWORD'] ?? '') !== '' ? $_ENV['MQTT_PASSWORD'] : null;
$topic    = $_ENV['MQTT_TOPIC'] ?? '';
$clientId = $_ENV['MQTT_CLIENT_ID'] ?? 'php-radar-router';
$allowedLicenses = isset($_ENV['ALLOWED_LICENSES']) && $_ENV['ALLOWED_LICENSES'] !== ''
    ? explode(',', $_ENV['ALLOWED_LICENSES'])
    : null;

$redis = new RedisClient($_ENV['REDIS_URL'] ?? 'tcp://127.0.0.1:6379');

$settings = (new ConnectionSettings())
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(120);

function pushToForwardQueue(RedisClient $redis, string $idLicenca, string $topic, string $message): void
{
    $now = microtime(true);
    $queueKey = "mqtt:forward:$idLicenca";

    $redis->sadd('mqtt:forward:licenses', $idLicenca);
    $redis->rpush($queueKey, json_encode([
        'topic'        => $topic,
        'message'      => $message,
        'license'      => $idLicenca,
        'queued_at'    => time(),
        'queued_at_ms' => (int)round($now * 1000),
    ]));
}

function handleMqttMessage(string $topic, string $message, RedisClient $redis, ?array $allowedLicenses): void
{
    $parts = explode('/', $topic);
    if (count($parts) < 3) {
        Logger::warn("Invalid topic format: $topic");
        return;
    }

    $idLicenca = $parts[1] ?? null;
    if (!$idLicenca) {
        Logger::warn("No id_licenca in topic: $topic");
        return;
    }

    // A licenca entra no nome da chave: dois pontos colidiriam com as chaves internas.
    if (!QueueItem::isValidLicense((string)$idLicenca)) {
        Logger::warn("Rejected topic with invalid license segment: $topic");
        return;
    }

    if ($allowedLicenses !== null && !in_array($idLicenca, $allowedLicenses, true)) {
        return;
    }

    pushToForwardQueue($redis, $idLicenca, $topic, $message);

    Logger::info("Queued forward for license $idLicenca - topic: $topic");
}



function createMqttClient(string $server, int $port, string $clientId): MqttClient
{
    return new MqttClient($server, $port, $clientId);
}

Logger::info("MQTT Worker started");

$reconnectDelay = 2;
while (true) {
    try {
        $mqtt = createMqttClient($server, (int)$port, $clientId);
        $mqtt->connect($settings, true);
        Logger::info("MQTT connected");
        $mqtt->subscribe($topic, function ($topic, $message) use ($redis, $allowedLicenses) {
            handleMqttMessage($topic, $message, $redis, $allowedLicenses);
        }, 1);
        $reconnectDelay = 2;
        $mqtt->loop(true);
    } catch (\Exception $e) {
        // Um so ramo: ligacao perdida e ligacao recusada exigem ambas recuo.
        Logger::error("MQTT error: {$e->getMessage()}, reconnecting in {$reconnectDelay}s...");
        usleep($reconnectDelay * 1000000);
        $reconnectDelay = min($reconnectDelay * 2, 60);
    }
}
