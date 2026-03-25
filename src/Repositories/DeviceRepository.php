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
            INSERT INTO dispositivos (codigo_dispositivo)
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
            SELECT d.codigo_dispositivo, d.id 
            FROM dispositivos d
            JOIN utilizador_dispositivos ud ON ud.dispositivo_id = d.id
            WHERE ud.ativo = 1
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
