<?php

namespace App\Repositories;

use PDO;
use App\Database;

class DetectionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function insertDetection(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO radar_detections
            (event_id, device_id, category, type, level, source, person_index, region_id, message)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['event_id'],
            $data['device_id'],
            $data['category'],
            $data['type'],
            $data['level'],
            $data['source'],
            $data['person_index'],
            $data['region_id'],
            $data['message'],
        ]);
    }

    public function resolveDetection($deviceId, $personIndex, $type): void
    {
        $stmt = $this->db->prepare("
            UPDATE radar_detections
            SET resolved_at = NOW()
            WHERE device_id = ?
              AND type = ?
              AND person_index <=> ?
              AND resolved_at IS NULL
        ");
        $stmt->execute([$deviceId, $type, $personIndex]);
    }
}
