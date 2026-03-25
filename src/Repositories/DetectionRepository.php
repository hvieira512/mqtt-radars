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
            INSERT INTO radar_detecoes
            (evento_id, dispositivo_id, categoria, tipo, nivel, origem, indice_pessoa, regiao_id, mensagem)
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
            UPDATE radar_detecoes
            SET resolvido_em = NOW()
            WHERE dispositivo_id = ?
              AND tipo = ?
              AND indice_pessoa <=> ?
              AND resolvido_em IS NULL
        ");
        $stmt->execute([$deviceId, $type, $personIndex]);
    }
}
