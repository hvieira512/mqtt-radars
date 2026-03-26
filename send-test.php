<?php
require __DIR__ . '/vendor/autoload.php';

$settings = (new \PhpMqtt\Client\ConnectionSettings())
    ->setUsername('havicare')
    ->setPassword('hitCare');

$client = new \PhpMqtt\Client\MqttClient('127.0.0.1', 1883, 'test-radar');
$client->connect($settings);

$payloads = [
    'radar/1001/AD8A613B0493' => json_encode([
        'payload' => [
            'deviceCode' => 'RADAR001',
            'position' => 'SGVsbG8gVGVzdA==',
            'heartbreath' => 'SGVsbG8gSGI='
        ]
    ]),
    'radar/1002/uid456' => json_encode([
        'payload' => [
            'deviceCode' => 'RADAR002',
            'position' => 'VGVzdCBwb3NpdGlvbg=='
        ]
    ])
];

foreach ($payloads as $topic => $message) {
    echo "Publishing to: $topic\n";
    $client->publish($topic, $message, 0);
    usleep(500000);
}

$client->disconnect();
echo "Done - check mqtt-server logs\n";

