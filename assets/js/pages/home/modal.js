import { sendRequest } from "../../auth.js";
import { renderLoading, removeLoading } from "../../utils.js";

const modal = document.getElementById("radarModal");
const modalTitle = modal.querySelector(".modal-title");
const container = document.getElementById("radar-map");
const infoTab = document.getElementById("info");

let currUID = null;
let stage = null;
let layer = null;

/* ------------------------------------------------ */
/* CONFIG */
/* ------------------------------------------------ */

const AREA_LABELS = {
    0: "Inválido",
    1: "Customizado",
    2: "Cama",
    3: "Interferência",
    4: "Porta",
    5: "Cama de Monitorização",
    6: "Área de alarme",
};

const AREA_COLORS = {
    4: "#ffa500",
    5: "#32cd32",
    6: "#ff4500",
    3: "#808080",
    default: "#a9a9a9",
};

/* ------------------------------------------------ */
/* UTILS */
/* ------------------------------------------------ */

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

const parseRectangle = (rectangle) =>
    rectangle
        .replace(/[{}]/g, "")
        .split(";")
        .map((p) => p.trim().split(",").map(Number))
        .flat();

const reorderRect = (coords) => [
    coords[0], coords[1],
    coords[2], coords[3],
    coords[6], coords[7],
    coords[4], coords[5],
];

const parseAreas = (data) => {
    if (!data.declare_area) return [];

    return data.declare_area
        .split("},")
        .map((a) => a.replace(/[{}]/g, "").trim())
        .filter(Boolean)
        .map((area) => {
            const vals = area.split(",").map(Number);
            const key = vals[0];
            const type = vals[1];

            const coords = [];
            for (let i = 2; i < vals.length; i += 2) {
                coords.push(vals[i], vals[i + 1]);
            }

            return { key, type, coords };
        });
};

const getAreaName = (data, key, type) => {
    let label = AREA_LABELS[type] || `Area ${key}`;

    if (data.declare_area_name?.[key]) {
        const raw = data.declare_area_name[key];
        const parts = raw.split("_");
        label = parts.length > 1 ? parts.slice(1).join("_") : raw;
    }

    return label;
};

/* ------------------------------------------------ */
/* MAP RENDER */
/* ------------------------------------------------ */

const createStage = () => {
    if (stage) stage.destroy();

    if (container.offsetHeight === 0) container.style.height = "400px";

    stage = new Konva.Stage({
        container: "radar-map",
        width: container.offsetWidth,
        height: container.offsetHeight,
    });

    layer = new Konva.Layer();
    stage.add(layer);
};

const createTransform = (bounds, cw, ch, padding = 30) => {
    const scale = Math.min(
        (cw - 2 * padding) / bounds.width,
        (ch - 2 * padding) / bounds.height
    );

    const scaledWidth = bounds.width * scale;
    const scaledHeight = bounds.height * scale;

    const centerOffsetX = (cw - scaledWidth) / 2;
    const centerOffsetY = (ch - scaledHeight) / 2;

    const offsetX = -bounds.minX;
    const offsetY = -bounds.minY;

    return (coords) => {
        const result = [];

        for (let i = 0; i < coords.length; i += 2) {
            const x =
                (coords[i] + offsetX) * scale + centerOffsetX;

            const y =
                ch - ((coords[i + 1] + offsetY) * scale + centerOffsetY);

            result.push(x, y);
        }

        return result;
    };
};

/* ------------------------------------------------ */
/* DRAW FUNCTIONS */
/* ------------------------------------------------ */

const drawRoom = (coords, transform) => {
    layer.add(
        new Konva.Line({
            points: transform(coords),
            stroke: "gray",
            strokeWidth: 3,
            closed: true,
        })
    );
};

const drawArea = (area, data, transform) => {
    const color = AREA_COLORS[area.type] || AREA_COLORS.default;
    const label = getAreaName(data, area.key, area.type);

    const coords = reorderRect(area.coords);
    const points = transform(coords);

    layer.add(
        new Konva.Line({
            points,
            stroke: color,
            strokeWidth: 2.5,
            closed: true,
            fill: color + "33",
        })
    );

    const bounds = getBounds(coords);
    const cx = (bounds.minX + bounds.maxX) / 2;
    const cy = (bounds.minY + bounds.maxY) / 2;

    const [x, y] = transform([cx, cy]);

    layer.add(
        new Konva.Text({
            x: x - 60,
            y: y - 10,
            width: 120,
            align: "center",
            text: label,
            fontSize: 14,
            fontFamily: "Poppins",
            fill: color,
            shadowColor: "white",
            shadowBlur: 5,
        })
    );
};

const drawRadar = (transform) => {
    const [x, y] = transform([0, 0]);

    layer.add(
        new Konva.Circle({
            x,
            y,
            radius: 6,
            fill: "red",
            stroke: "white",
            strokeWidth: 2,
        })
    );
};

/* ------------------------------------------------ */
/* MAIN MAP */
/* ------------------------------------------------ */

