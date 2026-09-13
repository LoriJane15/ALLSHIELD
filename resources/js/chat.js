const POLL_INTERVAL = 3000;
const UNREAD_POLL_INTERVAL = 15000;
const ERROR_INTERVAL = 10000;

export function initChat() {
    const root = document.querySelector('[data-chat]');
    const navLinks = [...document.querySelectorAll('[data-chat-nav]')];
    if (!root && !navLinks.length) return;

    const requestJson = async (url, options = {}) => {
        const { headers = {}, ...requestOptions } = options;
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...requestOptions,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...headers },
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(payload.message || 'The request could not be completed.');
            error.response = response;
            error.payload = payload;
            throw error;
        }
        return payload;
    };

    const unreadUrl = navLinks.find((link) => link.dataset.unreadUrl)?.dataset.unreadUrl;
    let unreadPolling = false;
    let unreadTimer;

    const applyUnread = (summary) => {
        if (!summary) return;
        navLinks.forEach((link) => {
            updateBadge(link.querySelector('[data-chat-nav-badge]'), Number(summary.total || 0), summary.total_text);
        });
        root?.querySelectorAll('[data-conversation-id]').forEach((conversation) => {
            const unread = summary.conversations?.[conversation.dataset.conversationId];
            updateBadge(
                conversation.querySelector('[data-conversation-unread]'),
                Number(unread?.count || 0),
                unread?.count_text,
            );
        });
    };

    const scheduleUnread = (delay = UNREAD_POLL_INTERVAL) => {
        window.clearTimeout(unreadTimer);
        if (unreadUrl) unreadTimer = window.setTimeout(pollUnread, delay);
    };

    const pollUnread = async () => {
        if (unreadPolling || document.hidden || !navigator.onLine) {
            scheduleUnread();
            return;
        }
        unreadPolling = true;
        try {
            const payload = await requestJson(unreadUrl);
            applyUnread(payload.unread);
            scheduleUnread();
        } catch (error) {
            if ([401, 419].includes(error.response?.status)) window.location.reload();
            scheduleUnread(ERROR_INTERVAL);
        } finally {
            unreadPolling = false;
        }
    };

    scheduleUnread();
    if (!root) {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && navigator.onLine) scheduleUnread(0);
        });
        window.addEventListener('online', () => scheduleUnread(0));
        return;
    }

    root.querySelectorAll('[data-chat-time]').forEach(formatTime);

    const pollUrl = root.dataset.pollUrl;
    const readUrl = root.dataset.readUrl;
    const thread = root.querySelector('[data-message-thread]');
    const list = root.querySelector('[data-message-list]');
    if (!pollUrl || !thread || !list) return;

    let latestId = Number(thread.dataset.latestId || 0);
    let oldestId = Number(thread.dataset.oldestId || 0);
    let polling = false;
    let timer;
    let pendingReadId = latestId;
    let readAcknowledging = false;
    let readRetryTimer;

    const schedule = (delay = POLL_INTERVAL) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(poll, delay);
    };

    const acknowledgeRead = async () => {
        if (!readUrl || !pendingReadId || readAcknowledging || document.hidden || !navigator.onLine) return;
        readAcknowledging = true;
        const throughId = pendingReadId;
        try {
            const payload = await requestJson(readUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: new URLSearchParams({ through_id: String(throughId) }),
            });
            if (pendingReadId <= throughId) pendingReadId = 0;
            applyUnread(payload.unread);
        } catch (error) {
            if ([401, 419].includes(error.response?.status)) window.location.reload();
            window.clearTimeout(readRetryTimer);
            readRetryTimer = window.setTimeout(acknowledgeRead, ERROR_INTERVAL);
        } finally {
            readAcknowledging = false;
            if (pendingReadId > throughId) acknowledgeRead();
        }
    };

    const requestReadAcknowledgement = (throughId) => {
        pendingReadId = Math.max(pendingReadId, Number(throughId) || 0);
        acknowledgeRead();
    };

    const poll = async () => {
        if (polling || document.hidden || !navigator.onLine) {
            schedule();
            return;
        }

        polling = true;
        try {
            const url = new URL(pollUrl, window.location.origin);
            url.searchParams.set('after_id', String(latestId));
            const payload = await requestJson(url);
            payload.messages?.forEach((message) => appendMessage(list, message));
            if (payload.messages?.length) {
                latestId = Math.max(latestId, ...payload.messages.map((message) => Number(message.id)));
                if (!oldestId) oldestId = Number(payload.messages[0].id);
                thread.dataset.latestId = String(latestId);
                thread.dataset.oldestId = String(oldestId);
                scrollToBottom(thread);
                requestReadAcknowledgement(latestId);
            }
            schedule(payload.has_more ? 0 : POLL_INTERVAL);
        } catch (error) {
            if ([401, 419].includes(error.response?.status)) window.location.reload();
            schedule(ERROR_INTERVAL);
        } finally {
            polling = false;
        }
    };

    const form = root.querySelector('[data-message-form]');
    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = form.querySelector('[data-message-input]');
        const referenceInput = form.querySelector('[data-document-reference-input]');
        const button = form.querySelector('[data-send-button]');
        const errorBox = form.querySelector('[data-message-error]');
        const body = input.value.trim();

        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        if (!body) {
            showError(errorBox, 'Enter a message before sending.');
            return;
        }

        button.disabled = true;
        try {
            const payload = await requestJson(form.action, { method: 'POST', body: new FormData(form) });
            appendMessage(list, payload.message);
            latestId = Math.max(latestId, Number(payload.message.id));
            if (!oldestId) oldestId = Number(payload.message.id);
            thread.dataset.latestId = String(latestId);
            thread.dataset.oldestId = String(oldestId);
            input.value = '';
            if (referenceInput) referenceInput.value = '';
            scrollToBottom(thread);
            requestReadAcknowledgement(latestId);
        } catch (error) {
            const validation = error.payload?.errors?.body?.[0]
                || error.payload?.errors?.document_reference?.[0];
            showError(errorBox, validation || error.message);
            if ([401, 419].includes(error.response?.status)) window.location.reload();
        } finally {
            button.disabled = false;
            input.focus();
        }
    });

    const olderButton = root.querySelector('[data-load-older]');
    olderButton?.addEventListener('click', async () => {
        if (!oldestId) return;
        olderButton.disabled = true;
        const previousHeight = thread.scrollHeight;
        try {
            const url = new URL(pollUrl, window.location.origin);
            url.searchParams.set('before_id', String(oldestId));
            const payload = await requestJson(url);
            [...(payload.messages || [])].reverse().forEach((message) => prependMessage(list, message));
            if (payload.messages?.length) {
                oldestId = Number(payload.messages[0].id);
                thread.dataset.oldestId = String(oldestId);
                thread.scrollTop += thread.scrollHeight - previousHeight;
            }
            root.querySelector('[data-load-older-wrap]')?.classList.toggle('d-none', !payload.has_more);
        } catch (error) {
            if ([401, 419].includes(error.response?.status)) window.location.reload();
        } finally {
            olderButton.disabled = false;
        }
    });

    const resume = () => {
        if (!document.hidden && navigator.onLine) {
            schedule(0);
            scheduleUnread(0);
            acknowledgeRead();
        }
    };
    document.addEventListener('visibilitychange', resume);
    window.addEventListener('online', resume);

    scrollToBottom(thread);
    requestReadAcknowledgement(latestId);
    schedule();
}

