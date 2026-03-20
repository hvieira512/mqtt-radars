import { animateNumber } from "../../../utils.js";

let elements = {};
const previousValues = new WeakMap();

const formatKPI = (value, unit) => {
    if (value === null || value === undefined || value === "-") return "-";

    switch (unit) {
        case "vezes":
            return `${value} ${value === 1 ? "vez" : "vezes"}`;
        case "passos":
            return `${value} ${value === 1 ? "passo" : "passos"}`;
        case "%":
            return `${value}%`;
        default:
            return unit ? `${value} ${unit}` : value;
    }
};

const setValue = (el, value, unit) => {
    if (!el) return;

    if (value === null || value === undefined || value === "-") {
        el.textContent = "-";
        return;
    }

    const num = Number(value);

    if (isNaN(num)) {
        el.textContent = formatKPI(value, unit);
        return;
    }

    const prev = previousValues.get(el) ?? 0;
    previousValues.set(el, num);

    animateNumber({
        from: prev,
        to: num,
        onUpdate: (val) => {
            const rounded =
                unit === "%" || Number.isInteger(num)
                    ? Math.round(val)
                    : val.toFixed(1);

            el.textContent = formatKPI(rounded, unit);
        },
    });
};

const mapSleepData = (list = []) => {
    const map = {};
    list.forEach((item) => {
        map[item.name] = item;
    });
    return map;
};

export const initKPIElements = () => {
    elements = {
        general: {
            sleepDuration: document.getElementById(
                "general-sleep-duration-value",
            ),
            leaveBed: document.getElementById("leave-bed-value"),
            deepSleepPercentage: document.getElementById(
                "deep-sleep-percentage-value",
            ),
            ahi: document.getElementById("ahi-value"),
            breathRate: document.getElementById("breath-rate-value"),
            heartRate: document.getElementById("heart-rate-value"),
        },
        sleep: {
            hours: {
                deepSleep: document.getElementById("deep-sleep-value"),
                lightSleep: document.getElementById("light-sleep-value"),
                rem: document.getElementById("rem-sleep-value"),
                awake: document.getElementById("awake-time-value"),
                sleepTotal: document.getElementById("sleep-duration-value"),
            },
            times: {
                bedExits: document.getElementById("number-of-bed-exits-value"),
            },
            percent: {
                deepSleepPercent: document.getElementById("deep-sleep-meta"),
                lightSleepPercent: document.getElementById("light-sleep-meta"),
                remPercent: document.getElementById("rem-sleep-meta"),
            },
        },
        heartRate: {
            bpm: {
                min: document.getElementById("min-heart-rate-value"),
                avg: document.getElementById("avg-heart-rate-value"),
                max: document.getElementById("max-heart-rate-value"),
            },
        },
        breathRate: {
            bpm: {
                min: document.getElementById("min-breath-rate-value"),
                avg: document.getElementById("avg-breath-rate-value"),
                max: document.getElementById("max-breath-rate-value"),
            },
            times: {
                apnea: document.getElementById("apnea-value"),
                tachypnea: document.getElementById("tachypnea-value"),
                bradypnea: document.getElementById("bradypnea-value"),
            },
        },
        daytimeActivity: {
            times: {
                inOutRoom: document.getElementById("in-out-room-value"),
                walkingSteps: document.getElementById("walking-steps-value"),
            },
            speed: {
                walkingSpeed: document.getElementById("walking-speed-value"),
            },
        },
    };
};

// =========================
// UPDATE
// =========================
export const updateKPIs = (data) => {
    if (!data) return;

    const sleepMap = mapSleepData(data.sleepIndexCommonList);

    // === General ===
    setValue(elements.general.sleepDuration, data.sleepTotalTime);
    setValue(elements.general.leaveBed, data.leaveBedCount, "vezes");
    setValue(
        elements.general.deepSleepPercentage,
        sleepMap["深睡时长"]?.ratio,
        "%",
    );
    setValue(elements.general.ahi, data.ahi);
    setValue(elements.general.breathRate, data.breathRateVo?.avg, "BPM");
    setValue(elements.general.heartRate, data.heartRateVo?.avg, "BPM");

    // === Sleep ===
    setValue(elements.sleep.hours.deepSleep, sleepMap["深睡时长"]?.value);
    setValue(elements.sleep.hours.lightSleep, sleepMap["浅睡时长"]?.value);
    setValue(elements.sleep.hours.rem, sleepMap["眼动时长"]?.value);
    setValue(elements.sleep.hours.awake, sleepMap["夜醒时长"]?.value);
    setValue(elements.sleep.hours.sleepTotal, data.sleepTotalTime);

    setValue(elements.sleep.times.bedExits, data.leaveBedCount, "vezes");

    setValue(
        elements.sleep.percent.deepSleepPercent,
        sleepMap["深睡时长"]?.ratio,
        "%",
    );
    setValue(
        elements.sleep.percent.lightSleepPercent,
        sleepMap["浅睡时长"]?.ratio,
        "%",
    );
    setValue(
        elements.sleep.percent.remPercent,
        sleepMap["眼动时长"]?.ratio,
        "%",
    );

    // === Heart Rate ===
    setValue(elements.heartRate.bpm.min, data.heartRateVo?.min, "BPM");
    setValue(elements.heartRate.bpm.avg, data.heartRateVo?.avg, "BPM");
    setValue(elements.heartRate.bpm.max, data.heartRateVo?.max, "BPM");

    // === Breath Rate ===
    setValue(elements.breathRate.bpm.min, data.breathRateVo?.min, "BPM");
    setValue(elements.breathRate.bpm.avg, data.breathRateVo?.avg, "BPM");
    setValue(elements.breathRate.bpm.max, data.breathRateVo?.max, "BPM");

    setValue(
        elements.breathRate.times.apnea,
        data.breathKPIs?.apneaEvents,
        "vezes",
    );
    setValue(
        elements.breathRate.times.tachypnea,
        data.breathKPIs?.tachypneaEvents,
        "vezes",
    );
    setValue(
        elements.breathRate.times.bradypnea,
        data.breathKPIs?.bradypneaEvents,
        "vezes",
    );

    // === Daytime Activity ===
    setValue(
        elements.daytimeActivity.times.inOutRoom,
        data.userActivity?.entryRoomCount,
        "vezes",
    );
    setValue(
        elements.daytimeActivity.times.walkingSteps,
        data.userActivity?.stepNumber,
        "passos",
    );
    setValue(
        elements.daytimeActivity.speed.walkingSpeed,
        data.userActivity?.speed,
        "m/min",
    );
};
