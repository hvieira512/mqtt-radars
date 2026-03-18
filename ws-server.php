<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';

use App\Logger;
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
            $client->send(json_encode(array_values($data)));
        }

        Logger::info("Broadcast completed. Clients: {$this->clients->count()}");
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
Logger::info("HTTP server started on http://127.0.0.1:8081/broadcast");

// Keep the loop running
$loop->run();
