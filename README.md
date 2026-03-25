# MQTT Radar System

Real-time radar monitoring system with MQTT message processing, WebSocket broadcasting, and sleep report management.

## Architecture Overview

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   MQTT Broker   │────▶│   mqtt-worker   │────▶│  ws-server     │
│                 │     │   (PHP)         │     │  (WebSocket)   │
└─────────────────┘     │                  │     │                │
                        │  - Parse data    │     │  - Broadcast   │
                        │  - Save to DB   │     │    to clients  │
                        │  - Evaluate     │     │                │
                        │    alarms       │     └────────┬────────┘
                        └────────┬─────────┘              │
                                 │                        │
                                 ▼                        ▼
                        ┌─────────────────┐     ┌─────────────────┐
                        │     MySQL       │     │    Browser      │
                        │   (Database)    │     │  (Dashboard)    │
                        └─────────────────┘     └─────────────────┘
```

## Features

- **Real-time Radar Monitoring**: Live position tracking and vital signs via MQTT
- **WebSocket Broadcasting**: Instant data push to connected browsers
- **Sleep Reports**: Daily sleep analysis with detailed charts and KPIs
- **Alarm System**: Configurable alarms for falls, heart rate anomalies, and more
- **Position Tracking**: Real-time people detection and tracking on room map

## Requirements

- PHP 8.1+
- MySQL 8.0+
- Composer
- MQTT Broker (e.g., Mosquitto)
- Node.js (optional, for frontend asset management)

## Installation

### 1. Clone and Install Dependencies

```bash
git clone <repository-url>
cd mqtt-test
composer install
```

### 2. Environment Configuration

Copy the example environment file and configure your settings:

```bash
cp .env.example .env
```

Edit `.env` with your configuration (see [.env Configuration](#env-configuration) below).

### 3. Database Setup

#### Option A: Using Docker

```bash
docker-compose up -d
```

This starts a MySQL 8.3 container on port 3306.

#### Option B: Manual Setup

Create a MySQL database and run the schema:

```bash
mysql -u root -p your_database < schema_pt.sql
```

### 4. Seed Event Types

The system requires event types to be seeded in the database:

```sql
INSERT INTO radar_tipos_evento (id, nome) VALUES 
(1, 'position'),
(2, 'minute_stats'),
(3, 'vitals'),
(4, 'hbstatics'),
(5, 'alarm'),
(10, 'fall_detection'),
(11, 'heart_rate_high'),
(12, 'heart_rate_low'),
(13, 'apnea'),
(14, 'no_activity'),
(15, 'empty_room'),
(16, 'presence_detected');
```

## Configuration

### .env Configuration

Create a `.env` file based on `.env.example`:

```env
# MQTT Broker Connection
MQTT_SERVER=your_mqtt_server_ip
MQTT_PORT=1883
MQTT_USERNAME=your_mqtt_username
MQTT_PASSWORD=your_mqtt_password
MQTT_TOPIC=radar/frontend

# Database Connection
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=radar
DB_USERNAME=radar_user
DB_PASSWORD=your_db_password

# HobaCare API (for sleep reports)
APP_ID=your_app_id
SECRET=your_app_secret
HOBACARE_USERNAME=your_hobacare_username
HOBACARE_PASSWORD=your_hobacare_password
BASE_URL=https://api.hobacare.com
```

## Running the Application

The system requires three separate processes running simultaneously:

### 1. PHP Development Server

Serves the web interface and proxy API:

```bash
php -S localhost:8000
```

Access the dashboard at `http://localhost:8000`

### 2. MQTT Worker

Processes incoming MQTT messages, parses data, saves to database, and broadcasts via HTTP to the WebSocket server:

```bash
php mqtt-worker.php
```

### 3. WebSocket Server

Handles real-time WebSocket connections and broadcasts data to browsers:

```bash
php ws-server.php
```

### Using tmux (Recommended)

Create a tmux session with all three processes:

```bash
tmux new-session -d -s radar
tmux send-keys -t radar:0 'cd /path/to/mqtt-test' Enter
tmux split-window -t radar -v
tmux send-keys -t radar:1 'php -S localhost:8000' Enter
tmux split-window -t radar -h
tmux send-keys -t radar:2 'php mqtt-worker.php' Enter
tmux select-pane -t radar:0
tmux send-keys 'php ws-server.php' Enter
tmux attach -t radar
```

## Project Structure

