import { sendRequest } from "../../../auth.js";
import { renderLoading, removeLoading } from "../../../utils.js";
import { initRadarMap, renderRoom, destroyRadarMap } from "./map.js";
import { renderRadarInfo } from "./info.js";
import { initRadarWebsocket, setCurrentUID } from "./websocket.js";
import toast from "../../../toastr.js";

const mapCache = new Map();

const setMapCache = (uid, data) => {
    mapCache.set(uid, data);
    try {
        localStorage.setItem(`mapCache_${uid}`, JSON.stringify(data));
    } catch (err) {
        console.warn("Failed to write map cache to localStorage", err);
    }
};

const getMapCache = (uid) => {
    if (mapCache.has(uid)) return mapCache.get(uid);

    try {
        const cached = localStorage.getItem(`mapCache_${uid}`);
        if (cached) {
            const data = JSON.parse(cached);
            mapCache.set(uid, data);
            return data;
        }
    } catch (err) {
        console.warn("Failed to read map cache from localStorage", err);
    }
    return null;
};

const fetchDevicePropWithTimeout = (uid, timeout = 3000) =>
    Promise.race([
        sendRequest("deviceProp", { uid }),
        new Promise((_, reject) =>
            setTimeout(() => reject(new Error("Request timed out")), timeout),
        ),
    ]);

const modal = document.getElementById("radarModal");
const modalTitle = modal.querySelector(".modal-title");
const container = document.getElementById("radar-map");
const infoTab = document.getElementById("info");

let currUID = null;

modal.addEventListener("shown.bs.modal", async (e) => {
    currUID = e.relatedTarget.dataset.id;
    if (!currUID) return;

    modal.dataset.id = currUID;
    setCurrentUID(currUID);

    renderLoading(container);

    let layoutData = getMapCache(currUID);

    try {
        const res = await fetchDevicePropWithTimeout(currUID);

        if (res?.code === 500) {
            toast.warning(
                "Sinal fraco do equipamento",
                "Por favor verifique o equipamento",
            );
        }

        if (res?.data) {
            layoutData = res.data;
            setMapCache(currUID, layoutData);
        }
    } catch (err) {
        console.error(err);

        if (!layoutData) {
            toast.error(
                "Erro ao comunicar com o radar",
                "Não há dados de mapa disponíveis.",
            );
        }
    } finally {
        removeLoading(container);
    }

    if (layoutData) {
        modalTitle.textContent = `Detalhes do Radar - ${currUID}`;
        initRadarMap(container);
        renderRoom(layoutData.rectangle, layoutData.declare_area, layoutData);
        renderRadarInfo(infoTab, layoutData);
    }
});

modal.addEventListener("hidden.bs.modal", () => {
    currUID = null;
    modal.dataset.id = "";
    modalTitle.textContent = "Detalhes do Radar";
    infoTab.innerHTML = "";
    destroyRadarMap();
    setCurrentUID(null);
});

initRadarWebsocket();
