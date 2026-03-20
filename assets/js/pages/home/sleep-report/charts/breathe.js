let root, chart, xAxis, yAxis, series;

// ────────────────────────────────────────────────
// Constants & Config
// ────────────────────────────────────────────────
const ANOMALY_TYPES = {
    apnea: { shape: "circle", color: "#ffc107", label: "Apnea" }, // warning yellow
    bradypnea: { shape: "rectangle", color: "#EB8142", label: "Bradypnea" },
    tachypnea: { shape: "triangle", color: "#dc3545", label: "Tachypnea" }, // danger red
};

const SHAPE_SIZES = {
    circle: { radius: 6 },
    rectangle: { width: 9, height: 9, cornerRadius: 2 },
    triangle: { width: 11, height: 11, rotation: 180 }, // point up
};

const THRESHOLDS = [8, 24];

// ────────────────────────────────────────────────
// Initialization (called once)
// ────────────────────────────────────────────────
export const initBreatheChart = () => {
    root = am5.Root.new("breathe-chart");
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

    // Optional: make cursor line visible & styled
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

    // Y Axis – breaths per minute
    yAxis = chart.yAxes.push(
        am5xy.ValueAxis.new(root, {
            min: 0,
            max: 32,
            strictMinMax: true,
            maxPrecision: 0,
            renderer: am5xy.AxisRendererY.new(root, {
                minGridDistance: 40, // adjust if too many/few labels appear
            }),
        }),
    );

    yAxis.get("renderer").labels.template.setAll({
        textAlign: "right",
        fontSize: 12,
        fill: am5.color("#67b7dc"),
    });

    // Main breath rate line series
    series = chart.series.push(
        am5xy.LineSeries.new(root, {
            name: "Breath Rate",
            xAxis,
            yAxis,
            valueYField: "value",
            categoryXField: "time",
            strokeWidth: 2,
            connect: false,
            tooltip: am5.Tooltip.new(root, {
                labelText: "{categoryX}\n[bold]{valueY} BPM[/]", // ← the format you want
            }),
        }),
    );

    // If you prefer setting tooltipText on the series template instead of tooltip object:
    // series.set("tooltipText", "{categoryX}\n{valueY} BPM");

    // Threshold lines (unchanged)
    THRESHOLDS.forEach((value) => {
        const rangeDataItem = yAxis.makeDataItem({ value, endValue: value });
        const range = yAxis.createAxisRange(rangeDataItem);
        range.get("grid").setAll({
            strokeOpacity: 0.6,
            strokeDasharray: [4, 4],
        });
        range.get("label").setAll({
            text: value.toString(),
            location: 1,
            centerX: am5.p100,
        });
    });

    // Bullet configuration (unchanged – your existing code)
    series.bullets.push((root, series, dataItem) => {
        const anomaly = dataItem.dataContext?.anomaly;
        if (!anomaly || !ANOMALY_TYPES[anomaly]) return undefined;
        const config = ANOMALY_TYPES[anomaly];
        const sizeConfig = SHAPE_SIZES[config.shape];
        let shape;
        if (config.shape === "circle") {
            shape = am5.Circle.new(root, { ...sizeConfig });
        } else if (config.shape === "rectangle") {
            shape = am5.Rectangle.new(root, { ...sizeConfig });
        } else if (config.shape === "triangle") {
            shape = am5.Triangle.new(root, { ...sizeConfig });
        }
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
        return am5.Bullet.new(root, {
            sprite: shape,
            locationY: 0,
        });
    });

    return { root, chart, series, xAxis };
};

// ────────────────────────────────────────────────
// Data Update
// ────────────────────────────────────────────────
export const updateBreatheChart = (data) => {
    const values = data.breathRateVo?.dataList ?? [];
    const timestamps = data.timestamps ?? [];

    if (values.length !== timestamps.length) {
        console.warn(
            "Breath data length mismatch between values and timestamps",
        );
    }

    let chartData = [];
    let bradypneaEvents = 0;
    let apneaEvents = 0;
    let tachypneaEvents = 0;
    let prevValidValue = null;

    // Track if we are currently in a bradypnea episode
    let inBradypnea = false;

    for (let i = 0; i < values.length; i++) {
        const raw = Number(values[i]);
        const time = timestamps[i];

        const value = raw === -1 ? null : raw;
        let anomaly = null;

        if (raw !== -1) {
            if (raw === 0) {
                anomaly = "apnea";
                apneaEvents++;
                inBradypnea = false;
            } else if (
                !inBradypnea &&
                prevValidValue !== null &&
                prevValidValue > 8 &&
                raw <= 8
            ) {
                anomaly = "bradypnea";
                bradypneaEvents++;
                inBradypnea = true;
            } else if (inBradypnea && raw > 8) {
                inBradypnea = false;
            } else if (raw > 24) {
                anomaly = "tachypnea";
                tachypneaEvents++;
                inBradypnea = false; // break any ongoing bradypnea episode
            }

            prevValidValue = raw;
        } else {
            // Missing data breaks bradypnea episodes
            inBradypnea = false;
        }

        chartData.push({ time, value, anomaly });
    }

    xAxis.data.setAll(chartData);
    series.data.setAll(chartData);

    return {
        bradypneaEvents,
        apneaEvents,
        tachypneaEvents,
    };
};
