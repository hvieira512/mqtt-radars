<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use App\Logger;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use React\EventLoop\Factory;
use React\Socket\Server as ReactSocket;
use React\Http\Server as ReactHttpServer;
use React\Http\Message\Response;
use React\Http\Message\Request;
use Psr\Http\Message\ServerRequestInterface;

$wsHost = $_ENV['WS_SERVER_HOST'] ?? '0.0.0.0';
$wsPort = (int)($_ENV['WS_SERVER_PORT'] ?? 8080);
$httpPort = (int)($_ENV['WS_HTTP_PORT'] ?? 8081);
$pollUrl = $_ENV['POLL_URL'] ?? 'https://gucc.dev.hitcare.net/modulos/radares/_ajax/radar-poll.php';
$pollInterval = (float)($_ENV['POLL_INTERVAL'] ?? 1.0); // seconds

class RadarWebSocket implements MessageComponentInterface
{
    private $clients;
    private array $clientTenant = [];

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $this->clientTenant[$conn->resourceId] = null;
        Logger::info("Client {$conn->resourceId} connected (total: {$this->clients->count()})");
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!$data) return;

        if (isset($data['type']) && $data['type'] === 'register') {
            $tenant = $data['tenant'] ?? 'default';
            $this->clientTenant[$from->resourceId] = $tenant;
            $from->send(json_encode(['type' => 'registered', 'tenant' => $tenant]));
            Logger::info("Client {$from->resourceId} registered for tenant: $tenant");
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        unset($this->clientTenant[$conn->resourceId]);
        Logger::info("Client {$conn->resourceId} disconnected");
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        Logger::error("Client {$conn->resourceId} error: {$e->getMessage()}");
        $conn->close();
    }

    public function broadcast(array $data, ?string $tenantId = null): void
    {
        if ($this->clients->count() === 0) {
            return;
        }

        $json = json_encode($data);
        $count = 0;

        foreach ($this->clients as $client) {
            $clientTenant = $this->clientTenant[$client->resourceId] ?? null;
            if ($tenantId === null || $clientTenant === $tenantId) {
                $client->send($json);
                $count++;
            }
        }
        
        if ($count > 0) {
            $tenantLog = $tenantId ? " (tenant: $tenantId)" : "";
            Logger::info("Broadcast to {$count} clients{$tenantLog}");
        }
    }

    public function getClientsCount(): int
    {
        return $this->clients->count();
    }
}

$loop = Factory::create();
$radarWs = new RadarWebSocket();
$webSocket = new WsServer($radarWs);

// WebSocket server on React socket
$wsSocket = new ReactSocket("$wsHost:$wsPort");
$wsServer = new \Ratchet\Server\IoServer(new HttpServer($webSocket), $wsSocket, $loop);

// HTTP Server for broadcast endpoint
$httpHandler = function ($request) use ($radarWs): Response {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    if ($method === 'OPTIONS') {
        return new Response(200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
            'Access-Control-Max-Age' => '86400',
        ], '');
    }

    if ($method === 'POST' && $path === '/broadcast') {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);

        if (!$data) {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'Invalid JSON']));
        }

        $tenantId = $data['tenant_id'] ?? null;
        $payload = $data['payload'] ?? $data;

        $radarWs->broadcast($payload, $tenantId);

        return new Response(200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ], json_encode([
            'status' => 'ok',
            'clients_count' => $radarWs->getClientsCount(),
            'broadcast_to' => $tenantId ?? 'all',
        ]));
    }

    if ($method === 'GET' && $path === '/health') {
        return new Response(200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ], json_encode([
            'status' => 'ok',
            'clients' => $radarWs->getClientsCount(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]));
    }

    return new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Not found']));
};

$httpServer = new ReactHttpServer($loop, $httpHandler);
$httpSocket = new ReactSocket("$wsHost:$httpPort");
$httpServer->listen($httpSocket);

Logger::info("WebSocket server on {$wsHost}:{$wsPort}");
Logger::info("HTTP broadcast endpoint on {$wsHost}:{$httpPort}");

// Polling state
$lastPollId = 0;
$pollTries = 0;

function pollForData($radarWs, &$lastPollId, &$pollTries, $url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    
    $pollUrl = $url . '?after_id=' . $lastPollId . '&limit=20';
    $response = @file_get_contents($pollUrl, false, $context);
    
    if ($response === false) {
        $pollTries++;
        if ($pollTries % 10 === 0) {
            Logger::info("Poll failed ({$pollTries} tries): " . error_get_last()['message'] ?? 'Unknown error');
        }
        return;
    }
    
    $pollTries = 0;
    $data = json_decode($response, true);
    
    if (!$data || empty($data['items'])) {
        return;
    }
    
    foreach ($data['items'] as $item) {
        $lastPollId = max($lastPollId, $item['event_id'] ?? 0);
        
        $broadcast = [
            'type' => 'radar_data',
            'device_code' => $item['device_code'] ?? 'unknown',
            'event_id' => $item['event_id'],
            'payload' => $item['payload'] ?? [],
            'alarms' => [],
        ];
        
        $radarWs->broadcast($broadcast);
        Logger::info("Poll broadcast: device={$item['device_code']} event={$item['event_id']}");
    }
}

// Start polling
$basePollUrl = $pollUrl;
$loop->addPeriodicTimer($pollInterval, function () use ($radarWs, &$lastPollId, &$pollTries, $basePollUrl) {
    pollForData($radarWs, $lastPollId, $pollTries, $basePollUrl);
});

Logger::info("Polling every {$pollInterval}s");

$loop->run();
