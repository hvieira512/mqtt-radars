# MQTT Radar Router

Real-time radar MQTT message router with async Redis queue and HTTP forwarding.

## Architecture

```
MQTT Broker ──► mqtt-worker.php ──► Redis ──► forward-consumer.php ──► Tenant Apps
                        │            │
                        │            └── Pub/Sub: radar:ingest:{idLicenca}
                        │
                        └── mqtt:forward:{idLicenca} (list)
```

1. **mqtt-worker.php** subscribes to `radar/+/+`, pushes every message to a Redis list and publishes to a Redis channel
2. **forward-consumer.php** pops from the Redis list, looks up the tenant URL via CRM, and HTTP POSTs the payload to the tenant's ingest endpoint
3. Multiple forward-consumer instances can run in parallel per license for throughput

## Files

| File | Purpose |
|------|---------|
| `mqtt-worker.php` | MQTT subscriber → Redis queue + pub/sub |
| `forward-consumer.php` | Redis consumer → HTTP forward to tenants |
| `bootstrap.php` | Environment loader |
| `src/Logger.php` | Logging utility |
| `.env.example` | Environment config template |

## Environment Variables

```
# MQTT Broker
MQTT_SERVER=127.0.0.1
MQTT_PORT=1883
MQTT_USERNAME=
MQTT_PASSWORD=
MQTT_TOPIC=radar/+/+
MQTT_CLIENT_ID=php-radar-router

# Redis
REDIS_URL=tcp://127.0.0.1:6379

# CRM
CRM_URL=https://crm.hitcare.net/api/get.url.php
CRM_CACHE_TTL=3600

# Forward Consumer
FORWARD_SLEEP_MS=50
FORWARD_CONNECT_TIMEOUT_MS=750
FORWARD_TIMEOUT_MS=5000
FORWARD_MAX_ATTEMPTS=3
FORWARD_EXCLUDE_LICENSES=
```

## Running

```bash
# MQTT Worker (subscribe + enqueue)
php mqtt-worker.php

# Forward Consumer (single license)
php forward-consumer.php --license=2103

# Forward Consumer (all licenses)
php forward-consumer.php

# Forward Consumer (exclude some licenses)
php forward-consumer.php --exclude=1001,2004

# Multiple consumers per license for parallelism
php forward-consumer.php --license=2103 &
php forward-consumer.php --license=2103 &
```

## Redis Keys

| Key | Type | Purpose |
|-----|------|---------|
| `mqtt:forward:{license}` | List | Pending forward queue (LPOP) |
| `mqtt:forward:licenses` | Set | Active license IDs |
| `mqtt:forward_failed:{license}` | List | Failed messages after max retries |
| `crm:target:{license}` | String | Cached tenant URL (TTL) |
| `radar:ingest:{license}` | Pub/Sub | Real-time message channel |

## Systemd

```ini
# /etc/systemd/system/mqtt-worker.service
[Unit]
Description=MQTT Radar Worker
After=redis.service

[Service]
WorkingDirectory=/opt/mqtt-radars
ExecStart=/usr/bin/php mqtt-worker.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/forward-consumer@.service
[Unit]
Description=MQTT Forward Consumer (%i)
After=redis.service

[Service]
WorkingDirectory=/opt/mqtt-radars
ExecStart=/usr/bin/php forward-consumer.php --license=%i
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

## Requirements

- PHP 8.1+
- Redis
- Composer
- MQTT broker access