const renderLiveMap = (data) => {
    if (!container) return;

    container.innerHTML = "";
    createStage();

    const cw = container.offsetWidth;
    const ch = container.offsetHeight;

    const rect = reorderRect(parseRectangle(data.rectangle));
    const bounds = getBounds(rect);

    const transform = createTransform(bounds, cw, ch);

    drawRoom(rect, transform);

    const areas = parseAreas(data);
    areas.forEach((area) => drawArea(area, data, transform));

    drawRadar(transform);

    layer.draw();
};

/* ------------------------------------------------ */
/* More Informations */
/* ------------------------------------------------ */

const renderInfo = (data) => {
    if (!infoTab) return;

    infoTab.innerHTML = ""; // Clear previous content

    // Helper: format values nicely
    const formatValue = (val) => {
        if (val === undefined || val === null || val === "") return "—";
        if (typeof val === "string" && val.trim() === "") return "—";
        return val;
    };

    // Helper: map working mode
    const getWorkingMode = (mode) => {
        const modes = {
            15: "Monitorização de Cama",
            11: "Respiração e Sono",
            7: "Monitorização de Queda",
            3: "Rastreamento de Pessoas",
        };
        return modes[mode] || `Desconhecido (${mode})`;
    };

    // Helper: map signal strength (Wi-Fi / 4G)
    const getSignalStrength = (val) => {
        if (!val || val === "-") return "—";
        const num = Number(val);
        if (isNaN(num)) return val;

        if (val.includes("CSQ")) {
            // 4G case
            if (num >= 23) return `${val} — Forte`;
            if (num >= 15) return `${val} — Médio`;
            if (num >= 0) return `${val} — Fraco`;
            return `${val} — Sem sinal`;
        }
        // Wi-Fi
        if (num <= -100) return `${val} — Sem Sinal`;
        if (num > -100 && num <= -88) return `${val} — Fraco`;
        if (num > -88 && num <= -66) return `${val} — OK`;
        if (num > -66 && num <= -55) return `${val} — Bom`;
        return `${val} — Forte`;
    };

    // Helper: parse postureParams
    const parsePostureParams = (str) => {
        if (!str) return { fallTime: "—", bits: "—", sitTime: "—" };
        const parts = str.split(",").map((s) => s.trim());
        if (parts.length < 3) return { fallTime: str, bits: "—", sitTime: "—" };

        let fallSec = Number(parts[0]) * 10;
        if (parts[0] === "0") fallSec = 30;

        const bits = Number(parts[1]);
        const bitDesc = [];
        if (bits & 1) bitDesc.push("Alarme de sentar/levantar LIGADO");
        if (bits & 2) bitDesc.push("Deteção de Postura LIGADO");
        if (bits & 4) bitDesc.push("Alarme para sentar na cama LIGADO");

        let sitSec = Number(parts[2]) * 10;
        if (parts[2] === "0") sitSec = 30;

        return {
            fallTime: fallSec === 0 ? "Padrão (30s)" : `${fallSec} segundos`,
            bits: bitDesc.length ? bitDesc.join(", ") : "Nenhum ativo",
            sitTime: sitSec === 0 ? "Padrão (30s)" : `${sitSec} segundos`,
        };
    };

    // ────────────────────────────────────────────────
    // Build content
    // ────────────────────────────────────────────────
    let html = `
        <div class="container-fluid py-3">
            <div class="row g-4">
                <!-- Main Settings Card -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-primary-subtle text-primary h5 mb-0">
                            Configuração do Radar
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-5 text-muted">Modo atual</dt>
                                <dd class="col-sm-7">${getWorkingMode(data.radar_func_ctrl)}</dd>

                                <dt class="col-sm-5 text-muted">Método de Instalação</dt>
                                <dd class="col-sm-7">${formatValue(data.radar_install_style)}</dd>

                                <dt class="col-sm-5 text-muted">Altura de Instalação</dt>
                                <dd class="col-sm-7">${formatValue(data.radar_install_height)} dm</dd>

                                <dt class="col-sm-5 text-muted">Força do Sinal</dt>
                                <dd class="col-sm-7">${getSignalStrength(data.signal_intensity)}</dd>

                                <dt class="col-sm-5 text-muted">Inclinação do Radar (X:Y:Z:V)</dt>
                                <dd class="col-sm-7">${formatValue(data.accelera)}</dd>

                                <dt class="col-sm-5 text-muted">Tempo de Compilação (Radar)</dt>
                                <dd class="col-sm-7">${formatValue(data.radar_compile_time)}</dd>

                                <dt class="col-sm-5 text-muted">Tempo de Compilação (App)</dt>
                                <dd class="col-sm-7">${formatValue(data.app_compile_time)}</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Alarm & Area Settings Card -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-info-subtle text-info h5 mb-0">
                            Alarmes e Deteção
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-5 text-muted">Tempo de Queda Suspeita</dt>
                                <dd class="col-sm-7">${formatValue(data.suspected_fall_time)} × 10s</dd>

                                <dt class="col-sm-5 text-muted">Alarme de Saída da Cama</dt>
                                <dd class="col-sm-7">${data.leaveAlarmSwitch === "0" ? "ON" : data.leaveAlarmSwitch === "1" ? "OFF" : "—"}</dd>

                                <dt class="col-sm-5 text-muted">Tempo de Ativação da Saída</dt>
                                <dd class="col-sm-7">${formatValue(data.leaveDetectionTime)} min</dd>

                                <dt class="col-sm-5 text-muted">Faixa de Detecção da Saída</dt>
                                <dd class="col-sm-7">${formatValue(data.leaveDetectionRange)}</dd>

                                <dt class="col-sm-5 text-muted">Monitoramento de Ausência Longa</dt>
                                <dd class="col-sm-7">${data.longAwaySwitch === "0" ? "ON" : data.longAwaySwitch === "1" ? "OFF" : "—"}</dd>

                                <dt class="col-sm-5 text-muted">Alarme de Detenção</dt>
                                <dd class="col-sm-7">${data.detentionAlarmSwitch === "0" ? "ON" : data.detentionAlarmSwitch === "1" ? "OFF" : "—"}</dd>

                                <dt class="col-sm-5 text-muted">Tempo de Ativação da Detenção</dt>
                                <dd class="col-sm-7">${formatValue(data.entryDetectionTime)} min</dd>

                                <dt class="col-sm-5 text-muted">Sinais Vitais Fracos</dt>
                                <dd class="col-sm-7">${data.suddenDeathSwitch === "0" ? "ON" : data.suddenDeathSwitch === "1" ? "OFF" : "—"}</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <!-- Posture & Breath/Heart Card (full width if needed) -->
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-secondary-subtle text-secondary h5 mb-0">
                            Postura e Parâmetros de Sinais Vitais
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3">Parâmetros de Postura</h6>
                                    ${(() => {
                                        const p = parsePostureParams(
                                            data.postureParams,
                                        );
                                        return `
                                            <dl class="row small mb-0">
                                                <dt class="col-sm-5 text-muted">Tempo de Queda Suspeita</dt>
                                                <dd class="col-sm-7">${p.fallTime}</dd>
                                                <dt class="col-sm-5 text-muted">Funcionalidades ativas</dt>
                                                <dd class="col-sm-7">${p.bits}</dd>
                                                <dt class="col-sm-5 text-muted">Tempo de Alarme de Sentado</dt>
                                                <dd class="col-sm-7">${p.sitTime}</dd>
                                            </dl>
                                        `;
                                    })()}
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3">Faixa de Frequência Cardíaca e Respiratória</h6>
                                    ${(() => {
                                        if (!data.heart_breath_param)
                                            return "<p class='text-muted'>—</p>";
                                        const vals = data.heart_breath_param
                                            .replace(/[\[\]]/g, "")
                                            .split(",")
                                            .map(
                                                (v) => Number(v.trim()) & 0xff,
                                            ); // handle negative → unsigned
                                        if (vals.length < 7)
                                            return "<p class='text-muted'>Formato inválido</p>";

                                        return `
                                            <dl class="row small mb-0">
                                                <dt class="col-sm-6 text-muted">Respiração Superior</dt>
                                                <dd class="col-sm-6">${vals[0]}</dd>
                                                <dt class="col-sm-6 text-muted">Frequência Cardíaca Superior</dt>
                                                <dd class="col-sm-6">${vals[1]}</dd>
                                                <dt class="col-sm-6 text-muted">Respiração Inferior</dt>
                                                <dd class="col-sm-6">${vals[2]}</dd>
                                                <dt class="col-sm-6 text-muted">Frequência Cardíaca Inferior</dt>
                                                <dd class="col-sm-6">${vals[3]}</dd>
                                                <dt class="col-sm-6 text-muted">Medição Contínua</dt>
                                                <dd class="col-sm-6">${vals[4] ? "LIGADO" : "DESLIGADO"}</dd>
                                                <dt class="col-sm-6 text-muted">Tempo de Ativação Fraco</dt>
                                                <dd class="col-sm-6">${vals[5]} min</dd>
                                                <dt class="col-sm-6 text-muted">Sensatividade</dt>
                                                <dd class="col-sm-6">${vals[6]}</dd>
                                            </dl>
                                        `;
                                    })()}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    infoTab.innerHTML = html;
};

/* ------------------------------------------------ */
/* MODAL EVENTS */
/* ------------------------------------------------ */

modal.addEventListener("shown.bs.modal", async (e) => {
    currUID = e.relatedTarget.dataset.id;
    if (!currUID) return;

    modal.dataset.id = currUID;

    try {
        renderLoading(container);

        const res = await sendRequest("deviceProp", { uid: currUID });

        if (!res?.data) return;

        modalTitle.innerHTML = `Detalhes do Radar - ${currUID}`;

        renderLiveMap(res.data);
        renderInfo(res.data);

    } catch (err) {
        console.error(err);
    } finally {
        removeLoading(container);
    }
});

modal.addEventListener("hidden.bs.modal", () => {
    currUID = null;
    delete modal.dataset.id;

    modalTitle.innerHTML = "Detalhes do Radar";
    infoTab.innerHTML = "";

    if (stage) stage.destroy();
});

window.addEventListener("resize", () => {
    if (!stage || !currUID) return;
    stage.width(container.offsetWidth);
    stage.height(container.offsetHeight);
});
