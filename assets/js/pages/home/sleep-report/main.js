import { getRequest } from "../../../auth.js";
import toast from "../../../toastr.js";
import { removeLoading, renderLoading } from "../../../utils.js";

import { initBreatheChart, updateBreatheChart } from "./charts/breathe.js";
import {
    initDaytimeActivityChart,
    updateDaytimeActivityChart,
} from "./charts/daytime.js";
import {
    initHealthScoreChart,
    updateHealthScoreChart,
} from "./charts/health-score.js";
import {
    initHeartRateChart,
    updateHeartRateChart,
} from "./charts/heart-rate.js";
import { initSleepChart, updateSleepChart } from "./charts/sleep.js";
import {
    initSleepTimelineChart,
    updateSleepTimeline,
} from "./charts/timeline-sleep.js";
import { initKPIElements, updateKPIs } from "./kpis.js";

const modal = document.getElementById("sleepReportModal");
const container = modal?.querySelector(".modal-body");
const radarName = document.getElementById("device-name-model-sleep-report");
const dateField = document.getElementById("pick-date-field");
const noDataState = document.getElementById("no-data-state");
const reportContent = document.getElementById("report-content-wrapper");

const toggleEmptyState = (isEmpty) => {
    if (isEmpty) {
        noDataState?.classList.remove("d-none");
        reportContent?.classList.add("d-none");
    } else {
        noDataState?.classList.add("d-none");
        reportContent?.classList.remove("d-none");
    }
};

let currentDevice = { id: null, name: null };

const getToday = () => new Date().toISOString().split("T")[0];

const isValidDevice = (device) => device?.id;

const fetchReport = async (uid, name, date) => {
    if (!uid) return;

    try {
        renderLoading(container);
        toggleEmptyState(false);

        const params = { uid, date, lang: "en_US" };
        const response = await getRequest("radar/monitor/report", params);

        const data = response.data || response;
        if (data?.code === 500 || !data) {
            toggleEmptyState(true);
            if (radarName) radarName.textContent = `${name} | ${uid}`;
            return;
        }

        toggleEmptyState(false);
        if (radarName) radarName.textContent = `${name} | ${uid}`;
        updateHealthScoreChart(data.score, data.scoreLabel);
        updateSleepChart(data.statisticalData);
        data.breathKPIs = updateBreatheChart(data);
        data.heartKPIs = updateHeartRateChart(data);

        updateSleepTimeline(
            data.getBedIdx,
            data.sleepStIdx,
            data.sleepEdIdx,
            data.leaveBedIdx,
        );
        updateDaytimeActivityChart(data);

        updateKPIs(data);
    } catch (error) {
        console.error("[SleepReport] Fetch error:", error);
        toggleEmptyState(true);
        toast.error("Erro ao carregar o relatório de sono");
    } finally {
        removeLoading(container);
    }
};

const handleModalOpen = async (e) => {
    const trigger = e.relatedTarget;
    if (!trigger?.dataset) return;

    const { id, name } = trigger.dataset;
    if (!id) return;

    currentDevice = { id, name };

    await fetchReport(id, name, dateField?.value || getToday());
};

const handleDateChange = async () => {
    if (!isValidDevice(currentDevice)) return;

    await fetchReport(currentDevice.id, currentDevice.name, dateField.value);
};

export const initSleepReportModal = () => {
    if (!modal) return;

    initHealthScoreChart();
    initSleepChart();
    initBreatheChart();
    initHeartRateChart();
    initSleepTimelineChart();
    initKPIElements();
    initDaytimeActivityChart();

    if (dateField) dateField.value = getToday();

    modal.addEventListener("shown.bs.modal", handleModalOpen);
    dateField?.addEventListener("change", handleDateChange);
};
