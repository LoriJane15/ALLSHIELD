import L from 'leaflet';
import 'leaflet.markercluster';
import { Chart, registerables } from 'chart.js';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

Chart.register(...registerables);

// Fix Leaflet's default marker asset paths under Vite bundling.
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

const DAVAO_SUR = [6.7497, 125.3572];

function token() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

async function postJson(url, body, method = 'POST') {
    const isForm = body instanceof FormData;
    const res = await fetch(url, {
        method,
        headers: {
            'X-CSRF-TOKEN': token(),
            'Accept': 'application/json',
            ...(isForm ? {} : { 'Content-Type': 'application/json' }),
        },
        body: isForm ? body : JSON.stringify(body),
    });
    return res.json().catch(() => ({}));
}

// -- Municipality -> barangay cascade (register/edit forms) -------------
export function initFormCascade() {
    const muni = document.querySelector('[data-barangay-source]');
    if (!muni) return;
    const target = document.querySelector(muni.dataset.barangayTarget);
    muni.addEventListener('change', async () => {
        target.innerHTML = '<option value="">Loading…</option>';
        if (!muni.value) {
            target.innerHTML = '<option value="">Select barangay</option>';
            return;
        }
        const url = `${muni.dataset.barangaySource}?municipality_id=${muni.value}`;
        const rows = await fetch(url, { headers: { Accept: 'application/json' } }).then((r) => r.json());
        target.innerHTML = '<option value="">Select barangay</option>'
            + rows.map((b) => `<option value="${b.id}">${b.name}</option>`).join('');
    });
}

// -- Dashboard: charts + clustered map ----------------------------------
export function initDashboard() {
    const el = document.getElementById('mblrcData');
    if (!el) return;

    fetch(el.dataset.analytics, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((d) => {
            lineChart('programChart', d.labels, [
                dataset('Not-Started', d.program.not_started, '#98a2b3'),
                dataset('On-going', d.program.ongoing, '#f79009'),
                dataset('Completed', d.program.completed, '#039855'),
            ]);
            lineChart('overallChart', d.labels, [
                dataset('Registered', d.overall.registered, '#2c4199'),
                dataset('Reintegrated', d.overall.reintegrated, '#12b76a'),
            ]);
        });

    initFrMap();
}

function dataset(label, data, color) {
    return { label, data, borderColor: color, backgroundColor: color + '22', tension: 0.35, fill: true, pointRadius: 3 };
}

function lineChart(id, labels, datasets) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
}

function initFrMap() {
    const mapEl = document.getElementById('frMap');
    if (!mapEl) return;
    const map = L.map(mapEl).setView(DAVAO_SUR, 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap', maxZoom: 19,
    }).addTo(map);

    const cluster = L.markerClusterGroup();
    map.addLayer(cluster);
    let all = [];

    fetch(mapEl.dataset.locations, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((rows) => { all = rows; render(rows); });

    function render(rows) {
        cluster.clearLayers();
        rows.forEach((fr) => {
            L.marker([fr.lat, fr.lng]).bindPopup(
                `<strong>${fr.name}</strong><br>${fr.status} · ${fr.batch ?? ''}<br>`
                + `${fr.address ?? ''}<br><a href="${fr.url}" class="text-blue-600">View profile</a>`
            ).addTo(cluster);
        });
        if (rows.length) map.fitBounds(cluster.getBounds().pad(0.2));
    }

    const search = document.getElementById('mapSearch');
    const status = document.getElementById('mapStatusFilter');
    const apply = () => {
        const q = (search?.value ?? '').toLowerCase();
        const s = status?.value ?? '';
        render(all.filter((fr) =>
            (!q || fr.name.toLowerCase().includes(q))
            && (!s || fr.status === s)));
    };
    search?.addEventListener('input', apply);
    status?.addEventListener('change', apply);
}

// -- Profile page interactions ------------------------------------------
export function initProfile() {
    const root = document.getElementById('frProfile');
    if (!root) return;

    // Program status
    root.querySelector('[data-program-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const r = await postJson(root.dataset.programStatus, {
            reintegration_status: f.reintegration_status.value,
            reintegration_date: f.reintegration_date.value || null,
        }, 'PUT');
        if (r.success) location.reload();
    });

    initLocationMap(root);
    initSkills(root);
    initAssistance(root);

    // Education / work
    root.querySelector('[data-education-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const r = await postJson(root.dataset.educationStore, {
            educational_attainment: f.educational_attainment.value,
            occupation: f.occupation.value,
        });
        if (r.success) location.reload();
    });
}

function initLocationMap(root) {
    const mapEl = document.getElementById('frLocationMap');
    if (!mapEl) return;
    const lat = parseFloat(root.dataset.lat) || DAVAO_SUR[0];
    const lng = parseFloat(root.dataset.lng) || DAVAO_SUR[1];
    const map = L.map(mapEl).setView([lat, lng], root.dataset.lat ? 14 : 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap', maxZoom: 19,
    }).addTo(map);

    const form = root.querySelector('[data-location-form]');
    let marker = root.dataset.lat ? L.marker([lat, lng]).addTo(map) : null;

    map.on('click', (e) => {
        const { lat, lng } = e.latlng;
        if (marker) marker.setLatLng(e.latlng);
        else marker = L.marker(e.latlng).addTo(map);
        form.latitude.value = lat.toFixed(8);
        form.longitude.value = lng.toFixed(8);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.latitude.value) return alert('Click the map to set a location first.');
        const r = await postJson(root.dataset.locationSave, {
            placement_address: form.placement_address.value,
            latitude: form.latitude.value,
            longitude: form.longitude.value,
        });
        if (r.success) location.reload();
    });

    // History
    fetch(root.dataset.locationHistory, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((rows) => {
            const box = root.querySelector('[data-location-history]');
            if (!box) return;
            box.innerHTML = rows.length
                ? '<p class="font-semibold text-slate-600">History</p>' + rows.map((h) =>
                    `<p>• ${h.placement_address ?? ''} <span class="text-slate-400">(${h.updated_by ?? ''})</span></p>`).join('')
                : '';
        });
}

function initSkills(root) {
    const list = root.querySelector('[data-skills-list]');

    // suggestions
    fetch(root.dataset.skillsSuggest, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((rows) => {
            const dl = document.getElementById('skillSuggestions');
            if (dl) dl.innerHTML = rows.map((s) => `<option value="${s}">`).join('');
        });

    root.querySelector('[data-skill-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const r = await postJson(root.dataset.skillsStore, {
            skill_name: f.skill_name.value,
            proficiency_level: f.proficiency_level.value,
        });
        if (r.success) location.reload();
    });

    list?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-skill-delete]');
        if (!btn) return;
        const r = await postJson(btn.dataset.skillDelete, {}, 'DELETE');
        if (r.success) btn.closest('[data-skill-id]').remove();
    });
}

function initAssistance(root) {
    const list = root.querySelector('[data-assistance-list]');

    root.querySelector('[data-assistance-form]')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const r = await postJson(root.dataset.assistanceStore, new FormData(e.target));
        if (r.success) location.reload();
    });

    list?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-assistance-delete]');
        if (!btn) return;
        const r = await postJson(btn.dataset.assistanceDelete, {}, 'DELETE');
        if (r.success) location.reload();
    });
}
