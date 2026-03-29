<?php
// Test script to simulate radar data and send through the system
// Usage: php test-simulate.php

error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/vendor/autoload.php';

$mqttServer = $_ENV['MQTT_SERVER'] ?? '127.0.0.1';
$mqttPort = $_ENV['MQTT_PORT'] ?? 1883;
$mqttUsername = $_ENV['MQTT_USERNAME'] ?? 'havicare';
$mqttPassword = $_ENV['MQTT_PASSWORD'] ?? 'hitCare';
$topic = 'radar/+/+';

echo "MQTT Test Client\n";
echo "=================\n\n";

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

$settings = (new ConnectionSettings())
    ->setUsername($mqttUsername)
    ->setPassword($mqttPassword)
    ->setKeepAliveInterval(120);

try {
    $mqtt = new MqttClient($mqttServer, (int)$mqttPort, 'test-publisher');
    $mqtt->connect($settings, true);
    echo "[*] Connected to Mosquitto at $mqttServer:$mqttPort\n\n";
    
    $devices = [
        ['license' => '1001', 'uid' => 'RADAR001', 'device' => 'DEV001'],
        ['license' => '1001', 'uid' => 'RADAR002', 'device' => 'DEV002'],
        ['license' => '1002', 'uid' => 'RADAR003', 'device' => 'DEV003'],
        ['license' => '1003', 'uid' => 'RADAR004', 'device' => 'DEV004'],
    ];
    
    $count = 0;
    $maxMessages = 20;
    
    echo "[*] Sending $maxMessages test messages...\n";
    echo "[*] Topics will be: radar/{license}/{uid}\n\n";
    
    while ($count < $maxMessages) {
        $device = $devices[$count % count($devices)];
        
        // Generate random position data
        $position = base64_encode(pack('C*', 
            $count % 3,  // person_index
            rand(0, 10),  // x
            rand(0, 10),  // y
            rand(150, 200),  // z (height cm)
            rand(0, 255),  // rotation
            0, 0, 0, 0, 0, 0, 0,  // padding
            rand(10, 60),  // time_left_s
            4,  // posture: Standing
            1,  // event: Enter Room
            1   // region
        ));
        
        // Generate heartbreath data
        $heartbreath = base64_encode(pack('C*',
            0,  // padding
            rand(12, 20),  // breathing
            rand(60, 100),  // heart_rate
            0, 0, 0, 0, 0, 0, 0, 0, 0,
            0b11000000  // status: Awake
        ));
        
        // Generate hbstatics data
        $hbstatics = base64_encode(pack('C*',
            0,
            rand(12, 20),  // real_time_breathing
            rand(60, 100),  // real_time_heart_rate
            0, 0,
            rand(12, 20),  // avg_breathing
            rand(60, 100),  // avg_heart_rate
            0, 0, 0, 0,
            0b00000000  // all normal
        ));
        
        // Generate posstatics data
        $posstatics = base64_encode(pack('C*',
            2,  // version
            rand(0, 3),  // people count
            rand(10, 100),  // walking_distance
            rand(1, 10),  // walking_time
            rand(0, 30),  // meditation_time
            rand(0, 60),  // in_bed_time
            rand(0, 30),  // standing_time
            0,  // multiplayer_time
            0,
            0  // breathing_active
        ));
        
        $payload = json_encode([
            'deviceCode' => $device['device'],
            'position' => $position,
            'heartbreath' => $heartbreath,
            'hbstatics' => $hbstatics,
            'posstatics' => $posstatics,
        ]);
        
        $topicName = "radar/{$device['license']}/{$device['uid']}";
        
        $mqtt->publish($topicName, $payload, 1);
        echo "[+] Sent to $topicName (message $count)\n";
        
        $count++;
        
        // Send every 2 seconds
        if ($count < $maxMessages) {
            sleep(2);
        }
    }
    
    echo "\n[*] Finished sending $maxMessages messages\n";
    
    $mqtt->disconnect();
    
} catch (Exception $e) {
    echo "[!] Error: {$e->getMessage()}\n";
}
