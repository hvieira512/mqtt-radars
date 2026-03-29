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

class RedisSubscriber
{
    private RedisClient $redis;
    private RedisClient $redisPub;
    private PositionParser $positionParser;
    private HeartBreathParser $vitalsParser;
    private string $wsServerUrl;
    private bool $running = true;

    public function __construct()
    {
        $this->redis = new RedisClient($_ENV['REDIS_URL'] ?? 'tcp://127.0.0.1:6379');
        $this->redisPub = new RedisClient($_ENV['REDIS_URL'] ?? 'tcp://127.0.0.1:6379');
        $this->positionParser = new PositionParser();
        $this->vitalsParser = new HeartBreathParser();
        $this->wsServerUrl = $_ENV['WS_SERVER_URL'] ?? 'http://127.0.0.1:8080';
        
        pcntl_signal(SIGINT, fn() => $this->running = false);
        pcntl_signal(SIGTERM, fn() => $this->running = false);
    }

    public function getSubscriptions(): array
    {
        $pattern = $_ENV['REDIS_CHANNEL_PATTERN'] ?? 'radar:ingest:*';
        if ($pattern === '*') {
            $keys = $this->redis->keys('radar:ingest:*');
            return array_unique(array_map(fn($k) => str_replace('radar:ingest:', '', $k), $keys));
        }
        return [];
    }

    public function subscribeToChannel(string $channel): void
    {
        Logger::info("Subscribing to Redis channel: $channel");
        
        $this->redisPub->subscribe([$channel], function ($redis, $channel, $message) {
            $this->handleMessage($channel, $message);
        });
    }

    public function subscribeToPattern(): void
    {
        Logger::info("Subscribing to pattern: radar:ingest:*");
        
        $this->redisPub->psubscribe(['radar:ingest:*'], function ($redis, $pattern, $channel, $message) {
            $this->handleMessage($channel, $message);
        });
    }

    public function subscribeToAll(array $tenants): void
    {
        $channels = array_map(fn($t) => "radar:ingest:$t", $tenants);
        
        if (empty($channels)) {
            Logger::warn("No channels to subscribe to");
            return;
        }

        Logger::info("Subscribing to " . count($channels) . " channels");
        
        $this->redisPub->subscribe($channels, function ($redis, $channel, $message) {
            $this->handleMessage($channel, $message);
        });
    }

    private function handleMessage(string $channel, string $message): void
    {
        $tenantId = str_replace('radar:ingest:', '', $channel);
        
        $data = json_decode($message, true);
        if (!$data) {
            Logger::warn("Invalid JSON on channel $channel");
            return;
        }

        $topic = $data['topic'] ?? '';
        $payload = $data['message'] ?? '';

        try {
            $this->processMessage($tenantId, $topic, $payload);
        } catch (\Exception $e) {
            Logger::error("Error processing message: {$e->getMessage()}");
        }
    }

    private function processMessage(string $tenantId, string $topic, string $payload): void
    {
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
            Logger::warn("Failed to parse data for device: $deviceCode");
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
            $stmt->execute([
                $eventId,
                $parsed['breathing'] ?? 0,
                $parsed['heart_rate'] ?? 0,
                $parsed['sleep_state'] ?? 'Undefined'
            ]);
        } else {
            $people = $parsed['people'] ?? [];
            if (!empty($people)) {
                $stmt = $db->prepare("INSERT INTO radares_posicao_pessoas (evento_id, dispositivo_id, indice_pessoa, posicao_x_dm, posicao_y_dm, posicao_z_cm, tempo_restante_seg, estado_postura, ultimo_evento, regiao_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($people as $p) {
                    $stmt->execute([
                        $eventId, $deviceId,
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

        $parsed['event_id'] = $deviceId;
        $parsed['device_id'] = $deviceId;

        $alarms = AlarmEngine::evaluate($parsed);
        if (!empty($alarms)) {
            $stmt = $db->prepare("INSERT INTO radares_detecoes (evento_id, dispositivo_id, categoria, tipo, nivel, indice_pessoa, regiao_id, mensagem) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($alarms as $alarm) {
                $stmt->execute([
                    $eventId, $deviceId,
                    $alarm['category'] ?? 'alarm',
                    $alarm['type'] ?? 'unknown',
                    $alarm['level'] ?? 'info',
                    $alarm['person_index'] ?? 0,
                    $alarm['region_id'] ?? 0,
                    $alarm['message'] ?? ''
                ]);

                $alarmData = array_merge($alarm, [
                    'device_code' => $deviceCode,
                    'category' => $alarm['category'] ?? 'alarm'
                ]);
                $this->broadcast($alarmData, $tenantId);
            }
        }

        $this->broadcast($parsed, $tenantId);
        Logger::info("[$tenantId] Processed: $deviceCode ($dataType)");
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
        curl_close($ch);
    }

    public function run(): void
    {
        Logger::info("Redis Subscriber started");
        
        $usePattern = ($_ENV['REDIS_SUBSCRIBE_MODE'] ?? 'pattern') === 'pattern';
        
        if ($usePattern) {
            $this->subscribeToPattern();
        } else {
            $tenants = $this->getSubscriptions();
            if (!empty($tenants)) {
                $this->subscribeToAll($tenants);
            } else {
                Logger::warn("No tenants found, using pattern subscription");
                $this->subscribeToPattern();
            }
        }
    }
}

$subscriber = new RedisSubscriber();
$subscriber->run();
