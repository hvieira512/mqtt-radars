<?php require "helpers.php"; ?>

<?php

$generalKPIs = [
    [
        'label' => 'Duração de Sono',
        'value' => '8.9',
        'unit' => 'h',
        'meta' => '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Conformidade</span>',
        'metaId' => 'sleep-duration-meta',
        'icon' => 'fa-clock',
        'color' => 'success',
        'id' => 'sleep-duration',
        'statId' => 'sleep-duration-value'
    ],
    [
        'label' => 'Saídas da Cama',
        'value' => '0',
        'unit' => 'times',
        'meta' => '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Conformidade</span>',
        'metaId' => 'leave-bed-meta',
        'icon' => 'fa-bed',
        'color' => 'danger',
        'id' => 'leave-bed',
        'statId' => 'leave-bed-value'
    ],
    [
        'label' => 'Sono Profundo %',
        'value' => '9',
        'unit' => '%',
        'meta' => '<span class="text-danger"><i class="fa-solid fa-circle-xmark"></i> Não Conformidade</span>',
        'metaId' => 'deep-sleep-percentage-meta',
        'icon' => 'fa-moon',
        'color' => 'danger',
        'id' => 'deep-sleep-percentage',
        'statId' => 'deep-sleep-percentage-value'
    ],
    [
        'label' => 'AHI',
        'value' => '0.43',
        'unit' => '',
        'meta' => '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Conformidade</span>',
        'metaId' => 'ahi-meta',
        'icon' => 'fa-wave-square',
        'color' => 'info',
        'id' => 'ahi',
        'statId' => 'ahi-value'
    ],
    [
        'label' => 'Taxa média de respiração',
        'value' => '11',
        'unit' => 'BPM',
        'meta' => '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Normal</span>',
        'metaId' => 'breath-rate-meta',
        'icon' => 'fa-wind',
        'color' => 'primary',
        'id' => 'breath-rate',
        'statId' => 'breath-rate-value'
    ],
    [
        'label' => 'Frequência cardíaca média',
        'value' => '65',
        'unit' => 'BPM',
        'meta' => '<span class="text-success"><i class="fa-solid fa-heart"></i> Normal</span>',
        'metaId' => 'heart-rate-meta',
        'icon' => 'fa-heart',
        'color' => 'danger',
        'id' => 'heart-rate',
        'statId' => 'heart-rate-value'
    ],
];

