let root;
let chart;
let xAxis;
let yAxis;
let rangeSeries;
let eventSeries;

const CATEGORY = "Sono";

const processTimeInput = (timeStr, baseDate) => {
    const isNextDay = timeStr.includes("After");
    const cleanTime = timeStr.replace("After ", "");
    const [h, m] = cleanTime.split(":").map(Number);

    const date = new Date(baseDate);
    date.setHours(h, m, 0, 0);

    if (isNextDay) {
        date.setDate(date.getDate() + 1);
    }
    return date;
};

const formatDuration = (ms) => {
    const totalMinutes = Math.floor(ms / 60000);
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;

    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
};

const buildTimelineData = (getBed, sleepStart, sleepEnd, leaveBed) => {
    const latencyMs = sleepStart - getBed;
    const sleepMs = sleepEnd - sleepStart;

    return [
        {
            category: CATEGORY,
            start: getBed.getTime(),
            end: sleepStart.getTime(),
            color: am5.color(0x6c757d),
            label: `Latência (${formatDuration(latencyMs)})`,
        },
        {
            category: CATEGORY,
            start: sleepStart.getTime(),
            end: sleepEnd.getTime(),
            color: am5.color(0x0d6efd),
            label: `Sono (${formatDuration(sleepMs)})`,
        },
        {
            category: CATEGORY,
            start: sleepEnd.getTime(),
            end: leaveBed.getTime(),
            color: am5.color(0xffc107),
            label: "Acordado",
        },
    ];
};

export function initSleepTimelineChart(containerId = "timeline-sleep-chart") {
    if (root) root.dispose();

    root = am5.Root.new(containerId);
    root._logo?.dispose();

    // Use a standard XYChart configured for horizontal bars (Gantt style)
    chart = root.container.children.push(
        am5xy.XYChart.new(root, {
            panX: true,
            wheelX: "panX",
            layout: root.verticalLayout,
            paddingLeft: 0,
        }),
    );

    /* --- Axes --- */

    xAxis = chart.xAxes.push(
        am5xy.DateAxis.new(root, {
            baseInterval: { timeUnit: "minute", count: 1 },
            startLocation: -0.1, // Adds half an interval of padding at the start
            endLocation: 0.1, // Adds half an interval of padding at the end
            extraMin: 0.01, // Adds 1% extra space to the left
            extraMax: 0.01, // Adds 1% extra space to the right
            renderer: am5xy.AxisRendererX.new(root, {
                minGridDistance: 70,
            }),
            dateFormats: {
                day: "HH:mm",
                hour: "HH:mm",
                minute: "HH:mm",
            },
            periodChangeDateFormats: {
                day: "HH:mm",
                hour: "HH:mm",
                minute: "HH:mm",
            },
        }),
    );

    yAxis = chart.yAxes.push(
        am5xy.CategoryAxis.new(root, {
            categoryField: "category",
            renderer: am5xy.AxisRendererY.new(root, {
                inversed: true, // Keeps the "Sono" row at the top
            }),
        }),
    );

    // Visual cleanup: Hide Y-axis labels and grid for a "single-bar" timeline look
    yAxis.get("renderer").labels.template.set("forceHidden", true);
    yAxis.get("renderer").grid.template.set("forceHidden", true);

    /* --- Range Series (The actual timeline bars) --- */

    rangeSeries = chart.series.push(
        am5xy.ColumnSeries.new(root, {
            xAxis,
            yAxis,
            openValueXField: "start",
            valueXField: "end",
            categoryYField: "category",
            sequencedInterpolation: true,
        }),
    );

    rangeSeries.columns.template.setAll({
        height: am5.percent(40), // Slimmer bar for a modern look
        cornerRadiusTL: 4,
        cornerRadiusTR: 4,
        cornerRadiusBL: 4,
        cornerRadiusBR: 4,
        strokeOpacity: 0,
        tooltipText:
            "{label}: [bold]{openValueX.formatDate('HH:mm')} - {valueX.formatDate('HH:mm')}[/]",
    });

    // Adapter for dynamic coloring
    rangeSeries.columns.template.adapters.add("fill", (fill, target) => {
        return target.dataItem.dataContext.color || fill;
    });

    // Label inside the bars
    rangeSeries.bullets.push(() => {
        return am5.Bullet.new(root, {
            locationX: 0.5,
            sprite: am5.Label.new(root, {
                text: "{label}",
                fill: am5.color(0xffffff),
                centerX: am5.p50,
                centerY: am5.p50,
                fontSize: 11,
                populateText: true,
            }),
        });
    });

    /* --- Event Series (Icons/Markers) --- */

    eventSeries = chart.series.push(
        am5xy.LineSeries.new(root, {
            xAxis,
            yAxis,
            valueXField: "time",
            categoryYField: "category",
            strokeOpacity: 0, // We only want the bullets
        }),
    );

    eventSeries.bullets.push(() => {
        const container = am5.Container.new(root, {
            centerY: am5.p50,
            centerX: am5.p50,
        });

        container.children.push(
            am5.Circle.new(root, {
                radius: 12,
                fill: am5.color(0xffffff),
                stroke: am5.color(0xe0e0e0),
                strokeWidth: 1,
                shadowColor: am5.color(0x000000),
                shadowBlur: 4,
                shadowOpacity: 0.1,
            }),
        );

        container.children.push(
            am5.Label.new(root, {
                text: "{icon}",
                populateText: true,
                fontFamily: '"Font Awesome 6 Free", "Font Awesome 5 Free"', // Ensure this matches your FA version
                fontWeight: "900", // Required for "Solid" icons
                centerX: am5.p50,
                centerY: am5.p50,
                fontSize: 14,
                fill: am5.color(0x495057), // Color the icon specifically
            }),
        );

        return am5.Bullet.new(root, {
            sprite: container,
        });
    });

    chart.set("cursor", am5xy.XYCursor.new(root, { behavior: "none" }));
}

export function updateSleepTimeline(
    getBedIdx,
    sleepStIdx,
    sleepEdIdx,
    leaveBedIdx,
) {
    if (!root) return;

    console.log({ getBedIdx, sleepStIdx, sleepEdIdx, leaveBedIdx });

    const baseDate = new Date();
    baseDate.setHours(18, 0, 0, 0);

    const getBed = processTimeInput(getBedIdx, baseDate);

    const ensureForward = (prevDate, currentStr, base) => {
        let current = processTimeInput(currentStr, base);
        if (current < prevDate) current.setDate(current.getDate() + 1);
        return current;
    };

    const sleepStart = ensureForward(getBed, sleepStIdx, baseDate);
    const sleepEnd = ensureForward(sleepStart, sleepEdIdx, baseDate);
    const leaveBed = ensureForward(sleepEnd, leaveBedIdx, baseDate);

    const timelineData = buildTimelineData(
        getBed,
        sleepStart,
        sleepEnd,
        leaveBed,
    );
    const eventData = [
        { category: CATEGORY, time: getBed.getTime(), icon: "\uf236" }, // bed
        { category: CATEGORY, time: sleepStart.getTime(), icon: "\uf186" }, // moon
        { category: CATEGORY, time: sleepEnd.getTime(), icon: "\uf185" }, // sun
        { category: CATEGORY, time: leaveBed.getTime(), icon: "\uf554" }, // walking
    ];

    yAxis.data.setAll([{ category: CATEGORY }]);
    rangeSeries.data.setAll(timelineData);
    eventSeries.data.setAll(eventData);

    rangeSeries.appear(1000);
    chart.appear(1000, 100);
}
