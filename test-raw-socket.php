<?php
require __DIR__ . '/vendor/autoload.php';

$clientId = 'test-' . uniqid();
$username = 'havicare';
$password = 'hitCare';

$socket = fsockopen('127.0.0.1', 1883, $errno, $errstr);
if (!$socket) die("Failed to connect: $errstr ($errno)\n");

echo "Connected to MQTT server\n";

$connectPacket = buildConnectPacket($clientId, $username, $password);
fwrite($socket, $connectPacket);
$response = fread($socket, 4);
echo "Connect response: " . bin2hex($response) . "\n";

function buildConnectPacket(string $clientId, string $username, string $password): string {
    $protocol = 'MQIsdp';
    $protocolLevel = 3;
    
    $flags = 0xC0; 
    
    $payload = pack('n', strlen($protocol)) . $protocol . chr($protocolLevel);
    $payload .= chr($flags);
    $payload .= pack('n', 60);
    $payload .= pack('n', strlen($clientId)) . $clientId;
    
    if ($username) {
        $payload .= pack('n', strlen($username)) . $username;
    }
    if ($password) {
        $payload .= pack('n', strlen($password)) . $password;
    }
    
    $fixedHeader = chr(0x10) . encodeRemainingLen(strlen($payload));
    return $fixedHeader . $payload;
}

function encodeRemainingLen(int $len): string {
    $result = '';
    do {
        $digit = $len % 128;
        $len = intdiv($len, 128);
        if ($len > 0) $digit |= 0x80;
        $result .= chr($digit);
    } while ($len > 0);
    return $result;
}

echo "Publishing messages...\n";
for ($i = 0; $i < 10; $i++) {
    $topic = "radar/1001/RADAR00" . ($i % 5 + 1);
    $payload = json_encode(['test' => 'message ' . $i]);
    
    $publishPacket = buildPublishPacket($topic, $payload, 0);
    fwrite($socket, $publishPacket);
    
    echo "Sent to $topic\n";
    usleep(100000);
}

$disconnectPacket = chr(0xE0) . chr(0x00);
fwrite($socket, $disconnectPacket);
fclose($socket);

echo "Done!\n";

function buildPublishPacket(string $topic, string $payload, int $qos): string {
    $topicLen = pack('n', strlen($topic)) . $topic;
    
    $flags = $qos << 1;
    
    $packet = $topicLen . $payload;
    $fixedHeader = chr(0x30 | $flags) . encodeRemainingLen(strlen($packet));
    return $fixedHeader . $packet;
}