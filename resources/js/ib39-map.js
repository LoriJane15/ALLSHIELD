import L from 'leaflet';

/**
 * 39th-IB operational map — port of the legacy qgis2web/OpenLayers module at
 * accounts/39th-IB/final_mapping/.
 *
 * Same behaviour as the original:
 *   - Google Satellite basemap (identical XYZ endpoint).
 *   - Every barangay polygon filled rgba(0,255,0,0.5) with a rgba(35,35,35,1)
 *     hairline, which is what the legacy `scanAndUpdateBarangayColors()` painted.
 *   - Clicking a barangay opens the right-hand panel and loads its details plus
 *     the last five colour-history entries.
 *   - Layer switcher and a barangay/municipality search box.
 *
 * Rebuilt on Leaflet (already bundled) rather than shipping the 6.6MB
 * qgis2web OpenLayers bundle.
 */

const DAVAO_SUR = [6.7497, 125.3572];

// Legacy default fill: the "Recovery" green every barangay is painted on load.
const DEFAULT_FILL = 'rgba(0, 255, 0, 0.5)';
const STROKE = 'rgba(35,35,35,1.0)';

export function initIb39FullMap() {
    const el = document.getElementById('ib39FullMap');
    if (!el) return;

    if (el._leaflet_id) return;

    const map = L.map(el, { attributionControl: false }).setView(DAVAO_SUR, 10);

    const resize = () => map.invalidateSize();
    requestAnimationFrame(resize);
    window.addEventListener('load', resize);
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(resize).observe(el);

    // Same tile endpoint the legacy layers.js used for "Google Satellite".
    const satellite = L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
        maxZoom: 20,
    }).addTo(map);

    const hybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', { maxZoom: 20 });
    const streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 });

    const layerControl = L.control
        .layers({ 'Google Satellite': satellite, 'Google Hybrid': hybrid, Streets: streets }, null, {
            position: 'topright',
        })
        .addTo(map);

    const sidebar = document.getElementById('barangayDetail');
    const index = [];

    // Style/refresh state, so a live update can restyle without a reload.
    let areaData = {};
    const municipalityLayers = {};

    // Two colouring modes. Legacy painted EVERY polygon flat green (the default
    // here); "status" colours each barangay by its infestation rule colour.
    let colorMode = 'flat';

    const styleFor = (feature) => {
        let fill = DEFAULT_FILL;
        if (colorMode === 'status' && feature) {
            const area = areaData[keyFor(feature)];
            if (area?.color) fill = area.color;
        }

        return { fillColor: fill, fillOpacity: 1, color: STROKE, weight: 0.988 };
    };

    Promise.all([
        fetch(el.dataset.geojson).then((r) => r.json()),
        fetchAreas(el),
    ]).then(([geo, areas]) => {
        areaData = areas;

        // The legacy layers.js kept one toggleable vector layer per municipality,
        // so the layer switcher could show/hide each. Group the features the same way.
        const byMunicipality = {};
        geo.features.forEach((f) => {
            const m = f.properties.municipality || 'Unknown';
            (byMunicipality[m] ??= []).push(f);
        });

        const bounds = [];

        Object.keys(byMunicipality)
            .sort()
            .forEach((name) => {
                const lg = L.geoJSON(
                    { type: 'FeatureCollection', features: byMunicipality[name] },
                    {
                        style: styleFor,
                        onEachFeature: (f, lyr) => {
                            const p = f.properties;

                            // Legacy showed both: an attribute popup on the polygon
                            // and the history panel on the right.
                            lyr.bindPopup(() => popupHtml(p), { minWidth: 240 });
                            lyr.on('click', () => {
                                openDetail(el, sidebar, p.municipality, p.barangay);
                                fillPopup(el, lyr, p);
                            });
                            lyr.on('mouseover', () => lyr.setStyle({ weight: 2.5 }));
                            lyr.on('mouseout', () => lyr.setStyle({ weight: 0.988 }));

                            index.push({ name: `${p.barangay}, ${p.municipality}`, layer: lyr, props: p });
                        },
                    },
                ).addTo(map);

                municipalityLayers[`Barangay ( ${name} )`] = lg;
                bounds.push(lg.getBounds());
            });

        // Add the per-municipality overlays to the existing basemap switcher.
        Object.entries(municipalityLayers).forEach(([label, lg]) => layerControl.addOverlay(lg, label));

        map.fitBounds(bounds.reduce((acc, b) => acc.extend(b), L.latLngBounds(bounds[0])), { padding: [12, 12] });

        addSearch(map, el, sidebar, index);
        addColorToggle(map, () => {
            colorMode = colorMode === 'flat' ? 'status' : 'flat';
            Object.values(municipalityLayers).forEach((lg) => lg.setStyle(styleFor));
            return colorMode;
        });
        listenForUpdates(el, municipalityLayers, styleFor, (fresh) => {
            areaData = fresh;
        });
    });
}

/** A small control that flips the map between flat green and colour-by-status. */
function addColorToggle(map, toggle) {
    const control = L.control({ position: 'bottomright' });

    control.onAdd = () => {
        const wrap = L.DomUtil.create('div', 'ib39-color-toggle');
        wrap.innerHTML = '<button type="button">Colour: <strong>Flat</strong></button>';
        L.DomEvent.disableClickPropagation(wrap);

        wrap.querySelector('button').addEventListener('click', (e) => {
            const mode = toggle();
            e.currentTarget.querySelector('strong').textContent = mode === 'status' ? 'By status' : 'Flat';
        });

        return wrap;
    };

    control.addTo(map);
}

