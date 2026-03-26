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

let chartHR = null, chartBR = null;
let hrSeries = null, brSeries = null;
let hrAxisX = null, brAxisX = null;
let rootHR = null, rootBR = null;
let hrData = [], brData = [];
let hrStats = { min: null, max: null, sum: 0, count: 0 };
let brStats = { min: null, max: null, sum: 0, count: 0 };

const sleepStateMap = {
    Undefined: {
        label: "Indefinido",
        icon: "fa-question-circle",
        gradient: "linear-gradient(135deg, #6c757d 0%, #495057 100%)",
    },
    "Light Sleep": { 
        label: "Sono Leve", 
        icon: "fa-bed", 
        gradient: "linear-gradient(135deg, #0dcaf0 0%, #0aa2c0 100%)",
    },
    "Deep Sleep": {
        label: "Sono Profundo",
        icon: "fa-moon",
        gradient: "linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)",
    },
    Awake: { 
        label: "Acordado", 
        icon: "fa-eye",
        gradient: "linear-gradient(135deg, #22c55e 0%, #16a34a 100%)",
    },
};

function createChart(containerId, color) {
    const container = document.getElementById(containerId);
    if (!container) return null;
    
    if (container.__am5root) {
        container.__am5root.dispose();
    }
    container.innerHTML = '';
    
    const root = am5.Root.new(containerId);
    container.__am5root = root;
    root._logo?.dispose();
    root.setThemes([am5themes_Animated.new(root)]);

    const chart = root.container.children.push(
        am5xy.XYChart.new(root, {
            layout: root.verticalLayout,
            paddingLeft: 5,
            paddingRight: 5,
            paddingTop: 5,
            paddingBottom: 5,
        }),
    );

    const xAxis = chart.xAxes.push(
        am5xy.DateAxis.new(root, {
            baseInterval: { timeUnit: "second", count: 1 },
            renderer: am5xy.AxisRendererX.new(root, {
                visible: false,
                minGridDistance: 0,
            }),
            tooltipLocation: 0,
        }),
    );

    const yAxis = chart.yAxes.push(
        am5xy.ValueAxis.new(root, {
            renderer: am5xy.AxisRendererY.new(root, {
                minGridDistance: 30,
                strokeOpacity: 0.1,
            }),
            labels: {
                template: {
                    maxWidth: 35,
                    text: "{value}",
                },
            },
        }),
    );

    const series = chart.series.push(
        am5xy.SmoothedXLineSeries.new(root, {
            xAxis: xAxis,
            yAxis: yAxis,
            valueYField: "value",
            valueXField: "time",
            stroke: am5.color(color),
            strokeWidth: 2,
            tensionX: 0.8,
            fill: am5.color(color),
            tooltip: am5.Tooltip.new(root, {
                labelText: "{valueY}",
            }),
        }),
    );

    series.fills.template.setAll({
        visible: true,
        fillOpacity: 0.2,
    });

    series.bullets.push(function() {
        const bullet = am5.Bullet.new(root, {
            locationX: 1,
            sprite: am5.Circle.new(root, {
                radius: 5,
                fill: am5.color(color),
                strokeWidth: 2,
                stroke: "#fff",
            }),
        });
        return bullet;
    });

    return { chart, series, xAxis, root };
}

function calculateStats(data, stats) {
    if (data.length === 0) return stats;
    
    const values = data.map(d => d.value).filter(v => v > 0);
    if (values.length === 0) return stats;

    stats.min = Math.min(...values);
    stats.max = Math.max(...values);
    stats.sum = values.reduce((a, b) => a + b, 0);
    stats.count = values.length;

    return stats;
}

function getTrendIcon(data) {
    if (data.length < 3) return '<i class="fas fa-horizontal-rule text-muted"></i>';
    
    const recent = data.slice(-5);
    if (recent.length < 2) return '<i class="fas fa-horizontal-rule text-muted"></i>';
    
    const first = recent[0].value;
    const last = recent[recent.length - 1].value;
    const diff = last - first;
    
    if (Math.abs(diff) < 2) return '<i class="fas fa-horizontal-rule text-muted"></i>';
    if (diff > 0) return '<i class="fas fa-arrow-up text-success"></i>';
    return '<i class="fas fa-arrow-down text-danger"></i>';
}

