# MQTT Radar System - Multi-Tenant Architecture

Real-time radar monitoring system with MQTT message processing and database polling.

## Architecture Overview

```
┌─────────────────┐      ┌──────────────────────────────────────────────────────────────────────────┐
│  MQTT Broker    │      │                         Server (MQTT Worker)                             │
│  (external)     │      │                                                                          │
│                 │      │   ┌──────────────────────────────────────────────────────────────────┐   │
└────────┬────────┘      │   │ mqtt-worker.php                                                  │   │
         │               │   │                                                                  │   │
         │  MQTT Stream  │   │ - Subscribe to MQTT topics: radar/{idLicenca}/+
         └──────────────▶│   │ - Extract idLicenca from topic                                   │   │
                         │   │ - Push to Redis forward queue (mqtt:forward:{license})           │   │
                         │   │ - Publish to Redis for real-time subscribers                     │   │
                         │   └──────────────────────────────────────────────────────────────────┘   │
                         └─────────────────────────────────────┬────────────────────────────────────┘
                                                               │
                                                               │ Redis Queue
                                                               ▼
                         ┌──────────────────────────────────────────────────────────────────────────┐
                         │                        Server (Forward Consumer)                         │
                         │                                                                          │
                         │   ┌──────────────────────────────────────────────────────────────────┐   │
                         │   │ forward-consumer.php --license={id}                              │   │
                         │   │                                                                  │   │
                         │   │ - Pop messages from mqtt:forward:{license} queue                 │   │
                         │   │ - Calculate queue_delay_ms (queue wait time)                     │   │
                         │   │ - HTTP POST to tenant's radar-data-ingest.php                    │   │
                         │   └──────────────────────────────────────────────────────────────────┘   │
                         └─────────────────────────────────────┬────────────────────────────────────┘
                                                               │
                                                               │ HTTP POST (JSON payload)
                                                               ▼
        ┌──────────────────────────────────────────────────────────────────────────────────────────┐
        │                          Tenant App (gucc.dev, gerpii, etc.)                             │
        │                                                                                          │
        │   ┌──────────────────────────────────────────────────────────────────────────────────┐   │
        │   │ modulos/radares/_ajax/radar-data-ingest.php                                      │   │
        │   │                                                                                  │   │
        │   │ 1. Parse binary data (position, vitals, stats)                                   │   │
        │   │ 2. Store in tenant's database                                                    │   │
        │   │ 3. Evaluate alarms via AlarmEngine                                               │   │
        │   │ 4. Store any alarms triggered                                                    │   │
        │   └──────────────────────────────────────────────────────────────────────────────────┘   │
        │                                                                                          │
        └──────────────────────────────────────────────────────────────────────────────────────────┘                           
```

## Components

### Server-Side (mqtt-radars repository)

| File | Purpose |
|------|---------|
| `mqtt-worker.php` | Subscribes to MQTT broker, pushes messages to Redis forward queue |
| `forward-consumer.php` | Consumes Redis forward queue, HTTP POSTs to tenant apps |
| `queue-consumer.php` | Alternative consumer that processes messages locally (parsing, DB storage) |
| `redis-subscriber.php` | WebSocket relay - subscribes to Redis pub/sub for real-time updates |
| `simulate-radars.php` | Test tool that simulates radar MQTT messages |

### Tenant-Side (gucc.dev, gerpii, etc.)

| File | Purpose |
|------|---------|
| `modulos/radares/_ajax/radar-data-ingest.php` | Receives MQTT payload, parses binary data, stores in DB |

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
2. Push to Redis forward queue with queued_at_ms timestamp:
   LPUSH mqtt:forward:{license} {data}
3. Publish to Redis pub/sub for real-time subscribers:
   PUBLISH radar:ingest:{license} {data}
```

### Step 3: Server - Forward Consumer

```php
forward-consumer.php --license={id} processes queue:

1. Pop message from Redis queue: RPOP mqtt:forward:{license}
2. Calculate queue_delay_ms (current_time - queued_at_ms)
3. Lookup tenant URL from CRM API (with Redis cache)
4. HTTP POST to tenant:
   POST https://{tenant}/modulos/radares/_ajax/radar-data-ingest.php
   Body: {"payload": {"deviceCode": "RADAR001", "position": "AQID..."}}
5. On failure, retry up to FORWARD_MAX_ATTEMPTS (3)
```

## Topic Structure

```
radar/{idLicenca}/{deviceCode}

