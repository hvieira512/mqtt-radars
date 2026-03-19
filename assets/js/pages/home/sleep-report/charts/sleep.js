const SLEEP_STATUS = {
    1: { label: "Sono leve", color: 0x80c0ff },
    2: { label: "Acordado", color: 0xc0c0c0 },
    3: { label: "Sono profundo", color: 0x003366 },
    7: { label: "REM", color: 0xff9933 },
};

const STATUS_TO_Y = {
    2: 3, // Acordado     → top
    1: 2, // Light     → middle-upper
    7: 1, // REM       → middle-lower
    3: 0, // Deep      → bottom
};

let chartComponents = null;
let sleepSeries = null;

function createSleepChart() {
    const root = am5.Root.new("sleep-chart");
    root._logo?.dispose();

    const chart = root.container.children.push(
        am5xy.XYChart.new(root, {
            panX: false,
            panY: false,
            wheelX: "none",
            wheelY: "none",
            layout: root.verticalLayout,
        }),
    );

    // ─── X Axis (time) ───────────────────────────────────────
    const xAxis = chart.xAxes.push(
        am5xy.DateAxis.new(root, {
            baseInterval: { timeUnit: "minute", count: 1 },
            renderer: am5xy.AxisRendererX.new(root, {}),
            tooltip: am5.Tooltip.new(root, {}),
        }),
    );

    // ─── Y Axis (sleep stages) ───────────────────────────────
    const yAxis = chart.yAxes.push(
        am5xy.ValueAxis.new(root, {
            min: 0,
            max: 4,
            strictMinMax: true,
            renderer: am5xy.AxisRendererY.new(root, {
                minGridDistance: 40,
                strokeOpacity: 0.15,
            }),
        }),
    );

    // Hide default numeric labels
    yAxis.get("renderer").labels.template.set("forceHidden", true);

    // Add stage names as custom labels (centered in each band)
    const stageLabels = [
        { y: 0.5, text: "Sono profundo", color: 0x002244 },
        { y: 1.5, text: "REM", color: 0xcc7700 },
        { y: 2.5, text: "Sono leve", color: 0x4070cc },
        { y: 3.5, text: "Acordado", color: 0x555555 },
    ];

    stageLabels.forEach(({ y, text, color }) => {
        const rangeDataItem = yAxis.makeDataItem({ value: y });
        const range = yAxis.createAxisRange(rangeDataItem);

        range.get("label").setAll({
            text: text,
            fontSize: "0.95em",
            fontWeight: "500",
            fill: am5.color(color),
            centerY: am5.p50,
            textAlign: "right",
            paddingRight: 12,
            forceHidden: false,
        });

        // No extra grid/tick clutter
        range.get("grid").set("forceHidden", true);
        range.get("tick").set("forceHidden", true);
    });

    // ─── Column Series ───────────────────────────────────────
    sleepSeries = chart.series.push(
        am5xy.ColumnSeries.new(root, {
            xAxis: xAxis,
            yAxis: yAxis,
            openValueYField: "y1",
            valueYField: "y2",
            openValueXField: "startTime",
            valueXField: "endTime",
            categoryXField: null, // not needed
        }),
    );

    sleepSeries.columns.template.setAll({
        strokeOpacity: 0,
        cornerRadiusTL: 0,
        cornerRadiusTR: 0,
        cornerRadiusBL: 0,
        cornerRadiusBR: 0,
    });

    // Color from data
    sleepSeries.columns.template.adapters.add("fill", function (fill, target) {
        return target.dataItem?.dataContext?.fill ?? fill;
    });

    sleepSeries.columns.template.adapters.add(
        "stroke",
        function (stroke, target) {
            return target.dataItem?.dataContext?.fill ?? stroke;
        },
    );

    // Store for updates
    chartComponents = { root, chart, xAxis, yAxis };

    return chartComponents;
}

export function initSleepChart() {
    if (chartComponents) return; // already initialized
    createSleepChart();
}

export function updateSleepChart(data) {
    if (!sleepSeries || !data?.length) return;

    const chartData = data.map((item) => {
        const status = item.status;
        const y = STATUS_TO_Y[status] ?? 0;
        const stage = SLEEP_STATUS[status] || { color: 0x999999 };

        return {
            startTime: new Date(item.startTime).getTime(),
            endTime: new Date(item.endTime).getTime(),
            y1: y,
            y2: y + 1,
            fill: am5.color(stage.color),
        };
    });

    sleepSeries.data.setAll(chartData);
}

// Optional: dispose on page unload / component destroy
export function disposeSleepChart() {
    if (chartComponents?.root) {
        chartComponents.root.dispose();
        chartComponents = null;
        sleepSeries = null;
    }
}
