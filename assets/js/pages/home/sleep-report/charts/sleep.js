export const initSleepChart = () => {
    const root = am5.Root.new("sleep-chart");
    root._logo?.dispose();
    root.setThemes([am5themes_Animated.new(root)]);

    const chart = root.container.children.push(
        am5xy.XYChart.new(root, {
            panX: true,
            panY: true,
            wheelX: "panX",
            wheelY: "zoomX",
        }),
    );

    const xAxis = chart.xAxes.push(
        am5xy.CategoryAxis.new(root, {
            categoryField: "day",
            renderer: am5xy.AxisRendererX.new(root, {}),
        }),
    );

    const yAxis = chart.yAxes.push(
        am5xy.ValueAxis.new(root, {
            renderer: am5xy.AxisRendererY.new(root, {}),
        }),
    );

    const series = chart.series.push(
        am5xy.ColumnSeries.new(root, {
            name: "Sleep Hours",
            xAxis,
            yAxis,
            valueYField: "hours",
            categoryXField: "day",
        }),
    );

    series.data.setAll([
        { day: "Mon", hours: 7 },
        { day: "Tue", hours: 6.5 },
        { day: "Wed", hours: 8 },
        { day: "Thu", hours: 7.5 },
        { day: "Fri", hours: 6 },
        { day: "Sat", hours: 8 },
        { day: "Sun", hours: 7 },
    ]);

    series.appear(1000);
    chart.appear(1000);
};
