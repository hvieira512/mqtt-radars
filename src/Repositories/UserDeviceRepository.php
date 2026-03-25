<?php

namespace App\Repositories;

use PDO;
use App\Database;

class UserDeviceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Get the currently active user for a given device.
     */
    public function getActiveUserId(int $deviceId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT utilizador_id 
            FROM utilizador_dispositivos
            WHERE dispositivo_id = ? AND ativo = 1
            LIMIT 1
        ");
        $stmt->execute([$deviceId]);

        $userId = $stmt->fetchColumn();
        return $userId ? (int)$userId : null;
    }
}