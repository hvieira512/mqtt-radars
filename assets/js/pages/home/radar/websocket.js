import { updatePeople } from "./map.js";
import { renderVitals } from "./info.js";

let ws = null;
let currUID = null;

export function setCurrentUID(uid) {
    currUID = uid;
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({ action: "subscribe", deviceCode: currUID }));
    }
}

export function initRadarWebsocket() {
    if (ws) return;

    ws = new WebSocket("ws://localhost:8080");

    ws.onopen = () => console.log("WS connected");

    ws.onmessage = (event) => {
        let msg;
        try {
            msg = JSON.parse(event.data);
        } catch (err) {
            console.error("Invalid WS message", err);
            return;
        }

        const messages = Array.isArray(msg) ? msg : [msg];

        messages.forEach((data) => {
            console.log(data);
            if (data.position && Array.isArray(data.position)) {
                updatePeople(data.position);
            }

            if (data.type === "vitals") {
                renderVitals(currUID, data);
            }
        });
    };
    ws.onerror = (e) => console.error("WS error", e);
}
