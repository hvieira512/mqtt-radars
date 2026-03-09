import { BASE_URL, sendRequest } from "../../auth.js";

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

    const age = Date.now() - Number(timestamp);

    if (age > MAX_AGE) return null;

    return JSON.parse(cached);
};

const cacheDevices = (devices) => {
    localStorage.setItem(CACHE_KEY, JSON.stringify(devices));
    localStorage.setItem(CACHE_TIME_KEY, Date.now());
};

const fetchDevices = async () => {
    const cached = getCachedDevices();
    if (cached) return cached;

    const { data } = await sendRequest(
        `${BASE_URL}/thirdparty/v2/getDeviceInfo`,
    );

    cacheDevices(data);

    return data;
};

const renderDevicesList = async () => {
    const devices = await fetchDevices();

    radarsWrapper.innerHTML = "";

    devices.forEach((device) => {
        const modelName = MODEL_MAP[device.modelNumber] || "Unknown model";
        const isOnline = device.isOnline === "0";

        const wifiIcon = isOnline
            ? `<i class="fa-solid fa-wifi text-success" data-bs-toggle="tooltip" data-bs-placement="top" title="Online"></i>`
            : `<i class="fa-solid fa-wifi text-danger" data-bs-toggle="tooltip" data-bs-placement="top" title="Offline"></i>`;

        const card = document.createElement("div");
        card.className = "col-md-4 col-lg-3";

        card.innerHTML = `
            <div class="card device-card shadow-sm h-100"
                 data-id="${device.uid}">
                 
                <div class="card-body d-flex flex-column">

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="device-model fw-bold">Model ${modelName}</span>
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
};

const init = async () => {
    let tooltips = document.querySelectorAll("[data-bs-toggle='tooltip']");
    tooltips.forEach((el) => {
        new bootstrap.Tooltip(el);
    });
    await renderDevicesList();
};

init();
