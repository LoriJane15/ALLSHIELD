@extends('layouts.skydash-v')
@section('title', 'File Viewer')
@section('heading', 'File Viewer')

@php
    $bgy = $form->rcspBarangay;
    $ext = strtolower(pathinfo($form->file, PATHINFO_EXTENSION));
    $fileUrl = $form->file ? Storage::url($form->file) : null;
    $fallbackAvatar = asset('assets/img/kc-logo.svg');
    $avatarFor = fn ($u) => $u && $u->logo ? asset('assets/'.$u->logo) : $fallbackAvatar;
    $avatar = $avatarFor($form->lguUser);          // header = uploader
    $myAvatar = $avatarFor(auth()->user());        // current viewer (comment form)
    $statusTone = match ($form->status) {
        'approved' => 'status-approved', 'disapproved' => 'status-disapproved', default => 'status-pending',
    };
@endphp

@section('content')
    <!-- Header Info -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <a href="{{ route('lgu.monitoring.show', $bgy) }}"
                               class="btn btn-primary d-flex align-items-center justify-content-center"
                               style="border-radius: 50%; width: 48px; height: 48px;">
                                <i class="fa fa-arrow-left"></i>
                            </a>
                        </div>
                        <div class="user-avatar me-3">
                            <img src="{{ $avatar }}" alt="User" class="rounded-circle" style="width: 48px; height: 48px;">
                        </div>
                        <div class="file-info ms-3">
                            <h5 class="mb-1">File ID: {{ $form->id }} · {{ $form->activity?->description }}</h5>
                            <div class="text-muted">Uploaded on: {{ $form->created_at?->format('F j, Y g:i A') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Information -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="status-container d-flex flex-wrap gap-3">
                <div class="status-card">
                    <div class="label">Status</div>
                    <div class="value {{ $statusTone }}">{{ ucfirst($form->status) }}</div>
                </div>
                <div class="status-card" style="background-color: #ebf0ff;">
                    <div class="label">File ID</div>
                    <div class="value">{{ $form->id }}</div>
                </div>
                <div class="status-card" style="background-color: #ebf0ff;">
                    <div class="label">City/Municipality</div>
                    <div class="value">{{ $bgy?->municipality?->name ?? 'Unknown Municipality' }}</div>
                </div>
                <div class="status-card" style="background-color: #ebf0ff;">
                    <div class="label">Barangay</div>
                    <div class="value">{{ $bgy?->barangay?->name ?? 'Unknown Barangay' }}</div>
                </div>
                <div class="status-card" style="background-color: #ebf0ff;">
                    <div class="label">Phase</div>
                    <div class="value">Phase {{ $form->phase?->number }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Viewer + Comments -->
    <div class="row">
        <div class="col-md-8">
            <div class="card" style="height: 800px;">
                <div class="card-body p-0">
                    @if (! $fileUrl)
                        <div class="d-flex align-items-center justify-content-center h-100" style="background:#f8f9fa;">
                            <div class="text-center p-5"><i class="fa fa-file-o fa-5x text-secondary mb-3"></i><h4>No file uploaded</h4></div>
                        </div>
                    @elseif ($ext === 'pdf')
                        <iframe src="{{ $fileUrl }}" style="width:100%; height:100%; border:none;" type="application/pdf"></iframe>
                    @elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                        <div class="d-flex align-items-center justify-content-center h-100" style="background:#f8f9fa;">
                            <img src="{{ $fileUrl }}" style="max-width:100%; max-height:100%; object-fit:contain;" alt="Document Image">
                        </div>
                    @else
                        <div class="d-flex align-items-center justify-content-center h-100" style="background:#f8f9fa;">
                            <div class="text-center p-5">
                                <div class="mb-4"><i class="fa fa-file-o fa-5x text-secondary"></i></div>
                                <h4 class="mb-3">{{ pathinfo($form->file, PATHINFO_FILENAME) }}</h4>
                                <a href="{{ $fileUrl }}" class="btn btn-primary" download><i class="fa fa-download me-2"></i>Download File</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title d-flex align-items-center gap-2">Comments
                        <i class="fa fa-circle small text-muted" data-live-indicator title="Offline — reload to see new remarks"></i>
                    </h4>

                    @if ($form->remarks)
                        <div class="alert alert-warning py-2 small"><strong>Reviewer remark:</strong> {{ $form->remarks }}</div>
                    @endif

                    <form id="commentForm" class="mb-4" data-post="{{ route('lgu.monitoring.comment', $form->id) }}">
                        <div class="d-flex gap-2 mb-3">
                            <div class="user-avatar"><img src="{{ $myAvatar }}" onerror="this.onerror=null;this.src='{{ $fallbackAvatar }}'" class="rounded-circle" alt="Profile" width="40" height="40" style="object-fit:cover;"></div>
                            <div class="flex-grow-1"><textarea class="form-control" name="comment_text" rows="2" placeholder="Add a message..."></textarea></div>
                        </div>
                        <div class="text-end"><button type="submit" class="btn btn-primary">Post Comment</button></div>
                    </form>

                    <div id="commentsList" data-form-id="{{ $form->id }}">
                        @forelse ($form->fileComments->sortByDesc('id') as $c)
                            @php $reviewer = in_array($c->user?->role, ['admin', 'super_admin']); @endphp
                            <div class="comment-card mb-3">
                                <div class="d-flex gap-2 {{ $reviewer ? '' : 'justify-content-end' }}">
                                    @if ($reviewer)
                                        <div class="user-avatar"><img src="{{ $avatarFor($c->user) }}" onerror="this.onerror=null;this.src='{{ $fallbackAvatar }}'" class="rounded-circle" width="40" height="40" alt="" style="object-fit:cover;"></div>
                                    @endif
                                    <div class="flex-grow-0">
                                        <div class="comment-content p-3 {{ $reviewer ? 'bg-light' : 'bg-primary text-white' }} rounded" style="max-width: 80%;">
                                            <p class="mb-1">{{ $c->text }}</p>
                                            <small class="{{ $reviewer ? 'text-muted' : 'text-white-50' }}">{{ $c->user?->name ?? 'User' }} · {{ $c->created_at?->format('M j, g:i A') }}</small>
                                        </div>
                                    </div>
                                    @unless ($reviewer)
                                        <div class="user-avatar"><img src="{{ $avatarFor($c->user) }}" onerror="this.onerror=null;this.src='{{ $fallbackAvatar }}'" class="rounded-circle" width="40" height="40" alt="" style="object-fit:cover;"></div>
                                    @endunless
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small" data-empty>No comments yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .status-card { background-color: #fff; border-radius: 8px; padding: 12px 20px; min-width: 140px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .status-card .label { font-size: .75rem; color: #858796; text-transform: uppercase; }
    .status-card .value { font-weight: 700; font-size: 1rem; }
    .status-pending { color: #f0ad4e; } .status-approved { color: #28a745; } .status-disapproved { color: #dc3545; }
    .comment-content { word-break: break-word; }
</style>
@endpush

@push('scripts')
<script>
    document.getElementById('commentForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const ta = this.querySelector('textarea[name="comment_text"]');
        if (!ta.value.trim()) { alert('Please enter a message'); return; }
        const res = await fetch(this.dataset.post, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Content-Type': 'application/json', 'Accept': 'application/json',
            },
            body: JSON.stringify({ text: ta.value }),
        }).then((r) => r.json());
        if (res.success) {
            document.querySelector('#commentsList [data-empty]')?.remove();
            const card = document.createElement('div');
            card.className = 'comment-card mb-3';
            card.innerHTML = `
                <div class="d-flex gap-2 justify-content-end">
                    <div class="flex-grow-0">
                        <div class="comment-content p-3 bg-primary text-white rounded" style="max-width: 80%;">
                            <p class="mb-1">${res.comment.text}</p>
                            <small class="text-white-50">${res.comment.user} · ${res.comment.at}</small>
                        </div>
                    </div>
                    <div class="user-avatar"><img src="{{ $myAvatar }}" onerror="this.onerror=null;this.src='{{ $fallbackAvatar }}'" class="rounded-circle" width="40" height="40" alt="" style="object-fit:cover;"></div>
                </div>`;
            document.getElementById('commentsList').prepend(card);
            ta.value = '';
        } else {
            alert('Error posting comment');
        }
    });
</script>
@endpush
