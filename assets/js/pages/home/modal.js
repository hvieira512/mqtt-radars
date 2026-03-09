import { sendRequest } from "../../auth.js";

const modal = document.getElementById("radarModal");

let currUID = null;
let stage = null;
let layer = null;

// Helper: compute bounding box of coordinates
const getBounds = (coords) => {
    const xs = coords.filter((_, i) => i % 2 === 0);
    const ys = coords.filter((_, i) => i % 2 === 1);
    return {
        minX: Math.min(...xs),
        maxX: Math.max(...xs),
        minY: Math.min(...ys),
        maxY: Math.max(...ys),
        width: Math.max(...xs) - Math.min(...xs),
        height: Math.max(...ys) - Math.min(...ys),
    };
};

const renderLiveMap = (data) => {
    const container = document.getElementById("radar-map");
    if (!container) return console.error("Radar container not found!");
    container.innerHTML = "";

    if (stage) {
        stage.destroy();
        stage = null;
        layer = null;
    }

    // Ensure container has height
    if (container.offsetHeight === 0) container.style.height = "400px";

    stage = new Konva.Stage({
        container: "radar-map",
        width: container.offsetWidth,
        height: container.offsetHeight,
    });

    layer = new Konva.Layer();
    stage.add(layer);

    const cw = container.offsetWidth;
    const ch = container.offsetHeight;
    const padding = 20;

    // --- 1. Parse room rectangle ---
    if (!data.rectangle) return;

    let rectCoords = data.rectangle
        .replace(/[{}]/g, "")
        .split(";")
        .map((p) => p.trim().split(",").map(Number)) // Trim whitespace
        .flat();

    // Reorder to perimeter: lowLeft, lowRight, upRight, upLeft (BL -> BR -> TR -> TL)
    rectCoords = [
        rectCoords[0],
        rectCoords[1], // lowLeft
        rectCoords[2],
        rectCoords[3], // lowRight
        rectCoords[6],
        rectCoords[7], // upRight
        rectCoords[4],
        rectCoords[5], // upLeft
    ];

    // Compute bounds for scaling
    const bounds = getBounds(rectCoords);
    const scaleX = (cw - 2 * padding) / bounds.width;
    const scaleY = (ch - 2 * padding) / bounds.height;
    const scale = Math.min(scaleX, scaleY);

    const offsetX = -bounds.minX;
    const offsetY = -bounds.minY;

    const transformCoords = (coords) => {
        const transformed = [];
        for (let i = 0; i < coords.length; i += 2) {
            const x = (coords[i] + offsetX) * scale + padding;
            const y = ch - ((coords[i + 1] + offsetY) * scale + padding); // flip Y
            transformed.push(x, y);
        }
        return transformed;
    };

    // Draw room boundary
    layer.add(
        new Konva.Line({
            points: transformCoords(rectCoords),
            stroke: "blue",
            strokeWidth: 2,
            closed: true,
        }),
    );

    // --- 2. Draw declared areas ---
    if (data.declare_area) {
        const areas = data.declare_area
            .split("},")
            .map((a) => a.replace(/[{}]/g, "").trim()); // Trim whitespace

        areas.forEach((areaStr) => {
            if (!areaStr) return; // Skip empty (trailing comma)
            const vals = areaStr.split(",").map(Number);
            if (vals.length < 10) return; // Expect 10 vals (key + type + 8 coords)

            const type = vals[1];
            let coords = [];
            for (let i = 2; i < vals.length; i += 2) {
                coords.push(vals[i], vals[i + 1]);
            }

            // Reorder to perimeter: lowLeft, lowRight, upRight, upLeft
            coords = [
                coords[0],
                coords[1], // lowLeft
                coords[2],
                coords[3], // lowRight
                coords[6],
                coords[7], // upRight
                coords[4],
                coords[5], // upLeft
            ];

            // Color logic (extended for warning area)
            let strokeColor = "gray";
            if (type === 5)
                strokeColor = "green"; // Monitoring bed
            else if (type === 4)
                strokeColor = "orange"; // Door
            else if (type === 6) strokeColor = "red"; // Warning area

            layer.add(
                new Konva.Line({
                    points: transformCoords(coords),
                    stroke: strokeColor,
                    strokeWidth: 2,
                    closed: true,
                    fill: `${strokeColor}20`, // Semi-transparent fill (optional; hex with alpha)
                }),
            );
        });
    }

    // --- 3. Optional: Draw radar position at (0,0) ---
    const radarPos = transformCoords([0, 0]);
    layer.add(
        new Konva.Circle({
            x: radarPos[0],
            y: radarPos[1],
            radius: 5,
            fill: "red",
        }),
    );

    layer.draw();
};

modal.addEventListener("shown.bs.modal", async (e) => {
    currUID = e.relatedTarget.dataset.id;
    if (!currUID) return;

    modal.dataset.id = currUID;

    try {
        const response = await sendRequest("deviceProp", { uid: currUID });
        if (!response || !response.data)
            return console.warn("No data for UID", currUID);

        renderLiveMap(response.data);
    } catch (err) {
        console.error("Error fetching device properties:", err);
    }
});

modal.addEventListener("hidden.bs.modal", () => {
    currUID = null;
    delete modal.dataset.id;

    if (stage) {
        stage.destroy();
        stage = null;
        layer = null;
    }
});
