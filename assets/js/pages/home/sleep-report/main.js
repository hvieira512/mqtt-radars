import { getRequest } from "../../../auth.js";
import toast from "../../../toastr.js";
import {
    getFirstDayOfMonth,
    removeLoading,
    renderLoading,
} from "../../../utils.js";

import * as Breathe from "./charts/breathe.js";
import * as Daytime from "./charts/daytime.js";
import * as HealthScore from "./charts/health-score.js";
import * as HeartRate from "./charts/heart-rate.js";
import * as Sleep from "./charts/sleep.js";
import * as Timeline from "./charts/timeline-sleep.js";
import { initKPIElements, updateKPIs } from "./kpis.js";
import * as Suggestion from "./suggestions.js";
import * as DatePicker from "./date-picker.js";

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
let isDueMessage = "O relatório de hoje deverá ser entregue após as 8h";

const getToday = () => new Date().toISOString().split("T")[0];

const setReportVisibility = (hasData) => {
    DOM.noDataState?.classList.toggle("d-none", hasData);
    DOM.reportContent?.classList.toggle("d-none", !hasData);
};

const updateDeviceHeader = (id, name) => {
    if (DOM.radarName) DOM.radarName.textContent = `${name} | ${id}`;
};

const refreshData = (data) => {
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
    Suggestion.updateSuggestions(
        data.evaluation.sleepAnalysisEvaluation,
        "sleep-analysis-content",
    );
    Suggestion.updateSuggestions(
        data.evaluation.sleepAHIEvaluation,
        "breath-analysis-content",
    );
};

const fetchReport = async (uid, name, date) => {
    if (!uid) return;

    const today = getToday();
    const now = new Date();
    const currentHour = now.getHours();

    try {
        renderLoading(DOM.container);

        const reportParams = { uid, date, lang: "en_US" };
        const response = await getRequest("radar/monitor/report", reportParams);
        const data = response.data || response;

        updateDeviceHeader(uid, name);

        const firstDayOfMonth = getFirstDayOfMonth(date);
        const daysParams = { uid, date: firstDayOfMonth, lang: "en_US" };

        const daysResponse = await getRequest(
            "radar/monitor/reportDays",
            daysParams,
        );
        const daysData = daysResponse.data || daysResponse;

        DatePicker.updateCalendar(daysData);

        if (date === today && currentHour < 8) toast.info(isDueMessage);

        if (!data.score || data.code === 500) {
            setReportVisibility(false);
            return;
        }

        setReportVisibility(true);
        refreshData(data);
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

    DatePicker.initCalendar();
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
