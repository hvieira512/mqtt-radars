<?php

$device = "594B3CF100A7";

$events = ['room_entry', 'room_exit', 'area_entry', 'area_exit'];
$alarms = ['fall_confirmed', 'sitting_confirmed'];

function send($payload)
{
    $ch = curl_init("http://127.0.0.1:8081/broadcast");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    curl_exec($ch);
}

echo "Alarm simulation started...\n";

while (true) {
    $payload = [];

    // GERAR EVENTO
    $payload[] = [
        'category'    => 'event',
        'type'        => $events[array_rand($events)],
        'device_code' => $device,
    ];

    // 40% chance de alarme
    if (rand(1, 5) <= 2) {
        $payload[] = [
            'category'    => 'alarm',
            'type'        => $alarms[array_rand($alarms)],
            'device_code' => $device,
            'region_id'   => rand(1, 6),  // mantemos region_id
        ];
    }

    send($payload);

    echo "Sent: " . json_encode($payload) . "\n";

    sleep(2);
}