const fetchAreas = (el) =>
    fetch(el.dataset.areas, { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : {}))
        .catch(() => ({}));

/**
 * Live refresh. The legacy map held open an EventSource on rcsp_updates.php,
 * which polled frmap_barangays and told the client to repaint. Here the Add Area
 * page broadcasts on the `rcsp-areas` channel instead, and we restyle in place.
 */
function listenForUpdates(el, municipalityLayers, styleFor, onData) {
    if (!window.Echo) return;

    window.Echo.channel('rcsp-areas').listen('.area.updated', () => {
        fetchAreas(el).then((fresh) => {
            onData(fresh);
            Object.values(municipalityLayers).forEach((lg) => lg.setStyle(styleFor));
        });
    });
}

const keyFor = (f) => `${f.properties.municipality}|${f.properties.barangay}`;

/** Legacy showSidebar(): fetch the row + colour history, then slide the panel in. */
function openDetail(el, sidebar, municipality, barangay) {
    if (!sidebar) return;

    sidebar.classList.add('active');
    setText('province-value', '…');
    setText('municipality-value', municipality);
    setText('barangay-value', barangay);
    setText('status-value', '…');
    setText('fr-count-value', '…');

    const url = `${el.dataset.detail}?municipality=${encodeURIComponent(municipality)}&barangay=${encodeURIComponent(barangay)}`;

    fetch(url, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
            if (data.error) {
                setText('status-value', 'No record for this location');
                renderHistory([]);
                return;
            }

            const swatch = document.getElementById('infestation-color');
            if (swatch) swatch.style.backgroundColor = data.infestation_color || DEFAULT_FILL;

            setText('province-value', data.province ?? '—');
            setText('municipality-value', data.municipality ?? municipality);
            setText('barangay-value', data.barangay ?? barangay);
            setText(
                'status-value',
                (data.status || 'Not yet assessed') + (data.is_rcsp ? ' · RCSP barangay' : ''),
            );
            setText('fr-count-value', String(data.frs ?? 0));

            renderHistory(data.color_history || []);
        })
        .catch(() => {
            setText('status-value', 'Could not load details');
            renderHistory([]);
        });
}

function renderHistory(items) {
    const box = document.querySelector('.color-history');
    if (!box) return;

    if (!items.length) {
        box.innerHTML = '<div class="no-history">No history available</div>';
        return;
    }

    box.innerHTML = items
        .map(
            (item) => `
            <div class="history-entry">
                <div class="history-dot" style="background-color: ${escapeAttr(item.color)};"></div>
                <div class="history-content">
                    <div class="history-status">${escapeHtml(item.status)}</div>
                    <div class="history-fr">FR's: ${escapeHtml(item.frs ?? '0')}</div>
                    <div class="history-timestamp">${escapeHtml(item.formatted_timestamp ?? '')}</div>
                </div>
            </div>`,
        )
        .join('');
}

function addSearch(map, el, sidebar, index) {
    const control = L.control({ position: 'topleft' });

    control.onAdd = () => {
        const wrap = L.DomUtil.create('div', 'ib39-map-search');
        wrap.innerHTML = '<input type="search" placeholder="Search now" aria-label="Search barangay or municipality"><ul hidden></ul>';

        L.DomEvent.disableClickPropagation(wrap);
        L.DomEvent.disableScrollPropagation(wrap);

        const input = wrap.querySelector('input');
        const list = wrap.querySelector('ul');

        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            list.innerHTML = '';

            if (q.length < 2) {
                list.hidden = true;
                return;
            }

            index
                .filter((e) => e.name.toLowerCase().includes(q))
                .slice(0, 8)
                .forEach((e) => {
                    const li = document.createElement('li');
                    li.textContent = e.name;
                    li.addEventListener('click', () => {
                        map.fitBounds(e.layer.getBounds(), { maxZoom: 14 });
                        openDetail(el, sidebar, e.props.municipality, e.props.barangay);
                        list.hidden = true;
                        input.value = e.name;
                    });
                    list.appendChild(li);
                });

            list.hidden = list.childElementCount === 0;
        });

        return wrap;
    };

    control.addTo(map);
}

/** Attribute popup on the polygon, as the legacy qgis2web map showed. */
function popupHtml(p, data = null) {
    const cell = (label, value) =>
        `<tr><th>${escapeHtml(label)}</th><td>${escapeHtml(value ?? '—')}</td></tr>`;

    return (
        `${data?.is_rcsp ? '<span class="rcsp-badge">RCSP</span>' : ''}` +
        '<table class="ib39-popup-table">' +
        cell('Barangay', p.barangay) +
        cell('Municipality', p.municipality) +
        cell('Province', data?.province ?? 'Davao del Sur') +
        cell("FR's", data ? data.frs : '…') +
        cell('Status', data ? (data.status ?? 'Not yet assessed') : '…') +
        '</table>'
    );
}

/** Fill the popup with the live figures once the detail request returns. */
function fillPopup(el, lyr, p) {
    const url = `${el.dataset.detail}?municipality=${encodeURIComponent(p.municipality)}&barangay=${encodeURIComponent(p.barangay)}`;

    fetch(url, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
            if (!data.error) lyr.setPopupContent(popupHtml(p, data));
        })
        .catch(() => {});
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

const escapeHtml = (s) =>
    String(s ?? '').replace(/[&<>"']/g, (ch) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);

const escapeAttr = (s) => escapeHtml(s).replace(/`/g, '&#96;');
