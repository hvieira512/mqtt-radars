CREATE TABLE radares (
    id          INT AUTO_INCREMENT,
    uid         VARCHAR(64) NOT NULL,
    criado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT uk_uid UNIQUE (uid)
) COLLATE = utf8mb4_general_ci;


CREATE TABLE radares_esquema (
    id          INT UNSIGNED AUTO_INCREMENT,
    id_quarto   INT NULL,
    id_cama     INT NULL,
    wc          BIT NULL DEFAULT b'0',
    id_radar    INT NULL,

    PRIMARY KEY (id)
) CHARSET = utf8mb3;

CREATE INDEX id_cama   ON radares_esquema (id_cama);
CREATE INDEX id_quarto ON radares_esquema (id_quarto);
CREATE INDEX id_radar  ON radares_esquema (id_radar);
CREATE INDEX wc        ON radares_esquema (wc);


CREATE TABLE radares_relatorios_sono (
    id               BIGINT AUTO_INCREMENT,
    utilizador_id    INT NULL,
    dispositivo_id   INT NOT NULL,
    data_relatorio   DATE NOT NULL,
    pontuacao        TINYINT UNSIGNED NULL,
    payload_bruto    LONGTEXT COLLATE utf8mb4_bin NOT NULL,
    criado_em        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT uk_utilizador_data UNIQUE (utilizador_id, data_relatorio),

    CONSTRAINT fk_relatorio_dispositivo
        FOREIGN KEY (dispositivo_id)
        REFERENCES radares (id),

    CHECK (JSON_VALID(`payload_bruto`))
) COLLATE = utf8mb4_general_ci;

CREATE INDEX idx_relatorios_procura
    ON radares_relatorios_sono (dispositivo_id, data_relatorio);


CREATE TABLE radares_tipos_evento (
    id      SMALLINT NOT NULL,
    nome    VARCHAR(30) NOT NULL,

    PRIMARY KEY (id),
    CONSTRAINT uk_nome_tipo_evento UNIQUE (nome)
) COLLATE = utf8mb4_general_ci;


CREATE TABLE radares_eventos (
    id               BIGINT AUTO_INCREMENT,
    dispositivo_id   INT NOT NULL,
    tipo_evento_id   SMALLINT NOT NULL,
    recebido_em      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT fk_evento_dispositivo
        FOREIGN KEY (dispositivo_id)
        REFERENCES radares (id),

    CONSTRAINT fk_evento_tipo
        FOREIGN KEY (tipo_evento_id)
        REFERENCES radares_tipos_evento (id)
) COLLATE = utf8mb4_general_ci;

CREATE INDEX idx_eventos_dispositivo
    ON radares_eventos (dispositivo_id);

CREATE INDEX idx_eventos_dispositivo_tempo
    ON radares_eventos (dispositivo_id, recebido_em);

CREATE INDEX idx_eventos_tempo
    ON radares_eventos (recebido_em);

CREATE INDEX idx_eventos_tipo
    ON radares_eventos (tipo_evento_id);


CREATE TABLE radares_detecoes (
    id               BIGINT AUTO_INCREMENT,
    evento_id        BIGINT NOT NULL,
    dispositivo_id   INT NOT NULL,
    categoria        ENUM('alarme', 'evento') NOT NULL,
    tipo             VARCHAR(50) NOT NULL,
    nivel            ENUM('info', 'aviso', 'perigo') NOT NULL,
    origem           VARCHAR(30) NOT NULL,
    indice_pessoa    TINYINT NULL,
    regiao_id        SMALLINT NULL,
    mensagem         VARCHAR(255) NULL,
    criado_em        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolvido_em     TIMESTAMP NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_detecao_evento
        FOREIGN KEY (evento_id)
        REFERENCES radares_eventos (id)
        ON DELETE CASCADE,

    CONSTRAINT fk_detecao_dispositivo
        FOREIGN KEY (dispositivo_id)
        REFERENCES radares (id)
        ON DELETE CASCADE
) COLLATE = utf8mb4_general_ci;

CREATE INDEX idx_det_analitica
    ON radares_detecoes (tipo, nivel, criado_em);

CREATE INDEX idx_det_ativo
    ON radares_detecoes (dispositivo_id, tipo, indice_pessoa, resolvido_em);

