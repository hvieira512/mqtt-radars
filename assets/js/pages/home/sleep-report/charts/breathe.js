let root, chart, xAxis, yAxis, series;

export const initBreatheChart = () => {
    root = am5.Root.new("breathe-chart");
    root._logo?.dispose();

    chart = root.container.children.push(
        am5xy.XYChart.new(root, {
            panX: true,
            panY: false,
            wheelX: "panX",
            wheelY: "zoomX",
        }),
    );

    // X Axis (time)
    xAxis = chart.xAxes.push(
        am5xy.CategoryAxis.new(root, {
            categoryField: "time",
            renderer: am5xy.AxisRendererX.new(root, {}),
        }),
    );

    // Y Axis (breath rate)
    yAxis = chart.yAxes.push(
        am5xy.ValueAxis.new(root, {
            min: 0,
            max: 30,
            renderer: am5xy.AxisRendererY.new(root, {}),
        }),
    );

    // Main line series
    series = chart.series.push(
        am5xy.LineSeries.new(root, {
            name: "Breath Rate",
            xAxis,
            yAxis,
            valueYField: "value",
            categoryXField: "time",
            strokeWidth: 2,
        }),
    );

    // --- Threshold lines (8 and 24) ---
    const createThreshold = (value) => {
        const rangeDataItem = yAxis.makeDataItem({
            value: value,
            endValue: value,
        });

        const range = yAxis.createAxisRange(rangeDataItem);

        range.get("grid").setAll({
            strokeOpacity: 0.6,
            strokeDasharray: [4, 4], // dotted
        });

        range.get("label").setAll({
            text: value.toString(),
            location: 1,
            centerX: am5.p100,
        });
    };

    createThreshold(8);
    createThreshold(24);

    return { root, chart, series, xAxis };
};

export const updateBreatheChart = (data) => {
    const values = data.breathRateVo.dataList;
    const timestamps = data.timestamps;

    let chartData = [];
    let bradypneaEvents = 0;
    let apneaEvents = 0;
    let tachypneaEvents = 0;

    let prev = null;

    for (let i = 0; i < values.length; i++) {
        let v = Number(values[i]);
        if (v === -1) continue;

        const time = timestamps[i];
        let anomaly = null;

        if (prev !== null && prev > 8 && v <= 8) {
            anomaly = "bradypnea";
            bradypneaEvents++;
        }
        if (v === 0) {
            anomaly = "apnea";
            apneaEvents++;
        }
        if (v > 24) {
            anomaly = "tachypnea";
            tachypneaEvents++;
        }

        chartData.push({
            time,
            value: v,
            anomaly,
        });

        prev = v;
    }

    xAxis.data.setAll(chartData);
    series.data.setAll(chartData);

    // --- BULLETS ---
    series.bullets.clear(); // remove old bullets
    series.bullets.push((root, series, dataItem) => {
        const anomaly = dataItem.dataContext.anomaly;
        if (!anomaly) return null; // no bullet for normal points

        let shape;
        if (anomaly === "apnea") shape = am5.Circle.new(root, { radius: 5 });
        else if (anomaly === "bradypnea")
            shape = am5.Rectangle.new(root, { width: 8, height: 8 });
        else if (anomaly === "tachypnea")
            shape = am5.Triangle.new(root, { width: 10, height: 10 });

        return am5.Bullet.new(root, {
            sprite: shape,
            locationY: 0.5,
        });
    });

    return { bradypneaEvents, apneaEvents, tachypneaEvents };
};
