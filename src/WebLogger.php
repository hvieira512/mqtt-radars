<?php

namespace App;

use WebSocket\Client;

class WebLogger
{
    private static ?Client $wsClient = null;
    private static bool $echoLocal = false;

    public static function initWebSocket(Client $client, bool $echoLocal = false): void
    {
        self::$wsClient = $client;
        self::$echoLocal = $echoLocal;
    }

    public static function send(array $data): void
    {
        $timestamp = date('H:i:s');

        $people = $data['people'] ?? [];

        // Enhance position type with rotation/direction for frontend
        if (($data['type'] ?? '') === 'position') {
            foreach ($people as &$p) {
                if (!isset($p['rotation_deg']) && isset($p['direction'])) {
                    $p['rotation_deg'] = rad2deg(atan2($p['direction']['dy'], $p['direction']['dx']));
                } elseif (!isset($p['direction']) && isset($p['rotation_deg'])) {
                    $angleRad = deg2rad($p['rotation_deg']);
                    $p['direction'] = ['dx' => cos($angleRad), 'dy' => sin($angleRad)];
                } elseif (!isset($p['rotation_deg']) && !isset($p['direction'])) {
                    $p['rotation_deg'] = 0;
                    $p['direction'] = ['dx' => 0, 'dy' => 1];
                }
            }
            unset($p);
        }

        $payload = [
            'timestamp' => $timestamp,
            'type'      => $data['type'] ?? 'unknown',
            'device'    => $data['device_code'] ?? null,
            'people'    => $people,
            'extra'     => array_diff_key($data, array_flip(['type', 'device_code', 'people']))
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        if (self::$echoLocal) {
            echo $json . "\n";
        }

        // Send to WebSocket server
        if (self::$wsClient) {
            try {
                self::$wsClient->send($json);
            } catch (\Exception $e) {
                echo "[WebSocket Error] " . $e->getMessage() . "\n";
            }
        }
    }
}
