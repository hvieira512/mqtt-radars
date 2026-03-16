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
    layer.add(
        new Konva.Text({
            x: x - 8, // center adjustment for icon
            y: y - 8,
            text: "\uf519", // fa-circle-info
            fontFamily: "Font Awesome 6 Free",
            fontStyle: "900",
            fontSize: 16,
            fill: "#0dcaf0", // Bootstrap info blue
        }),
    );
    layer.add(
        new Konva.Text({
            x: x - 20, // center the text roughly under the icon
            y: y + 10, // below the icon
            text: "Radar",
            fontSize: 12,
            fontFamily: "Poppins",
            fill: "#0dcaf0", // match the icon color
            fontStyle: "bold",
            align: "center",
            width: 40,
        }),
    );

    layer.draw();
}

// ──────────────────────────────
// Update people positions
// ──────────────────────────────
const POSTURE_STYLE = {
    Initialization: {
        icon: "\uf128", // fa-question
        color: "#6c757d", // bootstrap secondary
        labelPT: "Inicialização",
    },

    Walking: {
        icon: "\uf554", // fa-person-walking
        color: "#0d6efd", // bootstrap primary
        labelPT: "A Andar",
    },

    "Suspected Fall": {
        icon: "\uf071", // fa-triangle-exclamation
        color: "#ffc107", // bootstrap warning
        labelPT: "Suspeita de Queda",
    },

    Squatting: {
        icon: "\uf6ec", // fa-person-sitting
        color: "#fd7e14", // bootstrap orange
        labelPT: "Agachado",
    },

    Standing: {
        icon: "\uf183", // fa-person
        color: "#198754", // bootstrap success
        labelPT: "Em Pé",
    },

    "Fall Confirmation": {
        icon: "\uf071", // fa-triangle-exclamation
        color: "#dc3545", // bootstrap danger
        labelPT: "Queda Confirmada",
    },

    "Lying Down": {
        icon: "\uf236", // fa-bed
        color: "#6f42c1", // bootstrap purple
        labelPT: "Deitado",
    },

    "Suspected Sitting on Ground": {
        icon: "\uf6ec", // fa-person-sitting
        color: "#ffc107", // bootstrap warning
        labelPT: "Suspeita Sentado no Chão",
    },

    "Confirmed Sitting on Ground": {
        icon: "\uf6ec", // fa-person-sitting
        color: "#fd7e14", // bootstrap orange
        labelPT: "Sentado no Chão Confirmado",
    },

    "Sitting Up Bed": {
        icon: "\uf236", // fa-bed
        color: "#0dcaf0", // bootstrap info
        labelPT: "Sentado na Cama",
    },

    "Suspected Sitting Up Bed": {
        icon: "\uf236", // fa-triangle-exclamation
        color: "#ffc107", // bootstrap warning
        labelPT: "Suspeita Sentado na Cama",
    },

    "Confirmed Sitting Up Bed": {
        icon: "\uf236", // fa-procedures
        color: "#198754", // bootstrap success
        labelPT: "Sentado na Cama Confirmado",
    },
};

export function updatePeople(people) {
    if (!stage || !peopleLayer || !transformCoords) return;

    if (people.length === 1 && people[0].person_index === 88) {
        peopleNodes.forEach((node) => node.destroy());
        peopleNodes.clear();

        updateCurrentPeople(0);
        peopleLayer.batchDraw();
        return;
    }

    const activeIndexes = new Set();

    people.forEach((p) => {
        const [x, y] = transformCoords([p.x_position_dm, p.y_position_dm]);
        const style =
            POSTURE_STYLE[p.posture_state] ?? POSTURE_STYLE["Initialization"];

        let node = peopleNodes.get(p.person_index);
        activeIndexes.add(p.person_index);

        if (!node) {
            const group = new Konva.Group({ x, y });

            const circle = new Konva.Circle({
                x: 0,
                y: 0,
                radius: 10,
                fill: "#0d6efd22",
                stroke: style.color,
                strokeWidth: 3,
            });

            const icon = new Konva.Text({
                x: -6,
                y: -7,
                text: style.icon,
                fontFamily: "Font Awesome 6 Free",
                fontStyle: "900",
                fontSize: 12,
                fill: style.color,
            });

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

            group.add(circle, icon, label);

            peopleLayer.add(group);
            peopleNodes.set(p.person_index, group);
            node = group;
        } else {
            const { circle, icon, label } = node;

            circle.stroke(style.color);
            icon.text(style.icon);
            icon.fill(style.color);
            label.text(style.labelPT);
            label.fill(style.color);
        }

        node.to({
            x,
            y,
            duration: 0.2,
        });
    });

    peopleNodes.forEach((node, index) => {
        if (!activeIndexes.has(index)) {
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
