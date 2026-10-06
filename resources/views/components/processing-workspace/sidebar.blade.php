@php
    $roleLabel = fn (?string $role) => match ($role) {
        '39th_ib' => '39th IB',
        'japic' => 'JAPIC',
        'pswdo' => 'PSWDO',
        default => str((string) $role)->replace('_', ' ')->title(),
    };
@endphp
<aside class="process-workspace-sidebar" aria-label="Document activity">
    <section class="process-side-card" id="process-comments">
        <button class="process-side-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $sidebarId }}-comments" aria-expanded="true" aria-controls="{{ $sidebarId }}-comments">
            <span>Comments &amp; Remarks <span class="process-side-count">({{ $comments->count() }})</span></span><i class="mdi mdi-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="process-side-body collapse show" id="{{ $sidebarId }}-comments">
            <div class="process-comment-list" aria-label="Process comments">
                @forelse($comments as $comment)
                    @php
                        $author = $comment->user?->name ?? 'Former user';
                        $initials = collect(preg_split('/\s+/', trim($author)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                        $profileImage = $comment->user?->profileImageUrl();
                    @endphp
                    <article class="process-comment">
                        <div class="process-comment-head">
                            <span class="process-comment-avatar" aria-hidden="true">
                                @if($profileImage)
                                    <img src="{{ $profileImage }}" alt="" onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
                                    <span hidden>{{ $initials ?: '?' }}</span>
                                @else
                                    <span>{{ $initials ?: '?' }}</span>
                                @endif
                            </span>
                            <div><div class="process-comment-name">{{ $author }}</div><span class="process-comment-role">{{ $roleLabel($comment->author_role) }}</span><div class="process-comment-time"><time datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('M d, Y · h:i A') }}</time></div></div>
                        </div>
                        <p class="process-comment-text">{{ $comment->text }}</p>
                    </article>
                @empty
                    <p class="text-muted small mb-0">No comments yet.</p>
                @endforelse
            </div>
            {{-- Hosting actions authorize view, and these process comment abilities delegate to view. --}}
            @if($canComment)
                <form class="process-comment-form" method="POST" action="{{ $commentAction }}">
                    @csrf
                    <label class="small font-weight-bold" for="{{ $sidebarId }}-comment-text">Write a comment</label>
                    <textarea class="form-control @error('text') is-invalid @enderror" id="{{ $sidebarId }}-comment-text" name="text" maxlength="2000" required>{{ old('text') }}</textarea>
                    @error('text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn btn-primary btn-sm mt-2" type="submit"><i class="mdi mdi-send mr-1"></i> Post Comment</button>
                </form>
            @endif
        </div>
    </section>
    <section class="process-side-card" id="process-document-history">
        <button class="process-side-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $sidebarId }}-history" aria-expanded="false" aria-controls="{{ $sidebarId }}-history">
            <span>Document History</span><i class="mdi mdi-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="process-side-body collapse" id="{{ $sidebarId }}-history">
            @if($events->isEmpty())
                <p class="text-muted small mb-0">No document activity has been recorded.</p>
            @else
                <ol class="process-history-list">
                    @foreach($events as $event)
                        <li class="process-history-item">
                            <div class="process-history-title">{{ $event['title'] }}</div>
                            @if($event['context'])<div class="process-history-context">{{ $event['context'] }}</div>@endif
                            <div class="process-history-meta">{{ $event['actor'] }}@if($event['role']) · {{ $roleLabel($event['role']) }}@endif</div>
                            <div class="process-history-meta">@if($event['at'])<time datetime="{{ $event['at']->toIso8601String() }}">{{ $event['at']->format('M d, Y · h:i A') }}</time>@else Date unavailable @endif</div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </section>
</aside>
