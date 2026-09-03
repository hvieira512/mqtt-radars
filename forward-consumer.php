<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use App\BatchOutcome;
use App\Logger;
use App\OutboundBatch;
use App\QueueItem;
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
// Teto da fila de falhas: mantem as ultimas N como amostra. 0 desliga o teto.
$failedCap = (int)($_ENV['FORWARD_FAILED_CAP'] ?? 5000);
// Espera entre tentativas do mesmo lote, a dobrar a cada uma.
$retryBaseMs = (int)($_ENV['FORWARD_RETRY_BASE_MS'] ?? 1000);
$retryMaxMs = (int)($_ENV['FORWARD_RETRY_MAX_MS'] ?? 30000);
// A verificacao de certificado so se desliga onde o destino a nao suporte.
$tlsInsecure = filter_var($_ENV['FORWARD_TLS_INSECURE'] ?? false, FILTER_VALIDATE_BOOLEAN);

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

function getTargetUrlFromCrm(string $idLicenca, RedisClient $redis, int $cacheTtl, bool $tlsInsecure = false): ?string
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
        CURLOPT_SSL_VERIFYPEER => !$tlsInsecure,
        CURLOPT_SSL_VERIFYHOST => $tlsInsecure ? 0 : 2,
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

function forwardBatch(
    string $targetUrl,
    array $batch,
    int $connectTimeoutMs,
    int $timeoutMs,
    bool $tlsInsecure = false
): array {
    $url = rtrim($targetUrl, '/') . '/modulos/radares/_ajax/radar-data-ingest.php';

    $postData = json_encode([
        'batch' => true,
        'messages' => OutboundBatch::build($batch),
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
        // O que segue e telemetria de saude de pessoas identificadas: a
        // verificacao so cede onde o destino comprovadamente a nao suporte.
        CURLOPT_SSL_VERIFYPEER => !$tlsInsecure,
        CURLOPT_SSL_VERIFYHOST => $tlsInsecure ? 0 : 2,
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

/**
 * Lista onde ficam as mensagens em transito.
 *
 * Entre sair da fila e a plataforma confirmar, uma mensagem tem de existir em
 * algum sitio: com LPOP so existia na memoria do processo, e um reinicio
 * durante o pedido — que pode demorar ate FORWARD_TIMEOUT_MS — perdia o lote
 * inteiro sem deixar rasto.
 */
function processingKey(string $queueKey): string
{
    return "$queueKey:processing";
}

/**
 * Devolve a fila o que ficou em transito quando o processo morreu.
 *
 * Percorre a lista de tras para a frente para as mensagens reentrarem pela
 * cabeca na ordem original. Assume um consumidor por licenca: com dois, um
 * deles reclamaria o lote que o outro tem em voo.
 */
function recoverProcessing(RedisClient $redis, string $queueKey): int
{
    $processingKey = processingKey($queueKey);
    $recovered = 0;

    while ($redis->lmove($processingKey, $queueKey, 'RIGHT', 'LEFT') !== null) {
        $recovered++;
    }

    if ($recovered > 0) {
        Logger::warn("Recovered $recovered in-flight message(s) from $processingKey");
    }

    return $recovered;
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

    // O conjunto so cresce e ja acumulou entradas escritas a partir de topicos
    // malformados. Uma licenca invalida daria uma chave de fila que colide com
    // as chaves internas — a lista de transito, por exemplo.
    $licenses = array_values(array_filter($licenses, fn($license) => QueueItem::isValidLicense((string)$license)));

    sort($licenses);
    return array_map(fn($license) => "mqtt:forward:$license", $licenses);
}

/**
 * Escreve uma mensagem na fila de falhas, com o que a plataforma respondeu.
 *
 * Sem estes campos nao ha como saber, mais tarde, se a entrega falhou por 422,
 * por 500 ou por a ligacao ter sido recusada.
 */
function recordFailure(RedisClient $redis, array $item, array $result, string $reason, int $failedCap): void
{
    $license = (string)($item['license'] ?? 'unknown');

    $item['last_http_code'] = (int)($result['http_code'] ?? 0);
    $item['last_error'] = (string)($result['error'] ?? '');
    $item['last_response'] = substr((string)($result['response'] ?? ''), 0, 500);
    $item['last_reason'] = $reason;
    $item['last_failed_at'] = time();

    $key = "mqtt:forward_failed:$license";
    $redis->rpush($key, json_encode($item));

    // Ninguem consome esta fila. Sem teto, chegou a ocupar 925 MB num Redis de 4 GB.
    if ($failedCap > 0) {
        $redis->ltrim($key, -$failedCap, -1);
    }
}

/**
 * Contadores por resultado e codigo HTTP.
 *
 * Responde a "o que esta a falhar" com um HGETALL, em vez de obrigar a
 * percorrer listas de centenas de milhares de elementos.
 */
function countOutcome(RedisClient $redis, string $license, string $outcome, int $httpCode, int $count): void
{
    if ($count > 0) {
        $redis->hincrby("mqtt:forward:stats:$license", "$outcome:$httpCode", $count);
    }
}

/**
 * Aplica a decisao do BatchOutcome ao lote.
 *
 * @return bool se a falha e transitoria e justifica espera antes do proximo lote
 */
function handleBatchOutcome(
    RedisClient $redis,
    array $decision,
    array $result,
    array $batch,
    string $queueKey,
    string $license,
    int $maxAttempts,
    int $failedCap
): bool {
    $httpCode = (int)($result['http_code'] ?? 0);

    if ($decision['outcome'] === BatchOutcome::REJECTED) {
        // So contam os indices que existem mesmo no lote. Se a plataforma
        // nomear posicoes fora dele, nada seria removido, o lote voltaria
        // inteiro a fila e o ciclo repetia-se sem fim e sem progresso.
        $rejected = array_intersect_key($decision['rejected'], $batch);

        // Sem indices utilizaveis a recusa nao se consegue localizar, e o lote
        // inteiro fica sem destino.
        if (empty($rejected)) {
            if (!empty($decision['rejected'])) {
                Logger::warn(
                    "[$license] platform named " . count($decision['rejected'])
                    . " rejected index(es) outside the batch of " . count($batch)
                    . " — treating the whole batch as rejected"
                );
            }
            foreach ($batch as $item) {
                recordFailure($redis, $item, $result, $decision['reason'], $failedCap);
            }
            countOutcome($redis, $license, 'rejected', $httpCode, count($batch));
            Logger::warn(
                "[$license] batch of " . count($batch) . " rejected without per-message detail: "
                . "HTTP $httpCode {$decision['reason']}"
            );

            return false;
        }

        // A plataforma reverte o lote inteiro por causa das mensagens que
        // nomeia. Retirando-as, as restantes voltam a fila e seguem no proximo
        // ciclo em vez de morrerem com elas.
        $survivors = 0;
        foreach ($batch as $index => $item) {
            if (isset($rejected[$index])) {
                recordFailure($redis, $item, $result, $rejected[$index], $failedCap);
                continue;
            }
            $redis->rpush($queueKey, json_encode($item));
            $survivors++;
        }

        countOutcome($redis, $license, 'rejected', $httpCode, count($rejected));
        Logger::warn(
            "[$license] " . count($rejected) . " message(s) rejected, $survivors requeued: "
            . implode('; ', array_map(
                fn($i, $motivo) => "#$i $motivo",
                array_keys($rejected),
                $rejected
            ))
        );

        return false;
    }

    // Transitoria: vale a pena repetir o lote completo.
    $exhausted = 0;
    foreach ($batch as $item) {
        $item['attempts'] = ($item['attempts'] ?? 0) + 1;
        if ($item['attempts'] < $maxAttempts) {
            $redis->rpush($queueKey, json_encode($item));
            continue;
        }
        recordFailure($redis, $item, $result, $decision['reason'], $failedCap);
        $exhausted++;
    }

    countOutcome($redis, $license, 'retry', $httpCode, count($batch));
    Logger::warn(
        "[$license] batch of " . count($batch) . " failed (transient): HTTP $httpCode "
        . "{$decision['reason']}" . ($exhausted > 0 ? " — $exhausted gave up" : '')
    );

    return true;
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

// O que ficou em transito numa paragem anterior volta a fila antes de comecar.
foreach (getQueueKeys($redis, $licenseFilter, $excludeLicenses) as $queueKey) {
    recoverProcessing($redis, $queueKey);
}

while (true) {
    $processed = 0;

    foreach (getQueueKeys($redis, $licenseFilter, $excludeLicenses) as $queueKey) {
        $processingKey = processingKey($queueKey);
        // A licenca sai da chave da fila, nao do primeiro elemento: um elemento
        // corrompido a abrir o lote deixava a licenca vazia, e as falhas iam
        // parar a uma chave sem licenca nenhuma.
        $license = QueueItem::licenseFromQueueKey($queueKey);

        $batch = [];
        $unreadable = 0;
        for ($i = 0; $i < $batchSize; $i++) {
            // LMOVE em vez de LPOP: a mensagem sai da fila e entra na lista de
            // transito na mesma operacao, e nunca fica so em memoria.
            $raw = $redis->lmove($queueKey, $processingKey, 'LEFT', 'RIGHT');
            if ($raw === null) break;

            $item = QueueItem::parse($raw, $license);
            if ($item === null) {
                // Nao e uma mensagem. Guarda-se em separado para nao voltar a
                // entrar no lote: com reinicio automatico e recuperacao do
                // transito, um elemento ilegivel repetia-se sem fim.
                $invalidKey = "mqtt:forward_invalid:$license";
                $redis->rpush($invalidKey, $raw);
                if ($failedCap > 0) {
                    $redis->ltrim($invalidKey, -$failedCap, -1);
                }
                $unreadable++;
                continue;
            }

            $batch[] = $item;
        }

        if ($unreadable > 0) {
            countOutcome($redis, $license, 'unreadable', 0, $unreadable);
            Logger::warn("[$license] $unreadable unreadable queue item(s) moved to mqtt:forward_invalid:$license");
        }

        if (empty($batch)) {
            $redis->del($processingKey);
            continue;
        }

        $targetUrl = getTargetUrlFromCrm($license, $redis, $cacheTtl, $tlsInsecure);

        if (!$targetUrl) {
            $semDestino = ['http_code' => 0, 'error' => 'CRM did not resolve a target URL', 'response' => ''];
            foreach ($batch as $item) {
                $item['attempts'] = ($item['attempts'] ?? 0) + 1;
                recordFailure($redis, $item, $semDestino, 'target unresolved', $failedCap);
            }
            countOutcome($redis, (string)$license, 'unresolved', 0, count($batch));
            $redis->del($processingKey);
            continue;
        }

        if ($dryRun) {
            Logger::info("[$license] DRY RUN -> $targetUrl (batch of " . count($batch) . ")");
            // Em ensaio nada e entregue, por isso nada se consome.
            recoverProcessing($redis, $queueKey);
            continue;
        }

        $start = nowMs();
        $result = forwardBatch($targetUrl, $batch, $connectTimeoutMs, $timeoutMs, $tlsInsecure);
        $elapsed = nowMs() - $start;

        // O codigo HTTP nao chega para decidir: um lote revertido chega como
        // 200 sempre que a plataforma emita output antes de responder.
        $decision = BatchOutcome::classify($result);

        if ($decision['outcome'] === BatchOutcome::DELIVERED) {
            countOutcome($redis, (string)$license, 'delivered', (int)$result['http_code'], count($batch));
            Logger::info("[$license] batch=" . count($batch) . " HTTP {$result['http_code']} {$elapsed}ms");
            $redis->del($processingKey);
            $processed += count($batch);
            continue;
        }

        $transient = handleBatchOutcome(
            $redis,
            $decision,
            $result,
            $batch,
            $queueKey,
            (string)$license,
            $maxAttempts,
            $failedCap
        );

        // O lote ja esta na fila outra vez ou na de falhas: sai do transito.
        // Uma paragem entre as duas coisas repete a entrega em vez de a perder,
        // e e para isso que o traceId segue com cada mensagem.
        $redis->del($processingKey);

        if ($transient) {
            // Sem espera, uma hora de indisponibilidade da plataforma produz
            // dezenas de milhares de entradas mortas em vez de algumas centenas.
            // O lote mistura mensagens novas com reenfileiradas: a espera segue
            // a que ja tentou mais vezes, nao a primeira da lista.
            $attempt = 1;
            foreach ($batch as $item) {
                $attempt = max($attempt, (int)($item['attempts'] ?? 0) + 1);
            }
            usleep(min($retryBaseMs * (2 ** ($attempt - 1)), $retryMaxMs) * 1000);
        }

        $processed += count($batch);
    }

    if ($processed === 0) {
        usleep($sleepMs * 1000);
    }
}
