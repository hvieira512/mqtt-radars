<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory;
use React\Socket\Server;
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

        $conn->send(json_encode(["msg" => "Welcome"]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {}

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "[" . date('H:i:s') . "] WS disconnected: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "WS error: {$e->getMessage()}\n";
        $conn->close();
    }

    public function broadcast(array $data)
    {
        if ($this->clients->count() === 0) {
            return;
        }

        $json = json_encode($data);

        foreach ($this->clients as $client) {
            $client->send($json);
        }

        echo "[" . date('H:i:s') . "] Broadcast to {$this->clients->count()} clients\n";
    }
}

$loop = Factory::create();

$radarWs = new RadarWebSocket();

# WebSocket server
$wsSocket = new Server('0.0.0.0:8080', $loop);

new IoServer(
    new HttpServer(
        new WsServer($radarWs)
    ),
    $wsSocket,
    $loop
);

echo "WebSocket running on ws://localhost:8080\n";


# HTTP endpoint for MQTT worker
$httpServer = new ReactHttp(function (ServerRequestInterface $request) use ($radarWs) {

    if ($request->getUri()->getPath() !== '/broadcast') {
        return new React\Http\Message\Response(
            404,
            ['Content-Type' => 'text/plain'],
            'Not found'
        );
    }

    $body = json_decode($request->getBody()->getContents(), true);

    if (!$body) {
        return new React\Http\Message\Response(
            400,
            [],
            'Invalid JSON'
        );
    }

    $radarWs->broadcast($body);

    return new React\Http\Message\Response(
        200,
        ['Content-Type' => 'application/json'],
        json_encode(["status" => "ok"])
    );
});

$httpSocket = new Server('127.0.0.1:8081', $loop);
$httpServer->listen($httpSocket);

echo "HTTP broadcast endpoint running on http://127.0.0.1:8081/broadcast\n";

$loop->run();
