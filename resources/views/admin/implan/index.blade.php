@extends('layouts.skydash-h')
@section('title', 'IMPLAN')
@section('heading', 'Implementation Plans')

@php
    $targetNames = fn ($im) => collect($im->target_areas ?? [])
        ->map(fn ($id) => $areaNames[$id] ?? "Area #$id")->implode(', ');
    $tabMeta = [
        'verification' => ['For Verification', 'badge-info'],
        'ongoing'      => ['Ongoing', 'badge-warning'],
        'not_started'  => ['Awaiting Action', 'badge-danger'],
        'verified'     => ['Verified', 'badge-success'],
        'reassign'     => ['Reassign', 'badge-danger'],
    ];
@endphp

@section('content')
    <div class="row">
        <div class="col-12 grid-margin">
            <div class="card">
                <div class="card-body">
                    <h1 class="card-title mb-5" style="font-weight: bold; font-size: 1.5rem; text-align: left;">Bulk File Submission - IMPLAN</h1>

                    <ul class="nav nav-pills nav-pills-success" role="tablist" id="pills-tab" style="margin-bottom: 0; border-bottom: 0;">
                        @foreach ($tabMeta as $key => [$label, $badge])
                            <li class="nav-item">
                                <a class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" href="#tab-{{ $key }}" role="tab">
                                    {{ $label }} <span class="badge {{ $badge }}">{{ $tabs[$key]->count() }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <div class="tab-content" id="pills-tabContent">
                        {{-- Standard status tabs --}}
                        @foreach (['verification', 'ongoing', 'not_started', 'verified'] as $key)
                            @php [$label, $badge] = $tabMeta[$key]; @endphp
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab-{{ $key }}" role="tabpanel">
                                <div class="row mt-3">
                                    <div class="col-md-12 d-flex align-items-end justify-content-start">
                                        <div class="form-group mb-0 me-3" style="width: 200px">
                                            <select class="form-control form-control-sm"><option>Filter by: All</option></select>
                                        </div>
                                        <div class="form-group mb-0 me-3" style="width: 200px">
                                            <input type="date" class="form-control form-control-sm">
                                        </div>
                                        <div class="form-group mb-0 me-3" style="width: 200px">
                                            <input type="text" class="form-control form-control-sm" placeholder="Search...">
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Program</th>
                                                <th>Issue</th>
                                                <th>Target Area</th>
                                                <th>Status</th>
                                                <th class="{{ $key === 'verification' ? 'text-center' : '' }}">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($tabs[$key] as $im)
                                                <tr>
                                                    <td>{{ $im->program ?: '—' }}</td>
                                                    <td>{{ $im->issues }}</td>
                                                    <td>{{ $targetNames($im) ?: '—' }}</td>
                                                    <td><span class="badge {{ $badge }}">{{ $im->status }}</span></td>
                                                    <td class="{{ $key === 'verification' ? 'text-center' : '' }}">
                                                        <div class="d-flex {{ $key === 'verification' ? 'justify-content-center' : '' }} gap-2">
                                                            <a href="{{ route('admin.implan.show', $im) }}" class="btn btn-primary btn-sm">Open</a>
                                                            @if ($key === 'verification')
                                                                <form method="POST" action="{{ route('admin.implan.verify', $im) }}"
                                                                      data-confirm="Mark this IMPLAN as verified?" data-confirm-title="Verify IMPLAN" data-confirm-action="Verify" data-confirm-danger="false">
                                                                    @csrf
                                                                    <button class="btn btn-primary btn-sm">Verify</button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5" class="text-center">No records found for "{{ $label }}" status.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach

                        {{-- Reassign tab --}}
                        <div class="tab-pane fade" id="tab-reassign" role="tabpanel">
                            <div class="row mt-3">
                                <div class="col-md-12 d-flex align-items-end justify-content-start">
                                    <div class="form-group mb-0 me-3" style="width: 200px">
                                        <select class="form-control form-control-sm"><option>Filter by: All</option></select>
                                    </div>
                                    <div class="form-group mb-0 me-3" style="width: 200px">
                                        <input type="date" class="form-control form-control-sm">
                                    </div>
                                    <div class="form-group mb-0 me-3" style="width: 200px">
                                        <input type="text" class="form-control form-control-sm" placeholder="Search...">
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Program</th>
                                            <th>Issue</th>
                                            <th>Responsible Agency</th>
                                            <th>Reason</th>
                                            <th>Status</th>
                                            <th>Uploaded At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($tabs['reassign'] as $im)
                                            @php $tag = $im->taggings->firstWhere('status', 'Rejected'); @endphp
                                            <tr>
                                                <td>{{ $im->program ?: 'No Program' }}</td>
                                                <td>{{ $im->issues ?: 'No Issues' }}</td>
                                                <td>{{ $tag?->govAgency?->name ?? 'No Agency Found' }}</td>
                                                <td>{{ $tag?->reason ?? 'No Reason' }}</td>
                                                <td><span class="badge badge-danger">{{ $tag?->status ?? 'No Status' }}</span></td>
                                                <td>{{ $tag?->created_at?->format('Y-m-d H:i') ?? 'No Date' }}</td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm"
                                                            data-bs-toggle="modal" data-bs-target="#reassign_{{ $im->id }}">
                                                        <i class="mdi mdi-repeat"></i> Reassign
                                                    </button>
                                                </td>
                                            </tr>

                                            {{-- Reassign modal --}}
                                            <div class="modal fade" id="reassign_{{ $im->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <form method="POST" action="{{ route('admin.implan.reassign', $im) }}" class="reassign-form">
                                                            @csrf
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Refocus Implementation Plan</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Issues or Concerned</label>
                                                                    <input type="text" class="form-control" value="{{ $im->issues ?: 'No Issues' }}" readonly>
                                                                </div>
                                                                <div class="row mb-3">
                                                                    <div class="col">
                                                                        <label class="form-label">Program</label>
                                                                        <input type="text" class="form-control" value="{{ $im->program ?: 'No Program' }}" readonly>
                                                                    </div>
                                                                    <div class="col">
                                                                        <label class="form-label">Target Beneficiary</label>
                                                                        <input type="text" class="form-control" value="{{ $im->beneficiaries }}" readonly>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Target Area</label>
                                                                    <div class="selected-tags-container">
                                                                        @foreach (collect($im->target_areas ?? []) as $aid)
                                                                            <span class="tag">{{ $areaNames[$aid] ?? "Area #$aid" }}</span>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Responsible Agency</label>
                                                                    <div class="form-control modern-select-wrapper">
                                                                        <div class="selected-tags-container" data-agency-tags></div>
                                                                        <div class="select-dropdown">
                                                                            <select class="agency-select form-control">
                                                                                <option value="">Select Agency</option>
                                                                                @foreach ($allAgencies as $ag)
                                                                                    <option value="{{ $ag->id }}">{{ $ag->acronym }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <small class="text-muted">Add one or more agencies to reassign this plan to.</small>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                                <button type="submit" class="btn btn-primary">Submit Reassignment</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <tr><td colspan="7" class="text-center">No forms available for reassignment.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .nav-pills { padding: 0; margin-bottom: 0 !important; position: relative; z-index: 1; }
    .nav-pills .nav-item { margin-right: 6px; }
    .nav-pills .nav-link {
        border: 1px solid #dee2e6; border-radius: 8px 8px 0 0; padding: 8px 25px;
        background: #f8f9fa; color: #666; transition: all 0.2s ease;
    }
    .nav-pills .nav-link.active {
        background: #fff !important; color: #000 !important;
        border-bottom-color: transparent; border-top: 5px solid #280274; padding-top: 6px;
    }
    .nav-pills .nav-item:hover .nav-link:not(.active) { background: #f0f0f0 !important; }
    .nav-pills .nav-link .badge { margin-left: 5px; vertical-align: middle; }
    .tab-content { border: 1px solid #dee2e6; padding: 20px; background: #fff; margin-top: -1px; }
    .table thead th { background-color: #4b49ac; color: #fff; }
    .table td { word-wrap: break-word; white-space: normal; }
    .row.mt-3 { margin-top: 0 !important; margin-bottom: 12px; }

    .modern-select-wrapper { position: relative; border: 1px solid #ced4da; border-radius: .25rem; padding: .375rem .75rem; min-height: 38px; background: #fff; }
    .selected-tags-container { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .5rem; min-height: 24px; }
    .tag { background: #e9ecef; border-radius: .25rem; padding: .25rem .5rem; font-size: .875rem; display: flex; align-items: center; gap: .5rem; }
    .remove-tag { background: none; border: none; color: #666; cursor: pointer; font-size: 1.1rem; line-height: 1; padding: 0 2px; }
    .remove-tag:hover { color: #dc3545; }
    .select-dropdown select { width: 100%; border: none; background: transparent; outline: none; padding: 0; }
    .btn-danger.btn-sm { background-color: #FF69B4 !important; border: none !important; color: #fff !important; display: inline-flex; align-items: center; gap: 4px; }
    .btn-danger.btn-sm:hover { background-color: #FF1493 !important; }
</style>
@endpush

@push('scripts')
<script>
    // Reassign modal: agency select → tag + hidden agencies[] input.
    document.querySelectorAll('.reassign-form').forEach((form) => {
        const select = form.querySelector('.agency-select');
        const tags = form.querySelector('[data-agency-tags]');
        if (!select) return;
        select.addEventListener('change', function () {
            if (!this.value) return;
            if (tags.querySelector(`[data-id="${this.value}"]`)) { this.value = ''; return; }
            const name = this.options[this.selectedIndex].text;
            const span = document.createElement('span');
            span.className = 'tag';
            span.dataset.id = this.value;
            span.innerHTML = `${name}<input type="hidden" name="agencies[]" value="${this.value}">
                <button type="button" class="remove-tag">&times;</button>`;
            span.querySelector('.remove-tag').addEventListener('click', () => span.remove());
            tags.appendChild(span);
            this.value = '';
        });
    });
</script>
@endpush
