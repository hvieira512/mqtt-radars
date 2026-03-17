import {
    AREA_COLORS,
    getBounds,
    reorderRect,
    parseRectangle,
    parseAreas,
    getAreaName,
    updateCurrentPeople,
} from "./utils.js";

let stage = null;
let layer = null;
let peopleLayer = null;
let transformCoords = null;

const peopleNodes = new Map();
let currentLayout = null;

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
    peopleLayer = new Konva.Layer();

    stage.add(layer);
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
// Render room and areas
// ──────────────────────────────
export function renderRoom(rectangle, declare_area, data) {
    currentLayout = { rectangle, declare_area, data };

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
                dash: [10, 10],
                fill: color + "22",
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

    const radarIcon = new Konva.Text({
        text: "\uf519",
        fontFamily: "Font Awesome 6 Free",
        fontStyle: "900",
        fontSize: 16,
        fill: "#0dcaf0",
    });

    radarIcon.offsetX(radarIcon.width() / 2);
    radarIcon.offsetY(radarIcon.height() / 2);

    radarIcon.position({ x, y });

    const radarLabel = new Konva.Text({
        x: x - 20,
        y: y + 10,
        text: "Radar",
        fontSize: 12,
        fontFamily: "Poppins",
        fill: "#0dcaf0",
        fontStyle: "bold",
        align: "center",
        width: 40,
    });

    layer.add(radarIcon);
    layer.add(radarLabel);

    layer.draw();
}

// ──────────────────────────────
// Posture styles
// ──────────────────────────────
const POSTURE_STYLE = {
    Initialization: {
        icon: "\uf128",
        color: "#6c757d",
        labelPT: "Inicialização",
    },
    Walking: { icon: "\uf554", color: "#0d6efd", labelPT: "A Andar" },
    "Suspected Fall": {
        icon: "\uf071",
        color: "#ffc107",
        labelPT: "Suspeita de Queda",
    },
    Squatting: { icon: "\uf6ec", color: "#fd7e14", labelPT: "Agachado" },
    Standing: { icon: "\uf183", color: "#198754", labelPT: "Em Pé" },
    "Fall Confirmation": {
        icon: "\uf071",
        color: "#dc3545",
        labelPT: "Queda Confirmada",
    },
    "Lying Down": { icon: "\uf236", color: "#6f42c1", labelPT: "Deitado" },
    "Suspected Sitting on Ground": {
        icon: "\uf6ec",
        color: "#ffc107",
        labelPT: "Suspeita Sentado no Chão",
    },
    "Confirmed Sitting on Ground": {
        icon: "\uf6ec",
        color: "#fd7e14",
        labelPT: "Sentado no Chão Confirmado",
    },
    "Sitting Up Bed": {
        icon: "\uf236",
        color: "#0dcaf0",
        labelPT: "Sentado na Cama",
    },
    "Suspected Sitting Up Bed": {
        icon: "\uf236",
        color: "#ffc107",
        labelPT: "Suspeita Sentado na Cama",
    },
    "Confirmed Sitting Up Bed": {
        icon: "\uf236",
        color: "#198754",
        labelPT: "Sentado na Cama Confirmado",
    },
};

// ──────────────────────────────
// Person node factory
// ──────────────────────────────
function createPersonNode(index, x, y, style) {
    const group = new Konva.Group({ x, y });

    const circle = new Konva.Circle({
        radius: 10,
        fill: "#0d6efd22",
        stroke: style.color,
        strokeWidth: 3,
    });

    const icon = new Konva.Text({
        text: style.icon,
        fontFamily: "Font Awesome 6 Free",
        fontStyle: "900",
        fontSize: 12,
        fill: style.color,
    });

    icon.offsetX(icon.width() / 2);
    icon.offsetY(icon.height() / 2);

    const label = new Konva.Text({
        x: 14,
        y: -8,
        text: style.labelPT,
        fontSize: 12,
        fontStyle: "bold",
        fill: style.color,
    });

    group.circle = circle;
    group.icon = icon;
    group.label = label;

    group.moveTween = null;

    group.add(circle, icon, label);

    peopleLayer.add(group);

    return group;
}

// ──────────────────────────────
// Update people
// ──────────────────────────────
export function updatePeople(people) {
    if (!stage || !peopleLayer || !transformCoords) return;

    if (people.length === 1 && people[0].person_index === 88) {
        peopleNodes.forEach((node) => node.destroy());
        peopleNodes.clear();

        updateCurrentPeople(0);
        peopleLayer.batchDraw();
        return;
    }

    const active = new Set();

    people.forEach((p) => {
        const [x, y] = transformCoords([p.x_position_dm, p.y_position_dm]);

        const style =
            POSTURE_STYLE[p.posture_state] ?? POSTURE_STYLE["Initialization"];

        let node = peopleNodes.get(p.person_index);
        active.add(p.person_index);

        if (!node) {
            node = createPersonNode(p.person_index, x, y, style);
            peopleNodes.set(p.person_index, node);
        }

        const { circle, icon, label } = node;

        circle.stroke(style.color);

        icon.text(style.icon);
        icon.fill(style.color);
        icon.offsetX(icon.width() / 2);
        icon.offsetY(icon.height() / 2);

        label.text(style.labelPT);
        label.fill(style.color);

        // smooth movement only
        if (node.moveTween) node.moveTween.destroy();

        node.moveTween = new Konva.Tween({
            node,
            x,
            y,
            duration: 0.15,
            easing: Konva.Easings.Linear,
        });

        node.moveTween.play();
    });

    peopleNodes.forEach((node, index) => {
        if (!active.has(index)) {
            node.destroy();
            peopleNodes.delete(index);
        }
    });

    updateCurrentPeople(peopleNodes.size);
    peopleLayer.batchDraw();
}

// ──────────────────────────────
// Destroy map
// ──────────────────────────────
export function destroyRadarMap() {
    if (stage) {
        stage.destroy();
        stage = null;
    }

    layer = null;
    peopleLayer = null;
    transformCoords = null;

    peopleNodes.clear();
}

export function resizeRadarMap(container) {
    if (!stage || !currentLayout) return;

    stage.width(container.offsetWidth);
    stage.height(container.offsetHeight);

    layer.destroyChildren();

    renderRoom(
        currentLayout.rectangle,
        currentLayout.declare_area,
        currentLayout.data,
    );

    peopleLayer.batchDraw();
}
