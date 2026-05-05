<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use App\Logger;
use Predis\Client as RedisClient;

$redis = new RedisClient($_ENV['REDIS_URL'] ?? 'tcp://127.0.0.1:6379');
$cacheTtl = (int)($_ENV['CRM_CACHE_TTL'] ?? 3600);
$sleepMs = (int)($_ENV['FORWARD_SLEEP_MS'] ?? 50);
$connectTimeoutMs = (int)($_ENV['FORWARD_CONNECT_TIMEOUT_MS'] ?? 750);
$timeoutMs = (int)($_ENV['FORWARD_TIMEOUT_MS'] ?? 5000);
$maxAttempts = (int)($_ENV['FORWARD_MAX_ATTEMPTS'] ?? 3);
$licenseFilter = getArgValue($argv, '--license') ?: ($_ENV['FORWARD_LICENSE'] ?? null);
$excludeLicenses = parseLicenseList(getArgValue($argv, '--exclude') ?: ($_ENV['FORWARD_EXCLUDE_LICENSES'] ?? ''));
$dryRun = getFlag($argv, '--dry-run') || filter_var($_ENV['FORWARD_DRY_RUN'] ?? false, FILTER_VALIDATE_BOOLEAN);

function getArgValue(array $argv, string $name): ?string
{
    foreach ($argv as $index => $arg) {
        if ($arg === $name && isset($argv[$index + 1])) {
            return $argv[$index + 1];
        }

        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }

    return null;
}

function getFlag(array $argv, string $name): bool
{
    return in_array($name, $argv, true);
}

function nowMs(): int
{
    return (int)round(microtime(true) * 1000);
}

function parseLicenseList(string $value): array
{
    if (trim($value) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $value)), fn($license) => $license !== ''));
}

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
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => "id_licenca=$idLicenca",
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    if ($httpCode === 200 && $response) {
        $targetUrl = trim($response);
        $redis->setex($cacheKey, $cacheTtl, $targetUrl);
        return $targetUrl;
    }

    Logger::error("CRM lookup failed for license $idLicenca: HTTP $httpCode $error");
    return null;
}

function forwardToTarget(
    string $targetUrl,
    string $topic,
    string $payload,
    int $connectTimeoutMs,
    int $timeoutMs
): array {
    $url = rtrim($targetUrl, '/') . '/modulos/radares/_ajax/radar-data-ingest.php';
    $payloadData = json_decode($payload, true);
    if (!is_array($payloadData)) {
        $payloadData = [];
    }

    $postData = json_encode([
        'topic' => $topic,
        'payload' => $payloadData['payload'] ?? $payloadData,
    ]);

    $startedAtMs = nowMs();
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => $connectTimeoutMs,
        CURLOPT_TIMEOUT_MS => $timeoutMs,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    return [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'duration_ms' => nowMs() - $startedAtMs,
        'error' => $error,
        'response' => is_string($response) ? substr($response, 0, 500) : '',
    ];
}

function getQueueKeys(RedisClient $redis, ?string $licenseFilter, array $excludeLicenses): array
{
    if ($licenseFilter) {
        return ["mqtt:forward:$licenseFilter"];
    }

    $licenses = $redis->smembers('mqtt:forward:licenses');
    if (!empty($excludeLicenses)) {
        $licenses = array_values(array_diff($licenses, $excludeLicenses));
    }

    sort($licenses);
    return array_map(fn($license) => "mqtt:forward:$license", $licenses);
}

function processQueueItem(
    RedisClient $redis,
    string $item,
    int $cacheTtl,
    int $connectTimeoutMs,
    int $timeoutMs,
    int $maxAttempts,
    bool $dryRun
): void {
    $data = json_decode($item, true);
    if (!is_array($data)) {
    }

    $idLicenca = (string)($data['license'] ?? '');
    $topic = (string)($data['topic'] ?? '');
    $message = (string)($data['message'] ?? '');
    $attempts = (int)($data['attempts'] ?? 0);
    $queuedAtMs = isset($data['queued_at_ms']) ? (int)$data['queued_at_ms'] : null;

    if ($idLicenca === '' || $topic === '' || $message === '') {
        Logger::warn('Forward queue item missing license, topic, or message');
        return;
    }

    $targetUrl = getTargetUrlFromCrm($idLicenca, $redis, $cacheTtl);
    if (!$targetUrl) {
        $data['attempts'] = $attempts + 1;
        $redis->rpush("mqtt:forward_failed:$idLicenca", json_encode($data));
        return;
    }

    $queueDelay = $queuedAtMs ? nowMs() - $queuedAtMs : null;
    Logger::info("Forwarding to $targetUrl - topic: $topic - queue_delay_ms: " . ($queueDelay ?? 'n/a'));

    if ($dryRun) {
        Logger::info("[$idLicenca] DRY RUN -> $targetUrl");
        return;
    }

    $result = forwardToTarget($targetUrl, $topic, $message, $connectTimeoutMs, $timeoutMs);
    if ($result['ok']) {
        Logger::info("[$idLicenca] -> $targetUrl HTTP {$result['http_code']} in {$result['duration_ms']}ms");
        return;
    }

    Logger::warn(
        "Forward to $targetUrl failed: HTTP {$result['http_code']} in {$result['duration_ms']}ms {$result['error']}"
    );

    $data['attempts'] = $attempts + 1;
    $data['last_error'] = $result['error'];
    $data['last_http_code'] = $result['http_code'];
    $data['last_failed_at'] = time();

    if ($data['attempts'] < $maxAttempts) {
        $redis->rpush("mqtt:forward:$idLicenca", json_encode($data));
        return;
    }

    $redis->rpush("mqtt:forward_failed:$idLicenca", json_encode($data));
}

Logger::info(
    'Forward consumer started'
        . ($licenseFilter ? " for license $licenseFilter" : ' for all licenses')
        . (!$licenseFilter && $excludeLicenses ? ' excluding licenses ' . implode(',', $excludeLicenses) : '')
        . ($dryRun ? ' in dry-run mode' : '')
        . " (connect_timeout_ms=$connectTimeoutMs timeout_ms=$timeoutMs)"
);

while (true) {
    $processed = 0;

    foreach (getQueueKeys($redis, $licenseFilter, $excludeLicenses) as $queueKey) {
        $item = $redis->lpop($queueKey);
        if (!$item) {
            continue;
        }

        processQueueItem($redis, $item, $cacheTtl, $connectTimeoutMs, $timeoutMs, $maxAttempts, $dryRun);
        $processed++;
    }

    if ($processed === 0) {
        usleep($sleepMs * 1000);
    }
}
