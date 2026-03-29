# MQTT Radar System - Multi-Tenant Architecture

Real-time radar monitoring system with MQTT message processing and database polling.

## Architecture Overview

```
┌─────────────────┐     ┌──────────────────────────────────────────────────────────────────────────┐
│  MQTT Broker    │────▶│                         Server (MQTT Worker)                          │
│  (external)     │     │                                                                          │
│                 │     │   ┌──────────────────────────────────────────────────────────────────┐ │
└─────────────────┘     │   │ mqtt-worker.php                                               │ │
                         │   │                                                                │ │
                         │   │  - Subscribe to MQTT topics: radar/{idLicenca}/+/+            │ │
                         │   │  - Extract idLicenca from topic                              │ │
                         │   │  - Forward raw payload to each tenant's /ingest.php           │ │
                         │   │  - Publish to Redis for queue backup                          │ │
                         │   └──────────────────────────────────────────────────────────────────┘ │
                         └──────────────────────────────────────────────────────────────────────────┘
                                                   │ HTTP POST (raw payload)
                                                   ▼
                              ┌────────────────────────────────────────────────────────────────────┐
                              │                         Tenant App (gucc.dev, gerpii, etc.)       │
                              │                                                                      │
                              │   ┌──────────────────────────────────────────────────────────┐ │
                              │   │ _ajax/radar-data-ingest.php                                │ │
                              │   │                                                            │ │
                              │   │  1. Parse binary data (position, vitals, stats)           │ │
                              │   │  2. Store in tenant's database                            │ │
                              │   │  3. Evaluate alarms via AlarmEngine                        │ │
                              │   │  4. Store any alarms triggered                            │ │
                              │   └──────────────────────────────────────────────────────────┘ │
                              │                                                                      │
                              │   ┌──────────────────────────────────────────────────────────┐ │
                              │   │ _ajax/radar-poll.php                                     │ │
                              │   │                                                            │ │
                              │   │  - Query new events since last poll                     │ │
                              │   │  - Return events with full payload                       │ │
                              │   └──────────────────────────────────────────────────────────┘ │
                              │                                                                      │
                              │   ┌──────────────────────────────────────────────────────────┐ │
                              │   │ monitorizacao.php                                        │ │
                              │   │                                                            │ │
                              │   │  - JavaScript polls radar-poll.php every 1 second        │ │
                              │   │  - Updates UI on new radar data                          │ │
                              │   └──────────────────────────────────────────────────────────┘ │
                              └────────────────────────────────────────────────────────────────────┘
```

## Components

### Server-Side (mqtt-radars repository)

| File | Purpose |
|------|---------|
| `mqtt-worker.php` | Subscribes to MQTT broker, forwards raw payloads to tenant apps |
| `simulate-radars.php` | Test tool that simulates radar MQTT messages |

### Tenant-Side (gucc.dev, gerpii, etc.)

| File | Purpose |
|------|---------|
| `_ajax/radar-data-ingest.php` | Receives MQTT payload, parses binary data, stores in DB |
| `_ajax/radar-poll.php` | Returns new events since last poll (for browser polling) |
| `monitorizacao.php` | Dashboard page with JavaScript polling |

## Data Flow

### Step 1: MQTT Message Arrives

```
Radar Device → MQTT Broker
Topic: radar/{idLicenca}/{deviceCode}/{dataType}
Payload: {"payload": {"deviceCode": "...", "position": "base64..."}}
```

### Step 2: Server - MQTT Worker

```php
mqtt-worker.php receives message:

1. Extract idLicenca from topic → e.g., "1001"
2. Determine tenant URL (from CRM or config)
3. HTTP POST raw payload to tenant:
   POST https://gucc.dev/_ajax/radar-data-ingest.php
   Body: {"payload": {"deviceCode": "RADAR001", "position": "AQID..."}}
```

### Step 3: Tenant - Data Ingestion

```php
radar-data-ingest.php receives request:

1. Parse topic → deviceCode = "RADAR001"
2. Parse binary data using PositionParser/HeartBreathParser
3. Get or create device in tenant's database
4. Store parsed data (position, vitals, etc.)
5. Evaluate alarms via AlarmEngine
6. Store any alarms triggered
```

### Step 4: Browser - Database Polling

```javascript
// monitorizacao.php
let lastEventId = 0;

function pollRadarData() {
    fetch('_ajax/radar-poll.php?after_id=' + lastEventId)
        .then(r => r.json())
        .then(data => {
            if (data.items && data.items.length > 0) {
                data.items.forEach(item => {
                    console.log('[Radar]', item);
                    // Update UI with new data
                    updateRadarDisplay(item);
                });
                lastEventId = data.next_after_id;
            }
        });
}

setInterval(pollRadarData, 1000);
```

