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
            SELECT * FROM radares_relatorios_sono
            WHERE dispositivo_id = ? AND data_relatorio = ?
            LIMIT 1
        ");
        $stmt->execute([$deviceId, $date]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function insert(
        int $deviceId,
        ?int $userId,
        string $date,
        ?int $score,
        array $payload
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO radares_relatorios_sono (utilizador_id, dispositivo_id, data_relatorio, pontuacao, payload_bruto)
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

    public function getReportDates(int $deviceId, string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare("
            SELECT data_relatorio as date FROM radares_relatorios_sono
            WHERE dispositivo_id = ?
              AND data_relatorio >= ?
              AND data_relatorio <= ?
            ORDER BY data_relatorio DESC
        ");
        $stmt->execute([$deviceId, $startDate, $endDate]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
