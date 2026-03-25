const themes = {
    success: {
        icon: "success",
        customClass: { popup: "toast-success" },
    },

    danger: {
        icon: "error",
        customClass: { popup: "toast-danger" },
    },

    perigo: {
        icon: "error",
        customClass: { popup: "toast-danger" },
    },

    warning: {
        icon: "warning",
        customClass: { popup: "toast-warning" },
    },

    aviso: {
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
    timer = 4000,
    ...options
}) {
    Swal.fire({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: timer,
        timerProgressBar: true,
        ...themes[theme] || themes.info,
        title: title,
        text: text,
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
