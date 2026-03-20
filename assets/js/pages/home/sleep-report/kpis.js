let elements = {};
const previousValues = new WeakMap();

const getEl = (id) => document.getElementById(id);

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

const animateNumber = ({ from, to, duration = 700, onUpdate }) => {
    const start = performance.now();
    const easeOut = (t) => 1 - Math.pow(1 - t, 3);

    const frame = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = easeOut(progress);
        const value = from + (to - from) * eased;

        onUpdate(value);

        if (progress < 1) requestAnimationFrame(frame);
    };

    requestAnimationFrame(frame);
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
            sleepDuration: getEl("general-sleep-duration-value"),
            leaveBed: getEl("leave-bed-value"),
            deepSleepPercentage: getEl("deep-sleep-percentage-value"),
            ahi: getEl("ahi-value"),
            breathRate: getEl("breath-rate-value"),
            heartRate: getEl("heart-rate-value"),
        },
        sleep: {
            hours: {
                deepSleep: getEl("deep-sleep-value"),
                lightSleep: getEl("light-sleep-value"),
                rem: getEl("rem-sleep-value"),
                awake: getEl("awake-time-value"),
                sleepTotal: getEl("sleep-duration-value"),
            },
            times: {
                bedExits: getEl("number-of-bed-exits-value"),
            },
            percent: {
                deepSleepPercent: getEl("deep-sleep-meta"),
                lightSleepPercent: getEl("light-sleep-meta"),
                remPercent: getEl("rem-sleep-meta"),
            },
        },
        heartRate: {
            bpm: {
                min: getEl("min-heart-rate-value"),
                avg: getEl("avg-heart-rate-value"),
                max: getEl("max-heart-rate-value"),
            },
        },
        breathRate: {
            bpm: {
                min: getEl("min-breath-rate-value"),
                avg: getEl("avg-breath-rate-value"),
                max: getEl("max-breath-rate-value"),
            },
            times: {
                apnea: getEl("apnea-value"),
                tachypnea: getEl("tachypnea-value"),
                bradypnea: getEl("bradypnea-value"),
            },
        },
        daytimeActivity: {
            times: {
                inOutRoom: getEl("in-out-room-value"),
                walkingSteps: getEl("walking-steps-value"),
            },
            speed: {
                walkingSpeed: getEl("walking-speed-value"),
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