$sleepKPIs = [
    [
        'label' => 'Sono Profundo',
        'value' => '0.8',
        'unit' => 'h',
        'meta' => '<span class="text-muted small">(9%)</span>',
        'metaId' => 'deep-sleep-meta',
        'icon' => 'fa-star-and-crescent',
        'color' => 'info',
        'id' => 'deep-sleep',
        'statId' => 'deep-sleep-value'
    ],
    [
        'label' => 'Sono Leve',
        'value' => '4.8',
        'unit' => 'h',
        'meta' => '<span class="text-muted small">(53%)</span>',
        'metaId' => 'light-sleep-meta',
        'icon' => 'fa-moon',
        'color' => 'primary',
        'id' => 'light-sleep'
    ],
    [
        'label' => 'REM',
        'value' => '3.3',
        'unit' => 'h',
        'meta' => '<span class="text-muted small">(38%)</span>',
        'metaId' => 'rem-sleep-meta',
        'icon' => 'fa-eye',
        'color' => 'warning',
        'id' => 'rem-sleep'
    ],
    [
        'label' => 'Duração de Sono',
        'value' => '8.9',
        'unit' => 'h',
        'icon' => 'fa-clock',
        'color' => 'success',
        'id' => 'sleep-duration'
    ],
    [
        'label' => 'Acordado',
        'value' => '0.0',
        'unit' => 'h',
        'icon' => 'fa-sun',
        'color' => 'warning',
        'id' => 'awake-time'
    ],
    [
        'label' => 'Saídas da cama',
        'value' => '9',
        'unit' => 'vezes',
        'icon' => 'fa-arrow-right-from-bracket',
        'color' => 'danger',
        'id' => 'number-of-bed-exits'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MQTT hitEcosystem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="icon" type="image/x-icon" href="./assets/images/logo.jpeg">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/poppins@5.2.7/index.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Sweet Alerts 2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.22/dist/sweetalert2.min.css">
    <!-- AG Grid -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community/styles/ag-theme-quartz.css">

    <link rel="stylesheet" href="./assets/css/styles.css">
</head>

<body class="bg-light">

    <div class="container-xl mt-5">
        <div class="d-flex justify-content-between align-items-end gap-2 flex-wrap">
            <h1>Radares</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sleepReportModal">
                <i class="fa-solid fa-moon me-2"></i> Relatórios de Sono
            </button>
        </div>

        <div id="radars-wrappers" class="row g-3 row-cols-1 row-cols-md-2 row-cols-lg-4 mt-3"></div>
    </div>

    <div class="modal fade" id="sleepReportModal" tabindex="-1" aria-labelledby="sleepReportModal" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sleepReportModalLabel">Relatórios de Sono</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container-xxl d-flex flex-column gap-3">

                        <div class="row g-2">
                            <div class="col-12 col-md-5">
                                <div id="health-score-pie"></div>
                            </div>
                            <div class="col-12 col-md-7">
                                <div id="kpis-stats" class="row g-3 row-cols-1 row-cols-2">
                                    <?php foreach ($generalKPIs as $kpi): ?>
                                        <div class="col">
                                            <?php component('kpi-card', $kpi); ?>
                                        </div>
                                    <?php endforeach ?>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">Informações de Sono</div>
                            <div class="card-body">
                                <div class="d-flex justify-content-around" id="sleep-stats-1">
                                    <span>Adormeceu às 00:00:00 (Latência de sono 16min)</span>
                                    <span>Acordar após as 08h00</span>
                                </div>
                                <div id="sleep-data-chart" class="py-3"></div>
                                <div class="d-flex justify-content-around" id="sleep-stats-2"></div>

                                <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-md-3">
                                    <?php foreach ($sleepKPIs as $kpi): ?>
                                        <div class="col">
                                            <?php component('kpi-card', $kpi); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">Respiração</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="radarModal" tabindex="-1" aria-labelledby="radarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="radarModalLabel">Detalhes do Radar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3 d-flex align-items-stretch">
                        <div class="col-12 col-xl-8">
                            <div id="liveRadarMap" class="card shadow">
                                <div class="card-header mb-0">
                                    Monitorização de Trajetos
                                </div>
                                <div class="card-body">
                                    <div id="current-people"></div>
                                    <div id="radar-map" class="w-100" style="height: 600px;"></div>
                                    <div class="d-flex justify-content-end gap-3 flex-wrap mt-2">

                                        <div class="d-flex align-items-center gap-2">
                                            <span class="legend-color" style="background:#ffa500"></span>
                                            <small>Porta</small>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <span class="legend-color" style="background:#32cd32"></span>
                                            <small>Cama de Monitorização</small>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <span class="legend-color" style="background:#ff4500"></span>
                                            <small>Região de Alarme</small>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <span class="legend-color" style="background:#808080"></span>
                                            <small>Interferência</small>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <span class="legend-color" style="background:#a9a9a9"></span>
                                            <small>Outras Regiões</small>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-xl-4">
                            <div class="card shadow h-100">
                                <div class="card-header mb-0">
                                    Sinais Vitais
                                </div>
                                <div id="liveRadarInfo" class="card-body d-flex flex-column gap-3">
                                    <div id="sleep-state"></div>
                                    <div class="chart-container">
                                        <h6 class="fw-bold"><i class="fas fa-heart text-danger"></i> Frequência Cardíaca
                                            (BPM)</h6>
                                        <div id="chart-heart-rate" class="w-100" style="height: 250px;"></div>
                                    </div>
                                    <div class="chart-container mb-3">
                                        <h6 class="fw-bold"><i class="fas fa-lungs text-primary"></i> Frequência de
                                            Respiração (rpm)</h6>
                                        <div id="chart-breath-rate" class="w-100" style="height: 250px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="liveRadarEvents" class="card shadow">
                        <div class="card-header mb-0">
                            Eventos
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="deviceTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="alarms-tab" data-bs-toggle="tab"
                                        data-bs-target="#alarms" type="button">
                                        Alarmes
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="events-tab" data-bs-toggle="tab"
                                        data-bs-target="#events" type="button">
                                        Eventos
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="info-tab" data-bs-toggle="tab" data-bs-target="#info"
                                        type="button">
                                        Mais Informações
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content mt-3">
                                <div class="tab-pane fade show active" id="alarms">
                                    <div id="alarms-grid" class="ag-theme-quartz w-100"></div>
                                </div>
                                <div class="tab-pane fade" id="events">
                                    <div id="events-grid" class="ag-theme-quartz w-100"></div>
                                </div>
                                <div class="tab-pane fade" id="info"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script src="https://unpkg.com/konva@10.0.0-1/konva.min.js"></script>
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.22/dist/sweetalert2.all.min.js"></script>
    <!-- AG Grid -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ag-grid-enterprise/34.2.0/ag-grid-enterprise.min.js"
        integrity="sha512-aE+oj9Z0B9knKrq4Torrb8AlXMuaZNXJ9LvxXfv5stq5xbwVGuVgopQE5Ql10nQMNiFMwkSyvHFQQKkwy1xh/g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script type="module" src="./assets/js/pages/home/main.js"></script>
</body>

</html>