<?php

namespace App\Repositories;

use PDO;
use App\Database;

class DeviceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getDeviceId(string $deviceCode): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO devices (device_code)
            VALUES (?)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
        $stmt->execute([$deviceCode]);
        $id = (int)$this->db->lastInsertId();
        return $id;
    }
}