function updateBadge(badge, count, text) {
    if (!badge) return;
    badge.textContent = String(text ?? (count > 99 ? '99+' : count));
    badge.classList.toggle('d-none', count < 1);
    badge.setAttribute('aria-label', `${count} unread ${count === 1 ? 'message' : 'messages'}`);
}

function appendMessage(list, message) {
    if (list.querySelector(`[data-message-id="${Number(message.id)}"]`)) return;
    list.append(createMessage(message));
    list.closest('[data-message-thread]')?.querySelector('[data-no-messages]')?.classList.add('d-none');
}

function prependMessage(list, message) {
    if (list.querySelector(`[data-message-id="${Number(message.id)}"]`)) return;
    list.prepend(createMessage(message));
}

function createMessage(message) {
    const article = document.createElement('article');
    article.className = `shield-chat__message mb-3${message.is_mine ? ' is-mine' : ''}`;
    article.dataset.messageId = String(Number(message.id));

    const bubble = document.createElement('div');
    bubble.className = 'shield-chat__bubble rounded p-3 border';
    const body = document.createElement('div');
    body.className = 'shield-chat__body';
    body.textContent = String(message.body ?? '');
    bubble.append(body);
    appendDocumentReference(bubble, message.document_reference);

    const meta = document.createElement('div');
    meta.className = 'shield-chat__meta text-muted small mt-1';
    const sender = document.createElement('span');
    sender.textContent = String(message.sender_name ?? 'Unknown');
    const separator = document.createElement('span');
    separator.setAttribute('aria-hidden', 'true');
    separator.textContent = ' · ';
    const time = document.createElement('time');
    time.dateTime = String(message.sent_at_iso ?? '');
    time.title = String(message.sent_at_display ?? '');
    time.textContent = String(message.sent_at_display ?? '');
    time.dataset.chatTime = '';
    formatTime(time);
    meta.append(sender, separator, time);

    article.append(bubble, meta);
    return article;
}

function appendDocumentReference(bubble, reference) {
    if (!reference) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'mt-2 pt-2 border-top shield-chat__reference';
    const icon = document.createElement('i');
    icon.className = 'icon-doc mr-1';
    icon.setAttribute('aria-hidden', 'true');

    if (reference.available && reference.preview_url) {
        const link = document.createElement('a');
        link.className = 'font-weight-bold';
        link.href = String(reference.preview_url);
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.dataset.documentReference = '';
        link.append(icon, document.createTextNode(String(reference.label || 'Referenced document')));
        wrapper.append(link);
    } else {
        const unavailable = document.createElement('span');
        unavailable.className = 'text-muted';
        unavailable.dataset.documentReferenceUnavailable = '';
        unavailable.append(icon, document.createTextNode(String(reference.label || 'Referenced document unavailable')));
        wrapper.append(unavailable);
    }
    bubble.append(wrapper);
}

function formatTime(time) {
    const date = new Date(time.dateTime);
    if (Number.isNaN(date.getTime())) return;
    time.textContent = new Intl.DateTimeFormat(undefined, {
        year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    }).format(date);
}

function showError(element, message) {
    element.textContent = String(message || 'The message could not be sent.');
    element.classList.remove('d-none');
}

function scrollToBottom(thread) {
    thread.scrollTop = thread.scrollHeight;
}
