import { getAreaName, getMapCache } from "./utils.js";

let eventsGridApi = null;
let alarmsGridApi = null;

const typeTranslations = {
    room_entry: "Entrou na sala",
    room_exit: "Saiu da sala",
    area_entry: "Entrou na zona",
    area_exit: "Saiu da zona",
    fall_confirmed: "Queda confirmada",
    sitting_confirmed: "Pessoa sentada no chão",
};

const defaultOptions = {
    defaultColDef: {
        cellClass: "d-flex align-items-center",
    },
    rowHeight: 50,
    animateRows: true,
    domLayout: "autoHeight",
    pagination: true,
    paginationPageSize: 10,
    theme: "ag-theme-quartz",
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
                    field: "type",
                    headerName: "Tipo de Evento",
                    flex: 1,
                    valueFormatter: ({ value }) =>
                        typeTranslations[value] ?? value,
                },
                { field: "region_id", headerName: "Região" },
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
                    field: "type",
                    headerName: "Tipo de Alarme",
                    flex: 1,
                    valueFormatter: ({ value }) =>
                        typeTranslations[value] ?? value,
                },
                { field: "region_id", headerName: "Região" },
                { field: "cause", headerName: "Causa" },
                { field: "comments", headerName: "Comentários" },
                {
                    headerName: "Opções",
                    cellRenderer: () => {
                        const btn = document.createElement("button");
                        btn.className = "btn btn-outline-primary btn-sm me-2";
                        btn.innerHTML =
                            "<i class='fa-solid fa-pencil me-2'></i> Resolver";
                        return btn;
                    },
                },
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
                type: e.type,
                region_id: regionName,
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
                type: a.type,
                region_id: regionName,
                timestamp: new Date().toLocaleString(),
            },
        ],
        addIndex: 0,
    });
}
