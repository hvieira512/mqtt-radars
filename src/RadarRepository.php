<?php

namespace App;

use PDO;

class RadarRepository
{
    private PDO $db;
    private array $deviceCache = [];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getDeviceId(string $deviceCode): int
    {
        if (isset($this->deviceCache[$deviceCode])) {
            return $this->deviceCache[$deviceCode];
        }

        $stmt = $this->db->prepare("SELECT id FROM devices WHERE device_code = ?");
        $stmt->execute([$deviceCode]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            $stmt = $this->db->prepare("INSERT INTO devices (device_code) VALUES (?)");
            $stmt->execute([$deviceCode]);
            $id = $this->db->lastInsertId();
        }

        return $this->deviceCache[$deviceCode] = (int)$id;
    }


    public function createEvent(int $deviceId, int $eventTypeId): int
    {
        $stmt = $this->db->prepare("INSERT INTO radar_events (device_id, event_type_id) VALUES (?, ?)");
        $stmt->execute([$deviceId, $eventTypeId]);

        return (int)$this->db->lastInsertId();
    }

    public function insertPosition(int $eventId, array $people): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO radar_position_people
            (event_id, person_index, x_position_dm, y_position_dm, z_position_cm, time_left_seconds, posture_state, last_event, region_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($people as $p) {
            $stmt->execute([
                $eventId,
                $p['person_index'],
                $p['x_position_dm'],
                $p['y_position_dm'],
                $p['z_position_cm'],
                $p['time_left_s'],
                $p['posture_state'],
                $p['last_event'],
                $p['region_id']
            ]);
        }
    }

    public function insertMinuteStats(int $eventId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO radar_minute_stats
            (event_id, version, people_count,
             walking_distance, walking_time, meditation_time,
             in_bed_time, standing_time, multiplayer_time, breathing_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $eventId,
            $data['version'],
            $data['people'],
            $data['walking_distance'],
            $data['walking_time'],
            $data['meditation_time'],
            $data['in_bed_time'],
            $data['standing_time'],
            $data['multiplayer_time'],
            $data['breathing_active']
        ]);
    }

    public function insertVitals(int $eventId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO radar_vitals
            (event_id, breathing_rate, heart_rate, sleep_state)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $eventId,
            $data['breathing'],
            $data['heart_rate'],
            $data['sleep_state']
        ]);
    }

    public function insertHbStatics(int $eventId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO radar_sleep_stats
            (event_id, real_time_breathing, real_time_heart_rate,
             avg_breathing_per_minute, avg_heart_rate_per_minute,
             breathing_status, heart_rate_status,
             vital_signs_status, sleep_state_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $eventId,
            $data['real_time_breathing'],
            $data['real_time_heart_rate'],
            $data['avg_breathing_per_minute'],
            $data['avg_heart_rate_per_minute'],
            $data['breathing_status_per_minute'],
            $data['heart_rate_status_per_minute'],
            $data['vital_signs_status'],
            $data['sleep_state_status']
        ]);
    }

    public function insertAlarmEvent(int $eventId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO radar_alarm_events
            (event_id, event_code, event_name, zone_id, person_index, extra_data)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $eventId,
            $data['event_code'] ?? null,
            $data['event_name'] ?? null,
            $data['zone_id'] ?? null,
            $data['person_index'] ?? null,
            isset($data['params']) ? json_encode($data['params']) : null
        ]);
    }
}
