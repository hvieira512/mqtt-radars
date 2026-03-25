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
            "INSERT INTO radar_eventos (dispositivo_id, tipo_evento_id) VALUES (?, ?)"
        );
        $stmt->execute([$deviceId, $eventTypeId]);
        return (int)$this->db->lastInsertId();
    }

    public function getEventTypes(): array
    {
        return $this->db->query("SELECT * FROM radar_tipos_evento")->fetchAll(PDO::FETCH_ASSOC);
    }
}
