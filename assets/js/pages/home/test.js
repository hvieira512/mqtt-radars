const ws = new WebSocket('ws://localhost:8080');

ws.onopen = () => console.log('%cConnected to PHP WebSocket server', 'color: green; font-weight: bold;');

ws.onmessage = (msg) => {
    try {
        const data = JSON.parse(msg.data);
        const timestamp = new Date().toLocaleTimeString();
        console.group(`%c[${timestamp}] Live MQTT Data`, 'color: blue; font-weight: bold;');

        // Handle positions
        if (data.position) {
            data.position.forEach((p, i) => {
                console.log(`%cPerson ${i}: x=${p.x_position_dm} dm, y=${p.y_position_dm} dm, z=${p.z_position_cm} cm, Posture=${p.posture_state}, Event=${p.last_event}, Region=${p.region_id}, Rotation=${p.rotation_deg ?? 'N/A'}, Direction=(${p.direction?.dx ?? 0}, ${p.direction?.dy ?? 0})`, 'color: purple;');
            });
        }

        // Handle vitals
        if (data.vitals) {
            console.log(`%cVitals: Heart Rate=${data.vitals.heart_rate}, Breathing=${data.vitals.breathing}, Sleep State=${data.vitals.sleep_state}`, 'color: orange; font-weight: bold;');
        }

        // Handle minute_stats
        if (data.minute_stats) {
            console.log('%cMinute Stats:', 'color: teal; font-weight: bold;', data.minute_stats);
        }

        // Handle hbstatics
        if (data.hbstatics) {
            console.log('%cHB Statics:', 'color: brown; font-weight: bold;', data.hbstatics);
        }

        console.groupEnd();
    } catch (err) {
        console.error('Failed to parse WebSocket message', err, msg.data);
    }
};

ws.onclose = () => console.warn('%cWebSocket connection closed', 'color: red; font-weight: bold;');
ws.onerror = (err) => console.error('%cWebSocket error', 'color: red; font-weight: bold;', err);
