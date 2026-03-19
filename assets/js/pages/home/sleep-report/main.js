import { getRequest } from "../../../auth.js";
import toast from "../../../toastr.js";
import { removeLoading, renderLoading } from "../../../utils.js";
import { initBreatheChart } from "./charts/breathe.js";
import {
    initHealthScoreChart,
    updateHealthScoreChart,
} from "./charts/health-score.js";
import { initHeartRateChart } from "./charts/heart-rate.js";
import { initSleepChart, updateSleepChart } from "./charts/sleep.js";

const modal = document.getElementById("sleepReportModal");
const container = modal.querySelector(".modal-body");

export const initSleepReportModal = () => {
    initHealthScoreChart();
    initSleepChart();
    initBreatheChart();
    initHeartRateChart();

    modal.addEventListener("shown.bs.modal", async (e) => {
        const { id } = e.relatedTarget.dataset;
        if (!id) return;

        toast.success(`Fetching data for device: ${id}`);
        try {
            renderLoading(container);
            const today = new Date();
            const dateString = today.toISOString().split("T")[0];

            const params = { uid: id, date: dateString, lang: "en_US" };
            const { data } = await getRequest("radar/monitor/report", params);

            console.log(data);
            updateHealthScoreChart(data.score, data.scoreLabel);
            updateSleepChart(data.statisticalData);
        } catch (error) {
            console.error(error);
            toast.error("Erro ao carregar o relatório de sono");
        } finally {
            removeLoading(container);
        }
    });
};
