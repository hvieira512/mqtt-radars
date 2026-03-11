import { sendRequest } from "../../../auth.js";
import { renderLoading, removeLoading } from "../../../utils.js";
import { initRadarMap, renderRoom, destroyRadarMap } from "./map.js";
import { renderRadarInfo } from "./info.js";
import { initRadarWebsocket } from "./websocket.js";

const modal = document.getElementById("radarModal");
const modalTitle = modal.querySelector(".modal-title");
const container = document.getElementById("radar-map");
const infoTab = document.getElementById("info");

let currUID = null;

modal.addEventListener("shown.bs.modal", async (e) => {
    currUID = e.relatedTarget.dataset.id;
    if (!currUID) return;

    modal.dataset.id = currUID;
    renderLoading(container);

    try {
        const res = await sendRequest("deviceProp", { uid: currUID });
        if (!res?.data) return;

        modalTitle.textContent = `Detalhes do Radar - ${currUID}`;

        initRadarMap(container);
        renderRoom(res.data.rectangle, res.data.declare_area, res.data);
        renderRadarInfo(infoTab, res.data);
    } catch (err) {
        console.error(err);
    } finally {
        removeLoading(container);
    }
});

modal.addEventListener("hidden.bs.modal", () => {
    currUID = null;
    modal.dataset.id = "";
    modalTitle.textContent = "Detalhes do Radar";
    infoTab.innerHTML = "";
    destroyRadarMap();
});

window.addEventListener("resize", () => {
    if (!currUID) return;
});
