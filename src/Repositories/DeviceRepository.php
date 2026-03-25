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

    public function getActiveDevices(): array
    {
        $stmt = $this->db->query("
            SELECT d.device_code, d.id 
            FROM devices d
            JOIN user_devices ud ON ud.device_id = d.id
            WHERE ud.is_active = 1
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
