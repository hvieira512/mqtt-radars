import { sendRequest } from "../../auth.js";
import { initTooltips, removeLoading, renderLoading } from "../../utils.js";
import "./modal.js";

const MODEL_MAP = {
    1: "HC1",
    2: "HC2",
    3: "HC2N",
    4: "HC2F",
    5: "HC2S",
    6: "HC2-4G",
    7: "HC2F-4G",
    8: "HC2S-4G",
    9: "TK2",
    10: "TK2N",
    11: "TK2F",
    12: "TK2S",
    13: "HC2N-4G",
};

const radarsWrapper = document.getElementById("radars-wrappers");

const CACHE_KEY = "hobacare_devices";
const CACHE_TIME_KEY = "hobacare_devices_timestamp";
const MAX_AGE = 30000;

const getCachedDevices = () => {
    const cached = localStorage.getItem(CACHE_KEY);
    const timestamp = localStorage.getItem(CACHE_TIME_KEY);

    if (!cached || !timestamp) return null;

    try {
        const parsed = JSON.parse(cached);
        return parsed;
    } catch (e) {
        console.warn("Invalid cached devices, clearing cache");
        localStorage.removeItem(CACHE_KEY);
        localStorage.removeItem(CACHE_TIME_KEY);
        return null;
    }
};

const cacheDevices = (devices) => {
    localStorage.setItem(CACHE_KEY, JSON.stringify(devices));
    localStorage.setItem(CACHE_TIME_KEY, Date.now());
};

const fetchDevices = async () => {
    const cached = getCachedDevices();
    if (cached) return cached;

    const res = await sendRequest("getDeviceInfo");
    const { data } = res;
    if (!data) {
        console.error("Invalid response from proxy");
        return [];
    }

    cacheDevices(data);
    return data;
};

const renderDevicesList = async () => {
    try {
        renderLoading(radarsWrapper);
        const devices = await fetchDevices();
        radarsWrapper.innerHTML = "";


        devices.sort((a, b) => {
            const aOnline = a.isOnline === "0";
            const bOnline = b.isOnline === "0";

            if (aOnline !== bOnline) return bOnline - aOnline;

            const nameA = (a.eqt_name || "").toLowerCase();
            const nameB = (b.eqt_name || "").toLowerCase();

            return nameA.localeCompare(nameB);
        });

        devices.forEach((device) => {
            const modelName = MODEL_MAP[device.modelNumber] || "Unknown model";
            const isOnline = device.isOnline === "0";

            const wifiIcon = isOnline
                ? `<i class="fa-solid fa-wifi text-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Online"></i>`
                : `<i class="fa-solid fa-wifi text-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Offline"></i>`;

            const card = document.createElement("div");
            card.className = "col-md-4 col-lg-3";

            card.innerHTML = `
            <div role="button" class="card device-card shadow-sm h-100" 
                data-bs-toggle="modal" data-bs-target="#radarModal" data-id="${device.uid}">

                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="device-model fw-bold">Modelo ${modelName}</span>
                        ${wifiIcon}
                    </div>
                    <h5 class="device-name mb-1">
                        ${device.eqt_name || "Unnamed Device"}
                    </h5>
                    <small class="text-muted">
                        UID: ${device.uid}
                    </small>
                </div>
            </div>
            `;

            radarsWrapper.appendChild(card);
        });

        initTooltips();
    } catch (error) {
        console.error("Error rendering devices:", error);
    } finally {
        removeLoading(radarsWrapper);
    }
};

await renderDevicesList();

const ws = new WebSocket('ws://localhost:8080');

ws.onopen = () => console.log('Connected to PHP WebSocket server');
ws.onmessage = (msg) => console.log('Message:', msg.data);
ws.onerror = (err) => console.error('WebSocket error', err);
