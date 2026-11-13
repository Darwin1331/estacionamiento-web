import $ from 'jquery';

export function initParkingMap() {
    const root = document.getElementById('parking-map');
    const groups = $('#map-groups');
    const refresh = $('#map-refresh');
    const status = $('#map-update-status');
    const error = $('#map-error');
    const labels = {available: 'Disponible', occupied: 'Ocupado', inactive: 'Inactivo'};
    let timer = null;
    let pending = null;
    let stopped = false;
    let lastUpdate = null;

    function statusText() {
        const last = lastUpdate ? `Última actualización: ${lastUpdate}. ` : '';
        status.text(last + (document.hidden ? 'Actualización pausada mientras la pestaña está inactiva.' : 'Actualización automática cada 30 segundos.'));
    }

    function render(data) {
        const floors = new Map();
        const fragment = document.createDocumentFragment();
        for (const space of data) {
            let floor = floors.get(space.floor.id);
            if (!floor) {
                const section = $('<section class="card mb-3">');
                $('<div class="card-header">').append($('<h2 class="card-title">').text(space.floor.name)).appendTo(section);
                floor = {body: $('<div class="card-body">').appendTo(section), sectors: new Map()};
                floors.set(space.floor.id, floor);
                fragment.append(section[0]);
            }
            let sector = floor.sectors.get(space.sector.id);
            if (!sector) {
                const section = $('<section class="mb-3">').appendTo(floor.body);
                $('<h3 class="h4">').text(space.sector.name).appendTo(section);
                sector = $('<div class="parking-space-grid">').appendTo(section);
                floor.sectors.set(space.sector.id, sector);
            }
            const state = Object.hasOwn(labels, space.status) ? space.status : 'inactive';
            const cell = $('<div>').addClass(`parking-space parking-space-${state}`).attr('data-space-id', space.id);
            $('<div class="fw-bold">').text(space.code).appendTo(cell);
            $('<div class="small">').text(labels[state]).appendTo(cell);
            for (const plate of space.plates) $('<div class="fw-semibold mt-1">').text(plate).appendTo(cell);
            if (space.note) $('<div class="small mt-2">').text(space.note).appendTo(cell);
            sector.append(cell);
        }
        if (!data.length) fragment.append($('<div class="card card-body text-secondary">').text('No hay espacios registrados.')[0]);
        groups.empty().append(fragment);
    }

    function schedule() {
        clearTimeout(timer);
        if (!document.hidden && !stopped) timer = setTimeout(load, 30000);
    }

    function load() {
        if (pending || stopped || document.hidden) return;
        clearTimeout(timer);
        refresh.prop('disabled', true);
        groups.attr('aria-busy', 'true');
        pending = $.ajax({url: root.dataset.url, dataType: 'json', timeout: 15000, cache: false})
            .done(result => {
                render(result.data);
                for (const [key, value] of Object.entries(result.stats)) {
                    root.querySelector(`[data-map-stat="${key}"]`)?.replaceChildren(document.createTextNode(`${value}${key === 'occupancy_percent' ? '%' : ''}`));
                }
                lastUpdate = new Date(result.updated_at).toLocaleTimeString('es-BO');
                error.addClass('d-none').text('');
                statusText();
            })
            .fail(xhr => {
                if (xhr.statusText === 'abort') return;
                stopped = [401, 403, 419].includes(xhr.status);
                if (stopped) {
                    // Remove previously authorized vehicle information after access is revoked.
                    groups.empty();
                    root.querySelectorAll('[data-map-stat]').forEach(el => { el.textContent = '—'; });
                }
                error.removeClass('d-none').text(stopped
                    ? 'La sesión o los permisos cambiaron. Vuelve a iniciar sesión para consultar el mapa.'
                    : 'No se pudo actualizar el mapa. Los datos visibles pueden estar desactualizados. Se reintentará automáticamente.');
                status.text(stopped ? 'Actualización detenida.' : 'Actualización fallida. Puedes reintentar con Actualizar.');
            })
            .always(() => {
                pending = null;
                refresh.prop('disabled', stopped);
                groups.attr('aria-busy', 'false');
                schedule();
            });
    }

    refresh.on('click', load);
    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);
        if (stopped) return;
        statusText();
        if (!document.hidden) load();
    });
    window.addEventListener('pagehide', () => { clearTimeout(timer); stopped = true; pending?.abort(); });
    window.addEventListener('pageshow', event => { if (event.persisted) { stopped = false; load(); } });
    load();
}
