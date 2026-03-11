<?php
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\Server as Reactor;

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
        echo "[" . date('H:i:s') . "] Client connected: {$conn->resourceId} (total: {$this->clients->count()})\n";

        // Send welcome message
        $conn->send(json_encode(['msg' => 'Welcome!']));
    }

    public function onMessage(ConnectionInterface $from, $msg) {}

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        echo "[" . date('H:i:s') . "] Client disconnected: {$conn->resourceId} (total: {$this->clients->count()})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "[" . date('H:i:s') . "] WebSocket error: {$e->getMessage()}\n";
        $this->clients->detach($conn);
        $conn->close();
    }

    public function broadcast(array $data): void
    {
        if ($this->clients->count() === 0) {
            echo "[" . date('H:i:s') . "] Broadcasting to 0 clients\n";
            return;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        echo "[" . date('H:i:s') . "] Broadcasting to {$this->clients->count()} clients\n";

        foreach ($this->clients as $conn) {
            $conn->send($json);
        }
    }

    public function getClientCount(): int
    {
        return $this->clients->count();
    }
}

$loop = LoopFactory::create();
$radarWs = new RadarWebSocket(); // single instance

$webSock = new Reactor('0.0.0.0:8080', $loop);
$wsServer = new IoServer(
    new HttpServer(
        new WsServer($radarWs)
    ),
    $webSock,
    $loop
);

echo "[" . date('H:i:s') . "] WebSocket server started on ws://0.0.0.0:8080\n";

$loop->addPeriodicTimer(2, function () use ($radarWs) {
    echo "[" . date('H:i:s') . "] Connected WS clients: " . $radarWs->getClientCount() . "\n";
});

$loop->addPeriodicTimer(5, function () use ($radarWs) {
    $radarWs->broadcast([
        'test' => 'hello world',
        'ts'   => date('H:i:s')
    ]);
});

$loop->run();
