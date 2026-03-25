import { getRequest } from "../../auth.js";
import { initTooltips, removeLoading, renderLoading } from "../../utils.js";
import toast from "../../toastr.js";
import "./radar/modal.js";
import { initSleepReportModal } from "./sleep-report/main.js";

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

const getModelName = (modelNumber) => MODEL_MAP[modelNumber] || "Unknown model";

const getWifiIcon = (isOnline) => {
    const status = isOnline ? "success" : "danger";
    const title = isOnline ? "Online" : "Offline";
    return `<i class="fa-solid fa-wifi text-${status}" data-bs-toggle="tooltip" data-bs-placement="top" title="${title}"></i>`;
};

const renderDeviceCard = ({ uid, eqt_name, modelNumber, isOnline }) => {
    const name = eqt_name || "Unnamed Device";

    return `
    <div class="col-md-4 col-lg-3">
        <div role="button" class="card device-card shadow-sm h-100" data-bs-toggle="modal" data-bs-target="#radarModal" data-id="${uid}" data-name="${name}">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="device-model fw-bold">Modelo ${getModelName(modelNumber)}</span>
                    ${getWifiIcon(isOnline === "0")}
                </div>
                <h5 class="device-name mb-1">${name}</h5>
                <small class="text-muted">UID: ${uid}</small>
            </div>
        </div>
    </div>
    `;
};

// Fetch devices
const fetchDevices = async () => {
    try {
        const response = await getRequest("thirdparty/v2/getDeviceInfo");
        if (response.msg && response.msg.includes("Frequent requests")) {
            toast.warning("Limite de requisições. Tente novamente mais tarde");
            return [];
        }
        return response.data || [];
    } catch (error) {
        console.error("Error fetching devices:", error);
        return [];
    }
};

const renderDevicesList = async () => {
    try {
        renderLoading(radarsWrapper);

        const devices = await fetchDevices();

        // Sort online first, then by name
        devices.sort((a, b) => {
            const aOnline = a.isOnline === "0";
            const bOnline = b.isOnline === "0";
            if (aOnline !== bOnline) return bOnline - aOnline;

            return (a.eqt_name || "")
                .toLowerCase()
                .localeCompare((b.eqt_name || "").toLowerCase());
        });

        radarsWrapper.innerHTML = devices.map(renderDeviceCard).join("");

        initTooltips();
    } catch (error) {
        console.error("Error rendering devices:", error);
    } finally {
        removeLoading(radarsWrapper);
    }
};

await renderDevicesList();
initSleepReportModal();

const toolstips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
[...toolstips].map((el) => new bootstrap.Tooltip(el));
