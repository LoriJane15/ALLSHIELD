@extends($layout)

@section('title', 'Messages')
@section('heading', 'Private Messages')

@push('styles')
<style>
    .chat-page-container {
        max-width: 1360px;
        margin: 0 auto;
        padding-bottom: 2rem;
    }


    /* Main Chat Card */
    .shield-chat {
        min-height: calc(100vh - 210px);
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 24px -2px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }
    .shield-chat__sidebar {
        border-right: 1px solid #e2e8f0;
        background: #fafbfc;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .sidebar-header-box {
        padding: 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        background: #ffffff;
    }
    .sidebar-header-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }
    .sidebar-header-title h2 {
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.01em;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .sidebar-header-title .chat-icon-badge {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #eef2ff;
        color: #4338ca;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
    }
    .new-chat-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.85rem;
    }
    .new-chat-box label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        margin-bottom: 0.4rem;
    }
    .shield-chat__list {
        flex: 1 1 auto;
        max-height: calc(100vh - 410px);
        min-height: 360px;
        overflow-y: auto;
        padding: 0.5rem 0;
    }
    .shield-chat__list::-webkit-scrollbar {
        width: 5px;
    }
    .shield-chat__list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 9999px;
    }
    .shield-chat__conversation {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.85rem 1.25rem;
        border-left: 3px solid transparent;
        color: #334155;
        text-decoration: none !important;
        transition: all 0.15s ease;
        position: relative;
    }
    .shield-chat__conversation:hover {
        background: #f1f5f9;
        color: #0f172a;
    }
    .shield-chat__conversation.active {
        background: #eef2ff;
        border-left-color: #4338ca;
        color: #1e1b4b;
    }
    .contact-avatar {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: linear-gradient(135deg, #4338ca 0%, #6366f1 100%);
        color: #ffffff;
        font-weight: 750;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        position: relative;
        box-shadow: 0 2px 6px rgba(67, 56, 202, 0.2);
    }
    .contact-status-dot {
        position: absolute;
        bottom: -2px;
        right: -2px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #ffffff;
    }
    .contact-status-dot.active { background: #10b981; }
    .contact-status-dot.inactive { background: #94a3b8; }
    .contact-info {
        min-width: 0;
        flex: 1 1 auto;
    }
    .contact-name {
        font-size: 0.84rem;
        font-weight: 750;
        color: #0f172a;
        margin-bottom: 0.15rem;
    }
    .contact-role {
        font-size: 0.72rem;
        color: #64748b;
        display: block;
    }
    .unread-pill {
        background: #ef4444;
        color: #ffffff;
        font-size: 0.6875rem;
        font-weight: 800;
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
    }

    /* Conversation Thread Panel */
    .chat-workspace-panel {
        display: flex;
        flex-direction: column;
        height: 100%;
        background: #ffffff;
    }
    .chat-thread-header {
        padding: 1.15rem 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .thread-user-info {
        display: flex;
        align-items: center;
        gap: 0.85rem;
    }
    .thread-user-avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, #1e1b4b 0%, #4338ca 100%);
        color: #ffffff;
        font-weight: 800;
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .thread-security-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #475569;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 0.35rem 0.75rem;
        border-radius: 8px;
    }
    .shield-chat__thread {
        flex: 1 1 auto;
        height: calc(100vh - 460px);
        min-height: 380px;
        overflow-y: auto;
        background: #f8fafc;
        padding: 1.5rem;
    }
    .shield-chat__thread::-webkit-scrollbar {
        width: 6px;
    }
    .shield-chat__thread::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 9999px;
    }
    .shield-chat__message {
        max-width: 76%;
        margin-bottom: 1.25rem;
    }
    .shield-chat__bubble {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px 14px 14px 2px;
        padding: 0.85rem 1.15rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        color: #1e293b;
    }
    .shield-chat__message.is-mine {
        margin-left: auto;
    }
    .shield-chat__message.is-mine .shield-chat__bubble {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
        border: 1px solid #312e81;
        border-radius: 14px 14px 2px 14px;
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(49, 46, 129, 0.25);
    }
    .shield-chat__message.is-mine .shield-chat__meta {
        text-align: right;
    }
    .shield-chat__message.is-mine .shield-chat__reference {
        border-color: rgba(255, 255, 255, 0.25) !important;
    }
    .shield-chat__message.is-mine .shield-chat__reference a,
    .shield-chat__message.is-mine .shield-chat__reference span {
        color: #ffffff !important;
    }
    .shield-chat__body {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        font-size: 0.855rem;
        line-height: 1.55;
    }
    .shield-chat__reference {
        font-size: 0.78rem;
        background: rgba(0, 0, 0, 0.03);
        border-radius: 8px;
        padding: 0.4rem 0.65rem;
    }
    .shield-chat__message.is-mine .shield-chat__reference {
        background: rgba(255, 255, 255, 0.12);
    }
    .shield-chat__meta {
        font-size: 0.72rem;
        color: #94a3b8;
        margin-top: 0.35rem;
        padding: 0 0.25rem;
    }
    .shield-chat__footer {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid #e2e8f0;
        background: #ffffff;
    }
    .chat-textarea {
        border-radius: 10px;
        border-color: #cbd5e1;
        font-size: 0.84rem;
        padding: 0.65rem 0.95rem;
        resize: none;
        transition: all 0.2s ease;
    }
    .chat-textarea:focus {
        border-color: #4338ca;
        box-shadow: 0 0 0 3px rgba(67, 56, 202, 0.12);
    }
    .btn-send-message {
        background: #4338ca;
        border-color: #4338ca;
        color: #ffffff;
        font-weight: 750;
        font-size: 0.84rem;
        padding: 0.65rem 1.35rem;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        box-shadow: 0 4px 12px rgba(67, 56, 202, 0.25);
        transition: all 0.2s ease;
    }
    .btn-send-message:hover {
        background: #312e81;
        border-color: #312e81;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(49, 46, 129, 0.35);
    }
    .chat-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 4rem 2rem;
        flex-grow: 1;
        background: #f8fafc;
    }
    .chat-empty-icon {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        background: #eef2ff;
        color: #4338ca;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 4px 16px rgba(67, 56, 202, 0.12);
    }
    @media (max-width: 767.98px) {
        .shield-chat__sidebar { border-right: 0; border-bottom: 1px solid #e2e8f0; }
        .shield-chat__thread { height: 45vh; }
        .shield-chat__message { max-width: 92%; }
    }
</style>
@endpush

@section('content')
<div class="chat-page-container">
    {{-- Main Chat Container --}}
    <div class="card shield-chat" data-chat
         @if ($selectedConversation)
             data-poll-url="{{ route('chat.messages.index', $selectedConversation) }}"
             data-read-url="{{ route('chat.conversations.read', $selectedConversation) }}"
         @endif>
        <div class="card-body p-0">
            <div class="row g-0">
                {{-- Left Sidebar: Conversations --}}
                <aside class="col-md-4 col-lg-3 shield-chat__sidebar" aria-label="Conversations">
                    <div class="sidebar-header-box">
                        <div class="sidebar-header-title">
                            <h2>
                                <span class="chat-icon-badge"><i class="icon-bubbles" aria-hidden="true"></i></span>
                                <span>Messages</span>
                            </h2>
                        </div>
                        <div class="new-chat-box">
                            <form method="POST" action="{{ route('chat.conversations.store') }}" data-start-conversation>
                                @csrf
                                <label for="chat-recipient" class="form-label d-block">Start a conversation</label>
                                <div class="input-group">
                                    <select id="chat-recipient" name="recipient_id" class="form-select form-control form-control-sm" required style="border-radius: 8px 0 0 8px; font-size: 0.8125rem;">
                                        <option value="">Select an account</option>
                                        @foreach ($recipients as $recipient)
                                            <option value="{{ $recipient['id'] }}" @selected(old('recipient_id') == $recipient['id'])>
                                                {{ $recipient['name'] }} — {{ $recipient['role'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-primary btn-sm px-3" type="submit" @disabled($recipients->isEmpty()) style="background-color: #4338ca; border-color: #4338ca; border-radius: 0 8px 8px 0; font-weight: 700;">Open</button>
                                </div>
                                @error('recipient_id')
                                    <div class="text-danger small mt-2">{{ $message }}</div>
                                @enderror
                                @if ($recipients->isEmpty())
                                    <p class="text-muted small mb-0 mt-2">No other active supported accounts are available.</p>
                                @endif
                            </form>
                        </div>
                    </div>

                    {{-- Conversation List --}}
                    <div class="shield-chat__list" data-conversation-list>
                        @forelse ($conversationItems as $conversation)
                            @php
                                $initials = collect(explode(' ', $conversation['name']))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                            @endphp
                            <a href="{{ route('chat.conversations.show', $conversation['id']) }}"
                               class="shield-chat__conversation border-bottom {{ $conversation['is_selected'] ? 'active' : '' }}"
                               data-conversation-id="{{ $conversation['id'] }}"
                               @if ($conversation['is_selected']) aria-current="page" @endif>
                                <div class="contact-avatar">
                                    <span>{{ $initials ?: 'U' }}</span>
                                    <span class="contact-status-dot {{ $conversation['is_active'] ? 'active' : 'inactive' }}" title="{{ $conversation['is_active'] ? 'Active' : 'Inactive' }}"></span>
                                </div>
                                <div class="contact-info">
                                    <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                                        <span class="contact-name text-truncate">{{ $conversation['name'] }}</span>
                                        <span class="unread-pill {{ $conversation['unread_count'] > 0 ? '' : 'd-none' }}"
                                              data-conversation-unread aria-label="{{ $conversation['unread_count'] }} unread messages">
                                            {{ $conversation['unread_count_text'] }}
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="contact-role text-truncate">{{ $conversation['role'] }}</span>
                                        <span class="badge badge-sm {{ $conversation['is_active'] ? 'badge-success' : 'badge-secondary' }}" style="font-size: 0.65rem; padding: 0.2em 0.45em;">
                                            {{ $conversation['is_active'] ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="p-4 text-center text-muted" data-no-conversations>
                                <i class="icon-bubbles d-block mb-2" style="font-size: 2rem; color: #94a3b8;" aria-hidden="true"></i>
                                <strong>You have no conversations yet.</strong>
                                <p class="small text-muted mt-1 mb-0">Select an account above to start messaging.</p>
                            </div>
                        @endforelse
                    </div>
                </aside>

                {{-- Right Panel: Active Conversation --}}
                <section class="col-md-8 col-lg-9 chat-workspace-panel" aria-label="Selected conversation">
                    @if ($selectedConversation)
                        @php
                            $selInitials = collect(explode(' ', $selectedParticipant['name']))->map(fn($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
                        @endphp
                        {{-- Thread Header --}}
                        <header class="chat-thread-header">
                            <div class="thread-user-info">
                                <div class="thread-user-avatar">
                                    <span>{{ $selInitials ?: 'U' }}</span>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h2 class="h5 mb-0 font-weight-bold text-dark">{{ $selectedParticipant['name'] }}</h2>
                                        <span class="badge {{ $selectedParticipant['is_active'] ? 'badge-success' : 'badge-secondary' }}">
                                            {{ $selectedParticipant['is_active'] ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                    <p class="text-muted small mb-0">{{ $selectedParticipant['role'] }}</p>
                                </div>
                            </div>
                            <div class="thread-security-tag">
                                <i class="mdi mdi-lock-check text-success"></i>
                                <span>Private Conversation</span>
                            </div>
                        </header>

                        {{-- Thread Message Body --}}
                        <div class="shield-chat__thread" data-message-thread
                             data-latest-id="{{ $messages->last()?->id ?? 0 }}"
                             data-oldest-id="{{ $messages->first()?->id ?? 0 }}">
                            <div class="text-center mb-3 {{ $hasOlderMessages ? '' : 'd-none' }}" data-load-older-wrap>
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill font-weight-bold px-3 shadow-sm" data-load-older>
                                    <i class="mdi mdi-arrow-up-circle-outline me-1"></i> Load older messages
                                </button>
                            </div>

                            <div class="text-center text-muted py-5 {{ $messages->isEmpty() ? '' : 'd-none' }}" data-no-messages>
                                <div class="chat-empty-icon mx-auto" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                    <i class="mdi mdi-message-text-outline"></i>
                                </div>
                                <strong>No messages yet. Send the first message.</strong>
                                <p class="small text-muted mt-1">Start communication with {{ $selectedParticipant['name'] }}.</p>
                            </div>

                            <div data-message-list>
                                @foreach ($messages as $message)
                                    <article class="shield-chat__message mb-3 {{ $message->sender_id === auth()->id() ? 'is-mine' : '' }}"
                                             data-message-id="{{ $message->id }}">
                                        <div class="shield-chat__bubble rounded p-3 border">
                                            <div class="shield-chat__body">{{ $message->body }}</div>
                                            @if ($reference = $messageReferences->get($message->id))
                                                <div class="mt-2 pt-2 border-top shield-chat__reference">
                                                    @if ($reference['available'])
                                                        <a href="{{ $reference['preview_url'] }}" target="_blank" rel="noopener noreferrer"
                                                           class="font-weight-bold" data-document-reference>
                                                            <i class="icon-doc mr-1" aria-hidden="true"></i>{{ $reference['label'] }}
                                                        </a>
                                                    @else
                                                        <span class="text-muted" data-document-reference-unavailable>
                                                            <i class="icon-doc mr-1" aria-hidden="true"></i>{{ $reference['label'] }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div class="shield-chat__meta text-muted small mt-1">
                                            <span>{{ $message->sender->name }}</span>
                                            <span aria-hidden="true"> · </span>
                                            <time datetime="{{ $message->created_at->toIso8601String() }}"
                                                  title="{{ $message->created_at->format('M j, Y g:i A') }} UTC"
                                                  data-chat-time>{{ $message->created_at->format('M j, Y g:i A') }} UTC</time>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>

                        {{-- Footer Composer --}}
                        <footer class="shield-chat__footer">
                            @if ($canSend)
                                <form method="POST" action="{{ route('chat.messages.store', $selectedConversation) }}" data-message-form>
                                    @csrf
                                    @if ($documentReferenceOptions->isNotEmpty())
                                        <div class="mb-2 p-2 bg-light rounded-8 border">
                                            <label for="chat-document-reference" class="form-label mb-1 font-weight-bold small text-dark d-flex align-items-center gap-1">
                                                <i class="mdi mdi-paperclip text-primary"></i> Reference a SHIELD document (optional)
                                            </label>
                                            <select id="chat-document-reference" name="document_reference" class="form-select form-control form-control-sm" data-document-reference-input style="font-size: 0.8125rem;">
                                                <option value="">No document reference</option>
                                                @foreach ($documentReferenceOptions as $option)
                                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                            <small class="form-text text-muted" style="font-size: 0.72rem;">Only documents this recipient is responsible for and both of you may preview are listed.</small>
                                        </div>
                                    @endif
                                    <label for="chat-message" class="visually-hidden">Message</label>
                                    <div class="input-group">
                                        <textarea id="chat-message" name="body" class="form-control chat-textarea" rows="2" maxlength="5000"
                                                  placeholder="Type a private message…" required data-message-input></textarea>
                                        <button class="btn btn-send-message" type="submit" data-send-button>
                                            <i class="mdi mdi-send"></i>
                                            <span>Send</span>
                                        </button>
                                    </div>
                                    <div class="text-danger small mt-2 d-none" role="alert" data-message-error></div>
                                </form>
                            @else
                                <div class="alert alert-secondary mb-0" role="status">
                                    <i class="mdi mdi-alert-circle-outline me-1"></i> This conversation is read-only because a participant account is inactive.
                                </div>
                            @endif
                        </footer>
                    @else
                        {{-- Empty Selection State --}}
                        <div class="chat-empty-state" data-no-selection>
                            <div class="chat-empty-icon">
                                <i class="icon-bubbles" aria-hidden="true"></i>
                            </div>
                            <h2 class="h5 font-weight-bold text-dark mb-2">Select an account to start a private conversation.</h2>
                            <p class="text-muted mb-0" style="max-width: 420px; font-size: 0.875rem;">
                                Only you and the selected account can view its messages. All communications and document references are encrypted and confidential.
                            </p>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>
</div>
@endsection

