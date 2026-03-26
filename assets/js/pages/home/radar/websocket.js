import { updatePeople } from "./map.js";
import { renderVitals } from "./info.js";
import { renderAlarm } from "./alarm.js";
import { addAlarm, addEvent } from "./grid.js";
import toast from "../../../toastr.js";

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
            if (data && data.error) {
                toast.error("Erro de ligação ao MQTT");
                return;
            }

            if (!currUID || (data.device_code && data.device_code !== currUID))
                return;
            if (data.type === "position") updatePeople(data.people);
            if (data.type === "vitals") renderVitals(currUID, data);

            if (data.category === "alarm" || data.category === "event")
                renderAlarm(data);
            if (data.category === "alarm") addAlarm(data);
            if (data.category === "event") addEvent(data);

        });
    };
    ws.onerror = (e) => {
        toast.error("Erro de ligação ao WebSocket");
        console.error("WS error", e);
    }
}
