<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\Server as ReactSocket;
use React\Http\HttpServer as ReactHttp;
use Psr\Http\Message\ServerRequestInterface;

class RadarWebSocket implements MessageComponentInterface
{
    private \SplObjectStorage $clients;
    private array $subscriptions = []; // key: client resourceId, value: deviceCode

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "[" . date('H:i:s') . "] WS connected: {$conn->resourceId}\n";
        $conn->send(json_encode(["msg" => "Welcome"]));
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!$data) return;

        if (!empty($data['action']) && $data['action'] === 'subscribe' && !empty($data['deviceCode'])) {
            $this->subscriptions[$from->resourceId] = $data['deviceCode'];
            echo "[" . date('H:i:s') . "] Client {$from->resourceId} subscribed to deviceCode: {$data['deviceCode']}\n";
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        unset($this->subscriptions[$conn->resourceId]);
        $this->clients->detach($conn);
        echo "[" . date('H:i:s') . "] WS disconnected: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "[" . date('H:i:s') . "] WS error: {$e->getMessage()}\n";
        $conn->close();
    }

    public function broadcast(array $data)
    {
        if ($this->clients->count() === 0) return;

        foreach ($this->clients as $client) {
            $deviceCode = $this->subscriptions[$client->resourceId] ?? null;
            if (!$deviceCode) continue; // client not subscribed

            // Filter only data relevant to the subscribed device
            $filtered = array_filter($data, fn($d) => ($d['device_code'] ?? null) === $deviceCode);

            if (!empty($filtered)) {
                $client->send(json_encode(array_values($filtered)));
            }
        }

        echo "[" . date('H:i:s') . "] Broadcast completed. Clients: {$this->clients->count()}\n";
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
echo "WebSocket running on ws://localhost:8080\n";

// HTTP endpoint for MQTT worker
$httpServer = new ReactHttp(function (ServerRequestInterface $request) use ($radarWs) {
    if ($request->getUri()->getPath() !== '/broadcast') {
        return new React\Http\Message\Response(404, ['Content-Type' => 'text/plain'], 'Not found');
    }

    $body = json_decode($request->getBody()->getContents(), true);
    if (!$body) {
        return new React\Http\Message\Response(400, [], 'Invalid JSON');
    }

    $radarWs->broadcast($body);

    return new React\Http\Message\Response(200, ['Content-Type' => 'application/json'], json_encode(["status" => "ok"]));
});

$httpSocket = new ReactSocket('127.0.0.1:8081', $loop);
$httpServer->listen($httpSocket);
echo "HTTP broadcast endpoint running on http://127.0.0.1:8081/broadcast\n";

$loop->run();
