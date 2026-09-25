import L from 'leaflet';
import 'leaflet.markercluster';
import { Chart, registerables } from 'chart.js';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

Chart.register(...registerables);

// Fix Leaflet's default marker asset paths under Vite bundling.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

const DAVAO_SUR = [6.7497, 125.3572];

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (ch) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
}

function escapeAttr(s) {
    return escapeHtml(s).replace(/`/g, '&#96;');
}

function createShieldPinIcon(status = '') {
    let pinBg = '#312e81'; // Default SHIELD Deep Navy
    if (status === 'Completed') pinBg = '#2563eb'; // Blue
    else if (status === 'On-going') pinBg = '#d97706'; // Amber
    else if (status === 'Not-Started') pinBg = '#e11d48'; // Rose/Red

    return L.divIcon({
        className: 'mblrc-pin-wrapper',
        html: `
            <div style="
                position: relative;
                width: 32px;
                height: 32px;
                background: ${pinBg};
                border: 2.5px solid #ffffff;
                border-radius: 50% 50% 50% 0;
                transform: rotate(-45deg);
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.4);
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <svg style="transform: rotate(45deg); width: 15px; height: 15px; fill: #ffffff;" viewBox="0 0 24 24">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                </svg>
            </div>
        `,
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32],
    });
}

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

    const cluster = L.markerClusterGroup({
        showCoverageOnHover: false,
        maxClusterRadius: 40,
    });
    map.addLayer(cluster);
    let all = [];

    fetch(mapEl.dataset.locations, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((rows) => { all = rows; render(rows); });

    function render(rows) {
        cluster.clearLayers();
        rows.forEach((fr) => {
            const pinIcon = createShieldPinIcon(fr.status);
            const marker = L.marker([fr.lat, fr.lng], { icon: pinIcon });

            let badgeStyle = 'background: #eef2ff; color: #312e81; border: 1px solid #c7d2fe;';
            if (fr.status === 'Completed') {
                badgeStyle = 'background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe;';
            } else if (fr.status === 'On-going') {
                badgeStyle = 'background: #fffbeb; color: #78350f; border: 1px solid #fde68a;';
            } else if (fr.status === 'Not-Started') {
                badgeStyle = 'background: #fff1f2; color: #881337; border: 1px solid #fecdd3;';
            }

            marker.bindPopup(`
                <div style="font-family: inherit; padding: 4px; min-width: 190px;">
                    <div style="font-weight: 800; color: #0f172a; font-size: 0.95rem; line-height: 1.3; margin-bottom: 4px;">
                        ${escapeHtml(fr.name)}
                    </div>
                    <div style="display: inline-block; font-size: 0.725rem; font-weight: 750; padding: 2px 8px; border-radius: 9999px; margin-bottom: 6px; ${badgeStyle}">
                        ${escapeHtml(fr.status || 'Active')} ${fr.batch ? '· ' + escapeHtml(fr.batch) : ''}
                    </div>
                    <div style="font-size: 0.8125rem; color: #64748b; margin-bottom: 10px; line-height: 1.4;">
                        <span style="color: #4338ca; font-weight: bold;">📍</span> ${escapeHtml(fr.address || 'No address registered')}
                    </div>
                    <div>
                        <a href="${escapeAttr(fr.url)}" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.775rem; font-weight: 750; color: #ffffff; background: #312e81; padding: 5px 12px; border-radius: 6px; text-decoration: none !important; box-shadow: 0 2px 6px rgba(49, 46, 129, 0.25);">
                            View Profile &rarr;
                        </a>
                    </div>
                </div>
            `).addTo(cluster);
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

    const form = root.querySelector('[data-location-form]');
    if (!form) return;

    const address = form.elements.namedItem('placement_address');
    const landmark = form.elements.namedItem('landmark');
    const latitude = form.elements.namedItem('latitude');
    const longitude = form.elements.namedItem('longitude');
    const status = root.querySelector('[data-location-status]');
    const saveButton = root.querySelector('[data-location-save-button]');
    const savedLatitude = parseFloat(root.dataset.lat);
    const savedLongitude = parseFloat(root.dataset.lng);
    const hasSavedLocation = root.dataset.hasSavedLocation === '1'
        && Number.isFinite(savedLatitude)
        && Number.isFinite(savedLongitude);
    const map = L.map(mapEl).setView(
        hasSavedLocation ? [savedLatitude, savedLongitude] : DAVAO_SUR,
        hasSavedLocation ? 14 : 10,
    );
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap', maxZoom: 19,
    }).addTo(map);

    const pinIcon = createShieldPinIcon('#312e81');
    let marker = null;
    let resolvedKey = null;
    let pendingLookupKey = null;
    let lookupTimer = null;
    let lookupSequence = 0;

    const geotagTab = document.querySelector('[data-bs-toggle="tab"][href="#tab-geotag"]');
    const refreshMapLayout = () => {
        window.requestAnimationFrame(() => {
            map.invalidateSize();
            if (marker) {
                map.setView(marker.getLatLng(), map.getZoom(), { animate: false });
            }
        });
    };
    geotagTab?.addEventListener('shown.bs.tab', refreshMapLayout);

    const currentKey = () => `${address.value.trim()}\n${landmark.value.trim()}`;

    const setStatus = (message, tone = 'muted') => {
        if (!status) return;
        status.textContent = message;
        status.classList.remove('text-muted', 'text-success', 'text-danger');
        status.classList.add(`text-${tone}`);
    };

    const setSaveEnabled = (enabled) => {
        if (saveButton) saveButton.disabled = !enabled;
    };

    const clearPreview = () => {
        latitude.value = '';
        longitude.value = '';
        if (marker) {
            map.removeLayer(marker);
            marker = null;
        }
    };

    const showPreview = (result, zoom = 16) => {
        const lat = parseFloat(result.latitude);
        const lng = parseFloat(result.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return false;

        const point = [lat, lng];
        if (marker) marker.setLatLng(point);
        else marker = L.marker(point, { icon: pinIcon }).addTo(map);
        latitude.value = lat.toFixed(8);
        longitude.value = lng.toFixed(8);
        map.setView(point, zoom);
        return true;
    };

    const lookupLocation = async ({ registrationAddress = false } = {}) => {
        const sequence = ++lookupSequence;
        const addressValue = address.value.trim();
        const landmarkValue = landmark.value.trim();
        const key = registrationAddress ? null : currentKey();

        if (!registrationAddress && !addressValue) {
            clearPreview();
            resolvedKey = null;
            setSaveEnabled(false);
            setStatus('Enter a placement address to locate it on the map.');
            return false;
        }

        if (!registrationAddress && (resolvedKey === key || pendingLookupKey === key)) {
            return false;
        }

        pendingLookupKey = key;

        setSaveEnabled(false);
        setStatus(registrationAddress
            ? 'Locating the saved residential address…'
            : 'Locating the placement address…');

        try {
            const result = await postJson(root.dataset.locationGeocode, registrationAddress
                ? { registration_address: true }
                : { address: addressValue, landmark: landmarkValue || null });

            if (sequence !== lookupSequence) return false;

            if (!result.success || !showPreview(result, registrationAddress ? 15 : 16)) {
                clearPreview();
                resolvedKey = null;
                setStatus(result.message || 'Location could not be found. Please provide a more specific address or landmark.', 'danger');
                return false;
            }

            resolvedKey = registrationAddress ? null : currentKey();
            setSaveEnabled(!registrationAddress);
            if (registrationAddress && result.match_level === 'municipality') {
                setStatus(`Exact registered address could not be located. Showing the municipality area: ${result.display_name}`, 'success');
            } else if (registrationAddress) {
                setStatus(`Matched registered location: ${result.display_name}`, 'success');
            } else {
                setStatus(`Matched location: ${result.display_name}`, 'success');
            }
            return true;
        } catch (error) {
            if (sequence !== lookupSequence) return false;
            clearPreview();
            resolvedKey = null;
            setSaveEnabled(false);
            setStatus('Location lookup is temporarily unavailable. Please try again.', 'danger');
            return false;
        } finally {
            if (!registrationAddress && pendingLookupKey === key) pendingLookupKey = null;
        }
    };

    const queueLookup = () => {
        window.clearTimeout(lookupTimer);
        lookupSequence++;
        resolvedKey = null;
        clearPreview();
        setSaveEnabled(false);

        if (!address.value.trim()) {
            setStatus('Enter a placement address to locate it on the map.');
            return;
        }

        setStatus('Waiting to locate the updated address…');
        lookupTimer = window.setTimeout(() => {
            lookupTimer = null;
            lookupLocation();
        }, 700);
    };

    [address, landmark].forEach((input) => {
        input.addEventListener('input', queueLookup);
        input.addEventListener('blur', (event) => {
            if (event.relatedTarget === saveButton) return;
            const key = currentKey();
            if (resolvedKey === key || pendingLookupKey === key) return;
            window.clearTimeout(lookupTimer);
            lookupTimer = null;
            lookupLocation();
        });
    });

    if (hasSavedLocation) {
        showPreview({ latitude: savedLatitude, longitude: savedLongitude }, 14);
        resolvedKey = currentKey();
        setSaveEnabled(Boolean(address.value.trim()));
        setStatus(`Saved location: ${address.value.trim() || 'Coordinates available'}`);
    } else {
        setSaveEnabled(false);
        lookupLocation({ registrationAddress: true });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        window.clearTimeout(lookupTimer);

        if (!address.value.trim()) {
            setStatus('Enter a placement address before saving.', 'danger');
            return;
        }

        if (resolvedKey !== currentKey() && !await lookupLocation()) return;

        setSaveEnabled(false);
        setStatus('Confirming and saving the matched location…');

        try {
            const result = await postJson(root.dataset.locationSave, {
                placement_address: address.value.trim(),
                landmark: landmark.value.trim() || null,
            });
            if (result.success) {
                location.reload();
                return;
            }

            setStatus(result.message || 'Location could not be saved. Please try again.', 'danger');
            setSaveEnabled(true);
        } catch (error) {
            setStatus('Location lookup is temporarily unavailable. Please try again.', 'danger');
            setSaveEnabled(true);
        }
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
