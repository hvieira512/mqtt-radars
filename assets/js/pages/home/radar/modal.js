import { sendRequest } from "../../../auth.js";
import { renderLoading, removeLoading } from "../../../utils.js";
import {
    initRadarMap,
    renderRoom,
    destroyRadarMap,
    resizeRadarMap,
} from "./map.js";
import { renderRadarInfo } from "./info.js";
import { initRadarWebsocket, setCurrentUID } from "./websocket.js";
import toast from "../../../toastr.js";
import { setMapCache, getMapCache } from "./utils.js";
import { clearGrids, initGrids } from "./grid.js";

const fetchDevicePropWithTimeout = (uid, timeout = 3000) =>
    Promise.race([
        sendRequest("thirdparty/v2/deviceProp", { uid }),
        new Promise((_, reject) =>
            setTimeout(() => reject(new Error("Request timed out")), timeout),
        ),
    ]);

const modal = document.getElementById("radarModal");
const modalTitle = modal.querySelector(".modal-title");
const container = document.getElementById("radar-map");
const infoTab = document.getElementById("info");

let currUID = null;

initGrids();
modal.addEventListener("shown.bs.modal", async (e) => {
    currUID = e.relatedTarget.dataset.id;
    if (!currUID) return;

    modal.dataset.id = currUID;
    setCurrentUID(currUID);

    renderLoading(container);

    let layoutData = getMapCache(currUID);

    clearGrids();

    try {
        const res = await fetchDevicePropWithTimeout(currUID);

        if (res?.data?.code === 500) {
            toast.warning(
                "Sinal fraco do equipamento",
                "Por favor verifique o equipamento",
            );
        }

        if (res?.data) {
            layoutData = res.data;
            if (layoutData?.code !== 500) setMapCache(currUID, layoutData);
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
        resizeRadarMap(container);
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

window.addEventListener("resize", () => {
    if (modal.classList.contains("show")) {
        resizeRadarMap(container);
    }
});

initRadarWebsocket();
