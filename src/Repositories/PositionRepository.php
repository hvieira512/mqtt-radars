<?php

namespace App\Repositories;

use PDO;
use App\Database;

class PositionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function insertPosition(int $eventId, array $people): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO radar_posicao_pessoas
            (evento_id, indice_pessoa, posicao_x_dm, posicao_y_dm, posicao_z_cm, tempo_restante_seg, estado_postura, ultimo_evento, regiao_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($people as $p) {
            $stmt->execute([
                $eventId,
                $p['person_index'],
                $p['x_position_dm'],
                $p['y_position_dm'],
                $p['z_position_cm'],
                $p['time_left_s'],
                $p['posture_state'],
                $p['last_event'],
                $p['region_id']
            ]);
        }
    }
}
