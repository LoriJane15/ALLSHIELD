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

    if (!list || !formId) return;

    const commentForm = document.getElementById('commentForm');
    const fallbackAvatar = list.dataset.fallbackAvatar ?? '';

    commentForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const textarea = commentForm.querySelector('textarea[name="comment_text"]');
        const text = textarea?.value.trim() ?? '';
        if (!text) {
            window.alert(commentForm.dataset.blankMessage ?? 'Please enter a comment');
            return;
        }

        try {
            const response = await fetch(commentForm.dataset.post, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ text }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error('Comment request failed');

            renderComment(list, {
                id: result.comment.id,
                text: result.comment.text,
                user_name: result.comment.user,
                user_role: result.comment.role,
                user_logo: result.comment.user_logo,
                at: result.comment.at,
            }, fallbackAvatar);
            textarea.value = '';
        } catch {
            window.alert('Error posting comment');
        }
    });

    if (!window.Echo) return;

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
        .listen('.comment.posted', (comment) => renderComment(list, comment, fallbackAvatar));
}

function renderComment(list, comment, fallbackAvatar) {
    if (comment.id && list.querySelector(`[data-comment-id="${Number(comment.id)}"]`)) return;
    list.querySelector('[data-empty]')?.remove();

    const card = document.createElement('div');
    card.className = 'comment-card mb-3';
    if (comment.id) card.dataset.commentId = String(Number(comment.id));

    const reviewer = comment.user_role === 'admin';
    const row = document.createElement('div');
    row.className = `d-flex gap-2 ${reviewer ? '' : 'justify-content-end'}`.trim();
    const avatar = createAvatar(comment.user_logo, fallbackAvatar);
    const wrapper = document.createElement('div');
    wrapper.className = 'flex-grow-0';
    const content = document.createElement('div');
    content.className = `comment-content p-3 ${reviewer ? 'bg-light' : 'bg-primary text-white'} rounded`;
    content.style.maxWidth = '80%';
    const text = document.createElement('p');
    text.className = 'mb-1';
    text.textContent = String(comment.text ?? '');
    const meta = document.createElement('small');
    meta.className = reviewer ? 'text-muted' : 'text-white-50';
    meta.textContent = `${String(comment.user_name ?? 'Unknown')} · ${String(comment.at ?? '')}`;
    content.append(text, meta);
    wrapper.append(content);
    if (reviewer) row.append(avatar, wrapper);
    else row.append(wrapper, avatar);
    card.append(row);

    list.prepend(card);
}

function createAvatar(source, fallback) {
    const wrapper = document.createElement('div');
    wrapper.className = 'user-avatar';
    const image = document.createElement('img');
    image.className = 'rounded-circle';
    image.width = 40;
    image.height = 40;
    image.alt = '';
    image.style.objectFit = 'cover';
    image.src = safeImageUrl(source, fallback);
    image.addEventListener('error', () => {
        image.src = safeImageUrl(fallback, '');
    }, { once: true });
    wrapper.append(image);

    return wrapper;
}

function safeImageUrl(source, fallback) {
    try {
        const url = new URL(String(source ?? ''), window.location.origin);
        if (['http:', 'https:'].includes(url.protocol)) return url.href;
    } catch {
        // Use the trusted server-rendered fallback below.
    }

    return String(fallback ?? '');
}
