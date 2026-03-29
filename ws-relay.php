<?php
// Simple HTTP relay that broadcasts to WebSocket clients
// Run this alongside ws-server.php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

use App\Logger;

$host = '0.0.0.0';
$port = 8081;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($sock, $host, $port);
socket_listen($sock, 5);

Logger::info("HTTP Relay started on {$host}:{$port}");

while ($client = socket_accept($sock)) {
    $request = socket_read($client, 4096);
    
    if (preg_match('/POST \/broadcast HTTP\/1\.1/', $request)) {
        preg_match('/Content-Length: (\d+)/', $request, $matches);
        $contentLength = isset($matches[1]) ? (int)$matches[1] : 0;
        
        if ($contentLength > 0) {
            $body = '';
            while ($contentLength > 0) {
                $chunk = socket_read($client, $contentLength);
                $body .= $chunk;
                $contentLength -= strlen($chunk);
            }
            
            $data = json_decode($body, true);
            if ($data) {
                $tenantId = $data['tenant'] ?? null;
                $payload = $data['data'] ?? $data;
                
                Logger::info("Relay broadcast: " . json_encode($payload) . ($tenantId ? " (tenant: $tenantId)" : ""));
                
                $response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: 17\r\n\r\n{\"status\":\"ok\"}";
            } else {
                $response = "HTTP/1.1 400 Bad Request\r\nContent-Type: application/json\r\nContent-Length: 25\r\n\r\n{\"error\":\"Invalid JSON\"}";
            }
        } else {
            $response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: 17\r\n\r\n{\"status\":\"ok\"}";
        }
    } else {
        $response = "HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\n\r\n";
    }
    
    socket_write($client, $response);
    socket_close($client);
}

socket_close($sock);
