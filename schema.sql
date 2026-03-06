CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(32) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE radar_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    event_type ENUM(
        'position',
        'minute_stats',
        'vitals',
        'hbstatics'
    ) NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (device_id) REFERENCES devices(id)
);

CREATE TABLE radar_position_people (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,

    person_index TINYINT,
    x_position_dm SMALLINT,
    y_position_dm SMALLINT,
    z_position_cm SMALLINT,

    time_left_seconds SMALLINT,
    posture_state VARCHAR(50),
    last_event VARCHAR(50),
    region_id TINYINT,

    FOREIGN KEY (event_id) REFERENCES radar_events(id)
);

CREATE TABLE radar_minute_stats (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,

    version TINYINT,
    people_count TINYINT,

    walking_distance SMALLINT,
    walking_time SMALLINT,
    meditation_time SMALLINT,
    in_bed_time SMALLINT,
    standing_time SMALLINT,
    multiplayer_time SMALLINT,

    breathing_active BOOLEAN,

    FOREIGN KEY (event_id) REFERENCES radar_events(id)
);

CREATE TABLE radar_vitals (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,

    breathing_rate TINYINT,
    heart_rate TINYINT,
    sleep_state VARCHAR(20),

    FOREIGN KEY (event_id) REFERENCES radar_events(id)
);

CREATE TABLE radar_sleep_stats (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,

    real_time_breathing TINYINT,
    real_time_heart_rate TINYINT,

    avg_breathing_per_minute TINYINT,
    avg_heart_rate_per_minute TINYINT,

    breathing_status VARCHAR(20),
    heart_rate_status VARCHAR(20),
    vital_signs_status VARCHAR(20),
    sleep_state_status VARCHAR(20),

    FOREIGN KEY (event_id) REFERENCES radar_events(id)
);
