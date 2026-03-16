<?php

$alarms = [
    [
        'category'     => 'alarm',
        'alarm_type'   => 'fall_confirmed',
        'level'        => 'danger',
        'source'       => 'position',
        'person_index' => 1,
        'region_id'    => 5,
        'device_code'  => '594B3CF100A7',
        'message'      => 'Teste: queda simulada!'
    ],
    [
        'category'     => 'event',
        'alarm_type'   => 'room_entry',
        'level'        => 'info',
        'source'       => 'position',
        'person_index' => 1,
        'region_id'    => 5,
        'device_code'  => '594B3CF100A7'
    ]
];

$ch = curl_init("http://127.0.0.1:8081/broadcast");

curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($alarms)
]);

$response = curl_exec($ch);

echo "Test alarms sent. Response: {$response}\n";
