<?php

namespace App\Repositories;

use PDO;
use App\Database;

class EventRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function createEvent(int $deviceId, int $eventTypeId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO radar_events (device_id, event_type_id) VALUES (?, ?)"
        );
        $stmt->execute([$deviceId, $eventTypeId]);
        return (int)$this->db->lastInsertId();
    }

    public function getEventTypes(): array
    {
        return $this->db->query("SELECT * FROM radar_event_types")->fetchAll(PDO::FETCH_ASSOC);
    }
}
