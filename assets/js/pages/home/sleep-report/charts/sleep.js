const SLEEP_STATUS_MAP = {
    1: "Light sleep",
    2: "Awake",
    3: "Deep sleep",
    7: "REM",
};

const SLEEP_STATUS_COLOR = {
    1: am5.color(0x80c0ff), // Light sleep - blue
    2: am5.color(0xc0c0c0), // Awake - gray
    3: am5.color(0x003366), // Deep sleep - dark blue
    7: am5.color(0xff9933), // REM - orange
};

let sleepChartRoot;
let sleepSeries;

export const initSleepChart = () => {
    // Initialize root
    sleepChartRoot = am5.Root.new("sleep-chart");
    sleepChartRoot._logo?.dispose();

    // Create chart
    const chart = sleepChartRoot.container.children.push(
        am5xy.XYChart.new(sleepChartRoot, {
            panX: false,
            panY: false,
            wheelX: "none",
            wheelY: "none",
            layout: sleepChartRoot.verticalLayout,
        }),
    );

    // X-axis: time
    const xAxis = chart.xAxes.push(
        am5xy.DateAxis.new(sleepChartRoot, {
            baseInterval: { timeUnit: "minute", count: 1 },
            renderer: am5xy.AxisRendererX.new(sleepChartRoot, {}),
            tooltip: am5.Tooltip.new(sleepChartRoot, {}),
        }),
    );

    // Y-axis: numeric stages
    const yAxis = chart.yAxes.push(
        am5xy.ValueAxis.new(sleepChartRoot, {
            min: 0,
            max: 4, // 4 stages: 1=Light,2=Awake,3=Deep,7=REM
            strictMinMax: true,
            renderer: am5xy.AxisRendererY.new(sleepChartRoot, {}),
        }),
    );

    // Column series
    sleepSeries = chart.series.push(
        am5xy.ColumnSeries.new(sleepChartRoot, {
            xAxis: xAxis,
            yAxis: yAxis,
            openValueYField: "y1",
            valueYField: "y2",
            openValueXField: "startTime",
            valueXField: "endTime",
        }),
    );

    // Column styling
    sleepSeries.columns.template.setAll({
        strokeOpacity: 0,
        cornerRadiusTL: 0,
        cornerRadiusTR: 0,
        cornerRadiusBL: 0,
        cornerRadiusBR: 0,
    });

    // Safe fill adapter
    sleepSeries.columns.template.adapters.add("fill", (fill, target) => {
        const dataItem = target.dataItem;
        return dataItem?.dataContext?.fill ?? fill;
    });

    // Safe stroke adapter
    sleepSeries.columns.template.adapters.add("stroke", (stroke, target) => {
        const dataItem = target.dataItem;
        return dataItem?.dataContext?.fill ?? stroke;
    });

    // Store references for later updates
    sleepChartRoot = { chart, xAxis, yAxis };
};

// Update chart with API data
export const updateSleepChart = (data) => {
    if (!sleepSeries) return;

    // Map status to numeric Y-values
    const STATUS_Y = { 2: 3, 1: 2, 7: 1, 3: 0 }; // Top to bottom

    const chartData = data.map((item) => {
        const yValue = STATUS_Y[item.status] ?? 0;
        return {
            startTime: new Date(item.startTime).getTime(),
            endTime: new Date(item.endTime).getTime(),
            y1: yValue, // bottom of column
            y2: yValue + 1, // top of column
            fill: SLEEP_STATUS_COLOR[item.status] || am5.color(0x999999),
        };
    });

    sleepSeries.data.setAll(chartData);
};
