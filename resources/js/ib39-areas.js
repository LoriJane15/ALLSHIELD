/**
 * Add Area page — port of accounts/39th-IB/add_rcsp.php.
 *
 * Two behaviours from the legacy page:
 *   - the Add modal's municipality dropdown fills the barangay dropdown
 *     (legacy did this against api/get_barangays.php);
 *   - the Edit button opens the Update modal pre-filled for that row.
 */
export function initIb39Areas() {
    initMunicipalityCascade();
    initEditButtons();
}

function initMunicipalityCascade() {
    const municipality = document.getElementById('municipality-select');
    const barangay = document.getElementById('barangay-select');

    if (!municipality || !barangay) return;

    municipality.addEventListener('change', () => {
        const value = municipality.value;

        barangay.innerHTML = '<option value="">Select Barangay</option>';
        barangay.disabled = true;

        if (!value) return;

        barangay.innerHTML = '<option value="">Loading…</option>';

        fetch(`${municipality.dataset.barangays}?municipality=${encodeURIComponent(value)}`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((names) => {
                barangay.innerHTML = '<option value="">Select Barangay</option>';

                names.forEach((name) => {
                    const opt = document.createElement('option');
                    opt.value = name;
                    opt.textContent = name;
                    barangay.appendChild(opt);
                });

                barangay.disabled = names.length === 0;
            })
            .catch(() => {
                barangay.innerHTML = '<option value="">Could not load barangays</option>';
                barangay.disabled = true;
            });
    });
}

/**
 * Bootstrap opens the modal itself via data-bs-toggle on the button, so this
 * only has to point the form at the right row and prefill it. Doing the open
 * here too would depend on `window.bootstrap`, which is one more thing to break.
 */
function initEditButtons() {
    const modalEl = document.getElementById('editModal');
    const form = document.getElementById('editForm');

    if (!modalEl || !form) return;

    modalEl.addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        if (!btn) return;

        form.action = btn.dataset.action;
        setValue('edit_municipality', btn.dataset.municipality);
        setValue('edit_barangay', btn.dataset.barangay);
        setValue('edit_frs', btn.dataset.frs);
    });
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}
