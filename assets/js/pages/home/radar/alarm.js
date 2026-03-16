import toast from "../../../toastr.js";
import { getMapCache, getAreaName } from "./utils.js";

export const renderAlarm = (a) => {
    if (!a) return;

    const theme = a.category === "alarm" ? "warning" : "info";

    const layoutData = getMapCache(a.device_code);
    const regionName = layoutData
        ? getAreaName(layoutData, a.region_id ?? 0, "area")
        : "desconhecida";

    // Tradução simples
    const translations = {
        room_entry: `Entrou na sala ${regionName}`,
        room_exit: `Saiu da sala ${regionName}`,
        area_entry: `Entrou na zona ${regionName}`,
        area_exit: `Saiu da zona ${regionName}`,
        fall_confirmed: "Queda confirmada",
        sitting_confirmed: "Pessoa sentada no chão",
    };

    const text = translations[a.type] ?? a.type;

    toast({ text, theme });
};
