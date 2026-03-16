import toast from "../../../toastr.js";
import { getAreaName, getMapCache } from "./utils.js";

export const renderAlarm = (a) => {
    if (!a) return;

    const theme = a.level || "info";
    let title = "Alerta do sistema";
    let text = "";

    const layoutData = getMapCache(a.device_code);

    const regionName = layoutData
        ? getAreaName(layoutData, a.region_id, a.alarm_type.includes("bed") ? "bed" : a.alarm_type.includes("room") ? "room" : "area")
        : "desconhecida";

    if (a.category === "event") {
        const user = `Utilizador ${a.person_index ?? "?"}`;

        const eventTitles = {
            lying_down: "Pessoa deitada",
            room_entry: `${user} entrou na sala`,
            room_exit: `${user} saiu da sala`,
            area_entry: `${user} entrou na zona`,
            area_exit: `${user} saiu da zona`,
            bed_entry: `${user} entrou na cama monitorizada`,
            bed_exit: `${user} saiu da cama monitorizada`,
        };

        title = eventTitles[a.alarm_type] ?? "Evento de posição";

        if (a.alarm_type.includes("room")) text = `Sala: ${regionName}`;
        else if (a.alarm_type.includes("area")) text = `Zona: ${regionName}`;
        else if (a.alarm_type.includes("bed")) text = `Cama monitorizada: ${regionName}`;
    }

    if (a.category === "alarm") {
        const alarmTitles = {
            fall_confirmed: "Queda confirmada",
            sitting_confirmed: "Pessoa sentada no chão",
        };

        title = alarmTitles[a.alarm_type] ?? "Alerta do sistema";
        text = a.message || `Dispositivo: ${a.device_code}`;
    }

    toast({ title, text, theme });
};