```
├── src/
│   ├── Database.php              # PDO connection singleton
│   ├── EventTypes.php           # Event type constants
│   ├── AlarmEngine.php         # Alarm evaluation dispatcher
│   ├── Logger.php               # Simple logging utility
│   ├── Alarms/
│   │   ├── AlarmInterface.php   # Alarm contract
│   │   ├── HeartBreathAlarms.php # Heart/breathing alarm rules
│   │   └── PositionAlarms.php   # Position/fall alarm rules
│   ├── Parsers/
│   │   ├── ParserInterface.php  # Parser contract
│   │   ├── PositionParser.php   # Radar position data parser
│   │   ├── HeartBreathParser.php # Vitals data parser
│   │   ├── PosStaticsParser.php # Minute stats parser
│   │   └── HbStaticsParser.php  # Heart/breath statistics parser
│   ├── Repositories/
│   │   ├── DeviceRepository.php      # Device operations
│   │   ├── EventRepository.php       # Radar events
│   │   ├── DetectionRepository.php   # Alarm/event detections
│   │   ├── StatsRepository.php      # Minute/sleep statistics
│   │   ├── PositionRepository.php    # People position tracking
│   │   ├── VitalsRepository.php     # Vital signs data
│   │   ├── SleepReportRepository.php # Sleep reports
│   │   └── UserDeviceRepository.php  # User-device associations
│   └── Services/
│       └── SleepReportService.php    # Sleep report management
├── assets/
│   ├── js/
│   │   ├── pages/home/
│   │   │   ├── main.js           # Device list page
│   │   │   ├── radar/            # Radar modal components
│   │   │   │   ├── modal.js      # Radar modal logic
│   │   │   │   ├── websocket.js  # WebSocket client
│   │   │   │   ├── map.js        # Radar map rendering
│   │   │   │   ├── grid.js       # Events/alarms grid
│   │   │   │   ├── alarm.js      # Alarm toasts
│   │   │   │   └── info.js       # Vital signs display
│   │   │   └── sleep-report/     # Sleep report components
│   │   └── utils.js, toastr.js, auth.js
│   └── css/
├── modals/
│   ├── radar.php                # Radar detail modal
│   └── sleep-report.php         # Sleep report modal
├── helpers.php                  # Utility functions
├── bootstrap.php                # Autoloader and environment
├── proxy.php                    # API proxy for external requests
├── mqtt-worker.php             # MQTT message processor
├── ws-server.php               # WebSocket server
├── schema.sql                  # Database schema (English)
├── schema_pt.sql               # Database schema (Portuguese)
├── docker-compose.yml          # MySQL container
├── composer.json               # PHP dependencies
└── .env.example               # Environment template
```

## Database Schema

The system uses a Portuguese naming convention (configured in `schema_pt.sql`):

| Table | Description |
|-------|-------------|
| `dispositivos` | Device registry |
| `radar_tipos_evento` | Event type definitions |
| `radar_eventos` | Radar event records |
| `radar_detecoes` | Alarm/event detections |
| `radar_estatisticas_minuto` | Per-minute statistics |
| `radar_posicao_pessoas` | People position data |
| `radar_estatisticas_sono` | Sleep statistics |
| `radar_sinais_vitais` | Vital signs data |
| `radar_relatorios_sono` | Sleep reports |
| `utilizador_dispositivos` | User-device associations |

## Alarm Levels

| Level | Portuguese | Description |
|-------|------------|-------------|
| `info` | info | Informational |
| `warning` | aviso | Warning condition |
| `danger` | perigo | Critical condition |

## API Endpoints

### Proxy Endpoints (via proxy.php)

| Endpoint | Description |
|----------|-------------|
| `thirdparty/v2/getDeviceInfo` | List all devices |
| `thirdparty/v2/deviceProp` | Get device layout/properties |
| `radar/monitor/report` | Get sleep report for date |
| `radar/monitor/reportDays` | Get available report dates |

### Internal Services

The system automatically caches sleep reports in the database and serves them when available, even if the API is rate-limited.

## MQTT Message Format

Expected MQTT payload structure:

```json
{
  "payload": {
    "deviceCode": "DEVICE_UID",
    "position": "base64_encoded_position_data",
    "heartbreath": "base64_encoded_vitals_data",
    "posstatics": "base64_encoded_stats_data",
    "hbstatics": "base64_encoded_heart_breath_stats"
  }
}
```

## Troubleshooting

### WebSocket not receiving data

1. Check ws-server.php is running on port 8080
2. Verify HTTP broadcast endpoint is accessible: `curl http://127.0.0.1:8081/broadcast`
3. Check browser console for WebSocket connection errors

### MQTT worker not processing messages

1. Verify MQTT broker is accessible
2. Check credentials in `.env`
3. Confirm topic subscription matches broker configuration

### Database connection errors

1. Verify MySQL is running
2. Check credentials in `.env`
3. Ensure database and tables exist (run schema_pt.sql)

### Sleep reports not loading

1. Verify API credentials (`APP_ID`, `SECRET`, etc.)
2. Check credentials.json token is valid
3. Ensure report exists for the requested date

## Development

### Running the Cron Job

Sync sleep reports for all devices:

```bash
php cron_sync_sleep_reports.php
```

### Adding New Alarm Rules

1. Create or modify alarm class in `src/Alarms/`
2. Implement `AlarmInterface`
3. Register in `AlarmEngine::$alarms`

### Adding New Parsers

1. Create parser class in `src/Parsers/`
2. Implement `ParserInterface`
3. Register parser key in `mqtt-worker.php` `$parsers` array
4. Add corresponding event type in `EventTypes.php`

## License

MIT License
