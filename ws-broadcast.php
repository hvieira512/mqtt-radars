<?php
// Simple WebSocket broadcast client
// Usage: php ws-broadcast.php '{"data": {...}, "tenant": "gucc"}'

require __DIR__ . '/vendor/autoload.php';

if ($argc < 2) {
    echo "Usage: php ws-broadcast.php '<json>'\n";
    exit(1);
}

$data = json_decode($argv[1], true);
if (!$data) {
    echo "Invalid JSON\n";
    exit(1);
}

$host = '127.0.0.1';
$port = 8080;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($sock, $host, $port);

// WebSocket handshake
$key = base64_encode(random_bytes(16));
$handshake = "GET / HTTP/1.1\r\n";
$handshake .= "Host: localhost\r\n";
$handshake .= "Upgrade: websocket\r\n";
$handshake .= "Connection: Upgrade\r\n";
$handshake .= "Sec-WebSocket-Key: $key\r\n";
$handshake .= "Sec-WebSocket-Version: 13\r\n\r\n";

socket_write($sock, $handshake);
$response = socket_read($sock, 4096);

// Send broadcast via WebSocket frame
$payload = json_encode($data);
$frame = "\x81" . chr(strlen($payload)) . $payload;
socket_write($sock, $frame);

// Close
usleep(100000);
socket_close($sock);

echo "Broadcast sent\n";
