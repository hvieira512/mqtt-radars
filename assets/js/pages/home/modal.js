import { sendRequest } from "../../auth.js";

const modal = document.getElementById("radarModal");
let currUID = null;

const renderLiveMap = (data) => {
    const container = document.getElementById("radar-map");
    container.innerHTML = "";

    const stage = new Konva.Stage({
        container: "radar-map",
        width: container.offsetWidth,
        height: container.offsetHeight,
    });

    const layer = new Konva.Layer();
    stage.add(layer);

    // Parse the device rectangle (four vertices)
    // Example: "{-22,0;20,0;-22,40;20,40}"
    if (data.rectangle) {
        const rectCoords = data.rectangle
            .replace(/[{}]/g, "") // remove braces
            .split(";")
            .map((point) => point.split(",").map(Number));

        const polygonPoints = rectCoords.flat(); // flatten [[x,y],...] -> [x,y,x,y,...]

        const devicePolygon = new Konva.Line({
            points: polygonPoints,
            stroke: "blue",
            strokeWidth: 2,
            closed: true,
        });
        layer.add(devicePolygon);
    }

    // Parse declared areas (doors, beds)
    // Example: "{0,5,-10,0,5,0,-10,15,5,15},{1,4,10,0,15,0,10,30,15,30}"
    if (data.declare_area) {
        const areas = data.declare_area
            .split("},")
            .map((a) => a.replace(/[{}]/g, ""));
        areas.forEach((areaStr) => {
            const vals = areaStr.split(",").map(Number);
            const type = vals[1]; // second value indicates type (door=4, bed=5, etc.)
            const coords = [];
            for (let i = 2; i < vals.length; i += 2) {
                coords.push(vals[i], vals[i + 1]);
            }

            const color = type === 5 ? "green" : "orange"; // bed = green, others = orange
            const areaPolygon = new Konva.Line({
                points: coords,
                stroke: color,
                strokeWidth: 2,
                closed: true,
            });
            layer.add(areaPolygon);
        });
    }

    // Optionally: draw points for heart/breath or other signals
    if (data.heart_breath_param) {
        const params = JSON.parse(data.heart_breath_param);
        // Example: draw a small circle for each param (just illustrative)
        params.slice(0, 6).forEach((val, idx) => {
            const circle = new Konva.Circle({
                x: idx * 30 + 20,
                y: 200 - val, // scale for display
                radius: 5,
                fill: "red",
            });
            layer.add(circle);
        });
    }

    layer.draw();
};

modal.addEventListener("show.bs.modal", async (e) => {
    currUID = e.relatedTarget.dataset.id;
    modal.dataset.id = currUID;
    if (!currUID) return;

    try {
        const response = await sendRequest("deviceProp", { uid: currUID });

        if (!response || !response.data) {
            console.warn("No data returned for UID", currUID);
            return;
        }

        renderLiveMap(response.data);
    } catch (error) {
        console.error("Error fetching device properties:", error);
    }
});

modal.addEventListener("hidden.bs.modal", () => {
    currUID = null;
    delete modal.dataset.id;
});
