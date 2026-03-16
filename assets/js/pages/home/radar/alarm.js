import toast from "../../../toastr.js";
import { getAreaName } from "./utils.js";

export const renderAlarm = (a) => {
    if (!a) return;

    let theme = "info";

    switch (a.level) {
        case "warning":
            theme = "warning";
            break;

        case "alert":
        case "critical":
            theme = "danger";
            break;

        default:
            theme = "info";
    }

    let title = "";
    let text = "";

    if (a.category === "event") {
        const user = `Utilizador ${a.person_index ?? "?"}`;
        const regionName = getAreaName(a.region_id) ?? "zona desconhecida";

        switch (a.alarm_type) {
            case "lying_down":
                title = "Pessoa deitada";
                break;

            case "room_entry":
                title = `${user} entrou na sala`;
                text = `Zona: ${regionName}`;
                break;

            case "room_exit":
                title = `${user} saiu da sala`;
                text = `Zona: ${regionName}`;
                break;

            case "area_entry":
                title = `${user} entrou na zona`;
                text = `Zona: ${regionName}`;
                break;

            case "area_exit":
                title = `${user} saiu da zona`;
                text = `Zona: ${regionName}`;
                break;

            case "bed_entry":
                title = `${user} entrou na cama monitorizada`;
                text = `Zona: ${regionName}`;
                break;

            case "bed_exit":
                title = `${user} saiu da cama monitorizada`;
                text = `Zona: ${regionName}`;
                break;

            default:
                title = "Evento de posição";
                text = `Zona: ${regionName}`;
        }
    }

    if (a.category === "alarm") {
        switch (a.alarm_type) {
            case "fall_confirmed":
                title = "Queda confirmada";
                break;

            case "sitting_confirmed":
                title = "Pessoa sentada no chão";
                break;

            default:
                title = "Alerta do sistema";
        }

        text = a.message || `Dispositivo: ${a.device_code}`;
    }

    console.log({ title, text });
    toast({ title, text, theme });
};
