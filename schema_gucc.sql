-- Create syntax for TABLE 'radares'
CREATE TABLE `radares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(64) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create syntax for TABLE 'radares_detecoes'
CREATE TABLE `radares_detecoes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `evento_id` bigint(20) NOT NULL,
  `dispositivo_id` int(11) NOT NULL,
  `categoria` enum('alarme','evento') NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `nivel` enum('info','aviso','perigo') NOT NULL,
  `origem` varchar(30) NOT NULL,
  `indice_pessoa` tinyint(4) DEFAULT NULL,
  `regiao_id` smallint(6) DEFAULT NULL,
  `mensagem` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolvido_em` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_det_ativo` (`dispositivo_id`,`tipo`,`indice_pessoa`,`resolvido_em`),
  KEY `idx_det_analitica` (`tipo`,`nivel`,`criado_em`),
  KEY `idx_det_categoria` (`categoria`),
  KEY `idx_det_criado` (`criado_em`),
  KEY `idx_det_dispositivo` (`dispositivo_id`),
  KEY `idx_det_evento` (`evento_id`),
  KEY `idx_det_procura` (`dispositivo_id`,`indice_pessoa`,`tipo`,`criado_em`),
  KEY `idx_det_tipo` (`tipo`),
  CONSTRAINT `fk_detecao_dispositivo` FOREIGN KEY (`dispositivo_id`) REFERENCES `radares` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detecao_evento` FOREIGN KEY (`evento_id`) REFERENCES `radares_eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create syntax for TABLE 'radares_esquema'
CREATE TABLE `radares_esquema` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `id_quarto` int(11) DEFAULT NULL,
  `id_cama` int(11) DEFAULT NULL,
  `wc` bit(1) DEFAULT b'0',
  `id_radar` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_quarto` (`id_quarto`),
  KEY `id_cama` (`id_cama`),
  KEY `wc` (`wc`),
  KEY `id_radar` (`id_radar`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Create syntax for TABLE 'radares_estatisticas_minuto'
CREATE TABLE `radares_estatisticas_minuto` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `evento_id` bigint(20) NOT NULL,
  `versao` tinyint(4) DEFAULT NULL,
  `contagem_pessoas` tinyint(4) DEFAULT NULL,
  `distancia_caminhada` smallint(6) DEFAULT NULL,
  `tempo_caminhada` smallint(6) DEFAULT NULL,
  `tempo_meditacao` smallint(6) DEFAULT NULL,
  `tempo_na_cama` smallint(6) DEFAULT NULL,
  `tempo_em_pe` smallint(6) DEFAULT NULL,
  `tempo_multiplayer` smallint(6) DEFAULT NULL,
  `respiracao_ativa` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estatisticas_minuto_evento` (`evento_id`),
  CONSTRAINT `fk_estatisticas_minuto_evento` FOREIGN KEY (`evento_id`) REFERENCES `radares_eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create syntax for TABLE 'radares_estatisticas_sono'
CREATE TABLE `radares_estatisticas_sono` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `evento_id` bigint(20) NOT NULL,
  `respiracao_tempo_real` tinyint(4) DEFAULT NULL,
  `ritmo_cardiaco_tempo_real` tinyint(4) DEFAULT NULL,
  `media_respiracao_min` tinyint(4) DEFAULT NULL,
  `media_ritmo_cardiaco_min` tinyint(4) DEFAULT NULL,
  `estado_respiracao` varchar(20) DEFAULT NULL,
  `estado_ritmo_cardiaco` varchar(20) DEFAULT NULL,
  `estado_sinais_vitais` varchar(20) DEFAULT NULL,
  `estado_sono` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estatisticas_sono_evento` (`evento_id`),
  CONSTRAINT `fk_estatisticas_sono_evento` FOREIGN KEY (`evento_id`) REFERENCES `radares_eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create syntax for TABLE 'radares_eventos'
CREATE TABLE `radares_eventos` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `dispositivo_id` int(11) NOT NULL,
  `tipo_evento_id` smallint(6) NOT NULL,
  `recebido_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_eventos_dispositivo` (`dispositivo_id`),
  KEY `idx_eventos_dispositivo_tempo` (`dispositivo_id`,`recebido_em`),
  KEY `idx_eventos_tempo` (`recebido_em`),
  KEY `idx_eventos_tipo` (`tipo_evento_id`),
  CONSTRAINT `fk_evento_dispositivo` FOREIGN KEY (`dispositivo_id`) REFERENCES `radares` (`id`),
  CONSTRAINT `fk_evento_tipo` FOREIGN KEY (`tipo_evento_id`) REFERENCES `radares_tipos_evento` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create syntax for TABLE 'radares_posicao_pessoas'
CREATE TABLE `radares_posicao_pessoas` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `evento_id` bigint(20) NOT NULL,
  `indice_pessoa` tinyint(4) DEFAULT NULL,
  `posicao_x_dm` smallint(6) DEFAULT NULL,
  `posicao_y_dm` smallint(6) DEFAULT NULL,
  `posicao_z_cm` smallint(6) DEFAULT NULL,
  `tempo_restante_seg` smallint(6) DEFAULT NULL,
  `estado_postura` varchar(50) DEFAULT NULL,
  `ultimo_evento` varchar(50) DEFAULT NULL,
  `regiao_id` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_posicao_evento` (`evento_id`),
  KEY `idx_posicao_evento_pessoa` (`evento_id`,`indice_pessoa`),
  CONSTRAINT `fk_posicao_evento` FOREIGN KEY (`evento_id`) REFERENCES `radares_eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create syntax for TABLE 'radares_relatorios_sono'
CREATE TABLE `radares_relatorios_sono` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `utilizador_id` int(11) NOT NULL,
  `dispositivo_id` int(11) NOT NULL,
  `data_relatorio` date NOT NULL,
  `pontuacao` tinyint(3) unsigned DEFAULT NULL,
  `payload_bruto` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_bruto`)),
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_utilizador_data` (`utilizador_id`,`data_relatorio`),
  KEY `idx_relatorios_procura` (`dispositivo_id`,`data_relatorio`),
  CONSTRAINT `fk_relatorio_dispositivo` FOREIGN KEY (`dispositivo_id`) REFERENCES `radares` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create syntax for TABLE 'radares_sinais_vitais'
CREATE TABLE `radares_sinais_vitais` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `evento_id` bigint(20) NOT NULL,
  `taxa_respiracao` smallint(6) DEFAULT NULL,
  `ritmo_cardiaco` smallint(6) DEFAULT NULL,
  `estado_sono` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sinais_vitais_evento` (`evento_id`),
  CONSTRAINT `fk_sinais_vitais_evento` FOREIGN KEY (`evento_id`) REFERENCES `radares_eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create syntax for TABLE 'radares_tipos_evento'
CREATE TABLE `radares_tipos_evento` (
  `id` smallint(6) NOT NULL,
  `nome` varchar(30) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nome_tipo_evento` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
