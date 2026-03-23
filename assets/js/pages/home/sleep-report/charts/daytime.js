let daytimeChart = null;
let daytimeSeries = null;
let centerLabel = null;

const formatTime = (timeString) => {
    if (!timeString || typeof timeString !== "string") return "0m";

    const parts = timeString.split(":");

    const hours = parseInt(parts[0], 10) || 0;
    const minutes = parseInt(parts[1], 10) || 0;

    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${minutes}m`;
};

export const initDaytimeActivityChart = (
    container = "daytime-activity-chart",
) => {
    // Root
    const root = am5.Root.new(container);
    root._logo?.dispose();

    // Theme (optional)
    root.setThemes([am5themes_Animated.new(root)]);

    // Chart
    const chart = root.container.children.push(
        am5percent.PieChart.new(root, {
            layout: root.verticalLayout,
            innerRadius: am5.percent(60), // donut style
        }),
    );

    // Series
    const series = chart.series.push(
        am5percent.PieSeries.new(root, {
            valueField: "value",
            categoryField: "category",
            alignLabels: false,
        }),
    );

    // Labels
    series.labels.template.setAll({
        text: "{category}: {duration}",
        radius: 10,
    });

    // Legend
    const legend = chart.children.push(
        am5.Legend.new(root, {
            centerX: am5.percent(50),
            x: am5.percent(50),
            layout: root.horizontalLayout,
        }),
    );

    legend.data.setAll(series.dataItems);

    // Center label
    centerLabel = chart.seriesContainer.children.push(
        am5.Label.new(root, {
            text: "",
            centerX: am5.percent(50),
            centerY: am5.percent(50),
            textAlign: "center",
            fontSize: 20,
            fontWeight: "500",
        }),
    );

    // Save references
    daytimeChart = chart;
    daytimeSeries = series;
};

export const updateDaytimeActivityChart = (data) => {
    if (!data || !data.userActivity) return;

    const activity = data.userActivity;

    console.log(activity);

    // Prepare data for pie (use ratios for slice size)
    const chartData = [
        {
            category: "Andar",
            value: Number(activity.walkDurationRatio),
            duration: formatTime(activity.walkDuration),
        },
        {
            category: "Parado",
            value: Number(activity.staticDurationRatio),
            duration: formatTime(activity.staticDuration),
        },
        {
            category: "Outro",
            value: Number(activity.otherDurationRatio),
            duration: formatTime(activity.otherDuration),
        },
    ];

    // Update series
    daytimeSeries.data.setAll(chartData);

    // Update legend (important!)
    daytimeChart.children.values.forEach((child) => {
        if (child instanceof am5.Legend) {
            child.data.setAll(daytimeSeries.dataItems);
        }
    });

    // Update center value
    const formattedInRoom = formatTime(activity.inRoomDuration);
    if (centerLabel) centerLabel.set("text", `No quarto\n${formattedInRoom}`);
};
