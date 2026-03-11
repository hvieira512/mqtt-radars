<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\RadarRepository;
use App\Logger;
use App\Parsers\PositionParser;
use App\Parsers\PosStaticsParser;
use App\Parsers\HeartBreathParser;
use App\Parsers\HbStaticsParser;
use App\WebLogger;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class RadarWebSocket implements MessageComponentInterface
{
    public function onOpen(ConnectionInterface $conn)
    {
        echo "[" . date('H:i:s') . "] New WebSocket client connected: {$conn->resourceId}\n";
        WebLogger::registerClient($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {}
    public function onClose(ConnectionInterface $conn)
    {
        WebLogger::removeClient($conn);
    }
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        WebLogger::removeClient($conn);
        $conn->close();
    }
}

$repo = new RadarRepository();

$server   = $_ENV['MQTT_SERVER'];
$port     = isset($_ENV['MQTT_PORT']) && $_ENV['MQTT_PORT'] !== '' ? (int) $_ENV['MQTT_PORT'] : 1883;
$username = $_ENV['MQTT_USERNAME'];
$password = $_ENV['MQTT_PASSWORD'];
$topic    = $_ENV['MQTT_TOPIC'] ?? 'radar/frontend';

$connectionSettings = (new ConnectionSettings())
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(60);

$mqtt = new MqttClient($server, $port, 'php-radar-client');
$mqtt->connect($connectionSettings, true);
Logger::info("[" . date('H:i:s') . "] MQTT client connected");

// Parsers
$parsers = [
    'position'     => new PositionParser(),
    'posstatics'   => new PosStaticsParser(),
    'heartbreath'  => new HeartBreathParser(),
    'hbstatics'    => new HbStaticsParser(),
];

// Subscribe to MQTT topic
$mqtt->subscribe($topic, function ($_topic, $message) use ($parsers, $repo) {
    $timestamp = date('H:i:s');
    $data = json_decode($message, true);

    if (!$data || !isset($data['payload'])) {
        Logger::warn("[$timestamp] Invalid payload\n");
        return;
    }

    $payload = $data['payload'];
    $deviceCode = $payload['deviceCode'] ?? null;
    $broadcastData = [];

    foreach ($parsers as $key => $parser) {
        if (!isset($payload[$key])) continue;

        $parsed = $parser->parse($payload[$key], $deviceCode);
        if (!$parsed) continue;

        Logger::logData($parsed);

        $deviceId = $repo->getDeviceId($deviceCode);
        $eventId = $repo->createEvent($deviceId, $parsed['type']);

        switch ($parsed['type']) {
            case 'position':
                $repo->insertPosition($eventId, $parsed['people']);
                $broadcastData['position'] = $parsed['people'];
                break;
            case 'minute_stats':
                $repo->insertMinuteStats($eventId, $parsed);
                $broadcastData['minute_stats'] = $parsed;
                break;
            case 'vitals':
                $repo->insertVitals($eventId, $parsed);
                $broadcastData['vitals'] = $parsed;
                break;
            case 'hbstatics':
                $repo->insertHbStatics($eventId, $parsed);
                $broadcastData['hbstatics'] = $parsed;
                break;
        }
    }

    // Broadcast parsed data to WebSocket clients
    if (!empty($broadcastData)) {
        WebLogger::broadcast($broadcastData);
    }
}, 0);

$wsServer = IoServer::factory(
    new HttpServer(
        new WsServer(
            new RadarWebSocket()
        )
    ),
    8080,
    '0.0.0.0'
);

echo "[" . date('H:i:s') . "] WebSocket server started on port 8080\n";

$loop = $wsServer->loop;

$loop->addPeriodicTimer(0.1, function () use ($mqtt) {
    $mqtt->loop(true);
});

$wsServer->run();

