const ws = new WebSocket('ws://localhost:8080');

ws.onopen = () => console.log('✅ Connected to WebSocket server');

ws.onmessage = (e) => {
    let data;
    try {
        data = JSON.parse(e.data);
    } catch (err) {
        console.error('❌ Failed to parse message', e.data);
        return;
    }

    const ts = new Date().toLocaleTimeString();
    console.group(`[${ts}] Live Data`);

    // Position updates
    if (data.position) {
        data.position.forEach((p, i) => {
            console.log(
                `Person ${i}: x=${p.x_position_dm} dm, y=${p.y_position_dm} dm, z=${p.z_position_cm} cm, Posture=${p.posture_state}`
            );
        });
    }

    // Vitals updates
    if (data.vitals) {
        console.log(
            `Vitals: Heart=${data.vitals.heart_rate}, Breathing=${data.vitals.breathing}, Sleep=${data.vitals.sleep_state}`
        );
    }

    // Any test or other messages
    Object.keys(data).forEach((key) => {
        if (!['position', 'vitals'].includes(key)) {
            console.log(`${key}:`, data[key]);
        }
    });

    console.groupEnd();
};

// Handle errors
ws.onerror = (err) => console.error('❌ WebSocket error', err);

// Handle connection closed
ws.onclose = () => console.warn('⚠️ WebSocket connection closed');