## Topic Structure

```
radar/{idLicenca}/{deviceCode}/{dataType}

idLicenca  - License identifier (maps to tenant app)
deviceCode - Unique radar device UID
dataType   - Type of data: position, vitals, posstatics, hbstatics
```

Example topics:
- `radar/1001/RADAR001/position` - Position data
- `radar/1001/RADAR001/vitals` - Heart rate, breathing
- `radar/1002/RADAR003/position` - Position data for different tenant

## Multi-Tenant Isolation

Each tenant app:
- Has its own database
- Receives MQTT messages only for its own `idLicenca`
- Browser clients poll the same tenant's radar-poll.php

## Parsers

Binary data is parsed by tenant apps using parser classes:

| Parser | Input | Output |
|--------|-------|--------|
| `PositionParser` | 16-byte base64 | Position data with people coordinates, posture |
| `HeartBreathParser` | 16-byte base64 | Heart rate, breathing rate, sleep state |

## Requirements

### Server-Side

- PHP 8.1+
- Composer
- Redis (for message queuing)
- Access to MQTT broker

### Tenant-Side

- PHP capable of serving HTTP
- Own MySQL database
- Receives HTTP POST requests

## Environment Variables

### Server (.env)

```env
# MQTT Broker
MQTT_SERVER=127.0.0.1
MQTT_PORT=1883
MQTT_USERNAME=
MQTT_PASSWORD=
MQTT_TOPIC=radar/+

# Redis
REDIS_URL=tcp://127.0.0.1:6379

# CRM URL (optional - for tenant URL lookup)
CRM_URL=https://crm.hitcare.net/api/get.url.php
```

## Running the Server

### Development (Local Machine)

```bash
# Terminal 1: MQTT Worker
php mqtt-worker.php

# Terminal 2: (Optional) Radar Simulator for testing
php simulate-radars.php
```

### Production

```bash
# Start MQTT Worker
php mqtt-worker.php &

# Or use systemd/supervisor for process management
```

## Redis Keys

| Key Pattern | Type | Purpose |
|-------------|------|---------|
| `crm:target:{idLicenca}` | String | Cached CRM URL lookup (TTL: 300s) |
| `mqtt:ingest:{idLicenca}` | List | Backup message queue (LPUSH/RPOP) |
| `mqtt:failed:{idLicenca}` | List | Failed messages for retry |

## API Endpoints

### radar-data-ingest.php

Receives raw MQTT data, stores in database.

**Request:**
```json
POST /_ajax/radar-data-ingest.php
Content-Type: application/json

{
  "payload": {
    "deviceCode": "RADAR001",
    "position": "AQIDBAUGBwgJCgsMDQ4O",
    "heartbreath": "AQIOAQMFAA=="
  }
}
```

**Response:**
```json
{
  "status": "ok",
  "device": "RADAR001",
  "device_id": 5,
  "received_at": "2026-03-28 20:00:00",
  "events_count": 2
}
```

### radar-poll.php

Returns new events since last poll.

**Request:**
```
GET /_ajax/radar-poll.php?after_id=0
```

**Response:**
```json
{
  "items": [
    {
      "event_id": 123,
      "device_code": "RADAR001",
      "device_id": 5,
      "type": "position",
      "created_at": "2026-03-28 20:00:00",
      "payload": {
        "people": 2
      }
    }
  ],
  "next_after_id": 123,
  "count": 1
}
```

## Project Structure

```
mqtt-radars/                 # Server-side repository
├── mqtt-worker.php          # MQTT consumer & HTTP forwarder
├── simulate-radars.php       # Test radar simulator
├── README.md
└── ...

gucc.dev/                    # Tenant app (separate repo)
├── _ajax/
│   ├── radar-data-ingest.php  # Receives & stores radar data
│   └── radar-poll.php        # Polling endpoint for browser
├── monitorizacao.php         # Dashboard with polling
└── ...
```

## Troubleshooting

### Tenant app not receiving MQTT data

1. Verify mqtt-worker.php is running and connected to MQTT
2. Check server logs for forwarding errors
3. Verify tenant URL is correct (check CRM or config)
4. Ensure firewall allows HTTP from server to tenant

### Browser not receiving new data

1. Open browser console (F12)
2. Verify polling script is running (should see "[Radar Poll] Started")
3. Check for console errors
4. Verify radar-poll.php returns data:
   ```bash
   curl https://gucc.dev/_ajax/radar-poll.php?after_id=0
   ```

### Data not being stored in database

1. Verify tenant app's database connection
2. Check radar-data-ingest.php logs
3. Verify database schema is up to date
4. Check for SQL errors in response

## License

MIT License
