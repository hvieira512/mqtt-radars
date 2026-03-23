/**
 * Parses the scuffed API string and injects it into the target container.
 * @param {string} data - The string from the API
 * @param {string} el - The ID of the element to update
 */
export const updateSuggestions = (data, el) => {
    const container = document.getElementById(el);
    if (!container) return;

    const suggestions = data
        .split(/<br\s*\/?>/i)
        .map((line) => line.trim())
        .filter((line) => line.length > 0);

    const newContent = suggestions
        .map((s) => {
            return `
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-circle text-secondary mt-1" style="font-size: 0.4rem;"></i>
                <span>${s}</span>
            </div>`;
        })
        .join("");

    container.innerHTML = newContent;
};
