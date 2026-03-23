export const initTooltips = () => {
    const triggers = document.querySelectorAll('[data-bs-toggle="tooltip"]');

    triggers.forEach((el) => {
        const existing = bootstrap.Tooltip.getInstance(el);
        if (existing) existing.dispose();

        new bootstrap.Tooltip(el);
    });
};

// Renders a loading spinner inside a container without destroying its content
export const renderLoading = (container) => {
    const el =
        typeof container === "string"
            ? document.querySelector(container)
            : container;

    // Skip if already showing
    if (el.querySelector(".loading-overlay")) return;

    const overlay = document.createElement("div");
    overlay.className =
        "loading-overlay d-flex justify-content-center align-items-center";
    overlay.style.position = "absolute";
    overlay.style.top = "0";
    overlay.style.left = "0";
    overlay.style.width = "100%";
    overlay.style.height = "100%";
    overlay.style.background = "rgba(255, 255, 255, 0.7)";
    overlay.style.zIndex = "999";
    overlay.innerHTML = `<i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i>`;

    // Make container relative if not already
    const currentPosition = getComputedStyle(el).position;
    if (currentPosition === "static") el.style.position = "relative";

    el.appendChild(overlay);
};

// Removes the loading overlay safely
export const removeLoading = (container) => {
    const el =
        typeof container === "string"
            ? document.querySelector(container)
            : container;
    const overlay = el.querySelector(".loading-overlay");
    if (overlay) overlay.remove();
};

export const buttonLoading = (buttonEl) => {
    const button =
        typeof buttonEl === "string"
            ? document.querySelector(buttonEl)
            : buttonEl;
    button.dataset.originalContent = button.innerHTML;

    button.disabled = true;

    button.innerHTML = `
        <i class="fa-solid fa-spinner fa-spin"></i>
    `;
};

export const buttonReset = (buttonEl) => {
    const button =
        typeof buttonEl === "string"
            ? document.querySelector(buttonEl)
            : buttonEl;
    if (button.dataset.originalContent) {
        button.innerHTML = button.dataset.originalContent;
        delete button.dataset.originalContent;
    }

    button.disabled = false;
};

export const animateNumber = ({ from, to, duration = 700, onUpdate }) => {
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

export const getFirstDayOfMonth = (dateStr) => {
    let year, month;

    if (!dateStr) {
        const today = new Date();
        year = today.getFullYear();
        month = today.getMonth(); // 0-indexed
    } else if (dateStr.includes("/")) {
        // parse DD/MM/YYYY
        const parts = dateStr.split("/");
        if (parts.length !== 3) {
            const today = new Date();
            year = today.getFullYear();
            month = today.getMonth();
        } else {
            const [day, mon, yr] = parts;
            year = parseInt(yr, 10);
            month = parseInt(mon, 10) - 1; // 0-indexed
        }
    } else if (dateStr.includes("-")) {
        // parse YYYY-MM-DD
        const parts = dateStr.split("-");
        if (parts.length !== 3) {
            const today = new Date();
            year = today.getFullYear();
            month = today.getMonth();
        } else {
            const [yr, mon] = parts;
            year = parseInt(yr, 10);
            month = parseInt(mon, 10) - 1;
        }
    } else {
        // fallback
        const today = new Date();
        year = today.getFullYear();
        month = today.getMonth();
    }

    const d = new Date(year, month, 1); // first day of month
    return d.toISOString().split("T")[0]; // "YYYY-MM-DD"
};
