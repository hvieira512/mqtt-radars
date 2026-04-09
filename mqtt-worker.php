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

$redis = new RedisClient($_ENV['REDIS_URL'] ?? 'tcp://127.0.0.1:6379');
$cacheTtl = 3600;

$settings = (new ConnectionSettings())
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(120);

function getTargetUrlFromCrm(string $idLicenca, RedisClient $redis, int $cacheTtl): ?string
{
    $cacheKey = "crm:target:$idLicenca";

    $cached = $redis->get($cacheKey);
    if ($cached) {
        return $cached;
    }

    $crmUrl = ($_ENV['CRM_URL'] ?? 'https://crm.hitcare.net/api/get.url.php');
    $ch = curl_init($crmUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_POSTFIELDS      => "id_licenca=$idLicenca",
        CURLOPT_TIMEOUT         => 5,
        CURLOPT_SSL_VERIFYPEER  => false,
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
}

function forwardToTarget(string $targetUrl, string $topic, string $payload): bool
{
    $ch = curl_init($targetUrl . '/modulos/radares/_ajax/radar-data-ingest.php');

    $payloadData = json_decode($payload, true);

    $postData = json_encode([
        'topic' => $topic,
        'payload' => $payloadData['payload'] ?? $payloadData
    ]);

    Logger::info("Forwarding to $targetUrl - topic: $topic - payload: " . $payload);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    Logger::warn("Forward to $targetUrl failed: HTTP $httpCode");
    return false;
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
        Logger::warn("No target URL for license $idLicenca");
    } else {
        if (forwardToTarget($targetUrl, $topic, $message)) {
            Logger::info("[$idLicenca] -> $targetUrl");
        }
    }

    pushToQueue($redis, $idLicenca, $topic, $message);

    publishToRedis($redis, $idLicenca, $topic, $message);
}



Logger::info("MQTT Worker started");

function createMqttClient(string $server, int $port): MqttClient
{
    return new MqttClient($server, $port, 'php-radar-router');
}

Logger::info("MQTT Worker started");

$reconnectDelay = 2;
while (true) {
    try {
        $mqtt = createMqttClient($server, (int)$port);
        $mqtt->connect($settings, true);
        Logger::info("MQTT connected");
        $mqtt->subscribe($topic, function ($topic, $message) use ($redis, $cacheTtl) {
            handleMqttMessage($topic, $message, $redis, $cacheTtl);
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