export function renderVitals(uid, vitals) {
    const container = document.getElementById("liveRadarInfo");
    const hrContainer = document.getElementById("chart-heart-rate");
    const brContainer = document.getElementById("chart-breath-rate");
    const hrValue = document.getElementById("heart-rate-value");
    const brValue = document.getElementById("breath-rate-value");

    if (!container) return;
    if (container.closest("#radarModal").dataset.id !== uid) return;

    const now = new Date().getTime();
    const currentHR = vitals.heart_rate ?? 0;
    const currentBR = vitals.breathing ?? 0;

    if (hrValue) hrValue.textContent = currentHR > 0 ? currentHR : "--";
    if (brValue) brValue.textContent = currentBR > 0 ? currentBR : "--";

    if (!chartHR && hrContainer) {
        const hrChart = createChart("chart-heart-rate", 0xe74c3c);
        if (hrChart) {
            chartHR = hrChart.chart;
            hrSeries = hrChart.series;
            hrAxisX = hrChart.xAxis;
            rootHR = hrChart.root;
            hrSeries.data.setAll([]);
        }
    }

    if (!chartBR && brContainer) {
        const brChart = createChart("chart-breath-rate", 0x3498db);
        if (brChart) {
            chartBR = brChart.chart;
            brSeries = brChart.series;
            brAxisX = brChart.xAxis;
            rootBR = brChart.root;
            brSeries.data.setAll([]);
        }
    }

    if (!chartHR || !chartBR) return;

    hrData.push({ time: now, value: currentHR });
    brData.push({ time: now, value: currentBR });

    if (hrData.length > 60) hrData.shift();
    if (brData.length > 60) brData.shift();

    hrSeries.data.setAll([...hrData]);
    brSeries.data.setAll([...brData]);

    if (hrAxisX) {
        hrAxisX.set("start", 0.85);
        hrAxisX.set("end", 1);
    }
    if (brAxisX) {
        brAxisX.set("start", 0.85);
        brAxisX.set("end", 1);
    }

    calculateStats(hrData, hrStats);
    calculateStats(brData, brStats);

    const hrMin = document.getElementById("heart-rate-min");
    const hrAvg = document.getElementById("heart-rate-avg");
    const hrMax = document.getElementById("heart-rate-max");
    const hrTrend = document.getElementById("heart-rate-trend");

    if (hrMin) hrMin.textContent = hrStats.min ?? "--";
    if (hrAvg) hrAvg.textContent = hrStats.count > 0 ? Math.round(hrStats.sum / hrStats.count) : "--";
    if (hrMax) hrMax.textContent = hrStats.max ?? "--";
    if (hrTrend) hrTrend.innerHTML = getTrendIcon(hrData);

    const brMin = document.getElementById("breath-rate-min");
    const brAvg = document.getElementById("breath-rate-avg");
    const brMax = document.getElementById("breath-rate-max");
    const brTrend = document.getElementById("breath-rate-trend");

    if (brMin) brMin.textContent = brStats.min ?? "--";
    if (brAvg) brAvg.textContent = brStats.count > 0 ? Math.round(brStats.sum / brStats.count) : "--";
    if (brMax) brMax.textContent = brStats.max ?? "--";
    if (brTrend) brTrend.innerHTML = getTrendIcon(brData);

    const sleepContainer = document.getElementById("sleep-state-container");
    const state = sleepStateMap[vitals.sleep_state] ?? sleepStateMap["Undefined"];

    if (sleepContainer) {
        sleepContainer.style.background = state.gradient;
        const label = document.getElementById("sleep-state-label");
        if (label) label.textContent = state.label;
        const icon = document.querySelector("#sleep-state i");
        if (icon) icon.className = `fa-solid ${state.icon}`;
    }
}

export function resetVitalsCharts() {
    const hrContainer = document.getElementById("chart-heart-rate");
    const brContainer = document.getElementById("chart-breath-rate");

    if (chartHR) {
        chartHR.dispose();
        chartHR = null;
        hrSeries = null;
        hrAxisX = null;
        rootHR = null;
    }
    if (chartBR) {
        chartBR.dispose();
        chartBR = null;
        brSeries = null;
        brAxisX = null;
        rootBR = null;
    }
    if (hrContainer) {
        hrContainer.innerHTML = '';
        hrContainer.__am5root = null;
    }
    if (brContainer) {
        brContainer.innerHTML = '';
        brContainer.__am5root = null;
    }
    hrData = [];
    brData = [];
    hrStats = { min: null, max: null, sum: 0, count: 0 };
    brStats = { min: null, max: null, sum: 0, count: 0 };
}
