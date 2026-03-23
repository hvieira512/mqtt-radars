const SLEEP_STATUS = {
    1: { label: "Sono leve", color: 0x80c0ff },
    2: { label: "Acordado", color: 0xc0c0c0 },
    3: { label: "Sono profundo", color: 0x003366 },
    7: { label: "REM", color: 0xff9933 },
};

const STATUS_TO_Y = {
    2: 3, // Acordado → top
    1: 2, // Sono leve → middle-upper
    7: 1, // REM → middle-lower
    3: 0, // Sono profundo → bottom
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
            renderer: am5xy.AxisRendererX.new(root, {
                minGridDistance: 50, // prevent too dense labels
            }),
            tooltip: am5.Tooltip.new(root, {
                labelText: "{valueX.formatDate('HH:mm')}", // axis tooltip shows time
            }),
        }),
    );

    // Make X-axis labels visible and nicely formatted
    xAxis.get("renderer").labels.template.setAll({
        fontSize: "0.85em",
        rotation: -45,
        centerY: am5.p50,
        textAlign: "right",
        paddingRight: 8,
        paddingLeft: 8,
        maxPosition: 0.98, // prevent last label cutoff
        minPosition: 0.02,
    });

    // Format labels: e.g. 22:30, 23:00, etc.
    xAxis
        .get("renderer")
        .labels.template.adapters.add("text", function (text, target) {
            return target.dataItem?.value
                ? am5.time.formatDate(new Date(target.dataItem.value), "HH:mm")
                : text;
        });

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

    yAxis.get("renderer").labels.template.set("forceHidden", true);

    const stageLabels = [
        { y: 0.5, text: "Sono profundo", color: 0x002244 },
        { y: 1.5, text: "REM", color: 0xcc7700 },
        { y: 2.5, text: "Sono leve", color: 0x4070cc },
        { y: 3.5, text: "Acordado", color: 0x555555 },
    ];

    stageLabels.forEach(({ y, text, color }) => {
        const range = yAxis.createAxisRange(yAxis.makeDataItem({ value: y }));
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
        }),
    );

    sleepSeries.columns.template.setAll({
        strokeOpacity: 0,
        cornerRadiusTL: 0,
        cornerRadiusTR: 0,
        cornerRadiusBL: 0,
        cornerRadiusBR: 0,
        tooltipY: am5.percent(50), // center tooltip vertically
    });

    sleepSeries.columns.template.set("tooltipText", "");

    sleepSeries.columns.template.adapters.add(
        "tooltipText",
        function (_, target) {
            const dc = target.dataItem?.dataContext;
            if (!dc) return "";

            const start = new Date(dc.startTime);
            const end = new Date(dc.endTime);

            const formatTime = (date) => {
                const hours = String(date.getHours()).padStart(2, "0");
                const minutes = String(date.getMinutes()).padStart(2, "0");
                return `${hours}:${minutes}`;
            };

            const startStr = formatTime(start);
            const endStr = formatTime(end);

            const durationMin = Math.round((dc.endTime - dc.startTime) / 60000);

            const hours = Math.floor(durationMin / 60);
            const minutes = durationMin % 60;

            const durationStr =
                hours > 0 ? `${hours}h ${minutes}min` : `${minutes}min`;

            const status = SLEEP_STATUS[dc.status]?.label || "Desconhecido";

            return `${startStr} - ${endStr} | ${status} - ${durationStr}`;
        },
    );

    // Colors from data
    sleepSeries.columns.template.adapters.add("fill", function (fill, target) {
        return target.dataItem?.dataContext?.fill ?? fill;
    });

    sleepSeries.columns.template.adapters.add(
        "stroke",
        function (stroke, target) {
            return target.dataItem?.dataContext?.fill ?? stroke;
        },
    );

    chartComponents = { root, chart, xAxis, yAxis };
    return chartComponents;
}

export function initSleepChart() {
    if (chartComponents) return;
    createSleepChart();
}

export function updateSleepChart(data) {
    if (!sleepSeries || !data?.length) return;

    const chartData = data.map((item) => {
        const status = item.status;
        const y = STATUS_TO_Y[status] ?? 0;
        const stage = SLEEP_STATUS[status] || { color: 0x999999 };

        return {
            status: status, // needed for tooltip
            startTime: new Date(item.startTime).getTime(),
            endTime: new Date(item.endTime).getTime(),
            y1: y,
            y2: y + 1,
            fill: am5.color(stage.color),
        };
    });

    sleepSeries.data.setAll(chartData);

    // Optional: zoom to data range if needed
    // chartComponents.chart.zoomToIndexes(0, chartData.length - 1, false);
}

export function disposeSleepChart() {
    if (chartComponents?.root) {
        chartComponents.root.dispose();
        chartComponents = null;
        sleepSeries = null;
    }
}
