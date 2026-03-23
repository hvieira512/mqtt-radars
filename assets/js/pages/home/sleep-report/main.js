import { getRequest } from "../../../auth.js";
import toast from "../../../toastr.js";
import { removeLoading, renderLoading } from "../../../utils.js";

import * as Breathe from "./charts/breathe.js";
import * as Daytime from "./charts/daytime.js";
import * as HealthScore from "./charts/health-score.js";
import * as HeartRate from "./charts/heart-rate.js";
import * as Sleep from "./charts/sleep.js";
import * as Timeline from "./charts/timeline-sleep.js";
import { initKPIElements, updateKPIs } from "./kpis.js";

const DOM = {
    modal: document.getElementById("sleepReportModal"),
    container: document
        .getElementById("sleepReportModal")
        ?.querySelector(".modal-body"),
    radarName: document.getElementById("device-name-model-sleep-report"),
    dateField: document.getElementById("pick-date-field"),
    noDataState: document.getElementById("no-data-state"),
    reportContent: document.getElementById("report-content-wrapper"),
};

let currentDevice = { id: null, name: null };

const getToday = () => new Date().toISOString().split("T")[0];

const setReportVisibility = (hasData) => {
    DOM.noDataState?.classList.toggle("d-none", hasData);
    DOM.reportContent?.classList.toggle("d-none", !hasData);
};

const updateDeviceHeader = (id, name) => {
    DOM.radarName?.textContent = `${name} | ${id}`;
};

const refreshCharts = (data) => {
    HealthScore.updateHealthScoreChart(data.score, data.scoreLabel);
    Sleep.updateSleepChart(data.statisticalData);

    // Assigning KPIs returned by chart updates back to data object
    data.breathKPIs = Breathe.updateBreatheChart(data);
    data.heartKPIs = HeartRate.updateHeartRateChart(data);

    Timeline.updateSleepTimeline(
        data.getBedIdx,
        data.sleepStIdx,
        data.sleepEdIdx,
        data.leaveBedIdx,
    );

    Daytime.updateDaytimeActivityChart(data);
    updateKPIs(data);
};

const fetchReport = async (uid, name, date) => {
    if (!uid) return;

    try {
        renderLoading(DOM.container);
        const params = { uid, date, lang: "en_US" };
        const response = await getRequest("radar/monitor/report", params);
        const data = response.data || response;

        updateDeviceHeader(uid, name);

        if (!data || data.code === 500) {
            setReportVisibility(false);
            return;
        }

        setReportVisibility(true);
        refreshCharts(data);
    } catch (error) {
        console.error("[SleepReport] Fetch error:", error);
        setReportVisibility(false);
        toast.error("Erro ao carregar o relatório de sono");
    } finally {
        removeLoading(DOM.container);
    }
};

const handleModalOpen = (e) => {
    const { id, name } = e.relatedTarget?.dataset || {};
    if (!id) return;

    currentDevice = { id, name };
    fetchReport(id, name, DOM.dateField?.value || getToday());
};

const handleDateChange = () => {
    if (currentDevice.id) {
        fetchReport(currentDevice.id, currentDevice.name, DOM.dateField.value);
    }
};

export const initSleepReportModal = () => {
    if (!DOM.modal) return;

    HealthScore.initHealthScoreChart();
    Sleep.initSleepChart();
    Breathe.initBreatheChart();
    HeartRate.initHeartRateChart();
    Timeline.initSleepTimelineChart();
    Daytime.initDaytimeActivityChart();
    initKPIElements();

    if (DOM.dateField) DOM.dateField.value = getToday();

    DOM.modal.addEventListener("shown.bs.modal", handleModalOpen);
    DOM.dateField?.addEventListener("change", handleDateChange);
};
