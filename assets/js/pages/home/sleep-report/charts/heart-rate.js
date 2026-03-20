let root, chart, xAxis, yAxis, series;

// ────────────────────────────────────────────────
// Constants & Config
// ────────────────────────────────────────────────
const ANOMALY_TYPES = {
    weakVitals: {
        shape: "circle",
        color: "#ffc107",
        label: "Weak Vital Signs",
    },
    polycardia: { shape: "rectangle", color: "#EB8142", label: "Polycardia" },
    bradycardia: { shape: "triangle", color: "#dc3545", label: "Bradycardia" },
};

const SHAPE_SIZES = {
    circle: { radius: 6 },
    rectangle: { width: 9, height: 9, cornerRadius: 2 },
    triangle: { width: 11, height: 11, rotation: 180 }, // point up
};

const THRESHOLDS = [50, 90]; // beats per minute reference lines

// ────────────────────────────────────────────────
// Initialization (called once)
// ────────────────────────────────────────────────
export const initHeartRateChart = () => {
    root = am5.Root.new("heart-rate-chart");
    root._logo?.dispose();

    chart = root.container.children.push(
        am5xy.XYChart.new(root, {
            panX: true,
            panY: false,
            wheelX: "panX",
            wheelY: "zoomX",
            cursor: am5xy.XYCursor.new(root, { behavior: "zoomX" }),
        }),
    );

    chart.get("cursor").lineX.setAll({
        stroke: am5.color("#888888"),
        strokeOpacity: 0.4,
        strokeWidth: 1,
        strokeDasharray: [4, 4],
    });

    // X Axis – time categories
    xAxis = chart.xAxes.push(
        am5xy.CategoryAxis.new(root, {
            categoryField: "time",
            renderer: am5xy.AxisRendererX.new(root, {}),
        }),
    );

    // Y Axis – heart rate
    yAxis = chart.yAxes.push(
        am5xy.ValueAxis.new(root, {
            min: 0,
            max: 180,
            strictMinMax: true,
            maxPrecision: 0,
            renderer: am5xy.AxisRendererY.new(root, { minGridDistance: 40 }),
        }),
    );

    yAxis.get("renderer").labels.template.setAll({
        textAlign: "right",
        fontSize: 12,
        fill: am5.color("#90ee90"), // light green
    });

    // Main heart rate line series
    series = chart.series.push(
        am5xy.LineSeries.new(root, {
            name: "Heart Rate",
            xAxis,
            yAxis,
            valueYField: "value",
            categoryXField: "time",
            strokeWidth: 2,
            stroke: am5.color("#90ee90"), // ← line color
            connect: false,
            tooltip: am5.Tooltip.new(root, {
                labelText: "{categoryX}\n[bold]{valueY} BPM[/]",
                background: am5.Rectangle.new(root, {
                    fill: am5.color("#90ee90"), // ← tooltip background
                    fillOpacity: 0.2,
                    stroke: am5.color("#90ee90"), // optional border
                }),
                label: am5.Label.new(root, {
                    fill: am5.color("#90ee90"), // ← tooltip text
                    fontSize: 12,
                }),
            }),
        }),
    );

    // Threshold lines
    THRESHOLDS.forEach((value) => {
        const rangeDataItem = yAxis.makeDataItem({ value, endValue: value });
        const range = yAxis.createAxisRange(rangeDataItem);
        range
            .get("grid")
            .setAll({ strokeOpacity: 0.6, strokeDasharray: [4, 4] });
        range
            .get("label")
            .setAll({ text: value.toString(), location: 1, centerX: am5.p100 });
    });

    // Bullet configuration
    series.bullets.push((root, series, dataItem) => {
        const anomaly = dataItem.dataContext?.anomaly;
        if (!anomaly || !ANOMALY_TYPES[anomaly]) return undefined;

        const config = ANOMALY_TYPES[anomaly];
        const sizeConfig = SHAPE_SIZES[config.shape];
        let shape;

        if (config.shape === "circle")
            shape = am5.Circle.new(root, { ...sizeConfig });
        else if (config.shape === "rectangle")
            shape = am5.Rectangle.new(root, { ...sizeConfig });
        else if (config.shape === "triangle")
            shape = am5.Triangle.new(root, { ...sizeConfig });
        if (!shape) return undefined;

        shape.setAll({
            fill: am5.color(config.color),
            fillOpacity: 0.95,
            stroke: am5.color("#ffffff"),
            strokeWidth: 1.5,
            shadowOpacity: 0.25,
            shadowBlur: 4,
            shadowOffsetY: 2,
        });

        return am5.Bullet.new(root, { sprite: shape, locationY: 0 });
    });

    return { root, chart, series, xAxis };
};

// ────────────────────────────────────────────────
// Data Update
// ────────────────────────────────────────────────
export const updateHeartRateChart = (data) => {
    const values = data.heartRateVo?.dataList ?? [];
    const timestamps = data.timestamps ?? [];

    if (values.length !== timestamps.length) {
        console.warn(
            "Heart rate data length mismatch between values and timestamps",
        );
    }

    let chartData = [];
    let weakVitalsEvents = 0;
    let polycardiaEvents = 0;
    let bradycardiaEvents = 0;
    let prevValidValue = null;

    let inBradycardia = false;

    for (let i = 0; i < values.length; i++) {
        const raw = Number(values[i]);
        const time = timestamps[i];
        const value = raw === -1 ? null : raw;
        let anomaly = null;

        if (raw !== -1) {
            // Priority: Weak Vitals > Bradycardia > Polycardia

            if (raw === 0) {
                anomaly = "weakVitals";
                weakVitalsEvents++;
                inBradycardia = false;
            } else if (
                !inBradycardia &&
                prevValidValue !== null &&
                prevValidValue > 50 &&
                raw <= 50
            ) {
                anomaly = "bradycardia";
                bradycardiaEvents++;
                inBradycardia = true;
            } else if (inBradycardia && raw > 50) {
                inBradycardia = false;
            } else if (raw > 90) {
                anomaly = "polycardia";
                polycardiaEvents++;
                inBradycardia = false;
            }

            prevValidValue = raw;
        } else {
            inBradycardia = false;
        }

        chartData.push({ time, value, anomaly });
    }

    xAxis.data.setAll(chartData);
    series.data.setAll(chartData);

    return {
        weakVitalsEvents,
        bradycardiaEvents,
        polycardiaEvents,
    };
};
