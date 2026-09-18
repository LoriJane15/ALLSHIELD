import L from 'leaflet';
import '@geoman-io/leaflet-geoman-free';

const DAVAO_SUR = [6.7497, 125.3572];
const FILL = 'rgba(0, 255, 0, 0.5)';
const STROKE = 'rgba(35,35,35,1.0)';

/**
 * Boundary editor with a safety net (Phase: draft + snapshots).
 *
 * Edits do NOT touch the live map. They accumulate in an in-memory
 * FeatureCollection that is autosaved to a single server-side "draft", so the
 * map everyone else sees is unchanged until you Publish. You can Discard the
 * draft, save named Checkpoints, and Restore any checkpoint (including the
 * protected "Original map"). Leaflet-Geoman provides the drawing tools;
 * window.axios carries the CSRF token via the XSRF-TOKEN cookie.
 */
export function initIb39BoundaryEditor() {
    const wrap = document.querySelector('.editor-wrap');
    const el = document.getElementById('boundaryMap');
    if (!wrap || !el || el._leaflet_id) return;

    const routes = {
        draft: wrap.dataset.draft,
        publish: wrap.dataset.publish,
        snapshots: wrap.dataset.snapshots,
        snapshotCreate: wrap.dataset.snapshotCreate,
        snapshotRestore: wrap.dataset.snapshotRestore, // has __ID__
        snapshotDestroy: wrap.dataset.snapshotDestroy, // has __ID__
    };

    const map = L.map(el, { attributionControl: false }).setView(DAVAO_SUR, 10);
    const resize = () => map.invalidateSize();
    requestAnimationFrame(resize);
    window.addEventListener('load', resize);
    if (typeof ResizeObserver !== 'undefined') new ResizeObserver(resize).observe(el);

    L.tileLayer('https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', { maxZoom: 20 }).addTo(map);

    const baseStyle = { fillColor: FILL, fillOpacity: 0.55, color: STROKE, weight: 1 };
    let layerGroup = L.featureGroup().addTo(map);

    // municipality datalist for the naming dialog
    try {
        const munis = JSON.parse(wrap.dataset.municipalities || '[]');
        const list = document.getElementById('municipalityList');
        if (list) list.innerHTML = munis.map((m) => `<option value="${escapeAttr(m)}">`).join('');
    } catch { /* empty */ }

    // --- load (draft if present, else live) ---------------------------------
    function loadFeatures() {
        layerGroup.clearLayers();

        return fetch(routes.draft, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((res) => {
                renderFC(res.featureCollection);
                setDraft(res.hasDraft, res.updatedAt);
                if (layerGroup.getLayers().length) {
                    map.fitBounds(layerGroup.getBounds(), { padding: [16, 16] });
                }
            });
    }

    function renderFC(fc) {
        L.geoJSON(fc, {
            style: () => baseStyle,
            onEachFeature: (feature, layer) => {
                layer._props = {
                    id: feature.properties?.id ?? null,
                    municipality: feature.properties?.municipality ?? '',
                    barangay: feature.properties?.barangay ?? '',
                };
                layer.bindTooltip(`${layer._props.barangay}, ${layer._props.municipality}`, { sticky: true });
                wireLayer(layer);
                layerGroup.addLayer(layer);
            },
        });
    }

    // --- Geoman toolbar ------------------------------------------------------
    map.pm.addControls({
        position: 'topleft',
        drawMarker: false, drawCircle: false, drawCircleMarker: false,
        drawPolyline: false, drawRectangle: false, drawText: false,
        cutPolygon: true, rotateMode: false,
    });
    map.pm.setGlobalOptions({ snappable: true, snapDistance: 15 });

    // a brand-new polygon was drawn -> name it, keep it in the draft only
    map.on('pm:create', (e) => {
        const layer = e.layer;
        layer.setStyle?.(baseStyle);

        openNameModal((municipality, barangay) => {
            layer._props = { id: null, municipality, barangay };
            layer.bindTooltip(`${barangay}, ${municipality}`, { sticky: true });
            wireLayer(layer);
            layerGroup.addLayer(layer);
            queueSave(`Added ${barangay}, ${municipality}`);
        }, () => map.removeLayer(layer));
    });

    function wireLayer(layer) {
        layer.on('pm:update', () => queueSave('Reshaped area'));
        layer.on('pm:cut', (e) => {
            if (e.layer) { e.layer._props = layer._props; wireLayer(e.layer); }
            queueSave('Cut area');
        });
        layer.on('pm:remove', () => queueSave('Removed area'));
    }

    // --- serialize every layer to a FeatureCollection ------------------------
    function serialize() {
        const features = [];
        layerGroup.eachLayer((layer) => {
            if (!layer.toGeoJSON) return;
            const gj = layer.toGeoJSON();
            const geometry = gj.type === 'FeatureCollection' ? gj.features[0]?.geometry : gj.geometry;
            if (!geometry) return;
            features.push({ type: 'Feature', geometry, properties: layer._props || {} });
        });
        return { type: 'FeatureCollection', features };
    }

    // --- debounced autosave to the draft (never the live map) ----------------
    let saveTimer;
    function queueSave(note) {
        setDraft(true, 'saving…');
        flash(note);
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            window.axios.put(routes.draft, { featureCollection: serialize() })
                .then(({ data }) => setDraft(true, data.updatedAt))
                .catch(() => flash('Autosave failed', true));
        }, 700);
    }

    // --- draft badge + publish / discard ------------------------------------
    const badge = document.getElementById('draftBadge');
    const publishBtn = document.getElementById('publishBtn');
    const discardBtn = document.getElementById('discardBtn');

    function setDraft(has, when) {
        if (!badge) return;
        badge.hidden = !has;
        if (has && when) badge.querySelector('[data-when]').textContent = when;
        if (publishBtn) publishBtn.disabled = !has;
        if (discardBtn) discardBtn.disabled = !has;
    }

    publishBtn?.addEventListener('click', () => {
        if (!confirm('Publish these changes to the live map? The current map is saved as a checkpoint first.')) return;
        window.axios.post(routes.publish)
            .then(() => { flash('Published to the live map'); return loadFeatures(); })
            .then(loadSnapshots)
            .catch(() => flash('Publish failed', true));
    });

    discardBtn?.addEventListener('click', () => {
        if (!confirm('Discard your draft and reload the live map? Unpublished changes are lost.')) return;
        window.axios.delete(routes.draft)
            .then(() => { flash('Draft discarded'); return loadFeatures(); })
            .catch(() => flash('Discard failed', true));
    });

    // --- snapshots (checkpoints + original) ---------------------------------
    const snapList = document.getElementById('snapshotList');

    function loadSnapshots() {
        if (!snapList) return Promise.resolve();
        return fetch(routes.snapshots, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((rows) => {
                snapList.innerHTML = rows.map((s) => `
                    <li class="snap-item ${s.kind === 'original' ? 'snap-original' : ''}">
                        <div class="snap-meta">
                            <span class="snap-label">${escapeHtml(s.label)}</span>
                            <span class="snap-sub">${s.features} areas · ${escapeHtml(s.at)}</span>
                        </div>
                        <div class="snap-actions">
                            <button type="button" class="btn btn-xs btn-outline-primary" data-restore="${s.id}">Restore</button>
                            ${s.kind === 'checkpoint' ? `<button type="button" class="btn btn-xs btn-outline-danger" data-del="${s.id}">&times;</button>` : ''}
                        </div>
                    </li>`).join('');
            });
    }

    document.getElementById('checkpointBtn')?.addEventListener('click', () => {
        const label = prompt('Name this checkpoint (optional):', '');
        window.axios.post(routes.snapshotCreate, { label })
            .then(() => { flash('Checkpoint saved'); return loadSnapshots(); })
            .catch(() => flash('Could not save checkpoint', true));
    });

    snapList?.addEventListener('click', (e) => {
        const restore = e.target.closest('[data-restore]');
        const del = e.target.closest('[data-del]');

        if (restore) {
            if (!confirm('Restore the map to this snapshot? The current map is checkpointed first.')) return;
            window.axios.post(routes.snapshotRestore.replace('__ID__', restore.dataset.restore))
                .then(() => { flash('Map restored'); return loadFeatures(); })
                .then(loadSnapshots)
                .catch(() => flash('Restore failed', true));
        } else if (del) {
            window.axios.delete(routes.snapshotDestroy.replace('__ID__', del.dataset.del))
                .then(loadSnapshots)
                .catch(() => flash('Delete failed', true));
        }
    });

    // --- go ------------------------------------------------------------------
    loadFeatures().then(loadSnapshots);
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

const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const escapeAttr = (s) => escapeHtml(s);
