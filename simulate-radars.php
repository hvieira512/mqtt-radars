<?php
require __DIR__ . '/vendor/autoload.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$username = 'havicare';
$password = 'hitCare';

function encodeRemainingLen(int $len): string
{
    $result = '';
    do {
        $digit = $len % 128;
        $len = intdiv($len, 128);
        if ($len > 0) $digit |= 0x80;
        $result .= chr($digit);
    } while ($len > 0);
    return $result;
}

function buildConnectPacket(string $clientId, string $username, string $password): string
{
    $protocol = 'MQIsdp';
    $protocolLevel = 3;
    $flags = 0xC0;

    $payload = pack('n', strlen($protocol)) . $protocol . chr($protocolLevel);
    $payload .= chr($flags);
    $payload .= pack('n', 60);
    $payload .= pack('n', strlen($clientId)) . $clientId;

    if ($username) $payload .= pack('n', strlen($username)) . $username;
    if ($password) $payload .= pack('n', strlen($password)) . $password;

    return chr(0x10) . encodeRemainingLen(strlen($payload)) . $payload;
}

function buildPublishPacket(string $topic, string $payload, int $qos = 0): string
{
    $topicLen = pack('n', strlen($topic)) . $topic;
    $flags = $qos << 1;
    $packet = $topicLen . $payload;
    return chr(0x30 | $flags) . encodeRemainingLen(strlen($packet)) . $packet;
}

$socket = fsockopen('127.0.0.1', 1883, $errno, $errstr);
if (!$socket) die("Failed to connect: $errstr ($errno)\n");

fwrite($socket, buildConnectPacket('simulator-' . uniqid(), $username, $password));
fread($socket, 4);

echo "Connected! Starting simulation...\n";

$radars = [
    ['license' => 1001, 'uid' => '9D8A3204F853'],
    ['license' => 1001, 'uid' => 'AD8A613B0493'],
    ['license' => 1001, 'uid' => '3525E3DD1087'],
    ['license' => 1001, 'uid' => '9D8A3204F84F'],
    ['license' => 1001, 'uid' => '9D8A3204276B'],
    ['license' => 1001, 'uid' => '3525E3DDAA33'],
    ['license' => 1001, 'uid' => '3525E3DD76C7'],
    ['license' => 1001, 'uid' => 'RADAR_GUCC_1'],
];

$postures = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
$events = [0, 1, 2, 3, 4];
$sleepStates = [0b00, 0b01, 0b10, 0b11];

function generatePositionData(int $personIndex, int $x, int $y, int $z, int $posture, int $event, int $region): string
{
    $raw = chr($personIndex) . chr($x & 0xFF) . chr($y & 0xFF) . chr($z);
    $raw .= chr(rand(0, 255)) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0);
    $raw .= chr(rand(0, 60)) . chr($posture) . chr($event) . chr($region) . chr(0);
    return base64_encode($raw);
}

function generateHeartBreathData(int $breathing, int $heartRate, int $sleepState): string
{
    $raw = chr(0) . chr($breathing) . chr($heartRate) . chr(0);
    $raw .= chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0);
    $raw .= chr(0) . chr(0) . chr($sleepState << 6) . chr(0);
    return base64_encode($raw);
}

function generateHbStaticsData(int $rtBreathing, int $rtHeartRate, int $avgBreathing, int $avgHeartRate, int $statusByte): string
{
    $raw = chr(0) . chr($rtBreathing) . chr($rtHeartRate) . chr(0);
    $raw .= chr(0) . chr($avgBreathing) . chr($avgHeartRate);
    $raw .= chr(0) . chr(0) . chr(0) . chr($statusByte) . chr(0);
    return base64_encode($raw);
}

$messageCount = 0;
$lastReport = time();

while (true) {
    foreach ($radars as $radar) {
        $topic = "radar/{$radar['license']}/{$radar['uid']}";

        $payload = json_encode([
            'payload' => [
                'deviceCode' => $radar['uid'],
                'position' => generatePositionData(0, rand(-50, 50), rand(-50, 50), rand(200, 300), $postures[array_rand($postures)], $events[array_rand($events)], rand(1, 4)),
                // 'heartbreath' => generateHeartBreathData(rand(10, 25), rand(60, 100), $sleepStates[array_rand($sleepStates)]),
                // 'hbstatics' => generateHbStaticsData(rand(10, 25), rand(60, 100), rand(12, 20), rand(65, 85), rand(0, 255))
            ]
        ]);

        fwrite($socket, buildPublishPacket($topic, $payload, 0));
        $messageCount++;

        if ($messageCount % 5 === 0) {
            echo "-";
        }

        usleep(100000);
    }

    if (time() - $lastReport >= 5) {
        echo " [" . date('H:i:s') . " Total: $messageCount]\n";
        $lastReport = time();
    }
}
