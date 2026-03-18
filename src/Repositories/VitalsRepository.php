<?php

namespace App\Repositories;

use PDO;
use App\Database;

class VitalsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
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
}
