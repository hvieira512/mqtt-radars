<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$username = $_ENV['MQTT_USERNAME'] ?? null;
$password = $_ENV['MQTT_PASSWORD'] ?? null;

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

$socket = fsockopen($_ENV['MQTT_SERVER'] ?? '127.0.0.1', $_ENV['MQTT_PORT'] ?? 1883, $errno, $errstr);
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
    ['license' => 1001, 'uid' => '594B3CF100A7'],
];

$postures = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
$events = [0, 1, 2, 3, 4];
$sleepStates = [0b00, 0b01, 0b10, 0b11];

$personStates = [];
foreach ($radars as $index => $radar) {
    $personStates[$index] = [
        'x' => rand(-30, 30),
        'y' => rand(-30, 30),
        'z' => rand(200, 300),
        'vx' => 0,
        'vy' => 0,
        'vz' => 0,
        'targetX' => rand(-30, 30),
        'targetY' => rand(-30, 30),
        'targetZ' => rand(200, 300),
        'state' => 'idle',
        'stateTimer' => rand(3, 8),
        'posture' => $postures[array_rand($postures)],
    ];
}

function updateHumanMovement(array &$person, array $postures): void
{
    $person['stateTimer']--;
    
    if ($person['stateTimer'] <= 0) {
        $states = ['idle', 'walking', 'standing', 'idle', 'walking', 'standing'];
        $person['state'] = $states[array_rand($states)];
        $person['stateTimer'] = rand(3, 10);
        
        $person['targetX'] = rand(-35, 35);
        $person['targetY'] = rand(-35, 35);
        $person['targetZ'] = rand(200, 300);
        
        $person['posture'] = $postures[array_rand($postures)];
    }
    
    $maxSpeed = match($person['state']) {
        'idle' => 0.5,
        'standing' => 0.3,
        'walking' => 2.5,
        default => 1.0,
    };
    
    $dx = $person['targetX'] - $person['x'];
    $dy = $person['targetY'] - $person['y'];
    $dz = $person['targetZ'] - $person['z'];
    $dist = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
    
    if ($dist > 0.5) {
        $person['vx'] += ($dx / $dist) * $maxSpeed * (0.5 + rand(0, 100) / 100);
        $person['vy'] += ($dy / $dist) * $maxSpeed * (0.5 + rand(0, 100) / 100);
        $person['vz'] += ($dz / $dist) * $maxSpeed * 0.3 * (0.5 + rand(0, 100) / 100);
    } else {
        $person['vx'] *= 0.9;
        $person['vy'] *= 0.9;
        $person['vz'] *= 0.9;
    }
    
    $person['vx'] = max(-$maxSpeed, min($maxSpeed, $person['vx']));
    $person['vy'] = max(-$maxSpeed, min($maxSpeed, $person['vy']));
    $person['vz'] = max(-0.5, min(0.5, $person['vz']));
    
    $person['x'] += $person['vx'];
    $person['y'] += $person['vy'];
    $person['z'] += $person['vz'];
    
    $person['x'] = max(-50, min(50, $person['x']));
    $person['y'] = max(-50, min(50, $person['y']));
    $person['z'] = max(180, min(320, $person['z']));
    
    if (rand(1, 100) <= 10) {
        $person['posture'] = $postures[array_rand($postures)];
    }
}

function generatePositionData(int $personIndex, int $x, int $y, int $z, int $posture, int $event, int $region): string
{
    $raw = chr($personIndex) . chr($x & 0xFF) . chr($y & 0xFF) . chr($z);
    $raw .= chr(rand(0, 255)) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0);
    $raw .= chr(rand(0, 60)) . chr($posture) . chr($event) . chr($region);
    return base64_encode($raw);
}

function generateHeartBreathData(int $breathing, int $heartRate, int $sleepState): string
{
    $raw = chr(0) . chr($breathing) . chr($heartRate) . chr(0);
    $raw .= chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0);
    $raw .= chr(0) . chr($sleepState << 6) . chr(0) . chr(0);
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
$sendVitals = true;

while (true) {
    foreach ($radars as $index => $radar) {
        $topic = "radar/{$radar['license']}/{$radar['uid']}";
        
        updateHumanMovement($personStates[$index], $postures);
        $person = $personStates[$index];
        
        $payload = json_encode([
            'payload' => [
                'deviceCode' => $radar['uid'],
                'position' => generatePositionData(0, (int)round($person['x']), (int)round($person['y']), (int)round($person['z']), $person['posture'], $events[array_rand($events)], rand(1, 4)),
            ]
        ]);

        fwrite($socket, buildPublishPacket($topic, $payload, 0));
        $messageCount++;

        if ($sendVitals) {
            $vitalsPayload = json_encode([
                'payload' => [
                    'deviceCode' => $radar['uid'],
                    'heartbreath' => generateHeartBreathData(rand(10, 25), rand(60, 100), $sleepStates[array_rand($sleepStates)]),
                ]
            ]);
            fwrite($socket, buildPublishPacket($topic, $vitalsPayload, 0));
            $messageCount++;
        }

        if ($messageCount % 5 === 0) {
            echo "-";
        }

        usleep(100000);
    }

    $sendVitals = !$sendVitals;

    if (time() - $lastReport >= 5) {
        echo " [" . date('H:i:s') . " Total: $messageCount]\n";
        $lastReport = time();
    }
}
