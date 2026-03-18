<?php

namespace App\Repositories;

use PDO;
use App\Database;

class PositionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
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
}
