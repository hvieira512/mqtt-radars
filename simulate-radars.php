<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap.php';

error_reporting(E_ALL & ~E_DEPRECATED);

$options = getopt('', ['no-fall-confirmed', 'vitals-only', 'vitals-interval:', 'help']);
if (isset($options['help'])) {
    echo "Usage: php simulate-radars.php [options]\n";
    echo "  --no-fall-confirmed  Exclude fall confirmed postures (5)\n";
    echo "  --vitals-only      Only send heartbreath data (no position)\n";
    echo "  --vitals-interval N Send vitals every N seconds (default: 3)\n";
    exit(0);
}

$excludeFallConfirmed = isset($options['no-fall-confirmed']);
$vitalsOnly = isset($options['vitals-only']);
$vitalsInterval = isset($options['vitals-interval']) ? (int)$options['vitals-interval'] : 3;

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
    // ['license' => 1001, 'uid' => 'AD8A613B0493'],
    // ['license' => 1001, 'uid' => '3525E3DD1087'],
    // ['license' => 1001, 'uid' => '9D8A3204F84F'],
    // ['license' => 1001, 'uid' => '9D8A3204276B'],
    // ['license' => 1001, 'uid' => '3525E3DDAA33'],
    // ['license' => 1001, 'uid' => '3525E3DD76C7'],
    // ['license' => 1001, 'uid' => '594B3CF100A7'],
];

$allPostures = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
$postures = $excludeFallConfirmed
    ? [0, 1, 2, 3, 4, 6, 7, 8, 9, 10, 11]
    : $allPostures;

if ($excludeFallConfirmed) {
    echo "Running with --no-fall-confirmed (posture 5 excluded)\n";
}
if ($vitalsOnly) {
    echo "Running with --vitals-only (heartbreath only)\n";
}
echo "Vitals interval: {$vitalsInterval}s\n";

$events = [0, 1, 2, 3, 4];
$sleepStates = [0b00, 0b01, 0b10, 0b11];

$personStates = [];
foreach ($radars as $index => $radar) {
    $personStates[$index] = [
        'x' => rand(-30, 30),
        'y' => rand(-30, 30),
        'z' => rand(220, 280),
        'targetX' => rand(-30, 30),
        'targetY' => rand(-30, 30),
        'targetZ' => rand(220, 280),
        'state' => 'idle',
        'stateTimer' => rand(3, 8),
        'posture' => $postures[array_rand($postures)],
        'lastVitals' => 0,
    ];
}

function updateHumanMovement(array &$person, array $postures): void
{
    $person['stateTimer']--;

    if ($person['stateTimer'] <= 0) {
        $states = ['idle', 'walking', 'standing', 'idle', 'walking', 'standing', 'sitting'];
        $person['state'] = $states[array_rand($states)];
        $person['stateTimer'] = rand(4, 15);

        $dx = $person['targetX'] - $person['x'];
        $dy = $person['targetY'] - $person['y'];
        $walkDist = sqrt($dx * $dx + $dy * $dy);

        if ($walkDist > 5 || $person['state'] === 'idle') {
            $person['targetX'] = $person['x'] + rand(-8, 8);
            $person['targetY'] = $person['y'] + rand(-8, 8);
        }
        $person['targetX'] = max(-35, min(35, $person['targetX']));
        $person['targetY'] = max(-35, min(35, $person['targetY']));
        $person['targetZ'] = $person['state'] === 'sitting' ? rand(150, 180) : rand(220, 280);

        $person['posture'] = $postures[array_rand($postures)];
    }

    $speed = match ($person['state']) {
        'idle' => 0.2,
        'standing' => 0.1,
        'sitting' => 0.1,
        'walking' => 0.8,
        default => 0.3,
    };

    $dx = $person['targetX'] - $person['x'];
    $dy = $person['targetY'] - $person['y'];
    $dz = $person['targetZ'] - $person['z'];
    $dist = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

    if ($dist > 0.5) {
        $person['x'] += ($dx / $dist) * $speed;
        $person['y'] += ($dy / $dist) * $speed;
        $person['z'] += ($dz / $dist) * $speed * 0.3;
    }

    $person['x'] = max(-45, min(45, $person['x']));
    $person['y'] = max(-45, min(45, $person['y']));
    $person['z'] = max(150, min(300, $person['z']));

    if (rand(1, 100) <= 5) {
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
$lastVitalsSent = time();

while (true) {
    foreach ($radars as $index => $radar) {
        $topic = "radar/{$radar['license']}/{$radar['uid']}";

        if (!$vitalsOnly) {
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
        }

        $now = time();
        if (($now - $personStates[$index]['lastVitals']) >= $vitalsInterval) {
            $vitalsPayload = json_encode([
                'payload' => [
                    'deviceCode' => $radar['uid'],
                    'heartbreath' => generateHeartBreathData(rand(10, 25), rand(60, 100), $sleepStates[array_rand($sleepStates)]),
                ]
            ]);
            fwrite($socket, buildPublishPacket($topic, $vitalsPayload, 0));
            $messageCount++;
            $personStates[$index]['lastVitals'] = $now;
        }

        if ($messageCount % 5 === 0) {
            echo "-";
        }

        usleep(100000);
    }

    if (time() - $lastReport >= 5) {
        $mode = $vitalsOnly ? " [vitals-only]" : "[position+vitals]";
        echo " [" . date('H:i:s') . " Total: $messageCount]$mode\n";
        $lastReport = time();
    }
}
