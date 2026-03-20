let root;
let chart;
let xAxis;
let yAxis;
let rangeSeries;
let eventSeries;

const CATEGORY = "Sono";

const parseTime = (timeStr) => {
    if (timeStr.includes("After")) {
        return timeStr.replace("After ", "") + ":00";
    }
    return timeStr;
};

const toDate = (timeStr, baseDate, isNextDay = false) => {
    const [h, m, s] = timeStr.split(":").map(Number);
    const d = new Date(baseDate);
    d.setHours(h, m, s || 0);

    if (isNextDay) d.setDate(d.getDate() + 1);
    return d;
};

const buildTimelineData = (getBed, sleepStart, sleepEnd, leaveBed) => {
    const latencyMin = Math.round((sleepStart - getBed) / 60000);

    return [
        {
            category: CATEGORY,
            start: getBed.getTime(),
            end: sleepStart.getTime(),
            color: am5.color(0x6c757d),
            label: `Latência (${latencyMin} min)`,
        },
        {
            category: CATEGORY,
            start: sleepStart.getTime(),
            end: sleepEnd.getTime(),
            color: am5.color(0x0d6efd),
            label: "Sono",
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

const buildEventData = (getBed, sleepStart, sleepEnd, leaveBed) => [
    { category: CATEGORY, time: getBed.getTime(), icon: "🛏️" },
    { category: CATEGORY, time: sleepStart.getTime(), icon: "🌙" },
    { category: CATEGORY, time: sleepEnd.getTime(), icon: "🌅" },
    { category: CATEGORY, time: leaveBed.getTime(), icon: "🚶" },
];

export function initSleepTimelineChart(containerId = "timeline-sleep-chart") {
    if (root) root.dispose();

    root = am5.Root.new(containerId);
    root._logo?.dispose();

    chart = root.container.children.push(
        am5xy.XYChart.new(root, {
            panX: false,
            panY: false,
            layout: root.verticalLayout,
        }),
    );

    /* -------------------------- */
    /* Axes                       */
    /* -------------------------- */

    xAxis = chart.xAxes.push(
        am5xy.DateAxis.new(root, {
            baseInterval: { timeUnit: "minute", count: 1 },
            renderer: am5xy.AxisRendererX.new(root, {}),
        }),
    );

    yAxis = chart.yAxes.push(
        am5xy.CategoryAxis.new(root, {
            categoryField: "category",
            renderer: am5xy.AxisRendererY.new(root, {}),
        }),
    );

    // Clean look (important)
    yAxis.get("renderer").labels.template.set("forceHidden", true);
    yAxis.get("renderer").grid.template.set("forceHidden", true);

    /* -------------------------- */
    /* Range Series (Intervals)   */
    /* -------------------------- */

    rangeSeries = chart.series.push(
        am5xy.ColumnSeries.new(root, {
            xAxis,
            yAxis,
            openValueXField: "start",
            valueXField: "end",
            categoryYField: "category",
        }),
    );

    rangeSeries.columns.template.setAll({
        height: am5.percent(60),
        strokeOpacity: 0,
        tooltipText:
            "{label}\n{openValueX.formatDate('HH:mm')} - {valueX.formatDate('HH:mm')}",
    });

    // Color adapter
    rangeSeries.columns.template.adapters.add("fill", (_, target) => {
        return target.dataItem.dataContext.color;
    });

    rangeSeries.columns.template.adapters.add("stroke", (_, target) => {
        return target.dataItem.dataContext.color;
    });

    // Labels inside bars
    rangeSeries.bullets.push(() =>
        am5.Bullet.new(root, {
            locationX: 0.5,
            sprite: am5.Label.new(root, {
                text: "{label}",
                populateText: true,
                fill: am5.color(0xffffff),
                centerX: am5.p50,
                centerY: am5.p50,
                fontSize: 12,
            }),
        }),
    );

    /* -------------------------- */
    /* Event Series (Markers)     */
    /* -------------------------- */

    eventSeries = chart.series.push(
        am5xy.LineSeries.new(root, {
            xAxis,
            yAxis,
            valueXField: "time",
            categoryYField: "category",
            strokeOpacity: 0,
        }),
    );

    eventSeries.bullets.push(() =>
        am5.Bullet.new(root, {
            locationX: 0,
            sprite: am5.Container.new(root, {
                centerX: am5.p50,
                centerY: am5.p50,
                children: [
                    am5.Circle.new(root, {
                        radius: 7,
                        fill: am5.color(0xffffff),
                        stroke: am5.color(0x000000),
                        strokeWidth: 2,
                    }),
                    am5.Label.new(root, {
                        text: "{icon}",
                        centerX: am5.p50,
                        centerY: am5.p50,
                        fontSize: 12,
                    }),
                ],
            }),
        }),
    );
}

export function updateSleepTimeline(
    getBedIdx,
    sleepStIdx,
    sleepEdIdx,
    leaveBedIdx,
) {
    if (!root || !rangeSeries || !eventSeries) return;

    const baseDate = new Date();

    const getBed = toDate(parseTime(getBedIdx), baseDate);
    const sleepStart = toDate(parseTime(sleepStIdx), baseDate);

    const isNextDay =
        sleepEdIdx.includes("After") || leaveBedIdx.includes("After");

    const sleepEnd = toDate(parseTime(sleepEdIdx), baseDate, isNextDay);
    const leaveBed = toDate(parseTime(leaveBedIdx), baseDate, isNextDay);

    const timelineData = buildTimelineData(
        getBed,
        sleepStart,
        sleepEnd,
        leaveBed,
    );

    const eventData = buildEventData(getBed, sleepStart, sleepEnd, leaveBed);

    // Important: only one category
    yAxis.data.setAll([{ category: CATEGORY }]);

    rangeSeries.data.setAll(timelineData);
    eventSeries.data.setAll(eventData);

    // Nice animation
    rangeSeries.appear(800);
    eventSeries.appear(800);
    chart.appear(800, 100);
}
