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
                                    <div id="chart-heart-rate" class="w-100" style="height: 225px;"></div>
                                </div>
                                <div class="chart-container">
                                    <h6 class="fw-bold"><i class="fas fa-lungs text-primary"></i> Frequência de
                                        Respiração (rpm)</h6>
                                    <div id="chart-breath-rate" class="w-100" style="height: 225px;"></div>
                                </div>
                            </div>
                            <div class="mx-auto mb-3">
                                <button id="sleep-report-btn" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sleepReportModal">
                                    <i class="fa-solid fa-moon me-2"></i> Relatório de Sono
                                </button>
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