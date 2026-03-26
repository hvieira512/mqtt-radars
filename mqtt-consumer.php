<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use App\Logger;
use Predis\Client as RedisClient;

$redis = new RedisClient($_ENV['REDIS_URL'] ?? 'tcp://127.0.0.1:6379');
$maxRetries = 5;
$retryDelay = 5;

function forwardToClient(string $targetUrl, array $data): bool
{
    $ch = curl_init("$targetUrl/api/radar-data/ingest");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_TIMEOUT        => 30
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return $httpCode >= 200 && $httpCode < 300;
}

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

function processQueue(RedisClient $redis, string $idLicenca, int $maxRetries, int $retryDelay): void
{
    $queueKey = "mqtt:ingest:$idLicenca";
    $cacheTtl = 300;

    while (true) {
        $item = $redis->lpop($queueKey);
        if (!$item) {
            break;
        }

        $data = json_decode($item, true);
        if (!$data) {
            Logger::error("Invalid JSON in queue for license $idLicenca");
            continue;
        }

        $targetUrl = getTargetUrlFromCrm($idLicenca, $redis, $cacheTtl);
        if (!$targetUrl) {
            Logger::error("No target URL for license $idLicenca, re-queuing");
            $redis->rpush($queueKey, $item);
            sleep($retryDelay);
            continue;
        }

        $success = false;
        $attempts = 0;

        while (!$success && $attempts < $maxRetries) {
            $attempts++;

            if (forwardToClient($targetUrl, $data)) {
                $success = true;
                Logger::info("Forwarded message to $targetUrl");
            } else {
                Logger::warn("Forward failed (attempt $attempts/$maxRetries) to $targetUrl");
                sleep($retryDelay);
            }
        }

        if (!$success) {
            Logger::error("All retries exhausted for license $idLicenca, moving to dead letter");
            $redis->rpush("mqtt:deadletter:$idLicenca", $item);
        }
    }
}

Logger::info("MQTT Consumer started");

$knownLicenses = [];
$scanInterval = 5;

while (true) {
    try {
        $keys = $redis->keys('mqtt:ingest:*');

        foreach ($keys as $key) {
            $idLicenca = str_replace('mqtt:ingest:', '', $key);

            if (!in_array($idLicenca, $knownLicenses)) {
                $knownLicenses[] = $idLicenca;
                Logger::info("Discovered new license queue: $idLicenca");
            }

            $len = $redis->llen($key);
            if ($len > 0) {
                Logger::info("Processing $len messages for license $idLicenca");
                processQueue($redis, $idLicenca, $maxRetries, $retryDelay);
            }
        }

        sleep($scanInterval);
    } catch (\Exception $e) {
        Logger::error("Consumer error: {$e->getMessage()}");
        sleep($retryDelay);
    }
}

