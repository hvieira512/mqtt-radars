create table devices
(
    id          int auto_increment
        primary key,
    device_code varchar(64)                         null,
    created_at  timestamp default CURRENT_TIMESTAMP null,
    constraint device_code
        unique (device_code)
)
    charset = utf8mb3;

create table radar_event_types
(
    id   smallint    not null
        primary key,
    name varchar(30) not null,
    constraint name
        unique (name)
);

create table radar_events
(
    id            bigint auto_increment
        primary key,
    device_id     int                                 not null,
    event_type_id smallint                            not null,
    received_at   timestamp default CURRENT_TIMESTAMP null,
    constraint fk_event_type
        foreign key (event_type_id) references radar_event_types (id),
    constraint radar_events_ibfk_1
        foreign key (device_id) references devices (id)
);

create table radar_detections
(
    id           bigint auto_increment
        primary key,
    event_id     bigint                              not null,
    device_id    int                                 not null,
    category     enum ('alarm', 'event')             not null,
    type         varchar(50)                         not null,
    level        enum ('info', 'warning', 'danger')  not null,
    source       varchar(30)                         not null,
    person_index tinyint                             null,
    region_id    smallint                            null,
    message      varchar(255)                        null,
    created_at   timestamp default CURRENT_TIMESTAMP null,
    resolved_at  timestamp                           null,
    constraint fk_detection_device
        foreign key (device_id) references devices (id)
            on delete cascade,
    constraint fk_detection_event
        foreign key (event_id) references radar_events (id)
            on delete cascade
);

create index idx_det_active
    on radar_detections (device_id, type, person_index, resolved_at);

create index idx_det_analytics
    on radar_detections (type, level, created_at);

create index idx_det_category
    on radar_detections (category);

create index idx_det_created
    on radar_detections (created_at);

create index idx_det_device
    on radar_detections (device_id);

create index idx_det_event
    on radar_detections (event_id);

create index idx_det_lookup
    on radar_detections (device_id, person_index, type, created_at);

create index idx_det_type
    on radar_detections (type);

create index idx_events_device
    on radar_events (device_id);

create index idx_events_device_time
    on radar_events (device_id, received_at);

create index idx_events_time
    on radar_events (received_at);

create index idx_events_type
    on radar_events (event_type_id);

create table radar_minute_stats
(
    id               bigint auto_increment
        primary key,
    event_id         bigint     not null,
    version          tinyint    null,
    people_count     tinyint    null,
    walking_distance smallint   null,
    walking_time     smallint   null,
    meditation_time  smallint   null,
    in_bed_time      smallint   null,
    standing_time    smallint   null,
    multiplayer_time smallint   null,
    breathing_active tinyint(1) null,
    constraint radar_minute_stats_ibfk_1
        foreign key (event_id) references radar_events (id)
);

create index event_id
    on radar_minute_stats (event_id);

create table radar_position_people
(
    id                bigint auto_increment
        primary key,
    event_id          bigint      not null,
    person_index      tinyint     null,
    x_position_dm     smallint    null,
    y_position_dm     smallint    null,
    z_position_cm     smallint    null,
    time_left_seconds smallint    null,
    posture_state     varchar(50) null,
    last_event        varchar(50) null,
    region_id         smallint    null,
    constraint radar_position_people_ibfk_1
        foreign key (event_id) references radar_events (id)
);

create index idx_people_event
    on radar_position_people (event_id);

create index idx_people_event_person
    on radar_position_people (event_id, person_index);

create table radar_sleep_reports
(
    id          bigint auto_increment
        primary key,
    user_id     int                                 not null,
    device_id   int                                 not null,
    report_date date                                not null,
    score       tinyint unsigned                    null,
    raw_payload json                                not null,
    created_at  timestamp default CURRENT_TIMESTAMP null,
    updated_at  timestamp default CURRENT_TIMESTAMP null on update CURRENT_TIMESTAMP,
    constraint idx_user_date
        unique (user_id, report_date),
    constraint fk_report_device
        foreign key (device_id) references devices (id)
);

create index idx_report_lookup
    on radar_sleep_reports (device_id, report_date);

create table radar_sleep_stats
(
    id                        bigint auto_increment
        primary key,
    event_id                  bigint      not null,
    real_time_breathing       tinyint     null,
    real_time_heart_rate      tinyint     null,
    avg_breathing_per_minute  tinyint     null,
    avg_heart_rate_per_minute tinyint     null,
    breathing_status          varchar(20) null,
    heart_rate_status         varchar(20) null,
    vital_signs_status        varchar(20) null,
    sleep_state_status        varchar(20) null,
    constraint radar_sleep_stats_ibfk_1
        foreign key (event_id) references radar_events (id)
);

create index event_id
    on radar_sleep_stats (event_id);

create table radar_vitals
(
    id             bigint auto_increment
        primary key,
    event_id       bigint      not null,
    breathing_rate smallint    null,
    heart_rate     smallint    null,
    sleep_state    varchar(20) null,
    constraint radar_vitals_ibfk_1
        foreign key (event_id) references radar_events (id)
);

create index idx_vitals_event
    on radar_vitals (event_id);

create table user_devices
(
    id          int auto_increment
        primary key,
    user_id     int                                  not null,
    device_id   int                                  not null,
    is_active   tinyint(1) default 1                 null,
    assigned_at timestamp  default CURRENT_TIMESTAMP null,
    constraint idx_user_device
        unique (user_id, device_id),
    constraint fk_user_device_link
        foreign key (device_id) references devices (id)
);


