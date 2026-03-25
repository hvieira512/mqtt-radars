<?php

namespace App\Repositories;

use PDO;
use App\Database;

class SleepReportRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function find(int $deviceId, string $date): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM radar_relatorios_sono
            WHERE dispositivo_id = ? AND data_relatorio = ?
            LIMIT 1
        ");
        $stmt->execute([$deviceId, $date]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(
        int $deviceId,
        int $userId,
        string $date,
        ?int $score,
        array $payload
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO radar_relatorios_sono (utilizador_id, dispositivo_id, data_relatorio, pontuacao, payload_bruto)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pontuacao = VALUES(pontuacao),
                payload_bruto = VALUES(payload_bruto)
        ");

        $stmt->execute([
            $userId,
            $deviceId,
            $date,
            $score,
            json_encode($payload)
        ]);
    }
}