CREATE INDEX idx_det_categoria
    ON radares_detecoes (categoria);

CREATE INDEX idx_det_criado
    ON radares_detecoes (criado_em);

CREATE INDEX idx_det_dispositivo
    ON radares_detecoes (dispositivo_id);

CREATE INDEX idx_det_evento
    ON radares_detecoes (evento_id);

CREATE INDEX idx_det_procura
    ON radares_detecoes (dispositivo_id, indice_pessoa, tipo, criado_em);

CREATE INDEX idx_det_tipo
    ON radares_detecoes (tipo);


CREATE TABLE radares_estatisticas_minuto (
    id                      BIGINT AUTO_INCREMENT,
    evento_id               BIGINT NOT NULL,
    versao                  TINYINT NULL,
    contagem_pessoas        TINYINT NULL,
    distancia_caminhada     SMALLINT NULL,
    tempo_caminhada         SMALLINT NULL,
    tempo_meditacao         SMALLINT NULL,
    tempo_na_cama           SMALLINT NULL,
    tempo_em_pe             SMALLINT NULL,
    tempo_multiplayer       SMALLINT NULL,
    respiracao_ativa        TINYINT(1) NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_estatisticas_minuto_evento
        FOREIGN KEY (evento_id)
        REFERENCES radares_eventos (id)
        ON DELETE CASCADE
) COLLATE = utf8mb4_general_ci;

CREATE INDEX idx_estatisticas_minuto_evento
    ON radares_estatisticas_minuto (evento_id);


CREATE TABLE radares_estatisticas_sono (
    id                            BIGINT AUTO_INCREMENT,
    evento_id                     BIGINT NOT NULL,
    respiracao_tempo_real         TINYINT NULL,
    ritmo_cardiaco_tempo_real     TINYINT NULL,
    media_respiracao_min          TINYINT NULL,
    media_ritmo_cardiaco_min      TINYINT NULL,
    estado_respiracao             VARCHAR(20) NULL,
    estado_ritmo_cardiaco         VARCHAR(20) NULL,
    estado_sinais_vitais          VARCHAR(20) NULL,
    estado_sono                   VARCHAR(20) NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_estatisticas_sono_evento
        FOREIGN KEY (evento_id)
        REFERENCES radares_eventos (id)
        ON DELETE CASCADE
) COLLATE = utf8mb4_general_ci;

CREATE INDEX idx_estatisticas_sono_evento
    ON radares_estatisticas_sono (evento_id);


CREATE TABLE radares_posicao_pessoas (
    id                     BIGINT AUTO_INCREMENT,
    evento_id              BIGINT NOT NULL,
    indice_pessoa          TINYINT NULL,
    posicao_x_dm           SMALLINT NULL,
    posicao_y_dm           SMALLINT NULL,
    posicao_z_cm           SMALLINT NULL,
    tempo_restante_seg     SMALLINT NULL,
    estado_postura         VARCHAR(50) NULL,
    ultimo_evento          VARCHAR(50) NULL,
    regiao_id              SMALLINT NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_posicao_evento
        FOREIGN KEY (evento_id)
        REFERENCES radares_eventos (id)
        ON DELETE CASCADE
) COLLATE = utf8mb4_general_ci;

CREATE INDEX idx_posicao_evento
    ON radares_posicao_pessoas (evento_id);

CREATE INDEX idx_posicao_evento_pessoa
    ON radares_posicao_pessoas (evento_id, indice_pessoa);


CREATE TABLE radares_sinais_vitais (
    id                  BIGINT AUTO_INCREMENT,
    evento_id           BIGINT NOT NULL,
    taxa_respiracao     SMALLINT NULL,
    ritmo_cardiaco      SMALLINT NULL,
    estado_sono         VARCHAR(20) NULL,

    PRIMARY KEY (id),

    CONSTRAINT fk_sinais_vitais_evento
        FOREIGN KEY (evento_id)
        REFERENCES radares_eventos (id)
        ON DELETE CASCADE
) COLLATE = utf8mb4_general_ci;

CREATE INDEX idx_sinais_vitais_evento
    ON radares_sinais_vitais (evento_id);
