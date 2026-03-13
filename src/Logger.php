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

    public static function logData(array $data): void
    {
        $lines = [];
        $lines[] = "Type: {$data['type']}, Device: {$data['device_code']}";

        if (!empty($data['people']) && is_array($data['people'])) {

            foreach ($data['people'] as $p) {

                $line = "Person {$p['person_index']}: "
                    . "x={$p['x_position_dm']} dm, "
                    . "y={$p['y_position_dm']} dm, "
                    . "z={$p['z_position_cm']} cm, "
                    . "Posture={$p['posture_state']}, "
                    . "Event={$p['last_event']}, "
                    . "Region ID={$p['region_id']}";

                if (isset($p['rotation_deg'])) {
                    $line .= ", Rotation={$p['rotation_deg']}°";
                }

                if (isset($p['direction'])) {
                    $dx = $p['direction']['dx'] ?? 0;
                    $dy = $p['direction']['dy'] ?? 0;
                    $line .= ", Direction=(dx:$dx, dy:$dy)";
                }

                $lines[] = $line;
            }
        } else {

            foreach ($data as $k => $v) {
                if (!in_array($k, ['type', 'device_code', 'people'])) {
                    $lines[] = "$k: $v";
                }
            }
        }

        $lines[] = str_repeat("-", 50);

        Logger::info(implode("\n", $lines));
    }


    private static function log(string $level, string $msg): void
    {
        $time = date('Y-m-d H:i:s');
        echo "[$time] [$level] $msg\n";
    }
}
