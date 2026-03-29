<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap.php';

use App\Database;
use App\Logger;
use App\Parsers\PositionParser;
use App\Parsers\HeartBreathParser;
use App\Repositories\DeviceRepository;
use App\Repositories\EventRepository;
use App\Repositories\PositionRepository;
use App\Repositories\VitalsRepository;
use App\Repositories\DetectionRepository;
use App\AlarmEngine;

class WsBroadcaster
{
    private string $wsServerUrl;

    public function __construct(?string $wsServerUrl = null)
    {
        $this->wsServerUrl = $wsServerUrl ?? ($_ENV['WS_SERVER_URL'] ?? 'http://127.0.0.1:8080');
    }

    public function broadcast(array $data, string $tenantId): bool
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

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }
}

function getOrCreateDeviceId(PDO $db, string $deviceCode): int
{
    $stmt = $db->prepare("SELECT id FROM radares WHERE uid = ?");
    $stmt->execute([$deviceCode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        return (int)$row['id'];
    }

    $stmt = $db->prepare("INSERT INTO radares (uid) VALUES (?)");
    $stmt->execute([$deviceCode]);
    return (int)$db->lastInsertId();
}

function storePositionData(PDO $db, int $eventId, int $deviceId, array $parsed): void
{
    $people = $parsed['people'] ?? [];
    if (empty($people)) return;

    $stmt = $db->prepare("
        INSERT INTO radares_posicao_pessoas
        (evento_id, dispositivo_id, indice_pessoa, posicao_x_dm, posicao_y_dm, posicao_z_cm, tempo_restante_seg, estado_postura, ultimo_evento, regiao_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

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

function storeVitalsData(PDO $db, int $eventId, array $parsed): void
{
    $stmt = $db->prepare("
        INSERT INTO radares_sinais_vitais
        (evento_id, taxa_respiracao, ritmo_cardiaco, estado_sono)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $eventId,
        $parsed['breathing'] ?? 0,
        $parsed['heart_rate'] ?? 0,
        $parsed['sleep_state'] ?? 'Undefined'
    ]);
}

function storeAlarms(PDO $db, int $eventId, int $deviceId, array $alarms, string $tenantId, WsBroadcaster $broadcaster): void
{
    if (empty($alarms)) return;

    $stmt = $db->prepare("
        INSERT INTO radares_detecoes
        (evento_id, dispositivo_id, categoria, tipo, nivel, indice_pessoa, regiao_id, mensagem)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

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

        $broadcastData = array_merge($alarm, [
            'device_code' => $alarm['device_code'] ?? null,
            'event_id' => $eventId,
            'category' => $alarm['category'] ?? 'alarm'
        ]);

        $broadcaster->broadcast($broadcastData, $tenantId);
    }
}

function processMessage(string $rawPayload, string $topic, string $tenantId, WsBroadcaster $broadcaster): array
{
    $result = ['success' => false, 'parsed' => null, 'alarms' => [], 'error' => null];

    $topicParts = explode('/', trim($topic, '/'));
    $deviceCode = $topicParts[2] ?? null;
    $dataType = $topicParts[3] ?? 'position';

    if (!$deviceCode) {
        $result['error'] = 'Missing device code in topic';
        return $result;
    }

    $payload = json_decode($rawPayload, true);
    if (!$payload || !isset($payload['data'])) {
        $result['error'] = 'Invalid JSON payload or missing data field';
        return $result;
    }

    $db = Database::connection();
    $deviceId = getOrCreateDeviceId($db, $deviceCode);

    $eventTypeId = $dataType === 'vitals' ? 2 : 1;
    $stmt = $db->prepare("INSERT INTO radares_eventos (dispositivo_id, tipo_evento_id) VALUES (?, ?)");
    $stmt->execute([$deviceId, $eventTypeId]);
    $eventId = (int)$db->lastInsertId();

    $parsed = null;

    if ($dataType === 'vitals') {
        $parser = new HeartBreathParser();
        $parsed = $parser->parse($payload['data'], $deviceCode);
        if ($parsed) {
            storeVitalsData($db, $eventId, $parsed);
        }
    } else {
        $parser = new PositionParser();
        $parsed = $parser->parse($payload['data'], $deviceCode);
        if ($parsed) {
            storePositionData($db, $eventId, $deviceId, $parsed);
        }
    }

    if (!$parsed) {
        $result['error'] = 'Failed to parse data';
        return $result;
    }

    $parsed['event_id'] = $eventId;
    $parsed['device_id'] = $deviceId;
    $result['parsed'] = $parsed;

    $alarms = AlarmEngine::evaluate($parsed);
    $result['alarms'] = $alarms;

    if (!empty($alarms)) {
        storeAlarms($db, $eventId, $deviceId, $alarms, $tenantId, $broadcaster);
    }

    $broadcaster->broadcast($parsed, $tenantId);

    $result['success'] = true;
    return $result;
}

header('Content-Type: application/json');

$tenantId = $_SERVER['HTTP_X_TENANT_ID'] 
    ?? ($_GET['tenant'] ?? basename(dirname($_SERVER['SCRIPT_FILENAME'])));

$rawPayload = file_get_contents('php://input');
$topic = $_SERVER['HTTP_X_MQTT_TOPIC'] ?? ($_GET['topic'] ?? '');

if (empty($rawPayload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Empty payload']);
    exit;
}

$broadcaster = new WsBroadcaster();
$result = processMessage($rawPayload, $topic, $tenantId, $broadcaster);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'device_id' => $result['parsed']['device_id'] ?? null,
        'event_id' => $result['parsed']['event_id'] ?? null,
        'alarms_count' => count($result['alarms'])
    ]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $result['error']]);
}
