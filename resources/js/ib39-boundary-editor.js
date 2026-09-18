import L from 'leaflet';
import '@geoman-io/leaflet-geoman-free';

const DAVAO_SUR = [6.7497, 125.3572];
const FILL = 'rgba(0, 255, 0, 0.5)';
const STROKE = 'rgba(35,35,35,1.0)';

/**
 * Boundary editor — draw, reshape, add and delete barangay polygons, saving
 * each change straight to the database (Phase 2 of the map-control work).
 *
 * Leaflet-Geoman provides the drawing/editing toolbar. window.axios carries the
 * CSRF token automatically via the XSRF-TOKEN cookie, so the API calls are plain
 * PUT/POST/DELETE against the boundary endpoints.
 */
export function initIb39BoundaryEditor() {
    const wrap = document.querySelector('.editor-wrap');
    const el = document.getElementById('boundaryMap');
    if (!wrap || !el || el._leaflet_id) return;

    const map = L.map(el, { attributionControl: false }).setView(DAVAO_SUR, 10);
    const resize = () => map.invalidateSize();
    requestAnimationFrame(resize);
    window.addEventListener('load', resize);
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(resize).observe(el);

    L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', { maxZoom: 20 }).addTo(map);

    const baseStyle = { fillColor: FILL, fillOpacity: 0.55, color: STROKE, weight: 1 };
    const layerGroup = L.featureGroup().addTo(map);

    // Populate the municipality datalist for the naming dialog.
    try {
        const munis = JSON.parse(wrap.dataset.municipalities || '[]');
        const list = document.getElementById('municipalityList');
        if (list) list.innerHTML = munis.map((m) => `<option value="${escapeAttr(m)}">`).join('');
    } catch { /* empty */ }

    // --- load existing boundaries -------------------------------------------
    fetch(wrap.dataset.boundaries, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((fc) => {
            L.geoJSON(fc, {
                style: () => baseStyle,
                onEachFeature: (feature, layer) => {
                    layer._areaId = feature.properties?.id ?? null;
                    layer._areaName = `${feature.properties?.barangay ?? ''}, ${feature.properties?.municipality ?? ''}`;
                    layer.bindTooltip(layer._areaName, { sticky: true });
                    wireLayer(layer);
                    layerGroup.addLayer(layer);
                },
            });

            if (layerGroup.getLayers().length) map.fitBounds(layerGroup.getBounds(), { padding: [16, 16] });
        });

    // --- Geoman toolbar ------------------------------------------------------
    map.pm.addControls({
        position: 'topleft',
        drawMarker: false,
        drawCircle: false,
        drawCircleMarker: false,
        drawPolyline: false,
        drawRectangle: false,
        drawText: false,
        cutPolygon: true,
        rotateMode: false,
    });
    map.pm.setGlobalOptions({ snappable: true, snapDistance: 15 });

    // --- a brand-new polygon was drawn --------------------------------------
    let pendingNewLayer = null;

    map.on('pm:create', (e) => {
        const layer = e.layer;
        layer.setStyle?.(baseStyle);
        pendingNewLayer = layer;

        // Ask for municipality + barangay, then POST.
        openNameModal((municipality, barangay) => {
            const geometry = layer.toGeoJSON().geometry;

            window.axios.post(wrap.dataset.store, { municipality, barangay, geometry })
                .then(({ data }) => {
                    layer._areaId = data.id;
                    layer._areaName = `${barangay}, ${municipality}`;
                    layer.bindTooltip(layer._areaName, { sticky: true });
                    wireLayer(layer);
                    layerGroup.addLayer(layer);
                    flash(`Added ${layer._areaName}`);
                })
                .catch(() => { map.removeLayer(layer); flash('Could not save the new area', true); });
            pendingNewLayer = null;
        }, () => {
            // cancelled → discard the drawn shape
            if (pendingNewLayer) { map.removeLayer(pendingNewLayer); pendingNewLayer = null; }
        });
    });

    // --- per-layer edit + remove handlers -----------------------------------
    function wireLayer(layer) {
        layer.on('pm:update', () => saveGeometry(layer));
        layer.on('pm:cut', (e) => {
            // Cut replaces the layer with a new one; keep the id and re-save.
            if (e.layer && layer._areaId) {
                e.layer._areaId = layer._areaId;
                e.layer._areaName = layer._areaName;
                wireLayer(e.layer);
                saveGeometry(e.layer);
            }
        });
        layer.on('pm:remove', () => removeArea(layer));
    }

    function saveGeometry(layer) {
        if (!layer._areaId) return;
        const url = wrap.dataset.updateTemplate.replace('__ID__', layer._areaId);
        window.axios.put(url, { geometry: layer.toGeoJSON().geometry })
            .then(() => flash(`Saved ${layer._areaName || 'area'}`))
            .catch(() => flash('Save failed', true));
    }

    function removeArea(layer) {
        if (!layer._areaId) return;
        const url = wrap.dataset.destroyTemplate.replace('__ID__', layer._areaId);
        window.axios.delete(url)
            .then(({ data }) => flash(data.deleted ? `Deleted ${layer._areaName}` : `Cleared ${layer._areaName}`))
            .catch(() => flash('Delete failed', true));
    }
}

// --- naming dialog ----------------------------------------------------------
function openNameModal(onSave, onCancel) {
    const modalEl = document.getElementById('newAreaModal');
    const form = document.getElementById('newAreaForm');
    if (!modalEl || !form || !window.bootstrap) { onCancel?.(); return; }

    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    let saved = false;

    const submit = (ev) => {
        ev.preventDefault();
        const m = document.getElementById('newMunicipality').value.trim();
        const b = document.getElementById('newBarangay').value.trim();
        if (!m || !b) return;
        saved = true;
        form.reset();
        modal.hide();
        onSave(m, b);
    };

    const onHidden = () => {
        form.removeEventListener('submit', submit);
        modalEl.removeEventListener('hidden.bs.modal', onHidden);
        if (!saved) onCancel?.();
    };

    form.addEventListener('submit', submit);
    modalEl.addEventListener('hidden.bs.modal', onHidden);
    modal.show();
}

// --- toast ------------------------------------------------------------------
let flashTimer;
function flash(message, isError = false) {
    const box = document.getElementById('editorStatus');
    if (!box) return;
    box.textContent = message;
    box.style.background = isError ? '#b91c1c' : '#0f172a';
    box.classList.add('show');
    clearTimeout(flashTimer);
    flashTimer = setTimeout(() => box.classList.remove('show'), 2200);
}

const escapeAttr = (s) => String(s ?? '').replace(/"/g, '&quot;');
