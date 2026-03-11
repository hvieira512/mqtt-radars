import { updatePeople } from "./map.js";

let ws = null;

export function initRadarWebsocket() {
    if (ws) return;

    ws = new WebSocket("ws://localhost:8080");

    ws.onopen = () => console.log("WS connected");

    ws.onmessage = (event) => {
        let msg;
        try {
            msg = JSON.parse(event.data);
        } catch (err) {
            return;
        }

        if (msg.position && Array.isArray(msg.position)) {
            updatePeople(msg.position);
        }
    };

    ws.onerror = (e) => console.error("WS error", e);
}
