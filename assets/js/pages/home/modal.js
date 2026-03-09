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
    const padding = 30; // slightly more padding for labels

    // ────────────────────────────────────────────────
    // 1. Parse & reorder room boundary
    // ────────────────────────────────────────────────
    if (!data.rectangle) return;

    let rectCoords = data.rectangle
        .replace(/[{}]/g, "")
        .split(";")
        .map((p) => p.trim().split(",").map(Number))
        .flat();

    // Reorder: lowLeft → lowRight → upRight → upLeft
    rectCoords = [
        rectCoords[0],
        rectCoords[1],
        rectCoords[2],
        rectCoords[3],
        rectCoords[6],
        rectCoords[7],
        rectCoords[4],
        rectCoords[5],
    ];

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
            const y = ch - ((coords[i + 1] + offsetY) * scale + padding);
            transformed.push(x, y);
        }
        return transformed;
    };

    // Room boundary (no fill, just outline)
    layer.add(
        new Konva.Line({
            points: transformCoords(rectCoords),
            stroke: "gray",
            strokeWidth: 3,
            closed: true,
        }),
    );

    // ────────────────────────────────────────────────
    // 2. Parse & draw declared areas + labels
    // ────────────────────────────────────────────────
    const typeToLabel = {
        0: "Inválido",
        1: "Customizado",
        2: "Cama",
        3: "Interferência",
        4: "Porta",
        5: "Cama de Monitorizaçãi",
        6: "Área de alarme",
    };

    const typeToColor = {
        4: "#ffa500", // orange - door
        5: "#32cd32", // lime green - monitoring bed
        6: "#ff4500", // orange-red - warning
        3: "#808080", // gray - interference
        default: "#a9a9a9", // dark gray for others
    };

    if (data.declare_area) {
        const areas = data.declare_area
            .split("},")
            .map((a) => a.replace(/[{}]/g, "").trim())
            .filter((a) => a);

        areas.forEach((areaStr) => {
            const vals = areaStr.split(",").map(Number);
            if (vals.length < 10) return;

            const coordKey = vals[0];
            const type = vals[1];

            let coords = [];
            for (let i = 2; i < vals.length; i += 2) {
                coords.push(vals[i], vals[i + 1]);
            }

            // Reorder to perimeter order
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

            const color = typeToColor[type] || typeToColor.default;
            const labelText = typeToLabel[type] || `Area ${coordKey}`;

            const points = transformCoords(coords);

            // Draw area outline + semi-transparent fill
            layer.add(
                new Konva.Line({
                    points,
                    stroke: color,
                    strokeWidth: 2.5,
                    closed: true,
                    fill: color + "33", // hex + alpha (33 ≈ 20%)
                }),
            );

            // Calculate approximate center for label
            const areaBounds = getBounds(coords);
            const centerX = (areaBounds.minX + areaBounds.maxX) / 2;
            const centerY = (areaBounds.minY + areaBounds.maxY) / 2;
            const [labelX, labelY] = transformCoords([centerX, centerY]);

            // Add label
            console.log(
                `Adding label "${labelText}" at (${labelX.toFixed(1)}, ${labelY.toFixed(1)}) with color ${color}`,
            );
            layer.add(
                new Konva.Text({
                    x: labelX - 60, // rough centering - adjust as needed
                    y: labelY - 10,
                    text: labelText,
                    fontSize: 14,
                    fontFamily: "Poppins",
                    fill: color,
                    shadowColor: "white",
                    shadowBlur: 5,
                    shadowOffsetX: 1,
                    shadowOffsetY: 1,
                    shadowOpacity: 0.7,
                    align: "center",
                    width: 120, // helps with centering
                }),
            );
        });
    }

    // Radar position marker
    const radarPos = transformCoords([0, 0]);
    layer.add(
        new Konva.Circle({
            x: radarPos[0],
            y: radarPos[1],
            radius: 6,
            fill: "red",
            stroke: "white",
            strokeWidth: 2,
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
