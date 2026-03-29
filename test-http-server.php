<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use App\Logger;
use React\Http\Server as ReactHttpServer;
use React\Http\Message\Response;
use React\Socket\Server as ReactSocket;
use React\EventLoop\Factory;

$host = $argv[1] ?? '127.0.0.1';
$port = $argv[2] ?? '8081';

echo "Starting HTTP test server on {$host}:{$port}\n";
echo "Endpoints:\n";
echo "  GET  /health          - Health check\n";
echo "  POST /broadcast       - Broadcast message\n";
echo "\n";

$loop = Factory::create();

$server = new ReactHttpServer($loop, function ($request) use ($host, $port): Response {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    if ($method === 'GET' && $path === '/health') {
        return new Response(200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ], json_encode([
            'status' => 'ok',
            'server' => 'test-server',
            'timestamp' => date('Y-m-d H:i:s'),
        ]));
    }

    if ($method === 'POST' && $path === '/broadcast') {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);

        echo "[" . date('H:i:s') . "] Broadcast received: " . substr($body, 0, 200) . "...\n";

        return new Response(200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ], json_encode([
            'status' => 'ok',
            'received' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ]));
    }

    return new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Not found']));
});

$socket = new ReactSocket("{$host}:{$port}");
$server->listen($socket);

echo "Server running. Press Ctrl+C to stop.\n";
$loop->run();
