import {
    AREA_COLORS,
    getBounds,
    reorderRect,
    parseRectangle,
    parseAreas,
    getAreaName,
} from "./utils.js";

let stage = null;
let layer = null;
let peopleLayer = null;
let transformCoords = null;
const peopleNodes = new Map();

// ──────────────────────────────
// Initialize radar map
// ──────────────────────────────
export function initRadarMap(container) {
    if (stage) stage.destroy();
    if (container.offsetHeight === 0) container.style.height = "400px";

    stage = new Konva.Stage({
        container: container.id,
        width: container.offsetWidth,
        height: container.offsetHeight,
    });

    layer = new Konva.Layer();
    stage.add(layer);

    peopleLayer = new Konva.Layer();
    stage.add(peopleLayer);
}

// ──────────────────────────────
// Coordinate transform
// ──────────────────────────────
function createTransform(bounds, cw, ch, padding = 30) {
    const scale = Math.min(
        (cw - 2 * padding) / bounds.width,
        (ch - 2 * padding) / bounds.height,
    );
    const offsetX = -bounds.minX;
    const offsetY = -bounds.minY;
    const scaledWidth = bounds.width * scale;
    const scaledHeight = bounds.height * scale;
    const centerOffsetX = (cw - scaledWidth) / 2;
    const centerOffsetY = (ch - scaledHeight) / 2;

    return (coords) => {
        const result = [];
        for (let i = 0; i < coords.length; i += 2) {
            const x = (coords[i] + offsetX) * scale + centerOffsetX;
            const y = ch - ((coords[i + 1] + offsetY) * scale + centerOffsetY);
            result.push(x, y);
        }
        return result;
    };
}

// ──────────────────────────────
// Render room, areas, radar
// ──────────────────────────────
export function renderRoom(rectangle, declare_area, data) {
    const rect = reorderRect(parseRectangle(rectangle));
    const bounds = getBounds(rect);
    const cw = stage.width();
    const ch = stage.height();

    transformCoords = createTransform(bounds, cw, ch);

    // Room
    layer.add(
        new Konva.Line({
            points: transformCoords(rect),
            stroke: "gray",
            strokeWidth: 3,
            closed: true,
        }),
    );

    // Areas
    parseAreas(declare_area).forEach((area) => {
        const color = AREA_COLORS[area.type] || AREA_COLORS.default;
        const coords = reorderRect(area.coords);
        const points = transformCoords(coords);

        layer.add(
            new Konva.Line({
                points,
                stroke: color,
                strokeWidth: 2.5,
                fill: color + "33",
                closed: true,
            }),
        );

        const [x, y] = transformCoords([
            (Math.min(...coords.filter((_, i) => i % 2 === 0)) +
                Math.max(...coords.filter((_, i) => i % 2 === 0))) /
                2,
            (Math.min(...coords.filter((_, i) => i % 2 === 1)) +
                Math.max(...coords.filter((_, i) => i % 2 === 1))) /
                2,
        ]);

        layer.add(
            new Konva.Text({
                x: x - 60,
                y: y - 10,
                width: 120,
                align: "center",
                text: getAreaName(data, area.key, area.type),
                fontSize: 14,
                fontFamily: "Poppins",
                fill: color,
                shadowColor: "white",
                shadowBlur: 5,
            }),
        );
    });

    // Radar center
    const [x, y] = transformCoords([0, 0]);
    layer.add(
        new Konva.Circle({
            x,
            y,
            radius: 6,
            fill: "red",
            stroke: "white",
            strokeWidth: 2,
        }),
    );

    layer.draw();
}

// ──────────────────────────────
// Update people positions
// ──────────────────────────────
export function updatePeople(people) {
    if (!peopleLayer || !transformCoords) return;

    console.log(people);
    people.forEach((p) => {
        const [x, y] = transformCoords([p.x_position_dm, p.y_position_dm]);
        let node = peopleNodes.get(p.person_index);

        if (!node) {
            const group = new Konva.Group({ x, y });
            const circle = new Konva.Circle({
                x: 0,
                y: 0,
                radius: 8,
                fill: "#00bfff",
                stroke: "white",
                strokeWidth: 2,
            });

            const label = new Konva.Text({
                x: 10,
                y: -8,
                text: `P${p.person_index}`,
                fontSize: 12,
                fill: "white",
            });

            group.add(circle);
            group.add(label);

            peopleLayer.add(group);
            peopleNodes.set(p.person_index, group);
            node = group;
        }

        node.position({ x, y });
    });

    peopleLayer.draw();
}

// ──────────────────────────────
// Destroy map
// ──────────────────────────────
export function destroyRadarMap() {
    if (stage) stage.destroy();
    peopleNodes.clear();
}
