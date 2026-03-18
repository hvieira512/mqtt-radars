<?php

namespace App\Repositories;

use PDO;
use App\Database;

class DeviceRepository
{
    private PDO $db;
    private array $cache = [];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getDeviceId(string $deviceCode): int
    {
        if (isset($this->cache[$deviceCode])) return $this->cache[$deviceCode];

        $stmt = $this->db->prepare("SELECT id FROM devices WHERE device_code = ?");
        $stmt->execute([$deviceCode]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            $stmt = $this->db->prepare("INSERT INTO devices (device_code) VALUES (?)");
            $stmt->execute([$deviceCode]);
            $id = $this->db->lastInsertId();
        }

        return $this->cache[$deviceCode] = (int)$id;
    }
}
