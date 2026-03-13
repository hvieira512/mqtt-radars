export const renderAlarm = (a) => {
    if (!a || a.category !== "alarm") return;

    let icon = "info";
    switch (a.level) {
        case "warning":
            icon = "warning";
            break;
        case "alert":
        case "critical":
            icon = "error";
            break;
        case "info":
            icon = "info";
            break;
    }

    const title = `Alerta: ${a.alarm_type.replaceAll("_", " ")}`;
    const text  = a.message || `Source: ${a.source}\nDevice: ${a.device_code}`;

    console.log({ title, text });

    Swal.fire({
        title,
        text,
        icon,
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 8000,
        timerProgressBar: true,
        background: "#fff",
        color: "#333",
    });
};
