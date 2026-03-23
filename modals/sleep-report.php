<?php

$generalKPIs = [
    ['label' => 'Duração de Sono', 'value' => '0', 'unit' => 'h', 'meta' => '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Conformidade</span>', 'metaId' => 'sleep-duration-meta', 'icon' => 'fa-clock', 'color' => 'success', 'id' => 'general-sleep-duration', 'statId' => 'general-sleep-duration-value'],
    ['label' => 'Saídas da Cama', 'value' => '0', 'unit' => 'vezes', 'meta' => '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Conformidade</span>', 'metaId' => 'leave-bed-meta', 'icon' => 'fa-bed', 'color' => 'danger', 'id' => 'leave-bed', 'statId' => 'leave-bed-value'],
    ['label' => 'Percentagem de Sono Profundo %', 'value' => '0', 'unit' => '%', 'meta' => '<span class="text-danger"><i class="fa-solid fa-circle-xmark"></i> Não Conformidade</span>', 'metaId' => 'deep-sleep-percentage-meta', 'icon' => 'fa-brain', 'color' => 'danger', 'id' => 'deep-sleep-percentage', 'statId' => 'deep-sleep-percentage-value'],
    ['label' => 'AHI', 'value' => '0', 'unit' => '', 'meta' => '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Conformidade</span>', 'metaId' => 'ahi-meta', 'icon' => ' fa-lungs', 'color' => 'success', 'id' => 'ahi', 'statId' => 'ahi-value'],
    ['label' => 'Taxa média de respiração', 'value' => '0', 'unit' => 'BPM', 'meta' => '<span class="text-success"><i class="fa-solid fa-circle-check"></i> Normal</span>', 'metaId' => 'breath-rate-meta', 'icon' => 'fa-lungs', 'color' => 'success', 'id' => 'breath-rate', 'statId' => 'breath-rate-value'],
    ['label' => 'Frequência cardíaca média', 'value' => '0', 'unit' => 'BPM', 'meta' => '<span class="text-success"><i class="fa-solid fa-heart"></i> Normal</span>', 'metaId' => 'heart-rate-meta', 'icon' => 'fa-heart', 'color' => 'success', 'id' => 'heart-rate', 'statId' => 'heart-rate-value'],
];

$sleepKPIs = [
    ['label' => 'Sono Profundo', 'value' => '0', 'unit' => 'h', 'meta' => '<span class="text-muted small">(9%)</span>', 'metaId' => 'deep-sleep-meta', 'icon' => 'fa-brain', 'color' => 'info', 'id' => 'deep-sleep', 'statId' => 'deep-sleep-value'],
    ['label' => 'Sono Leve', 'value' => '0', 'unit' => 'h', 'meta' => '<span class="text-muted small">(53%)</span>', 'metaId' => 'light-sleep-meta', 'icon' => 'fa-moon', 'color' => 'primary', 'id' => 'light-sleep', 'statId' => 'light-sleep-value'],
    ['label' => 'REM', 'value' => '0', 'unit' => 'h', 'meta' => '<span class="text-muted small">(38%)</span>', 'metaId' => 'rem-sleep-meta', 'icon' => 'fa-eye', 'color' => 'warning', 'id' => 'rem-sleep', 'statId' => 'rem-sleep-value'],
    ['label' => 'Duração de Sono', 'value' => '0', 'unit' => 'h', 'icon' => 'fa-clock', 'color' => 'success', 'id' => 'sleep-duration', 'statId' => 'sleep-duration-value'],
    ['label' => 'Acordado', 'value' => '0', 'unit' => 'h', 'icon' => 'fa-sun', 'color' => 'secondary', 'id' => 'awake-time', 'statId' => 'awake-time-value'],
    ['label' => 'Saídas da cama', 'value' => '0', 'unit' => 'vezes', 'icon' => 'fa-person-walking-arrow-right', 'color' => 'success', 'id' => 'number-of-bed-exits', 'statId' => 'number-of-bed-exits-value'],
];

$heartRateKPIs = [
    ['label' => 'Frequência Cardíacada Máxima', 'value' => '0', 'unit' => 'BPM', 'icon' => 'fa-arrow-up', 'color' => 'danger', 'id' => 'max-heart-rate', 'statId' => 'max-heart-rate-value'],
    ['label' => 'Frequência Cardíacada Mínima', 'value' => '0', 'unit' => 'BPM', 'icon' => 'fa-arrow-down', 'color' => 'primary', 'id' => 'min-heart-rate', 'statId' => 'min-heart-rate-value'],
    ['label' => 'Frequência Cardíacada Média', 'value' => '0', 'unit' => 'BPM', 'icon' => 'fa-heart-pulse', 'color' => 'success', 'id' => 'avg-heart-rate', 'statId' => 'avg-heart-rate-value'],
    ['label' => 'Sinais Vitais Fracos', 'value' => '0', 'unit' => 'vezes', 'icon' => 'fa-circle', 'color' => 'warning', 'id' => 'weak-vital-signs', 'statId' => 'weak-vital-signs-value'],
    ['label' => 'Policardia', 'value' => '0', 'unit' => 'vezes', 'icon' => 'fa-triangle-exclamation', 'color' => 'danger', 'id' => 'polycardia', 'statId' => 'polycardia-value'],
    ['label' => 'Bradicardia', 'value' => '0', 'unit' => 'vezes', 'icon' => 'fa-square', 'color' => 'warning', 'id' => 'bradycardia', 'statId' => 'bradycardia-value'],
];

