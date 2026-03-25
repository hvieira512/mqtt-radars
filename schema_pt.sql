-- 1. Dispositivos
CREATE TABLE dispositivos (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    codigo_dispositivo VARCHAR(64) NOT NULL,
    criado_em          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_codigo_dispositivo UNIQUE (codigo_dispositivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tipos de Eventos Radar
CREATE TABLE radar_tipos_evento (
    id   SMALLINT NOT NULL PRIMARY KEY,
    nome VARCHAR(30) NOT NULL,
    CONSTRAINT uk_nome_tipo_evento UNIQUE (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Eventos Radar
CREATE TABLE radar_eventos (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    dispositivo_id  INT NOT NULL,
    tipo_evento_id  SMALLINT NOT NULL,
    recebido_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_evento_dispositivo FOREIGN KEY (dispositivo_id) REFERENCES dispositivos (id),
    CONSTRAINT fk_evento_tipo FOREIGN KEY (tipo_evento_id) REFERENCES radar_tipos_evento (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Deteções Radar
CREATE TABLE radar_detecoes (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    evento_id       BIGINT NOT NULL,
    dispositivo_id  INT NOT NULL,
    categoria       ENUM ('alarme', 'evento') NOT NULL,
    tipo            VARCHAR(50) NOT NULL,
    nivel           ENUM ('info', 'aviso', 'perigo') NOT NULL,
    origem          VARCHAR(30) NOT NULL,
    indice_pessoa   TINYINT NULL,
    regiao_id       SMALLINT NULL,
    mensagem        VARCHAR(255) NULL,
    criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolvido_em    TIMESTAMP NULL,
    CONSTRAINT fk_detecao_dispositivo FOREIGN KEY (dispositivo_id) REFERENCES dispositivos (id) ON DELETE CASCADE,
    CONSTRAINT fk_detecao_evento FOREIGN KEY (evento_id) REFERENCES radar_eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Estatísticas por Minuto
CREATE TABLE radar_estatisticas_minuto (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    evento_id           BIGINT NOT NULL,
    versao              TINYINT NULL,
    contagem_pessoas    TINYINT NULL,
    distancia_caminhada SMALLINT NULL,
    tempo_caminhada     SMALLINT NULL,
    tempo_meditacao     SMALLINT NULL,
    tempo_na_cama       SMALLINT NULL,
    tempo_em_pe         SMALLINT NULL,
    tempo_multiplayer   SMALLINT NULL,
    respiracao_ativa    TINYINT(1) NULL,
    CONSTRAINT fk_estatisticas_minuto_evento FOREIGN KEY (evento_id) REFERENCES radar_eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Posição de Pessoas
CREATE TABLE radar_posicao_pessoas (
    id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
    evento_id           BIGINT NOT NULL,
    indice_pessoa       TINYINT NULL,
    posicao_x_dm        SMALLINT NULL,
    posicao_y_dm        SMALLINT NULL,
    posicao_z_cm        SMALLINT NULL,
    tempo_restante_seg  SMALLINT NULL,
    estado_postura      VARCHAR(50) NULL,
    ultimo_evento       VARCHAR(50) NULL,
    regiao_id           SMALLINT NULL,
    CONSTRAINT fk_posicao_evento FOREIGN KEY (evento_id) REFERENCES radar_eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Relatórios de Sono
CREATE TABLE radar_relatorios_sono (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id   INT NOT NULL,
    dispositivo_id  INT NOT NULL,
    data_relatorio  DATE NOT NULL,
    pontuacao       TINYINT UNSIGNED NULL,
    payload_bruto   JSON NOT NULL,
    criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uk_utilizador_data UNIQUE (utilizador_id, data_relatorio),
    CONSTRAINT fk_relatorio_dispositivo FOREIGN KEY (dispositivo_id) REFERENCES dispositivos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Estatísticas de Sono
CREATE TABLE radar_estatisticas_sono (
    id                        BIGINT AUTO_INCREMENT PRIMARY KEY,
    evento_id                 BIGINT NOT NULL,
    respiracao_tempo_real     TINYINT NULL,
    ritmo_cardiaco_tempo_real TINYINT NULL,
    media_respiracao_min      TINYINT NULL,
    media_ritmo_cardiaco_min  TINYINT NULL,
    estado_respiracao         VARCHAR(20) NULL,
    estado_ritmo_cardiaco     VARCHAR(20) NULL,
    estado_sinais_vitais      VARCHAR(20) NULL,
    estado_sono               VARCHAR(20) NULL,
    CONSTRAINT fk_estatisticas_sono_evento FOREIGN KEY (evento_id) REFERENCES radar_eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Sinais Vitais
CREATE TABLE radar_sinais_vitais (
    id                BIGINT AUTO_INCREMENT PRIMARY KEY,
    evento_id         BIGINT NOT NULL,
    taxa_respiracao   SMALLINT NULL,
    ritmo_cardiaco    SMALLINT NULL,
    estado_sono       VARCHAR(20) NULL,
    CONSTRAINT fk_sinais_vitais_evento FOREIGN KEY (evento_id) REFERENCES radar_eventos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Dispositivos de Utilizador (Pivot/Associação)
CREATE TABLE utilizador_dispositivos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id  INT NOT NULL,
    dispositivo_id INT NOT NULL,
    ativo          TINYINT(1) DEFAULT 1,
    atribuido_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_utilizador_dispositivo UNIQUE (utilizador_id, dispositivo_id),
    CONSTRAINT fk_utilizador_dispositivo_dispositivo FOREIGN KEY (dispositivo_id) REFERENCES dispositivos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índices para radar_detecoes
CREATE INDEX idx_det_ativo ON radar_detecoes (dispositivo_id, tipo, indice_pessoa, resolvido_em);
CREATE INDEX idx_det_analitica ON radar_detecoes (tipo, nivel, criado_em);
CREATE INDEX idx_det_categoria ON radar_detecoes (categoria);
CREATE INDEX idx_det_criado ON radar_detecoes (criado_em);
CREATE INDEX idx_det_dispositivo ON radar_detecoes (dispositivo_id);
CREATE INDEX idx_det_evento ON radar_detecoes (evento_id);
CREATE INDEX idx_det_procura ON radar_detecoes (dispositivo_id, indice_pessoa, tipo, criado_em);
CREATE INDEX idx_det_tipo ON radar_detecoes (tipo);

-- Índices para radar_eventos
CREATE INDEX idx_eventos_dispositivo ON radar_eventos (dispositivo_id);
CREATE INDEX idx_eventos_dispositivo_tempo ON radar_eventos (dispositivo_id, recebido_em);
CREATE INDEX idx_eventos_tempo ON radar_eventos (recebido_em);
CREATE INDEX idx_eventos_tipo ON radar_eventos (tipo_evento_id);

-- Índices para radar_estatisticas_minuto
CREATE INDEX idx_estatisticas_minuto_evento ON radar_estatisticas_minuto (evento_id);

-- Índices para radar_posicao_pessoas
CREATE INDEX idx_posicao_evento ON radar_posicao_pessoas (evento_id);
CREATE INDEX idx_posicao_evento_pessoa ON radar_posicao_pessoas (evento_id, indice_pessoa);

-- Índices para radar_relatorios_sono
CREATE INDEX idx_relatorios_procura ON radar_relatorios_sono (dispositivo_id, data_relatorio);

-- Índices para radar_estatisticas_sono
CREATE INDEX idx_estatisticas_sono_evento ON radar_estatisticas_sono (evento_id);

-- Índices para radar_sinais_vitais
CREATE INDEX idx_sinais_vitais_evento ON radar_sinais_vitais (evento_id);
