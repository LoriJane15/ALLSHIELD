@extends($layout)

@section('title', 'Messages')
@section('heading', 'Private Messages')

@push('styles')
<style>
    .shield-chat { min-height: calc(100vh - 190px); }
    .shield-chat__sidebar { border-right: 1px solid #e7e7ee; }
    .shield-chat__list { max-height: 58vh; overflow-y: auto; }
    .shield-chat__conversation { border-left: 3px solid transparent; color: #343a40; }
    .shield-chat__conversation:hover, .shield-chat__conversation.active { background: #f4f0ff; border-left-color: #35127d; color: #35127d; text-decoration: none; }
    .shield-chat__thread { height: 52vh; min-height: 340px; overflow-y: auto; background: #f8f9fb; }
    .shield-chat__message { max-width: 78%; }
    .shield-chat__bubble { background: #fff; }
    .shield-chat__message.is-mine { margin-left: auto; }
    .shield-chat__message.is-mine .shield-chat__bubble { background: #35127d; color: #fff; }
    .shield-chat__message.is-mine .shield-chat__meta { text-align: right; }
    .shield-chat__message.is-mine .shield-chat__reference { border-color: rgba(255, 255, 255, .35) !important; }
    .shield-chat__message.is-mine .shield-chat__reference a,
    .shield-chat__message.is-mine .shield-chat__reference span { color: #fff !important; }
    .shield-chat__body { white-space: pre-wrap; overflow-wrap: anywhere; }
    @media (max-width: 767.98px) {
        .shield-chat__sidebar { border-right: 0; border-bottom: 1px solid #e7e7ee; }
        .shield-chat__thread { height: 48vh; }
        .shield-chat__message { max-width: 92%; }
    }
</style>
@endpush

@section('content')
<div class="card shield-chat" data-chat
     @if ($selectedConversation)
         data-poll-url="{{ route('chat.messages.index', $selectedConversation) }}"
         data-read-url="{{ route('chat.conversations.read', $selectedConversation) }}"
     @endif>
    <div class="card-body p-0">
        <div class="row g-0">
            <aside class="col-md-4 col-lg-3 shield-chat__sidebar" aria-label="Conversations">
                <div class="p-3 border-bottom">
                    <h2 class="h5 mb-3">Messages</h2>
                    <form method="POST" action="{{ route('chat.conversations.store') }}" data-start-conversation>
                        @csrf
                        <label for="chat-recipient" class="form-label">Start a conversation</label>
                        <div class="input-group">
                            <select id="chat-recipient" name="recipient_id" class="form-select" required>
                                <option value="">Select an account</option>
                                @foreach ($recipients as $recipient)
                                    <option value="{{ $recipient['id'] }}" @selected(old('recipient_id') == $recipient['id'])>
                                        {{ $recipient['name'] }} — {{ $recipient['role'] }}
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary" type="submit" @disabled($recipients->isEmpty())>Open</button>
                        </div>
                        @error('recipient_id')
                            <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror
                        @if ($recipients->isEmpty())
                            <p class="text-muted small mb-0 mt-2">No other active supported accounts are available.</p>
                        @endif
                    </form>
                </div>

                <div class="shield-chat__list" data-conversation-list>
                    @forelse ($conversationItems as $conversation)
                        <a href="{{ route('chat.conversations.show', $conversation['id']) }}"
                           class="d-block p-3 border-bottom shield-chat__conversation {{ $conversation['is_selected'] ? 'active' : '' }}"
                           data-conversation-id="{{ $conversation['id'] }}"
                           @if ($conversation['is_selected']) aria-current="page" @endif>
                            <span class="d-flex align-items-center justify-content-between gap-2">
                                <span class="font-weight-bold text-truncate">{{ $conversation['name'] }}</span>
                                <span class="badge {{ $conversation['is_active'] ? 'badge-success' : 'badge-secondary' }}">
                                    {{ $conversation['is_active'] ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="badge badge-danger {{ $conversation['unread_count'] > 0 ? '' : 'd-none' }}"
                                      data-conversation-unread aria-label="{{ $conversation['unread_count'] }} unread messages">
                                    {{ $conversation['unread_count_text'] }}
                                </span>
                            </span>
                            <span class="text-muted small">{{ $conversation['role'] }}</span>
                        </a>
                    @empty
                        <div class="p-4 text-center text-muted" data-no-conversations>
                            <i class="icon-bubbles d-block mb-2" aria-hidden="true"></i>
                            You have no conversations yet.
                        </div>
                    @endforelse
                </div>
            </aside>

            <section class="col-md-8 col-lg-9 d-flex flex-column" aria-label="Selected conversation">
                @if ($selectedConversation)
                    <header class="p-3 border-bottom d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <h2 class="h5 mb-1">{{ $selectedParticipant['name'] }}</h2>
                            <p class="text-muted mb-0">{{ $selectedParticipant['role'] }}</p>
                        </div>
                        <span class="badge {{ $selectedParticipant['is_active'] ? 'badge-success' : 'badge-secondary' }}">
                            {{ $selectedParticipant['is_active'] ? 'Active' : 'Inactive' }}
                        </span>
                    </header>

                    <div class="p-3 shield-chat__thread" data-message-thread
                         data-latest-id="{{ $messages->last()?->id ?? 0 }}"
                         data-oldest-id="{{ $messages->first()?->id ?? 0 }}">
                        <div class="text-center mb-3 {{ $hasOlderMessages ? '' : 'd-none' }}" data-load-older-wrap>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-load-older>Load older messages</button>
                        </div>

                        <div class="text-center text-muted py-5 {{ $messages->isEmpty() ? '' : 'd-none' }}" data-no-messages>
                            No messages yet. Send the first message.
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

                    <footer class="p-3 border-top">
                        @if ($canSend)
                            <form method="POST" action="{{ route('chat.messages.store', $selectedConversation) }}" data-message-form>
                                @csrf
                                @if ($documentReferenceOptions->isNotEmpty())
                                    <div class="mb-2">
                                        <label for="chat-document-reference" class="form-label mb-1">Reference a SHIELD document (optional)</label>
                                        <select id="chat-document-reference" name="document_reference" class="form-select" data-document-reference-input>
                                            <option value="">No document reference</option>
                                            @foreach ($documentReferenceOptions as $option)
                                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">Only documents this recipient is responsible for and both of you may preview are listed.</small>
                                    </div>
                                @endif
                                <label for="chat-message" class="visually-hidden">Message</label>
                                <div class="input-group">
                                    <textarea id="chat-message" name="body" class="form-control" rows="2" maxlength="5000"
                                              placeholder="Type a private message…" required data-message-input></textarea>
                                    <button class="btn btn-primary" type="submit" data-send-button>Send</button>
                                </div>
                                <div class="text-danger small mt-2 d-none" role="alert" data-message-error></div>
                            </form>
                        @else
                            <div class="alert alert-secondary mb-0" role="status">
                                This conversation is read-only because a participant account is inactive.
                            </div>
                        @endif
                    </footer>
                @else
                    <div class="d-flex flex-column align-items-center justify-content-center text-center p-5 flex-grow-1" data-no-selection>
                        <i class="icon-bubbles mb-3" style="font-size:2.5rem;color:#35127d;" aria-hidden="true"></i>
                        <h2 class="h5">Select an account to start a private conversation.</h2>
                        <p class="text-muted mb-0">Only you and the selected account can view its messages.</p>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
