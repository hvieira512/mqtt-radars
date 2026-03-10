<?php

namespace App;

class Logger
{
    public static function info(string $msg): void
    {
        self::log("INFO", $msg);
    }

    public static function warn(string $msg): void
    {
        self::log("WARN", $msg);
    }

    public static function error(string $msg): void
    {
        self::log("ERROR", $msg);
    }

    public static function logData(array $data, ?string $timestamp = null): void
    {
        $timestamp = $timestamp ?? date('H:i:s');

        echo "[$timestamp] Type: {$data['type']}, Device: {$data['device_code']}\n";

        if (isset($data['people']) && is_array($data['people'])) {
            foreach ($data['people'] as $p) {
                echo "  Person {$p['person_index']}: ";
                echo "x={$p['x_position_dm']} dm, ";
                echo "y={$p['y_position_dm']} dm, ";
                echo "z={$p['z_position_cm']} cm, ";
                echo "Posture={$p['posture_state']}, ";
                echo "Event={$p['last_event']}, ";
                echo "Region={$p['region_id']}";

                if (isset($p['rotation_deg'])) {
                    echo ", Rotation={$p['rotation_deg']}°";
                }

                if (isset($p['direction'])) {
                    $dx = $p['direction']['dx'] ?? 0;
                    $dy = $p['direction']['dy'] ?? 0;
                    echo sprintf(", Direction=(%.2f, %.2f)", $dx, $dy);
                }

                echo "\n";
            }
        } else {
            foreach ($data as $k => $v) {
                if (!in_array($k, ['type', 'device_code', 'people'])) {
                    echo "  $k: $v\n";
                }
            }
        }

        echo str_repeat("-", 50) . "\n";
    }

    private static function log(string $level, string $msg): void
    {
        $time = date('Y-m-d H:i:s');
        echo "[$time] [$level] $msg\n";
    }
}
