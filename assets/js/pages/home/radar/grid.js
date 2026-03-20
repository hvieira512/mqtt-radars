// grid.js
import { getAreaName, getMapCache } from "./utils.js";

let eventsGridApi = null;
let alarmsGridApi = null;

const typeTranslations = {
    room_entry: "Entrou na sala",
    room_exit: "Saiu da sala",
    area_entry: "Entrou na região",
    area_exit: "Saiu da região",
    fall_confirmed: "Queda confirmada",
    sitting_confirmed: "Pessoa sentada no chão",
};

const defaultOptions = {
    defaultColDef: {
        filter: true,
        cellClass: "d-flex align-items-center",
    },
    rowHeight: 50,
    animateRows: true,
    domLayout: "autoHeight",
    pagination: true,
    paginationPageSizeSelector: [10, 20, 50, 100, 200, 500],
    overlayNoRowsTemplate: "Sem resultados",
};

export function initGrids() {
    if (!eventsGridApi) {
        const eventsGridOptions = {
            columnDefs: [
                {
                    field: "timestamp",
                    headerName: "Data e Hora",
                    pinned: "left",
                },
                {
                    field: "alarm_type",
                    headerName: "Tipo de Evento",
                    flex: 1,
                    valueFormatter: ({ value }) =>
                        typeTranslations[value] ?? value,
                },
                {
                    field: "person_index",
                    headerName: "Pessoa",
                    valueFormatter: ({ value }) =>
                        value !== undefined ? value + 1 : "?",
                },
                { field: "region_id", headerName: "Região" },
                { field: "message", headerName: "Detalhes" },
            ],
            ...defaultOptions,
        };

        const gridDiv = document.querySelector("#events-grid");
        eventsGridApi = new agGrid.createGrid(gridDiv, eventsGridOptions);
    }

    if (!alarmsGridApi) {
        const alarmsGridOptions = {
            columnDefs: [
                {
                    field: "timestamp",
                    headerName: "Data e Hora",
                    pinned: "left",
                },
                {
                    field: "alarm_type",
                    headerName: "Tipo de Alarme",
                    flex: 1,
                    valueFormatter: ({ value }) =>
                        typeTranslations[value] ?? value,
                },
                {
                    field: "person_index",
                    headerName: "Pessoa",
                    valueFormatter: ({ value }) =>
                        value !== undefined ? value + 1 : "?",
                },
                { field: "region_id", headerName: "Região" },
                { field: "message", headerName: "Comentários" },
            ],
            ...defaultOptions,
        };

        const gridDiv = document.querySelector("#alarms-grid");
        alarmsGridApi = new agGrid.createGrid(gridDiv, alarmsGridOptions);
    }
}

export function clearGrids() {
    if (eventsGridApi) eventsGridApi.setGridOption("rowData", []);
    if (alarmsGridApi) alarmsGridApi.setGridOption("rowData", []);
}

export function addEvent(e) {
    if (!eventsGridApi) return;

    const layoutData = getMapCache(e.device_code);
    const regionName = layoutData
        ? getAreaName(layoutData, e.region_id ?? 0, "area")
        : "desconhecida";

    eventsGridApi.applyTransaction({
        add: [
            {
                alarm_type: e.alarm_type,
                person_index: e.person_index,
                region_id: regionName,
                message: e.message ?? "",
                timestamp: new Date().toLocaleString(),
            },
        ],
        addIndex: 0,
    });
}

export function addAlarm(a) {
    if (!alarmsGridApi) return;

    const layoutData = getMapCache(a.device_code);
    const regionName = layoutData
        ? getAreaName(layoutData, a.region_id ?? 0, "area")
        : "desconhecida";

    alarmsGridApi.applyTransaction({
        add: [
            {
                alarm_type: a.alarm_type,
                person_index: a.person_index,
                region_id: regionName,
                level: a.level,
                message: a.message ?? "",
                timestamp: new Date().toLocaleString(),
            },
        ],
        addIndex: 0,
    });
}
