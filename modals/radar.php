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
                        <div id="liveRadarMap" class="card shadow h-100">
                            <div class="card-header mb-0">
                                Monitorização de Trajetos
                            </div>
                            <div class="card-body d-flex flex-column">
                                <div id="current-people"></div>
                                <div id="radar-map" class="flex-grow-1 w-100" style="min-height: 600px;"></div>
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
                            <div class="card-header d-flex align-items-center justify-content-between py-2">
                                <span class="fw-semibold">Sinais Vitais</span>
                                <button id="sleep-report-btn" type="button" class="btn btn-sm btn-outline-primary py-1" data-bs-toggle="modal" data-bs-target="#sleepReportModal">
                                    <i class="fa-solid fa-moon me-1"></i> Relatório
                                </button>
                            </div>
                            <div id="liveRadarInfo" class="card-body d-flex flex-column gap-3">

                                <!-- Sleep State -->
                                <div id="sleep-state-container" class="flex-grow-1 text-center py-3 rounded-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <div id="sleep-state" class="d-flex align-items-center justify-content-center gap-3 text-white h-100">
                                        <i class="fa-solid fa-moon fs-2"></i>
                                        <div>
                                            <div class="fs-4 fw-bold" id="sleep-state-label"></div>
                                            <small class="opacity-75" id="sleep-state-subtitle">Estado do Sono</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Heart Rate -->
                                <div class="vital-card bg-white rounded-3 p-2 shadow-sm">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="vital-icon bg-danger bg-opacity-10 rounded-circle p-2">
                                                <i class="fas fa-heart text-danger"></i>
                                            </div>
                                            <span class="text-muted small fw-medium">Frequência Cardíaca</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span id="heart-rate-value" class="fs-5 fw-bold text-danger">--</span>
                                            <span class="text-muted small">BPM</span>
                                            <div id="heart-rate-trend" class="vital-trend">
                                                <i class="fas fa-horizontal-rule text-muted"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="chart-heart-rate" class="w-100" style="height: 225px;"></div>
                                    <div class="d-flex justify-content-between text-muted small mt-2">
                                        <span>Mín: <span id="heart-rate-min">--</span></span>
                                        <span>Média: <span id="heart-rate-avg">--</span></span>
                                        <span>Máx: <span id="heart-rate-max">--</span></span>
                                    </div>
                                </div>

                                <!-- Breath Rate -->
                                <div class="vital-card bg-white rounded-3 p-2 shadow-sm">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="vital-icon bg-primary bg-opacity-10 rounded-circle p-2">
                                                <i class="fas fa-lungs text-primary"></i>
                                            </div>
                                            <span class="text-muted small fw-medium">Frequência Respiratória</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span id="breath-rate-value" class="fs-5 fw-bold text-primary">--</span>
                                            <span class="text-muted small">rpm</span>
                                            <div id="breath-rate-trend" class="vital-trend">
                                                <i class="fas fa-horizontal-rule text-muted"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="chart-breath-rate" class="w-100" style="height: 225px;"></div>
                                    <div class="d-flex justify-content-between text-muted small mt-2">
                                        <span>Mín: <span id="breath-rate-min">--</span></span>
                                        <span>Média: <span id="breath-rate-avg">--</span></span>
                                        <span>Máx: <span id="breath-rate-max">--</span></span>
                                    </div>
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
