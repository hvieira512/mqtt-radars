<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\DataTransferException;
use App\Logger;
use Predis\Client as RedisClient;

$server   = $_ENV['MQTT_SERVER'] ?? '127.0.0.1';
$port     = $_ENV['MQTT_PORT'] ?? 1883;
$username = $_ENV['MQTT_USERNAME'] ?? null;
$password = $_ENV['MQTT_PASSWORD'] ?? null;
$topic    = $_ENV['MQTT_TOPIC'] ?? '';
$clientId = $_ENV['MQTT_CLIENT_ID'] ?? 'php-radar-router';

$redis = new RedisClient($_ENV['REDIS_URL'] ?? 'tcp://127.0.0.1:6379');

$settings = (new ConnectionSettings())
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(120);

function pushToQueue(RedisClient $redis, string $idLicenca, string $topic, string $message): void
{
    $queueKey = "mqtt:ingest:$idLicenca";
    $redis->rpush($queueKey, json_encode([
        'topic'     => $topic,
        'message'  => $message,
        'license'  => $idLicenca,
        'queued_at' => time()
    ]));
}

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

function publishToRedis(RedisClient $redis, string $idLicenca, string $topic, string $message): void
{
    $channel = "radar:ingest:$idLicenca";
    $payload = json_encode([
        'topic'    => $topic,
        'message' => $message,
        'license' => $idLicenca,
        'ts'      => time()
    ]);
    $redis->publish($channel, $payload);
    Logger::info("Published to Redis channel: $channel");
}

function handleMqttMessage(string $topic, string $message, RedisClient $redis): void
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

    pushToForwardQueue($redis, $idLicenca, $topic, $message);
    pushToQueue($redis, $idLicenca, $topic, $message);
    publishToRedis($redis, $idLicenca, $topic, $message);

    Logger::info("Queued forward for license $idLicenca - topic: $topic");
}



Logger::info("MQTT Worker started");

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
        $mqtt->subscribe($topic, function ($topic, $message) use ($redis) {
            handleMqttMessage($topic, $message, $redis);
        }, 1);
        $reconnectDelay = 2;
        $mqtt->loop(true);
    } catch (DataTransferException $e) {
        Logger::error("MQTT connection lost: {$e->getMessage()}, reconnecting in {$reconnectDelay}s...");
        usleep($reconnectDelay * 1000000);
        $reconnectDelay = min($reconnectDelay * 2, 60);
    } catch (\Exception $e) {
        Logger::error("Unexpected error: {$e->getMessage()}");
        sleep(5);
    }
}
