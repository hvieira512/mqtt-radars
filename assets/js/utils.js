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
