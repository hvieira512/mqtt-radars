export const initTooltips = () => {
    const triggers = document.querySelectorAll('[data-bs-toggle="tooltip"]');

    triggers.forEach((el) => {
        const existing = bootstrap.Tooltip.getInstance(el);
        if (existing) existing.dispose();

        new bootstrap.Tooltip(el);
    });
};

export const renderLoading = (el) => {
    const element = typeof el === "string" ? document.querySelector(el) : el;
    element.dataset.originalContent = el.innerHTML;

    element.innerHTML = `
        <div class="d-flex justify-content-center align-items-center py-4">
            <i class="fa-solid fa-spinner fa-spin fa-2x text-primary"></i>
        </div>
    `;
};

export const removeLoading = (element) => {
    const el =
        typeof element === "string" ? document.querySelector(element) : element;
    if (el.dataset.originalContent) {
        el.innerHTML = el.dataset.originalContent;
        delete el.dataset.originalContent;
    }
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
