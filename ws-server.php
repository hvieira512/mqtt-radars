<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use App\Logger;
use App\AlarmEngine;
use App\EventTypes;
use App\Parsers\PositionParser;
use App\Parsers\HeartBreathParser;
use App\Parsers\HbStaticsParser;
use App\Parsers\PosStaticsParser;
use App\Repositories\DetectionRepository;
use App\Repositories\DeviceRepository;
use App\Repositories\EventRepository;
use App\Repositories\PositionRepository;
use App\Repositories\StatsRepository;
use App\Repositories\VitalsRepository;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\Server as ReactSocket;
use React\Http\HttpServer as ReactHttp;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class RadarWebSocket implements MessageComponentInterface
{
    private \SplObjectStorage $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "[" . date('H:i:s') . "] WS connected: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {} // no subscription needed

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        Logger::info("Client {$conn->resourceId} disconnected");
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        Logger::error("Client {$conn->resourceId} error: {$e->getMessage()}");
        $conn->close();
    }

    public function broadcast(array $data)
    {
        if ($this->clients->count() === 0) return;

        foreach ($this->clients as $client) {
            $client->send(json_encode($data));
        }
    }
}

// Event loop
$loop = Factory::create();
$radarWs = new RadarWebSocket();

// WebSocket server
$wsSocket = new ReactSocket('0.0.0.0:8080', $loop);
new IoServer(
    new HttpServer(new WsServer($radarWs)),
    $wsSocket,
    $loop
);
Logger::info("WebSocket server started on ws://localhost:8080");

$eventsRepo    = new EventRepository();
$detRepo       = new DetectionRepository();
$deviceRepo    = new DeviceRepository();
$positionRepo  = new PositionRepository();
$statsRepo     = new StatsRepository();
$vitalsRepo    = new VitalsRepository();

$parsers = [
    'position'    => new PositionParser(),
    'heartbreath' => new HeartBreathParser(),
    'hbstatics'   => new HbStaticsParser(),
    'posstatics'  => new PosStaticsParser(),
];

$lastAlarms = [];

function handleRadarDataIngest(ServerRequestInterface $request): Response
{
    global $parsers, $lastAlarms, $detRepo, $deviceRepo, $positionRepo, $statsRepo, $vitalsRepo, $eventsRepo, $radarWs;

    $body = json_decode($request->getBody()->getContents(), true);
    if (!$body || !isset($body['message'])) {
        return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'Invalid payload']));
    }

    $message = $body['message'];
    $topic = $body['topic'] ?? 'unknown';

    $data = json_decode($message, true);
    if (!$data || !isset($data['payload'])) {
        return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'Invalid message format']));
    }

    $payload = $data['payload'];
    $deviceCode = $payload['deviceCode'] ?? null;
    if (!$deviceCode) {
        return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'No deviceCode']));
    }

    $deviceId = $deviceRepo->getDeviceId($deviceCode);
    $broadcast = [];

    foreach ($parsers as $key => $parser) {
        if (!isset($payload[$key])) continue;

        $parsed = $parser->parse($payload[$key], $deviceCode);
        if (!$parsed) continue;

        Logger::logData($parsed);

        $eventTypeId = EventTypes::fromString($parsed['type']);
        $eventId = $eventsRepo->createEvent($deviceId, $eventTypeId);

        switch ($parsed['type']) {
            case 'position':
                $positionRepo->insertPosition($eventId, $parsed['people']);
                break;
            case 'vitals':
                $vitalsRepo->insertVitals($eventId, $parsed);
                break;
            case 'minute_stats':
                $statsRepo->insertMinuteStats($eventId, $parsed);
                break;
            case 'hbstatics':
                $statsRepo->insertSleepStats($eventId, $parsed);
                break;
        }

        $alarms = AlarmEngine::evaluate($parsed);
        foreach ($alarms as $alarm) {
            $personIndex = $alarm['person_index'] ?? 'global';
            $alarmKey = "{$deviceCode}_{$personIndex}_{$alarm['alarm_type']}";

            if (($lastAlarms[$alarmKey] ?? null) !== $alarm['level']) {
                $alarm['device_code'] = $deviceCode;
                if (!isset($alarm['message'])) {
                    $alarm['message'] = "Evento detectado: {$alarm['alarm_type']}";
                }

                $broadcast[] = $alarm;

                $detRepo->insertDetection([
                    'event_id'     => $eventId,
                    'device_id'    => $deviceId,
                    'category'     => $alarm['category'],
                    'type'         => $alarm['alarm_type'],
                    'level'        => $alarm['level'],
                    'source'       => $alarm['source'],
                    'person_index' => $alarm['person_index'] ?? null,
                    'region_id'    => $alarm['region_id'] ?? null,
                    'message'      => $alarm['message'],
                ]);

                $lastAlarms[$alarmKey] = $alarm['level'];
            }
        }

        if (isset($parsed['people'])) {
            foreach ($parsed['people'] as $person) {
                $personIndex = $person['person_index'] ?? 0;
                $posture = $person['posture_state'] ?? '';
                $normalPostures = ['Standing', 'Walking', 'Sitting'];
                if (in_array($posture, $normalPostures)) {
                    foreach ($alarms as $a) {
                        if ($a['person_index'] === $personIndex) {
                            $key = "{$deviceCode}_{$personIndex}_{$a['alarm_type']}";
                            unset($lastAlarms[$key]);
                        }
                    }
                }
            }
        }

        $parsed['device_code'] = $deviceCode;
        $broadcast[] = $parsed;
    }

    if (!empty($broadcast)) {
        Logger::info("Broadcasting " . count($broadcast) . " messages");
        $radarWs->broadcast($broadcast);
    }

    return new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'ok', 'processed' => count($broadcast)]));
}

// HTTP endpoint for MQTT worker
$httpServer = new ReactHttp(function (ServerRequestInterface $request) use ($radarWs) {
    $path = $request->getUri()->getPath();

    if ($path === '/broadcast') {
        $body = json_decode($request->getBody()->getContents(), true);
        if (!$body) {
            return new React\Http\Message\Response(400, [], 'Invalid JSON');
        }

        $radarWs->broadcast($body);
        return new React\Http\Message\Response(200, ['Content-Type' => 'application/json'], json_encode(["status" => "ok"]));
    }

    if ($path === '/api/radar-data/ingest') {
        return handleRadarDataIngest($request);
    }

    return new React\Http\Message\Response(404, ['Content-Type' => 'text/plain'], 'Not found');
});

$httpSocket = new ReactSocket('127.0.0.1:8081', $loop);
$httpServer->listen($httpSocket);
Logger::info("HTTP server started on http://127.0.0.1:8081/broadcast");

// Keep the loop running
$loop->run();
