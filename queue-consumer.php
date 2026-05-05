<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use App\Database;
use App\Logger;
use App\Parsers\PositionParser;
use App\Parsers\HeartBreathParser;
use App\AlarmEngine;
use Predis\Client as RedisClient;

class QueueProcessor
{
    private RedisClient $redis;
    private PositionParser $positionParser;
    private HeartBreathParser $vitalsParser;
    private string $wsServerUrl;

    public function __construct()
    {
        $this->redis = new RedisClient($_ENV['REDIS_URL'] ?? 'tcp://127.0.0.1:6379');
        $this->positionParser = new PositionParser();
        $this->vitalsParser = new HeartBreathParser();
        $this->wsServerUrl = $_ENV['WS_SERVER_URL'] ?? 'http://127.0.0.1:8080';
    }

    public function getQueues(): array
    {
        $keys = $this->redis->keys('mqtt:ingest:*');
        return array_map(fn($k) => str_replace('mqtt:ingest:', '', $k), $keys);
    }

    public function processQueue(string $tenantId): int
    {
        $queueKey = "mqtt:ingest:$tenantId";
        $processed = 0;

        while ($item = $this->redis->lpop($queueKey)) {
            $data = json_decode($item, true);
            if (!$data) {
                Logger::warn("Invalid queue item for tenant $tenantId");
                continue;
            }

            try {
                $this->processMessage($data, $tenantId);
                $processed++;
            } catch (\Exception $e) {
                Logger::error("Error processing message: {$e->getMessage()}");
                $this->redis->rpush("mqtt:failed:$tenantId", $item);
            }
        }

        return $processed;
    }

    private function processMessage(array $data, string $tenantId): void
    {
        $topic = $data['topic'] ?? '';
        $payload = $data['message'] ?? '';
        $license = $data['license'] ?? $tenantId;

        $topicParts = explode('/', trim($topic, '/'));
        $deviceCode = $topicParts[2] ?? null;
        $dataType = $topicParts[3] ?? 'position';

        if (!$deviceCode) {
            return;
        }

        $jsonPayload = json_decode($payload, true);
        $base64Data = $jsonPayload['data'] ?? $payload;

        $parsed = null;

        if ($dataType === 'vitals') {
            $parsed = $this->vitalsParser->parse($base64Data, $deviceCode);
        } else {
            $parsed = $this->positionParser->parse($base64Data, $deviceCode);
        }

        if (!$parsed) {
            return;
        }

        $db = Database::connection();
        $stmt = $db->prepare("SELECT id FROM radares WHERE uid = ?");
        $stmt->execute([$deviceCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $deviceId = $row ? (int)$row['id'] : null;
        if (!$deviceId) {
            $stmt = $db->prepare("INSERT INTO radares (uid) VALUES (?)");
            $stmt->execute([$deviceCode]);
            $deviceId = (int)$db->lastInsertId();
        }

        $eventTypeId = $dataType === 'vitals' ? 2 : 1;
        $stmt = $db->prepare("INSERT INTO radares_eventos (dispositivo_id, tipo_evento_id) VALUES (?, ?)");
        $stmt->execute([$deviceId, $eventTypeId]);
        $eventId = (int)$db->lastInsertId();

        if ($dataType === 'vitals') {
            $stmt = $db->prepare("INSERT INTO radares_sinais_vitais (evento_id, taxa_respiracao, ritmo_cardiaco, estado_sono) VALUES (?, ?, ?, ?)");
            $stmt->execute([$eventId, $parsed['breathing'] ?? 0, $parsed['heart_rate'] ?? 0, $parsed['sleep_state'] ?? 'Undefined']);
        } else {
            $people = $parsed['people'] ?? [];
            if (!empty($people)) {
                $stmt = $db->prepare("INSERT INTO radares_posicao_pessoas (evento_id, dispositivo_id, indice_pessoa, posicao_x_dm, posicao_y_dm, posicao_z_cm, tempo_restante_seg, estado_postura, ultimo_evento, regiao_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($people as $p) {
                    $stmt->execute([
                        $eventId,
                        $deviceId,
                        $p['person_index'] ?? 0,
                        $p['x_position_dm'] ?? 0,
                        $p['y_position_dm'] ?? 0,
                        $p['z_position_cm'] ?? 0,
                        $p['time_left_s'] ?? 0,
                        $p['posture_state'] ?? 'Unknown',
                        $p['last_event'] ?? 'No Event',
                        $p['region_id'] ?? 0
                    ]);
                }
            }
        }

        $parsed['event_id'] = $eventId;
        $parsed['device_id'] = $deviceId;

        $alarms = AlarmEngine::evaluate($parsed);
        if (!empty($alarms)) {
            $stmt = $db->prepare("INSERT INTO radares_detecoes (evento_id, dispositivo_id, categoria, tipo, nivel, indice_pessoa, regiao_id, mensagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($alarms as $alarm) {
                $stmt->execute([
                    $eventId,
                    $deviceId,
                    $alarm['category'] ?? 'alarm',
                    $alarm['type'] ?? 'unknown',
                    $alarm['level'] ?? 'info',
                    $alarm['person_index'] ?? 0,
                    $alarm['region_id'] ?? 0,
                    $alarm['message'] ?? ''
                ]);

                $alarm['category'] = $alarm['category'] ?? 'alarm';
                $this->broadcast($alarm, $license);
            }
        }

        $this->broadcast($parsed, $license);
    }

    private function broadcast(array $data, string $tenantId): void
    {
        $payload = json_encode([
            'tenant' => $tenantId,
            'data' => $data,
            'timestamp' => time()
        ]);

        $ch = curl_init($this->wsServerUrl . '/broadcast');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        curl_exec($ch);
    }

    public function run(bool $daemon = true): void
    {
        $sleepInterval = (int)($_ENV['QUEUE_SLEEP_MS'] ?? 100);

        Logger::info("Queue processor started (daemon: " . ($daemon ? 'yes' : 'no') . ")");

        do {
            $tenants = $this->getQueues();
            $totalProcessed = 0;

            foreach ($tenants as $tenant) {
                $processed = $this->processQueue($tenant);
                if ($processed > 0) {
                    Logger::info("Processed $processed messages for tenant: $tenant");
                    $totalProcessed += $processed;
                }
            }

            if ($daemon) {
                if ($totalProcessed === 0) {
                    usleep($sleepInterval * 1000);
                }
            } else {
                break;
            }
        } while ($daemon);
    }
}

$daemon = in_array('--daemon', $argv) || in_array('-d', $argv);
$processor = new QueueProcessor();
$processor->run($daemon);
