# MQTT Event Mapping

| MQTT `event` | Internal Name             | Category        | Params                             | Notes                                                |
| ------------ | ------------------------- | --------------- | ---------------------------------- | ---------------------------------------------------- |
| `1`          | `PEOPLE_COUNT_CHANGE`     | Event           | `count`                            | Triggered when the number of detected people changes |
| `2`          | `FALL_DETECTION`          | **Alarm**       | none                               | Device detected a fall                               |
| `3`          | `VITAL_SIGN_ANOMALY`      | Alarm / Warning | `breath`, `heartbeat`, `alarmType` | Alarm type determined by `alarmType`                 |
| `4`          | `ROOM_ENTRY_EXIT`         | Event           | `entry2Exit`                       | `0 = entry`, `1 = exit`                              |
| `5`          | `DEVICE_ONLINE_STATUS`    | Event           | `isOnline`                         | `0 = online`, `1 = offline`                          |
| `6`          | `BED_ENTRY_EXIT`          | Event           | `entry2Exit`                       | `0 = enter bed`, `1 = leave bed`                     |
| `7`          | `POOR_SIGNAL`             | Event           | `recovery`                         | `0 = trigger`, `1 = recovered`                       |
| `8`          | `TILT_ANOMALY`            | Event           | `recovery`                         | `0 = trigger`, `1 = recovered`                       |
| `9`          | `GENERAL_ALERT`           | **Alarm**       | `alarmType`                        | Sub-type defines the alert                           |
| `10`         | `WARNING_AREA_ENTRY_EXIT` | Event           | `entry2Exit`                       | `0 = enter`, `1 = exit`                              |

---

# AlarmType Mapping

## Vital Sign Anomalies (`event = 3`)

| `alarmType` | Internal Name      | Description         | Level   |
| ----------- | ------------------ | ------------------- | ------- |
| `11`        | `HYPERPNEA`        | Breathing too fast  | Event   |
| `12`        | `HYPOPNEA`         | Breathing too slow  | Event   |
| `13`        | `APNEA`            | Breathing stopped   | Event   |
| `14`        | `HEART_RATE_HIGH`  | Heart rate too high | Event   |
| `15`        | `HEART_RATE_LOW`   | Heart rate too low  | Event   |
| `16`        | `VITAL_SIGNS_WEAK` | Weak vital signs    | Warning |

---

## General Alerts (`event = 9`)

| `alarmType` | Internal Name           | Description                 | Level    |
| ----------- | ----------------------- | --------------------------- | -------- |
| `1`         | `LEFT_BED`              | Left bed and did not return | Alarm    |
| `2`         | `LOITERING`             | Stayed in area too long     | Alarm    |
| `3`         | `NO_ACTIVITY_LONG_TIME` | No movement detected        | Alarm    |
| `4`         | `SITTING_DOWN`          | Sitting on ground           | Alarm    |
| `5`         | `SITTING_UP`            | Sitting alert               | Alarm    |
| `6`         | `EMPTY_RESERVED`        | Not available               | Disabled |
| `7`         | `EMPTY_RESERVED`        | Not available               | Disabled |
| `8`         | `SOS_BUTTON`            | Accessory button alarm      | Disabled |
| `9`         | `SOS_PULL_ROPE`         | Accessory pull rope alarm   | Disabled |
| `10`        | `SOS_VOICE`             | Voice help alarm            | Disabled |

⚠️ `alarmType 6–10` are **not enabled by the platform according to the documentation**.

---

# Example MQTT Payload

```json
{
    "cmd": "DEVICE_EVENT",
    "uid": "F59D3E873F5B",
    "event": "3",
    "eventName": "The device detected a high heart rate",
    "params": {
        "breath": 40,
        "heartbeat": 110,
        "alarmType": 14
    }
}
```

---

💡 **Implementation Tip**

Most integrations implement parsing like this:

```text
switch(event)
  case 3 -> check params.alarmType
  case 9 -> check params.alarmType
  default -> direct mapping
```

# To Do

- Sleep Report
    - Atualizar meta data
    - Gráfico Frequência cardíaca
        - retornar anomalias para os kpis
    - Gráfico Atividade Diurna
    - Sugestões
    - No data found element

# In Progress

- Sleep Report
    - Nome e Modelo do radar
    - Input date

# Done

- Criar estrutura tabela alarmes
- Inserir alarmes na base de dados
- Quando o modal de um radar abre, carregar o histórico de eventos e alarmes na tabela
- Inicializar os gráficos dos sinais vitais, independentemente se vêm dados ou não
- Resize do mapa quando é mexido na largura da janela do browser
- Movimentos suaves das pessoas
