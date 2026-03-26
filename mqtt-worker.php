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
$username = $_ENV['MQTT_USERNAME'] ?? '';
$password = $_ENV['MQTT_PASSWORD'] ?? '';
$topic    = 'radar/+/+';

$redis = new RedisClient($_ENV['REDIS_URL'] ?? 'tcp://127.0.0.1:6379');
$cacheTtl = 300;

$settings = (new ConnectionSettings())
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(120);

$mqtt = new MqttClient($server, (int)$port, 'php-radar-router');

function getTargetUrlFromCrm(string $idLicenca, RedisClient $redis, int $cacheTtl): ?string
{
    $cacheKey = "crm:target:$idLicenca";

    $cached = $redis->get($cacheKey);
    if ($cached) {
        return $cached;
    }

    $ch = curl_init($_ENV['CRM_API_URL'] . '/api/get.url.php');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_POSTFIELDS      => "id_licenca=$idLicenca",
        CURLOPT_TIMEOUT         => 5
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 200 && $response) {
        $targetUrl = trim($response);
        $redis->setex($cacheKey, $cacheTtl, $targetUrl);
        return $targetUrl;
    }

    Logger::error("CRM lookup failed for license $idLicenca: HTTP $httpCode");
    return null;
}

function pushToQueue(RedisClient $redis, string $idLicenca, string $topic, string $message): void
{
    $queueKey = "mqtt:ingest:$idLicenca";
    $redis->rpush($queueKey, json_encode([
        'topic'     => $topic,
        'message'  => $message,
        'license'  => $idLicenca,
        'queued_at' => time()
    ]));
    Logger::info("Pushed to queue for license $idLicenca");
}

function broadcastToWebsocket(array $payload): void
{
    $ch = curl_init("http://127.0.0.1:8081/broadcast");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    curl_exec($ch);
}

function handleMqttMessage(string $topic, string $message, RedisClient $redis, int $cacheTtl): void
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

    $targetUrl = getTargetUrlFromCrm($idLicenca, $redis, $cacheTtl);
    if (!$targetUrl) {
        Logger::error("No target URL for license $idLicenca, queuing for retry");
        pushToQueue($redis, $idLicenca, $topic, $message);
        return;
    }

    pushToQueue($redis, $idLicenca, $topic, $message);
    Logger::info("Message routed to license $idLicenca -> $targetUrl");
}

function connectAndSubscribe(MqttClient $mqtt, ConnectionSettings $settings, string $topic, RedisClient $redis, int $cacheTtl)
{
    try {
        $mqtt->connect($settings, true);
        Logger::info("MQTT connected");

        $mqtt->subscribe($topic, function ($topic, $message) use ($redis, $cacheTtl) {
            handleMqttMessage($topic, $message, $redis, $cacheTtl);
        }, 1);

        broadcastToWebsocket([
            'message'  => 'MQTT router connected'
        ]);
    } catch (\Exception $e) {
        Logger::error("MQTT connect failed: {$e->getMessage()}");
        broadcastToWebsocket([
            'error'  => "Failed to connect to MQTT broker: {$e->getMessage()}"
        ]);
        throw $e;
    }
}

Logger::info("MQTT Router started");

while (true) {
    try {
        if (!$mqtt->isConnected()) {
            connectAndSubscribe($mqtt, $settings, $topic, $redis, $cacheTtl);
        }
        $mqtt->loop(true);
    } catch (DataTransferException $e) {
        Logger::error("MQTT connection lost: {$e->getMessage()}, reconnecting...");
        broadcastToWebsocket([
            'error' => "MQTT connection lost: {$e->getMessage()}, reconnecting..."
        ]);
        sleep(3);
    } catch (\Exception $e) {
        Logger::error("Unexpected MQTT loop error: {$e->getMessage()}");
        broadcastToWebsocket([
            'error' => "Unexpected MQTT error: {$e->getMessage()}"
        ]);
        sleep(5);
    }
}

