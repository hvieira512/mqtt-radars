let calendarInstance = null;
let daysWithData = [];

const el = "#pick-date-field";

export const initCalendar = () => {
    const config = {
        locale: "pt",
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "d/m/Y",
        defaultDate: new Date(),
        static: true,
        onDayCreate: function (_dObj, _dStr, _fp, el) {
            const dateStr = dayjs(el.dateObj).format("YYYY-MM-DD");
            if (daysWithData.includes(dateStr))
                el.classList.add("has-data-dot");
        },
    };
    calendarInstance = flatpickr(el, config);
};

export const updateCalendar = (data) => {
    daysWithData = data || [];
    if (calendarInstance) calendarInstance.redraw?.();
};
