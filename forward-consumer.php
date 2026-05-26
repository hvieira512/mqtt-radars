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
$batchSize = (int)($_ENV['FORWARD_BATCH_SIZE'] ?? 100);
$batchEnabled = true;

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
    if (trim($value) === '') return [];
    return array_values(array_filter(array_map('trim', explode(',', $value)), fn($l) => $l !== ''));
}

function getTargetUrlFromCrm(string $idLicenca, RedisClient $redis, int $cacheTtl): ?string
{
    $cacheKey = "crm:target:$idLicenca";
    $cached = $redis->get($cacheKey);
    if ($cached) return $cached;

    $targetUrl = rtrim($_ENV['TEST_TARGET_URL'] ?? '', '/');
    if ($targetUrl) {
        $redis->setex($cacheKey, $cacheTtl, $targetUrl);
        return $targetUrl;
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

function forwardSingle(
    string $targetUrl,
    string $topic,
    string $payload,
    int $connectTimeoutMs,
    int $timeoutMs
): array {
    $url = rtrim($targetUrl, '/') . '/modulos/radares/_ajax/radar-data-ingest.php';
    $payloadData = json_decode($payload, true);
    if (!is_array($payloadData)) $payloadData = [];

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

function forwardBatch(
    string $targetUrl,
    array $batch,
    int $connectTimeoutMs,
    int $timeoutMs
): array {
    $url = rtrim($targetUrl, '/') . '/modulos/radares/_ajax/radar-data-ingest.php';

    $messages = [];
    foreach ($batch as $item) {
        $payloadData = json_decode($item['message'] ?? '{}', true);
        if (!is_array($payloadData)) $payloadData = [];
        $messages[] = [
            'topic' => $item['topic'] ?? '',
            'payload' => $payloadData['payload'] ?? $payloadData,
        ];
    }

    $postData = json_encode([
        'batch' => true,
        'messages' => $messages,
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

    $result = [
        'ok' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'duration_ms' => nowMs() - $startedAtMs,
        'error' => $error,
        'response' => is_string($response) ? $response : '',
    ];

    if ($result['ok'] && $response) {
        $decoded = json_decode($response, true);
        $result['batch_results'] = $decoded['results'] ?? $decoded['batch_result'] ?? [];
    }

    return $result;
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

function handleBatchResult(
    RedisClient $redis,
    array $result,
    array $batch,
    string $queueKey,
    int $maxAttempts
): void {
    if ($result['ok']) {
        Logger::info(
            "Batch of " . count($batch) . " forwarded OK "
            . "HTTP {$result['http_code']} in {$result['duration_ms']}ms"
        );
        return;
    }

    Logger::warn(
        "Batch of " . count($batch) . " failed: "
        . "HTTP {$result['http_code']} in {$result['duration_ms']}ms {$result['error']}"
    );

    foreach ($batch as $item) {
        $item['attempts'] = ($item['attempts'] ?? 0) + 1;
        if ($item['attempts'] < $maxAttempts) {
            $redis->rpush($queueKey, json_encode($item));
        } else {
            $license = $item['license'] ?? 'unknown';
            $redis->rpush("mqtt:forward_failed:$license", json_encode($item));
        }
    }
}

Logger::info(
    'Forward consumer started'
        . ($licenseFilter ? " for license $licenseFilter" : ' for all licenses')
        . ' with batching (max ' . $batchSize . ' per request)'
        . ($dryRun ? ' in dry-run mode' : '')
);

if (getFlag($argv, '--no-batch') || filter_var($_ENV['FORWARD_DISABLE_BATCH'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
    Logger::warn('Single-message forwarding is disabled in this environment; forcing batch mode.');
}

while (true) {
    $processed = 0;

    foreach (getQueueKeys($redis, $licenseFilter, $excludeLicenses) as $queueKey) {
        $batch = [];
        for ($i = 0; $i < $batchSize; $i++) {
            $item = $redis->lpop($queueKey);
            if ($item === null) break;
            $batch[] = json_decode($item, true);
        }

        if (empty($batch)) continue;

        $license = $batch[0]['license'] ?? '';
        $targetUrl = getTargetUrlFromCrm($license, $redis, $cacheTtl);

        if (!$targetUrl) {
            foreach ($batch as $item) {
                $item['attempts'] = ($item['attempts'] ?? 0) + 1;
                $redis->rpush("mqtt:forward_failed:$license", json_encode($item));
            }
            continue;
        }

        if ($dryRun) {
            Logger::info("[$license] DRY RUN -> $targetUrl (batch of " . count($batch) . ")");
            continue;
        }

        $start = nowMs();
        $result = forwardBatch($targetUrl, $batch, $connectTimeoutMs, $timeoutMs);
        $elapsed = nowMs() - $start;

        Logger::info(
            "[$license] batch=" . count($batch)
            . " HTTP {$result['http_code']} {$elapsed}ms"
            . ($result['ok'] ? '' : ' FAILED')
        );

        if (!$result['ok']) {
            handleBatchResult($redis, $result, $batch, $queueKey, $maxAttempts);
        }

        $processed += count($batch);
    }

    if ($processed === 0) {
        usleep($sleepMs * 1000);
    }
}
