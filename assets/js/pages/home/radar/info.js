export function renderRadarInfo(container, data) {
    if (!container) return;
    container.innerHTML = "";

    const formatValue = (val) =>
        val === undefined || val === null || val === "" ? "—" : val;
    const getWorkingMode = (mode) =>
        ({
            15: "Monitorização de Cama",
            11: "Respiração e Sono",
            7: "Monitorização de Queda",
            3: "Rastreamento de Pessoas",
        })[mode] || `Desconhecido (${mode})`;

    const getSignalStrength = (val) => {
        if (!val || val === "-") return "—";
        const num = Number(val);
        if (isNaN(num)) return val;
        if (val.includes("CSQ"))
            return num >= 23
                ? `${val} — Forte`
                : num >= 15
                  ? `${val} — Médio`
                  : `${val} — Fraco`;
        if (num <= -100) return `${val} — Sem Sinal`;
        if (num > -100 && num <= -88) return `${val} — Fraco`;
        if (num > -88 && num <= -66) return `${val} — OK`;
        if (num > -66 && num <= -55) return `${val} — Bom`;
        return `${val} — Forte`;
    };

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

    // Parse heart & breath param safely
    const parseHeartBreath = (str) => {
        if (!str) return null;
        const vals = str
            .replace(/[\[\]]/g, "")
            .split(",")
            .map((v) => Number(v.trim()) & 0xff);
        return vals.length >= 7 ? vals : null;
    };

    const posture = parsePostureParams(data.postureParams);
    const hb = parseHeartBreath(data.heart_breath_param);

    container.innerHTML = `
    <div class="container-fluid py-3">
        <div class="row g-4">

            <!-- Radar Settings -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-primary-subtle text-primary h5 mb-0">Configuração do Radar</div>
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

            <!-- Alarm & Detection -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-info-subtle text-info h5 mb-0">Alarmes e Deteção</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-muted">Tempo de Queda Suspeita</dt>
                            <dd class="col-sm-7">${formatValue(data.suspected_fall_time)} × 10s</dd>

                            <dt class="col-sm-5 text-muted">Alarme de Saída da Cama</dt>
                            <dd class="col-sm-7">${data.leaveAlarmSwitch === "0" ? "LIGADO" : data.leaveAlarmSwitch === "1" ? "DESLIGADO" : "—"}</dd>

                            <dt class="col-sm-5 text-muted">Tempo de Ativação da Saída</dt>
                            <dd class="col-sm-7">${formatValue(data.leaveDetectionTime)} min</dd>

                            <dt class="col-sm-5 text-muted">Faixa de Detecção da Saída</dt>
                            <dd class="col-sm-7">${formatValue(data.leaveDetectionRange)}</dd>

                            <dt class="col-sm-5 text-muted">Monitoramento de Ausência Longa</dt>
                            <dd class="col-sm-7">${data.longAwaySwitch === "0" ? "LIGADO" : data.longAwaySwitch === "1" ? "DESLIGADO" : "—"}</dd>

                            <dt class="col-sm-5 text-muted">Alarme de Detenção</dt>
                            <dd class="col-sm-7">${data.detentionAlarmSwitch === "0" ? "LIGADO" : data.detentionAlarmSwitch === "1" ? "DESLIGADO" : "—"}</dd>

                            <dt class="col-sm-5 text-muted">Tempo de Ativação da Detenção</dt>
                            <dd class="col-sm-7">${formatValue(data.entryDetectionTime)} min</dd>

                            <dt class="col-sm-5 text-muted">Sinais Vitais Fracos</dt>
                            <dd class="col-sm-7">${data.suddenDeathSwitch === "0" ? "LIGADO" : data.suddenDeathSwitch === "1" ? "DESLIGADO" : "—"}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Posture & Heart/Breath -->
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-secondary-subtle text-secondary h5 mb-0">Postura e Parâmetros de Sinais Vitais</div>
                    <div class="card-body">
                        <div class="row g-4">

                            <!-- Posture -->
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Parâmetros de Postura</h6>
                                <dl class="row small mb-0">
                                    <dt class="col-sm-5 text-muted">Tempo de Queda Suspeita</dt>
                                    <dd class="col-sm-7">${posture.fallTime}</dd>
                                    <dt class="col-sm-5 text-muted">Funcionalidades ativas</dt>
                                    <dd class="col-sm-7">${posture.bits}</dd>
                                    <dt class="col-sm-5 text-muted">Tempo de Alarme de Sentado</dt>
                                    <dd class="col-sm-7">${posture.sitTime}</dd>
                                </dl>
                            </div>

                            <!-- Heart & Breath -->
                            <div class="col-md-6">
                                <h6 class="fw-bold mb-3">Faixa de Frequência Cardíaca e Respiratória</h6>
                                ${
                                    hb
                                        ? `
                                <dl class="row small mb-0">
                                    <dt class="col-sm-6 text-muted">Respiração Superior</dt><dd class="col-sm-6">${hb[0]}</dd>
                                    <dt class="col-sm-6 text-muted">Frequência Cardíaca Superior</dt><dd class="col-sm-6">${hb[1]}</dd>
                                    <dt class="col-sm-6 text-muted">Respiração Inferior</dt><dd class="col-sm-6">${hb[2]}</dd>
                                    <dt class="col-sm-6 text-muted">Frequência Cardíaca Inferior</dt><dd class="col-sm-6">${hb[3]}</dd>
                                    <dt class="col-sm-6 text-muted">Medição Contínua</dt><dd class="col-sm-6">${hb[4] ? "LIGADO" : "DESLIGADO"}</dd>
                                    <dt class="col-sm-6 text-muted">Tempo de Ativação Fraco</dt><dd class="col-sm-6">${hb[5]} min</dd>
                                    <dt class="col-sm-6 text-muted">Sensatividade</dt><dd class="col-sm-6">${hb[6]}</dd>
                                </dl>`
                                        : "<p class='text-muted'>—</p>"
                                }
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    `;
}