$breatheKPIs = [
    ['label' => 'Frequência Respiratória Máxima', 'value' => '0', 'unit' => 'BPM', 'icon' => 'fa-arrow-up', 'color' => 'danger', 'id' => 'max-breath-rate', 'statId' => 'max-breath-rate-value'],
    ['label' => 'Frequência Respiratória Mínima', 'value' => '0', 'unit' => 'BPM', 'icon' => 'fa-arrow-down', 'color' => 'primary', 'id' => 'min-breath-rate', 'statId' => 'min-breath-rate-value'],
    ['label' => 'Frequência Respiratória Média', 'value' => '0', 'unit' => 'BPM', 'icon' => 'fa-lungs', 'color' => 'success', 'id' => 'avg-breath-rate', 'statId' => 'avg-breath-rate-value'],
    ['label' => 'Apneia', 'value' => '0', 'unit' => 'vezes', 'icon' => 'fa-circle', 'color' => 'warning', 'id' => 'apnea', 'statId' => 'apnea-value'],
    ['label' => 'Taquipneia', 'value' => '0', 'unit' => 'vezes', 'icon' => 'fa-triangle-exclamation', 'color' => 'danger', 'id' => 'tachypnea', 'statId' => 'tachypnea-value'],
    ['label' => 'Bradipneia', 'value' => '0', 'unit' => 'vezes', 'icon' => 'fa-square', 'color' => 'warning', 'id' => 'bradypnea', 'statId' => 'bradypnea-value'],
];

$daytimeActivityKPIs = [
    ['label' => 'Quarto interior / exterior', 'value' => '0', 'unit' => 'vezes', 'icon' => 'fa-door-open', 'color' => 'primary', 'id' => 'in-out-room', 'statId' => 'in-out-room-value'],
    ['label' => 'Passos a caminhar', 'value' => '0', 'unit' => 'vezes', 'icon' => 'fa-shoe-prints', 'color' => 'primary', 'id' => 'walking-steps', 'statId' => 'walking-steps-value'],
    ['label' => 'Velocidade de marcha', 'value' => '0', 'unit' => 'm/min', 'icon' => 'fa-gauge-high', 'color' => 'primary', 'id' => 'walking-speed', 'statId' => 'walking-speed-value'],
];

$suggestionCards = [
    ['title' => 'Sono', 'icon' => 'fa-brain', 'color' => 'info', 'contentId' => 'sleep-analysis-content'],
    ['title' => 'Respiração', 'icon' => 'fa-lungs', 'color' => 'success', 'contentId' => 'breath-analysis-content'],
];

function render_component_list($dataArray, $componentName) {
    foreach ($dataArray as $item) {
        echo '<div class="col">';
        component($componentName, $item);
        echo '</div>';
    }
}
?>

<div class="modal fade" id="sleepReportModal" tabindex="-1" aria-labelledby="sleepReportModal" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sleepReportModalLabel">Relatório de Sono</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="container-xxl">

                    <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 mb-3">
                        <span id="device-name-model-sleep-report"></span>
                        <input type="date" id="pick-date-field" class="form-control w-auto">
                    </div>

                    <div id="no-data-state" class="d-none card bg-light text-center py-5">
                        <div class="d-flex flex-column align-items-center gap-4">
                            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="text-muted opacity-50">
                                <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
                                <path d="m3.3 7 8.7 5 8.7-5" /><path d="M12 22V12" />
                            </svg>
                            <div>
                                <h4 class="fw-bold text-dark">Dados não encontrados</h4>
                                <p class="text-muted">Não existem registos ou o relatório ainda não foi processado para esta data.</p>
                            </div>
                        </div>
                    </div>

                    <div id="report-content-wrapper">
                        <div class="d-flex flex-column gap-3 bg-light p-3 shadow-sm">
                            
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <div id="health-score-pie" class="card w-100 h-100"></div>
                                </div>
                                <div class="col-12 col-md-8">
                                    <div id="kpis-stats" class="row g-3 row-cols-1 row-cols-md-2">
                                        <?php render_component_list($generalKPIs, 'kpi-card'); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">Informações de Sono</div>
                                <div class="card-body">
                                    <div id="timeline-sleep-chart" class="w-100" style="height: 100px;"></div>
                                    <div id="sleep-chart" class="w-100 py-3" style="height: 250px;"></div>
                                    <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-md-3 mt-2">
                                        <?php render_component_list($sleepKPIs, 'kpi-card'); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">Respiração</div>
                                <div class="card-body">
                                    <div id="breathe-chart" class="w-100 pb-3" style="height: 250px;"></div>
                                    <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-md-3 mt-2">
                                        <?php render_component_list($breatheKPIs, 'kpi-card'); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">Frequência cardíaca</div>
                                <div class="card-body">
                                    <div id="heart-rate-chart" class="w-100 mb-3" style="height: 250px;"></div>
                                    <div class="row g-3 row-cols-1 row-cols-sm-2 row-cols-md-3 mt-2">
                                        <?php render_component_list($heartRateKPIs, 'kpi-card'); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">Atividade Diurna</div>
                                <div class="card-body row g-3 row-cols-1 row-cols-md-2">
                                    <div class="col">
                                        <div id="daytime-activity-chart" class="w-100 h-100 mb-3"></div>
                                    </div>
                                    <div class="col">
                                        <div class="row g-3 row-cols-1">
                                             <?php render_component_list($daytimeActivityKPIs, 'kpi-card'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">Sugestões</div>
                                <div class="card-body row g-3 row-cols-1 row-cols-md-2 align-items-stretch">
                                    <?php render_component_list($suggestionCards, 'suggestion-card'); ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>