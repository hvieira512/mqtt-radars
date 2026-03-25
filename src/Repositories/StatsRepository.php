<?php

namespace App\Repositories;

use PDO;
use App\Database;

class StatsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function insertMinuteStats(int $eventId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO radar_estatisticas_minuto
            (evento_id, versao, contagem_pessoas, distancia_caminhada, tempo_caminhada, tempo_meditacao,
             tempo_na_cama, tempo_em_pe, tempo_multiplayer, respiracao_ativa)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $eventId,
            $data['version'],
            $data['people'],
            $data['walking_distance'],
            $data['walking_time'],
            $data['meditation_time'],
            $data['in_bed_time'],
            $data['standing_time'],
            $data['multiplayer_time'],
            $data['breathing_active']
        ]);
    }

    public function insertSleepStats(int $eventId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO radar_estatisticas_sono
            (evento_id, respiracao_tempo_real, ritmo_cardiaco_tempo_real,
             media_respiracao_min, media_ritmo_cardiaco_min,
             estado_respiracao, estado_ritmo_cardiaco,
             estado_sinais_vitais, estado_sono)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $eventId,
            $data['real_time_breathing'],
            $data['real_time_heart_rate'],
            $data['avg_breathing_per_minute'],
            $data['avg_heart_rate_per_minute'],
            $data['breathing_status_per_minute'],
            $data['heart_rate_status_per_minute'],
            $data['vital_signs_status'],
            $data['sleep_state_status']
        ]);
    }
}