idLicenca  - License identifier (maps to tenant app)
deviceCode - Unique radar device UID
```

Example topics:
- `radar/1001/RADAR001`
- `radar/1001/RADAR002`
- `radar/1002/RADAR003`
- `radar/1003/RADAR004`

## Multi-Tenant Isolation

Each tenant app:
- Has its own database
- Receives MQTT messages only for its own `idLicenca`

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

# CRM URL (for tenant URL lookup)
CRM_URL=https://crm.hitcare.net/api/get.url.php
CRM_CACHE_TTL=3600

# Forward Consumer Settings
FORWARD_SLEEP_MS=50              # Polling interval when queue is empty
FORWARD_CONNECT_TIMEOUT_MS=750   # HTTP connect timeout
FORWARD_TIMEOUT_MS=5000          # HTTP request timeout
FORWARD_MAX_ATTEMPTS=3           # Retry attempts for failed forwards
```

## Running the Server

### Development (Local Machine)

```bash
# Terminal 1: MQTT Worker
php mqtt-worker.php

# Terminal 2: Forward Consumer (for specific license)
php forward-consumer.php --license=1001

# Terminal 3: (Optional) Radar Simulator for testing
php simulate-radars.php
```

### Production

```bash
# Start MQTT Worker (single instance)
php mqtt-worker.php &

# Start forward consumers (scale based on message volume per license)
php forward-consumer.php --license=1001 &
php forward-consumer.php --license=2004 &
php forward-consumer.php --license=2051 &
php forward-consumer.php --license=2103 &

# Start generic consumer for remaining licenses
php forward-consumer.php --exclude=1001,2004,2051,2103 &

# Monitor queue sizes
redis-cli llen mqtt:forward:2103

# Scale up consumers if queues grow (e.g., for busy license 2103)
for i in {1..5}; do
  php forward-consumer.php --license=2103 &
done
```

Use systemd/supervisor for process management in production.

## Redis Keys

| Key Pattern | Type | Purpose |
|-------------|------|---------|
| `crm:target:{idLicenca}` | String | Cached CRM URL lookup (TTL: 3600s) |
| `mqtt:forward:{license}` | List | Forward queue - consumed by forward-consumer.php (LPUSH/RPOP) |
| `mqtt:forward_failed:{license}` | List | Failed forward messages (after max retries) |
| `mqtt:forward:licenses` | Set | Tracks licenses with active forward queues |
| `mqtt:ingest:{license}` | List | Alternative queue - consumed by queue-consumer.php |
| `radar:ingest:{license}` | Pub/Sub | Real-time relay for WebSocket subscribers |

## API Endpoints

### radar-data-ingest.php

Receives forwarded MQTT data from forward-consumer.php, stores in database.

**Request:**
```json
POST /modulos/radares/_ajax/radar-data-ingest.php
Content-Type: application/json

{
  "payload": {
    "deviceCode": "RADAR001",
    "position": "AQIDBAUGBwgJCgsMDQ4O",
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
├── mqtt-worker.php          # MQTT subscriber, pushes to Redis queues
├── forward-consumer.php     # Consumes forward queue, HTTP POSTs to tenants
├── queue-consumer.php       # Alternative consumer (local parsing & DB storage)
├── redis-subscriber.php     # WebSocket relay via Redis pub/sub
├── simulate-radars.php      # Test radar simulator
├── README.md
└── ...

gucc.dev/                    # Tenant app (separate repo)
├── modulos/radares/
│   └── _ajax/
│       ├── radar-data-ingest.php  # Receives & stores radar data
│       └── ...
├── _ajax/
│   └── radar-poll.php        # Polling endpoint for browser
├── monitorizacao.php         # Dashboard with polling
└── ...
```

## Troubleshooting

### Tenant app not receiving MQTT data

1. Verify mqtt-worker.php is running and connected to MQTT
2. Check forward-consumer.php is running for the license: `ps aux | grep forward-consumer.php`
3. Check server logs for forwarding errors and queue_delay_ms
4. Verify tenant URL is correct (check CRM or config)
5. Ensure firewall allows HTTP from server to tenant
6. Check Redis queue size: `redis-cli llen mqtt:forward:{license}`

### High queue_delay_ms

`queue_delay_ms` measures time (in ms) between mqtt-worker.php queuing the message and forward-consumer.php processing it.

1. Check queue size: `redis-cli llen mqtt:forward:{license}`
2. If queue is growing, scale up forward-consumer processes:
   ```bash
   for i in {1..5}; do
     php forward-consumer.php --license={id} &
   done
   ```
3. Check if tenant app is responding slowly (check `duration_ms` in logs)
4. Verify forward-consumer.php processes aren't stuck

### Browser not receiving new data

1. Open browser console (F12)
2. Verify polling script is running (should see "[Radar Poll] Started")
3. Check for console errors
4. Verify radar-poll.php returns data:
   ```bash
   curl https://{tenant}/_ajax/radar-poll.php?after_id=0
   ```

### Data not being stored in database

1. Verify tenant app's database connection
2. Check radar-data-ingest.php logs
3. Verify database schema is up to date
4. Check for SQL errors in response

## License

MIT License
