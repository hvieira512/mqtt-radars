import toast from "../../../toastr.js";
import { getMapCache, getAreaName } from "./utils.js";

export const renderAlarm = (a) => {
    if (!a) return;

    let theme = a.level || "info";

    const layoutData = getMapCache(a.device_code);
    const regionName = layoutData
        ? getAreaName(layoutData, a.region_id ?? 0, "area")
        : "desconhecida";

    const translations = {
        room_entry: "Pessoa {person} entrou na sala {region}",
        room_exit: "Pessoa {person} saiu da sala {region}",
        area_entry: "Pessoa {person} entrou na região {region}",
        area_exit: "Pessoa {person} saiu da região {region}",
        fall_confirmed: "Queda confirmada da pessoa {person}",
        sitting_confirmed: "Pessoa {person} sentada no chão",
    };

    const personNumber = (a.person_index ?? 0) + 1;

    let text = translations[a.alarm_type] ?? a.alarm_type;
    text = text
        .replace("{person}", personNumber)
        .replace("{region}", regionName);

    toast({ text, theme });
};
