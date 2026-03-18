<?php

namespace App\Repositories;

use PDO;
use App\Database;

class StatsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function insertMinuteStats(int $eventId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO radar_minute_stats
            (event_id, version, people_count, walking_distance, walking_time, meditation_time,
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

    public function insertSleepStats(int $eventId, array $data): void
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
}
