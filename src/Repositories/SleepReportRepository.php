<?php

namespace App\Repositories;

use PDO;
use App\Database;

class SleepReportRepository
{
    private PDO $db;    

    public function __construct() {
        $this->db = Database::connection();
    }

    public function find(int $deviceId, string $date): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM radar_sleep_reports
            WHERE device_id = ? AND report_date = ?
            LIMIT 1
        ");
        $stmt->execute([$deviceId, $date]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(
        int $deviceId,
        int $userId,
        string $date,
        ?int $score,
        array $payload
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO radar_sleep_reports (user_id, device_id, report_date, score, raw_payload)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                raw_payload = VALUES(raw_payload)
        ");

        $stmt->execute([
            $userId,
            $deviceId,
            $date,
            $score,
            json_encode($payload)
        ]);
    }
}