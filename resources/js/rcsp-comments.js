/**
 * Live RCSP comment thread.
 *
 * The legacy Ratchet client (`assets/js/websocket-client.js`) sent `join_room`
 * for a form id and rendered every `new_comment` the server pushed back. Here
 * Echo subscribes to the private `rcsp-form.{id}` channel instead; the comment
 * is still saved over HTTP by the existing form handler, and the broadcast is
 * `->toOthers()`, so the sender keeps its own optimistic render and never sees
 * a duplicate.
 */
export function initRcspComments() {
    const list = document.getElementById('commentsList');
    const formId = list?.dataset.formId;

    if (!list || !formId || !window.Echo) return;

    const connection = window.Echo.connector?.pusher?.connection;
    const indicator = document.querySelector('[data-live-indicator]');

    const setLive = (on) => {
        if (!indicator) return;
        indicator.classList.toggle('text-success', on);
        indicator.classList.toggle('text-muted', !on);
        indicator.title = on ? 'Live — new remarks appear instantly' : 'Offline — reload to see new remarks';
    };

    connection?.bind('connected', () => setLive(true));
    connection?.bind('unavailable', () => setLive(false));
    connection?.bind('disconnected', () => setLive(false));

    window.Echo.private(`rcsp-form.${formId}`)
        .listen('.comment.posted', (e) => append(list, e));
}

function append(list, c) {
    if (list.querySelector(`[data-comment-id="${c.id}"]`)) return;
    list.querySelector('[data-empty]')?.remove();

    const card = document.createElement('div');
    card.className = 'comment-card mb-3';
    card.dataset.commentId = c.id;
    card.innerHTML = `
        <div class="d-flex align-items-start">
            <img src="${escapeAttr(c.user_logo)}" class="rounded-circle me-2" width="40" height="40"
                 style="object-fit:cover;" alt="">
            <div class="flex-grow-1">
                <div class="comment-content p-3 bg-light rounded" style="max-width:80%;">
                    <p class="mb-1">${escapeHtml(c.text)}</p>
                    <small class="text-muted">${escapeHtml(c.user_name ?? 'Unknown')} · ${escapeHtml(c.at ?? '')}</small>
                </div>
            </div>
        </div>`;

    list.prepend(card);
}

const escapeHtml = (s) =>
    String(s ?? '').replace(/[&<>"']/g, (ch) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);

const escapeAttr = (s) => escapeHtml(s).replace(/`/g, '&#96;');
