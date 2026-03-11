<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use App\WebLogger;

class RadarWebSocket implements MessageComponentInterface
{
    public function onOpen(ConnectionInterface $conn)
    {
        echo "New client connected: {$conn->resourceId}\n";
        WebLogger::registerClient($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        // optional: handle browser messages if needed
    }

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

// MQTT setup
$server   = $_ENV['MQTT_SERVER'];
$port     = isset($_ENV['MQTT_PORT']) && $_ENV['MQTT_PORT'] !== '' ? (int) $_ENV['MQTT_PORT'] : 1883;
$username = $_ENV['MQTT_USERNAME'];
$password = $_ENV['MQTT_PASSWORD'];
$topic    = $_ENV['MQTT_TOPIC'] ?? 'radar/frontend';

$connectionSettings = (new ConnectionSettings)
    ->setUsername($username)
    ->setPassword($password)
    ->setKeepAliveInterval(60);

$mqtt = new MqttClient($server, $port, 'php-websocket-server');
$mqtt->connect($connectionSettings, true);
echo "[" . date('H:i:s') . "] MQTT client connected\n";

$mqtt->subscribe($topic, function ($_topic, $message) {
    $data = json_decode($message, true);
    if ($data) {
        WebLogger::broadcast($data);
    }
}, 0);

// Start WebSocket server
$wsServer = IoServer::factory(
    new HttpServer(
        new WsServer(
            new RadarWebSocket()
        )
    ),
    8080
);

echo "[" . date('H:i:s') . "] WebSocket server running on ws://localhost:8080\n";

// Run both MQTT loop and WebSocket server
while (true) {
    $mqtt->loop(true); // non-blocking with short timeout if needed
    $wsServer->loop(); // process WebSocket events
}
