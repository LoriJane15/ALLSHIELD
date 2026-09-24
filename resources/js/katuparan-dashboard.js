import L from 'leaflet';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const DAVAO_SUR = [6.7497, 125.3572];

// Legacy default fill: the "Recovery" green every barangay is painted on load.
const DEFAULT_FILL = 'rgba(0, 255, 0, 0.5)';
const STROKE = 'rgba(35,35,35,1.0)';

/**
 * Katuparan Center dashboard.
 *
 * Reproduces the legacy accounts/katuparan_center/index.php hero: a satellite
 * basemap with the Davao del Sur barangay polygons drawn over it, a search box
 * and a basemap toggle. The legacy page iframed the 39th-IB qgis2web module for
 * this; it is rendered inline here so it shares the app's data and styling.
 */
export function initKatuparanDashboard() {
    initHeroMap();
    initProgressChart();
}

function initHeroMap() {
    const el = document.getElementById('heroMap');
    if (!el) return;

    // Guard against a second init on the same element, which Leaflet throws on.
    if (el._leaflet_id) return;

    const map = L.map(el, { zoomControl: true, attributionControl: false }).setView(DAVAO_SUR, 9);

    // The hero is sized in vh inside an absolutely-positioned container, so
    // Leaflet can measure it before layout settles and render nothing. Recheck
    // on the next frame, on load, and whenever the box actually resizes.
    const resize = () => map.invalidateSize();
    requestAnimationFrame(resize);
    window.addEventListener('load', resize);

    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(resize).observe(el);
    }

    // Same tile endpoint the legacy final_mapping module used, since the legacy
    // hero iframed exactly that module.
    const satellite = L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
        maxZoom: 20,
    }).addTo(map);

    const streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 });

    L.control.layers({ 'Google Satellite': satellite, Streets: streets }, null, { position: 'topright' }).addTo(map);

    Promise.all([
        fetch(el.dataset.geojson).then((r) => r.json()),
        fetch(el.dataset.areas, { headers: { Accept: 'application/json' } })
            .then((r) => (r.ok ? r.json() : {}))
            .catch(() => ({})),
    ]).then(([geo, areas]) => {
        const index = [];

        const layer = L.geoJSON(geo, {
            // The legacy hero iframed the 39th-IB module, whose
            // scanAndUpdateBarangayColors() painted every polygon this same
            // green — the stored infestation_color never reaches the map.
            style: () => ({
                fillColor: DEFAULT_FILL,
                fillOpacity: 1,
                color: STROKE,
                weight: 0.988,
            }),
            onEachFeature: (f, lyr) => {
                const p = f.properties;
                const area = areas[`${p.municipality}|${p.barangay}`];
                lyr.bindPopup(
                    `<strong>${p.barangay}</strong><br>${p.municipality}` +
                    (area ? `<br>${area.frs} FR${area.frs === 1 ? '' : 's'} · ${area.status ?? 'Not yet assessed'}` : ''),
                );
                index.push({ name: `${p.barangay}, ${p.municipality}`, layer: lyr });
            },
        }).addTo(map);

        map.fitBounds(layer.getBounds(), { padding: [10, 10] });
        addSearch(map, index);
    });
}

/** Legacy hero had a "Search for barangay or municipality" box over the map. */
function addSearch(map, index) {
    const control = L.control({ position: 'topleft' });

    control.onAdd = () => {
        const wrap = L.DomUtil.create('div', 'hero-map-search');
        wrap.innerHTML = `
            <input type="search" placeholder="Search for barangay or municipality" aria-label="Search the map">
            <ul hidden></ul>`;

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
                        map.fitBounds(e.layer.getBounds(), { maxZoom: 13 });
                        e.layer.openPopup();
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

function initProgressChart() {
    const canvas = document.getElementById('rcspProgressChart');
    if (!canvas) return;

    const read = (key) => {
        try {
            return JSON.parse(canvas.dataset[key] || '[]');
        } catch {
            return [];
        }
    };

    const ctx = canvas.getContext('2d');

    // The legacy chart used vertical gradients for both series.
    const blue = ctx.createLinearGradient(0, 0, 0, 400);
    blue.addColorStop(0, '#38bdf8');
    blue.addColorStop(1, '#0ea5e9');

    const green = ctx.createLinearGradient(0, 0, 0, 400);
    green.addColorStop(0, '#34d399');
    green.addColorStop(1, '#059669');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: read('labels'),
            datasets: [
                { label: 'Completed', data: read('completed'), backgroundColor: blue, borderRadius: 8, borderSkipped: false },
                { label: 'In Progress', data: read('inprogress'), backgroundColor: green, borderRadius: 8, borderSkipped: false },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(59, 130, 246, 0.2)',
                    borderWidth: 1,
                    cornerRadius: 12,
                    padding: 12,
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 12, weight: '500' } } },
                y: {
                    beginAtZero: true,
                    ticks: { color: '#64748b', stepSize: 1, precision: 0 },
                    title: { display: true, text: 'Number of RCSP Barangays', color: '#64748b' },
                },
            },
        },
    });
}
