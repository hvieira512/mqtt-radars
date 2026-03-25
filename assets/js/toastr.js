const baseToast = Swal.mixin({
    toast: true,
    position: "top-end",
    showConfirmButton: false,
    timerProgressBar: true,
});

const themes = {
    success: {
        icon: "success",
        customClass: { popup: "toast-success" },
    },

    danger: {
        icon: "error",
        customClass: { popup: "toast-danger" },
    },

    warning: {
        icon: "warning",
        customClass: { popup: "toast-warning" },
    },

    info: {
        icon: "info",
        customClass: { popup: "toast-info" },
    },

    primary: {
        icon: "info",
        customClass: { popup: "toast-primary" },
    },

    secondary: {
        icon: "info",
        customClass: { popup: "toast-secondary" },
    },
};

export function toast({
    title = "",
    text = "",
    theme = "info",
    timer = 3000,
    ...options
}) {
    const themeConfig = themes[theme] || themes.info;

    return baseToast.fire({
        title,
        text,
        timer,
        ...themeConfig,
        ...options,
    });
}

toast.success = (title, text, opts = {}) =>
    toast({ title, text, theme: "success", ...opts });

toast.error = (title, text, opts = {}) =>
    toast({ title, text, theme: "danger", ...opts });

toast.warning = (title, text, opts = {}) =>
    toast({ title, text, theme: "warning", ...opts });

toast.info = (title, text, opts = {}) =>
    toast({ title, text, theme: "info", ...opts });

export default toast;
