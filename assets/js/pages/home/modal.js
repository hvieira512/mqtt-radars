import { sendRequest } from "../../auth.js";
import { renderLoading, removeLoading } from "../../utils.js";

const modal = document.getElementById("radarModal");
const modalTitle = modal.querySelector(".modal-title");
const container = document.getElementById("radar-map");

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
            layer.add(
                new Konva.Text({
                    x: labelX - 60,
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

const renderInfo = (data) => {
    const infoTab = document.getElementById("info");
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

modal.addEventListener("shown.bs.modal", async (e) => {
    currUID = e.relatedTarget.dataset.id;
    if (!currUID) return;

    modal.dataset.id = currUID;

    try {
        renderLoading(container);
        const response = await sendRequest("deviceProp", { uid: currUID });
        if (!response || !response.data)
            return console.warn("No data for UID", currUID);

        modalTitle.innerHTML = `Detalhes do Radar - ${currUID}`;
        renderLiveMap(response.data);
        renderInfo(response.data);
    } catch (err) {
        console.error("Error fetching device properties:", err);
    } finally {
        removeLoading(container);
    }
});

modal.addEventListener("hidden.bs.modal", () => {
    currUID = null;
    delete modal.dataset.id;
    modalTitle.innerHTML = "Detalhes do Radar";
    if (!stage) return;
    stage.destroy();
    stage = null;
    layer = null;
});
