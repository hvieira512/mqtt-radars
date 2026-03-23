let healthScoreSeries;
let healthScoreCenterLabel;
let healthScoreRoot;
let scoreSlice;
let remainingSlice;

const labelTranslations = {
    Poor: "Fraco",
    "Below average": "Abaixo da Média",
    Average: "Média",
    Good: "Bom",
    Excellent: "Excelente",
    "Very good": "Muito Bom",
};

export const initHealthScoreChart = () => {
    healthScoreRoot = am5.Root.new("health-score-pie");
    healthScoreRoot._logo?.dispose();

    const chart = healthScoreRoot.container.children.push(
        am5percent.PieChart.new(healthScoreRoot, {
            layout: healthScoreRoot.verticalLayout,
            innerRadius: am5.percent(75),
        }),
    );

    const series = chart.series.push(
        am5percent.PieSeries.new(healthScoreRoot, {
            valueField: "value",
            categoryField: "category",
            alignLabels: false,
        }),
    );

    series.labels.template.set("forceHidden", true);
    series.ticks.template.set("forceHidden", true);
    series.slices.template.setAll({ cornerRadius: 10, strokeWidth: 0 });

    // Initialize slices: Remaining first, Score second
    series.data.setAll([
        { category: "Restante", value: 100, fill: am5.color(0xe9ecef) },
        { category: "Pontuação", value: 0, fill: am5.color(0x198754) },
    ]);

    [remainingSlice, scoreSlice] = series.dataItems;

    // Center labels
    const centerLabel = chart.seriesContainer.children.push(
        am5.Container.new(healthScoreRoot, {
            centerX: am5.percent(50),
            centerY: am5.percent(50),
            layout: healthScoreRoot.verticalLayout,
            textAlign: "center",
        }),
    );

    const scoreLabel = centerLabel.children.push(
        am5.Label.new(healthScoreRoot, {
            text: "0",
            fontSize: 32,
            fontWeight: "700",
            centerX: am5.percent(50),
            textAlign: "center",
        }),
    );

    const gradeLabel = centerLabel.children.push(
        am5.Label.new(healthScoreRoot, {
            text: "-",
            fontSize: 20,
            fill: am5.color(0x6c757d),
            centerX: am5.percent(50),
        }),
    );

    centerLabel.children.push(
        am5.Label.new(healthScoreRoot, {
            text: "Pontuação de Saúde",
            fontSize: 12,
            fill: am5.color(0xadb5bd),
            centerX: am5.percent(50),
        }),
    );

    series.appear(1000, 100);
    chart.appear(1000, 100);

    healthScoreSeries = series;
    healthScoreCenterLabel = { scoreLabel, gradeLabel };
};

// Animate the chart update smoothly
export const updateHealthScoreChart = (score, grade) => {
    if (!healthScoreSeries || !healthScoreCenterLabel) return;

    const numericScore = Math.max(0, Math.min(Number(score), 100));

    // Determine fill color
    let fillColor = am5.color(0x198754); // green
    if (numericScore < 60) fillColor = am5.color(0xf5c518); // yellow
    if (numericScore < 40) fillColor = am5.color(0xdc3545); // red

    // Update series values only
    healthScoreSeries.data.setAll([
        { category: "Restante", value: 100 - numericScore },
        { category: "Pontuação", value: numericScore },
    ]);

    // Explicitly set the slice fill after updating data
    const scoreSlice = healthScoreSeries.dataItems[1];
    scoreSlice.set("fill", fillColor);

    // Optional: also set Remaining slice color to be safe
    const remainingSlice = healthScoreSeries.dataItems[0];
    remainingSlice.set("fill", am5.color(0xe9ecef));

    const translatedGrade = labelTranslations[grade] || grade;

    // Update center labels
    healthScoreCenterLabel.scoreLabel.set("text", `${numericScore}`);
    healthScoreCenterLabel.gradeLabel.set("text", translatedGrade);
};