let chartHR, chartBR, hrSeries, brSeries;

const sleepStateMap = {
    Undefined: {
        label: "Indefinido",
        icon: "fa-question-circle",
        class: "text-secondary",
    },
    "Light Sleep": { label: "Sono Leve", icon: "fa-bed", class: "text-info" },
    "Deep Sleep": {
        label: "Sono Profundo",
        icon: "fa-moon",
        class: "text-primary",
    },
    Awake: { label: "Acordado", icon: "fa-eye", class: "text-success" },
};

export function renderVitals(uid, vitals) {
    const container = document.getElementById("liveRadarInfo");

    if (!container || container.closest("#radarModal").dataset.id !== uid)
        return;

    const now = new Date().getTime();

    // Initialize Heart Rate chart
    if (!chartHR) {
        const heartRateGraph = am5.Root.new("chart-heart-rate");
        heartRateGraph._logo?.dispose();
        heartRateGraph.setThemes([am5themes_Animated.new(heartRateGraph)]);
        chartHR = heartRateGraph.container.children.push(
            am5xy.XYChart.new(heartRateGraph, {
                layout: heartRateGraph.verticalLayout,
            }),
        );

        const xAxisHR = chartHR.xAxes.push(
            am5xy.DateAxis.new(heartRateGraph, {
                baseInterval: { timeUnit: "second", count: 1 },
                renderer: am5xy.AxisRendererX.new(heartRateGraph, {
                    visible: false,
                }),
            }),
        );

        const yAxisHR = chartHR.yAxes.push(
            am5xy.ValueAxis.new(heartRateGraph, {
                min: 40,
                max: 180,
                strictMinMax: true,
                renderer: am5xy.AxisRendererY.new(heartRateGraph, {
                    labels: { fill: am5.color(0xff0000), fontSize: 12 },
                    strokeOpacity: 0.3,
                }),
            }),
        );

        hrSeries = chartHR.series.push(
            am5xy.LineSeries.new(heartRateGraph, {
                name: "Heart Rate",
                xAxis: xAxisHR,
                yAxis: yAxisHR,
                valueYField: "value",
                valueXField: "time",
                stroke: am5.color(0xff0000),
                strokeWidth: 2,
                tensionX: 0.7,
                tooltip: am5.Tooltip.new(heartRateGraph, {
                    labelText: "{valueY}",
                }),
            }),
        );

        hrSeries.data.setAll([]);
    }

    // Initialize Breath Rate chart
    if (!chartBR) {
        const breathRateGraph = am5.Root.new("chart-breath-rate");
        breathRateGraph._logo?.dispose();
        breathRateGraph.setThemes([am5themes_Animated.new(breathRateGraph)]);
        chartBR = breathRateGraph.container.children.push(
            am5xy.XYChart.new(breathRateGraph, {
                layout: breathRateGraph.verticalLayout,
            }),
        );

        const xAxisBR = chartBR.xAxes.push(
            am5xy.DateAxis.new(breathRateGraph, {
                baseInterval: { timeUnit: "second", count: 1 },
                renderer: am5xy.AxisRendererX.new(breathRateGraph, {
                    visible: false,
                }),
            }),
        );

        const yAxisBR = chartBR.yAxes.push(
            am5xy.ValueAxis.new(breathRateGraph, {
                min: 0,
                max: 40,
                strictMinMax: true,
                renderer: am5xy.AxisRendererY.new(breathRateGraph, {
                    labels: { fill: am5.color(0x00ffff), fontSize: 12 },
                    strokeOpacity: 0.3,
                }),
            }),
        );

        brSeries = chartBR.series.push(
            am5xy.LineSeries.new(breathRateGraph, {
                name: "Breath Rate",
                xAxis: xAxisBR,
                yAxis: yAxisBR,
                valueYField: "value",
                valueXField: "time",
                stroke: am5.color(0x00ffff),
                strokeWidth: 2,
                tensionX: 0.7,
                tooltip: am5.Tooltip.new(breathRateGraph, {
                    labelText: "{valueY}",
                }),
            }),
        );

        brSeries.data.setAll([]);
    }

    // Push new points
    hrSeries.data.push({ time: now, value: vitals.heart_rate ?? 0 });
    brSeries.data.push({ time: now, value: vitals.breathing ?? 0 });

    // Keep last 60 points
    if (hrSeries.dataItems.length > 60) hrSeries.data.removeIndex(0);
    if (brSeries.dataItems.length > 60) brSeries.data.removeIndex(0);

    const sleepContainer = document.getElementById("sleep-state");
    if (!sleepContainer) return;
    const state =
        sleepStateMap[vitals.sleep_state] ?? sleepStateMap["Undefined"];

    sleepContainer.innerHTML = `
        <span class="d-flex align-items-center justify-content-center gap-2 fw-bold ${state.class}" style="font-size:1.1rem;">
            <i class="fa-solid ${state.icon}"></i> ${state.label}
        </span>
    `;
}
