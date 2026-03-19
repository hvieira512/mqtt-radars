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
        'unit' => 'vezes',
        'meta' => '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Conformidade</span>',
        'metaId' => 'leave-bed-meta',
        'icon' => 'fa-bed',
        'color' => 'danger',
        'id' => 'leave-bed',
        'statId' => 'leave-bed-value'
    ],
    [
        'label' => 'Percentagem de Sono Profundo %',
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
        'value' => '0',
        'unit' => 'vezes',
        'icon' => 'fa-arrow-right-from-bracket',
        'color' => 'danger',
        'id' => 'number-of-bed-exits'
    ],
];
?>

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
                            <div id="health-score-pie" class="w-100" style="height: 375px;"></div>
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
                            <div class="d-flex justify-content-between fw-bold text-uppercase text-dark" id="sleep-stats-1">
                                <span>Adormeceu às 00:00:00 (Latência de sono 16min)</span>
                                <span>Acordou após as 08h00</span>
                            </div>
                            <div id="sleep-chart" class="w-100 py-3" style="height: 250px;"></div>
                            <div class="d-flex justify-content-between fw-bold text-uppercase text-dark" id="sleep-stats-2">
                                <span>Entrou na cama 22:47:33</span>
                                <span>Levantou-se depois das 8h.</span>
                            </div>

                            <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-md-3 mt-2">
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