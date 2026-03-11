export const AREA_LABELS = {
    0: "Inválido",
    1: "Customizado",
    2: "Cama",
    3: "Interferência",
    4: "Porta",
    5: "Cama de Monitorização",
    6: "Área de alarme",
};

export const AREA_COLORS = {
    4: "#ffa500",
    5: "#32cd32",
    6: "#ff4500",
    3: "#808080",
    default: "#a9a9a9",
};

export const getBounds = (coords) => {
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

export const parseRectangle = (rectangle) =>
    rectangle
        .replace(/[{}]/g, "")
        .split(";")
        .map((p) => p.trim().split(",").map(Number))
        .flat();

export const reorderRect = (coords) => [
    coords[0],
    coords[1],
    coords[2],
    coords[3],
    coords[6],
    coords[7],
    coords[4],
    coords[5],
];

export const parseAreas = (data) => {
    if (!data) return [];
    return data
        .split("},")
        .map((a) => a.replace(/[{}]/g, "").trim())
        .filter(Boolean)
        .map((area) => {
            const vals = area.split(",").map(Number);
            const key = vals[0];
            const type = vals[1];
            const coords = [];
            for (let i = 2; i < vals.length; i += 2)
                coords.push(vals[i], vals[i + 1]);
            return { key, type, coords };
        });
};

export const getAreaName = (data, key, type) => {
    let label = AREA_LABELS[type] || `Area ${key}`;
    if (data?.declare_area_name?.[key]) {
        const parts = data.declare_area_name[key].split("_");
        label =
            parts.length > 1
                ? parts.slice(1).join("_")
                : data.declare_area_name[key];
    }
    return label;
};
