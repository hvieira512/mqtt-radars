// 1. Configuração de Status e Cores (Igual)
const SLEEP_STATUS = {
    3: { label: "Saída da Cama", color: 0x003366 },
    2: { label: "Acordado", color: 0xc0c0c0 },
    7: { label: "REM", color: 0xff9933 },
    1: { label: "Sono leve", color: 0x80c0ff },
    0: { label: "Sono profundo", color: 0x744596 },
};

const STATUS_TO_Y = {
    3: 4,
    2: 3,
    7: 2,
    1: 1,
    0: 0,
};

let chartComponents = null;
let sleepSeries = null;

export function initSleepChart() {
    if (chartComponents) return;

    const root = am5.Root.new("sleep-chart");
    root._logo?.dispose();

    const chart = root.container.children.push(
        am5xy.XYChart.new(root, {
            panX: false,
            panY: false,
            layout: root.verticalLayout,
        }),
    );

    // --- Eixo X ---
    const xAxis = chart.xAxes.push(
        am5xy.DateAxis.new(root, {
            baseInterval: { timeUnit: "minute", count: 1 },
            renderer: am5xy.AxisRendererX.new(root, {
                minGridDistance: 50,
                // OPCIONAL: Se quiseres limpar a grelha vertical também:
                // strokeOpacity: 0.1
            }),
        }),
    );

    // --- Eixo Y (O SEGREDO ESTÁ AQUI) ---
    const yRenderer = am5xy.AxisRendererY.new(root, {
        strokeOpacity: 0.1,
        minGridDistance: 1,
        inside: false,
    });

    // 1. DESATIVAR AS LINHAS DE GRELHA PADRÃO DO EIXO Y
    // Isso remove aquelas "várias grelhas" que estás a ver no fundo
    yRenderer.grid.template.setAll({
        forceHidden: true,
    });

    // 2. DESATIVAR AS LABELS PADRÃO
    yRenderer.labels.template.setAll({
        forceHidden: true,
    });

    const yAxis = chart.yAxes.push(
        am5xy.ValueAxis.new(root, {
            min: 0,
            max: 5,
            strictMinMax: true,
            renderer: yRenderer,
        }),
    );

    // 3. Labels Manuais (Só estas linhas serão desenhadas agora)
    const stageLabels = [
        { value: 4.5, text: "Saída da Cama", color: 0x003366 },
        { value: 3.5, text: "Acordado", color: 0x555555 },
        { value: 2.5, text: "REM", color: 0xcc7700 },
        { value: 1.5, text: "Sono leve", color: 0x4070cc },
        { value: 0.5, text: "Sono profundo", color: 0x744596 },
    ];

    stageLabels.forEach((stage) => {
        const rangeDataItem = yAxis.makeDataItem({ value: stage.value });
        const range = yAxis.createAxisRange(rangeDataItem);

        range.get("label").setAll({
            text: stage.text,
            fill: am5.color(stage.color),
            fontSize: "12px",
            fontWeight: "bold",
            centerX: am5.p100,
            paddingRight: 15,
            visible: true,
            forceHidden: false,
        });

        // Estas são as únicas linhas que vais ver no fundo (as pontilhadas)
        range.get("grid").setAll({
            strokeOpacity: 0.2, // Um pouco mais visível agora que o resto saiu
            strokeDasharray: [3, 3],
            location: 1,
            visible: true,
            forceHidden: false,
        });
    });

    // --- Série (O resto do código é igual) ---
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
        width: am5.percent(100),
        tooltipText: "{statusText}: {startTimeFormatted} - {endTimeFormatted}",
    });

    sleepSeries.columns.template.adapters.add("fill", (fill, target) => {
        return target.dataItem?.dataContext?.fill ?? fill;
    });

    chartComponents = { root, chart, xAxis, yAxis };
    return chartComponents;
}

export function updateSleepChart(data) {
    if (!sleepSeries || !data?.length) return;

    const chartData = data.map((item) => {
        const statusKey = parseInt(item.status);
        const yPos = STATUS_TO_Y[statusKey] ?? 0;
        const config = SLEEP_STATUS[statusKey] || {
            label: "Desconhecido",
            color: 0x999999,
        };

        const start = new Date(item.startTime);
        const end = new Date(item.endTime);

        return {
            status: statusKey,
            statusText: config.label,
            startTime: start.getTime(),
            endTime: end.getTime(),
            startTimeFormatted: chartComponents.root.dateFormatter.format(
                start,
                "HH:mm",
            ),
            endTimeFormatted: chartComponents.root.dateFormatter.format(
                end,
                "HH:mm",
            ),
            y1: yPos,
            y2: yPos + 1,
            fill: am5.color(config.color),
        };
    });

    sleepSeries.data.setAll(chartData);
}
