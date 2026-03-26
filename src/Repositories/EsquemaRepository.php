<?php

namespace App\Repositories;

use PDO;
use App\Database;

class EsquemaRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByRadarId(int $radarId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, q.nome as quarto_nome, c.nome as cama_nome
            FROM radares_esquema e
            LEFT JOIN quartos q ON q.id = e.id_quarto
            LEFT JOIN camas c ON c.id = e.id_cama
            WHERE e.id_radar = ?
            LIMIT 1
        ");
        $stmt->execute([$radarId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getLocation(int $radarId): ?string
    {
        $esquema = $this->findByRadarId($radarId);
        if (!$esquema) return null;

        $parts = [];
        
        if (!empty($esquema['quarto_nome'])) {
            $parts[] = $esquema['quarto_nome'];
        }
        
        if (!empty($esquema['cama_nome'])) {
            $parts[] = $esquema['cama_nome'];
        }
        
        if (!empty($esquema['wc'])) {
            $parts[] = '(WC)';
        }

        return empty($parts) ? null : implode(' - ', $parts);
    }
}
