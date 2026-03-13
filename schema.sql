CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(32) NOT NULL UNIQUE,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE radar_event_types (
    id SMALLINT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE radar_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    event_type_id SMALLINT NOT NULL,
    received_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_events_device (device_id),
    INDEX idx_events_time (received_at),
    INDEX idx_events_device_time (device_id, received_at),
    INDEX idx_events_type (event_type_id),

    CONSTRAINT fk_event_type
        FOREIGN KEY (event_type_id)
        REFERENCES radar_event_types (id),

    CONSTRAINT fk_event_device
        FOREIGN KEY (device_id)
        REFERENCES devices (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE radar_alarm_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    event_code SMALLINT NOT NULL,
    event_name VARCHAR(100) NULL,
    zone_id SMALLINT NULL,
    person_index TINYINT NULL,
    extra_data JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_alarm_event (event_id),
    INDEX idx_alarm_code (event_code),

    CONSTRAINT fk_alarm_event
        FOREIGN KEY (event_id)
        REFERENCES radar_events (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE radar_minute_stats (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    version TINYINT NULL,
    people_count TINYINT NULL,
    walking_distance SMALLINT NULL,
    walking_time SMALLINT NULL,
    meditation_time SMALLINT NULL,
    in_bed_time SMALLINT NULL,
    standing_time SMALLINT NULL,
    multiplayer_time SMALLINT NULL,
    breathing_active TINYINT(1) NULL,

    INDEX idx_minute_event (event_id),

    CONSTRAINT fk_minute_event
        FOREIGN KEY (event_id)
        REFERENCES radar_events (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE radar_position_people (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    person_index TINYINT NULL,
    x_position_dm SMALLINT NULL,
    y_position_dm SMALLINT NULL,
    z_position_cm SMALLINT NULL,
    time_left_seconds SMALLINT NULL,
    posture_state VARCHAR(50) NULL,
    last_event VARCHAR(50) NULL,
    region_id TINYINT NULL,

    INDEX idx_people_event (event_id),
    INDEX idx_people_event_person (event_id, person_index),

    CONSTRAINT fk_people_event
        FOREIGN KEY (event_id)
        REFERENCES radar_events (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE radar_sleep_stats (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    real_time_breathing TINYINT NULL,
    real_time_heart_rate TINYINT NULL,
    avg_breathing_per_minute TINYINT NULL,
    avg_heart_rate_per_minute TINYINT NULL,
    breathing_status VARCHAR(20) NULL,
    heart_rate_status VARCHAR(20) NULL,
    vital_signs_status VARCHAR(20) NULL,
    sleep_state_status VARCHAR(20) NULL,

    INDEX idx_sleep_event (event_id),

    CONSTRAINT fk_sleep_event
        FOREIGN KEY (event_id)
        REFERENCES radar_events (id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4;


CREATE TABLE radar_vitals (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    breathing_rate TINYINT NULL,
    heart_rate TINYINT NULL,
    sleep_state VARCHAR(20) NULL,

    INDEX idx_vitals_event (event_id),

    CONSTRAINT fk_vitals_event
        FOREIGN KEY (event_id)
        REFERENCES radar_events (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;