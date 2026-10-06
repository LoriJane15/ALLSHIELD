<script>
document.addEventListener('click', function (event) {
    const remove = event.target.closest('[data-remove-row]'); if (remove) { remove.closest('tr').remove(); return; }
    const add = event.target.closest('[data-add-row]'); if (!add) return;
    const section = add.closest('[data-draft-table]'); let index = Number(section.dataset.nextIndex); if (section.querySelectorAll('tbody tr').length >= 30) return;
    const fragment = section.querySelector('template').content.cloneNode(true); fragment.querySelectorAll('[data-name]').forEach(input => { input.name = input.dataset.name.replace('__INDEX__', index); input.removeAttribute('data-name'); });
    section.querySelector('tbody').appendChild(fragment); section.dataset.nextIndex = String(index + 1);
});
document.querySelectorAll('[data-fea-draft-form]').forEach(function (form) {
    form.addEventListener('input', function () { form.dataset.dirty = 'true'; });
    form.addEventListener('submit', function () { form.dataset.dirty = 'false'; });
});
document.querySelectorAll('[data-supporting-photo-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        const editor = (form.closest('.fea-doc-pane') || document).querySelector('[data-fea-draft-form]');
        if (editor?.dataset.dirty === 'true' && !window.confirm('This upload reloads the editor. Continue without saving your text changes?')) event.preventDefault();
    });
});
</script>
