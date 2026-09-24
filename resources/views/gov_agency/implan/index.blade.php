@extends('layouts.skydash-v')
@section('title', 'IMPLAN List')
@section('heading', 'Implementation Plans')

@php
    $agencyLogo = $agency?->profile
        ? asset('assets/logoAgency/'.$agency->profile)
        : (auth()->user()->logo ? asset('assets/'.auth()->user()->logo) : asset('assets/img/kc-logo.svg'));
    $statusBadge = fn ($s) => match ($s) {
        'for verification' => 'badge-info', 'ongoing' => 'badge-primary',
        'not yet started' => 'badge-danger', default => 'badge-secondary',
    };
@endphp

@push('styles')
<style>
    .implan-card { background: #f8f9fa; border-radius: 15px; padding: 12px; border: none; }
    .implementation-list { display: flex; flex-direction: column; gap: 8px; }
    .implementation-item { background: #fff; border-radius: 12px; padding: 18px 24px; box-shadow: 0 2px 4px rgba(0,0,0,.05); transition: all .2s ease; }
    .implementation-item:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,.1); }
    .item-content { display: flex; align-items: center; gap: 16px; min-height: 56px; }
    .agency-info { flex: 1; display: flex; align-items: center; gap: 12px; }
    .agency-icon { width: 40px; height: 40px; min-width: 40px; border-radius: 50%; object-fit: contain; background: #fff; }
    .program-details h4 { margin: 0 0 4px; font-size: 16px; font-weight: 500; }
    .program-details .subtitle { margin: 0; color: #666; font-size: 14px; }
    .action-buttons { display: flex; align-items: center; gap: 8px; }
    .btn-view { background: #f8f0eb; color: #fe7936; padding: 6px 16px; border-radius: 20px; text-decoration: none; font-size: 14px; }
    .btn-view:hover { background: #fde4d8; color: #fe7936; }
    .badge-soft-success { background: #e8f5e9; color: #2e7d32; }
    .status-badge .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: normal; }
    .custom-request-btn { background-color: #35127d !important; color: #fff !important; border-radius: 8px; padding: 8px 16px; border: none; display: inline-flex; align-items: center; gap: 8px; }
    .custom-request-btn:hover { background-color: #280274 !important; }
    .status-button { background-color: #280274; color: #fff; border: none; padding: 8px 20px; border-radius: 20px; font-size: 14px; }
    .status-button:hover { background-color: #35127d; color: #fff; }
    .no-data { text-align: center; padding: 40px; color: #666; }
</style>
@endpush

@section('content')
    <div class="row mb-4">
        <div class="col-8 col-xl-8 mb-3 mb-xl-0">
            <h3 class="font-weight-bold">Implementation Plan
                <span class="fs-4" style="color: #fe7936; font-weight: bold;">- {{ $agency?->acronym }}</span>
            </h3>
            <h6 class="mb-0" style="color: rgba(156,156,156,1); font-weight: 300;">
                <span style="color: #280274; font-weight: bold;">View and manage assigned implementation plans</span> — accept, reject, and track their status.
            </h6>
        </div>
        <div class="col-4 col-xl-4">
            <div class="justify-content-end d-flex">
                <button class="btn btn-sm btn-light bg-white">
                    <i class="mdi mdi-calendar"></i> Today is <span class="text-primary">{{ now()->format('d F Y') }}</span>
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-12 grid-margin stretch-card">
        <div class="card implan-card">
            <div class="card-body">
                <div class="d-flex justify-content-end mb-3">
                    <button type="button" class="btn custom-request-btn" data-bs-toggle="modal" data-bs-target="#implementationModal">
                        <i class="mdi mdi-plus"></i> Request Implementation
                        @if ($grouped['pending']->count())
                            <span class="badge badge-warning ms-1">{{ $grouped['pending']->count() }}</span>
                        @endif
                    </button>
                </div>

                <div class="implementation-list">
                    @forelse ($grouped['accepted'] as $row)
                        <div class="implementation-item">
                            <div class="item-content">
                                <div class="agency-info">
                                    <img src="{{ $agencyLogo }}" class="agency-icon" alt="{{ $agency?->acronym }}"
                                         onerror="this.onerror=null;this.src='{{ asset('assets/img/kc-logo.svg') }}'">
                                    <div class="program-details">
                                        <h4>{{ $row->issues ?: 'No issue specified' }}</h4>
                                        <p class="subtitle">{{ $row->uploaded_at?->format('M d, Y') ?? 'Date not available' }}</p>
                                    </div>
                                </div>
                                <div class="action-buttons">
                                    <a href="{{ route('gov_agency.implan.show', $row) }}" class="btn-view"><i class="mdi mdi-eye"></i> View</a>
                                    <div class="status-badge"><span class="badge badge-soft-success">Accepted</span></div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="no-data">No accepted implementations available.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Request Implementation modal (pending plans) --}}
    <div class="modal fade" id="implementationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:15px;overflow:hidden;">
                <div class="modal-header" style="background-color:#35127d;height:8px;padding:.5rem;border:none;"></div>
                <div class="modal-body">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="float:right;"></button>
                    <h3 style="color:#35127d;font-weight:bold;">New List of Implan</h3>
                    <p class="text-muted">Verify Implan</p>

                    <div class="implementation-list">
                        @forelse ($grouped['pending'] as $row)
                            <div class="implementation-item">
                                <div class="item-content">
                                    <div class="agency-info">
                                        <img src="{{ $agencyLogo }}" class="agency-icon" alt="{{ $agency?->acronym }}"
                                             onerror="this.onerror=null;this.src='{{ asset('assets/img/kc-logo.svg') }}'">
                                        <div class="program-details">
                                            <h4>{{ $row->issues ?: 'No program specified' }}</h4>
                                            <p class="subtitle">{{ $row->uploaded_at?->format('M d, Y') ?? 'Date not available' }}</p>
                                        </div>
                                        <div class="status-badge">
                                            <span class="badge {{ $statusBadge($row->status) }}">{{ $row->status }}</span>
                                        </div>
                                    </div>
                                    <div class="action-buttons">
                                        <a href="{{ route('gov_agency.implan.show', $row) }}" class="btn-view">Details</a>
                                        <form method="POST" action="{{ route('gov_agency.implan.respond', $row) }}" style="display:inline;">
                                            @csrf
                                            <input type="hidden" name="response_status" value="accepted">
                                            <button type="submit" class="btn btn-sm status-button">Accept</button>
                                        </form>
                                        <button type="button" class="btn btn-sm status-button"
                                                data-bs-toggle="modal" data-bs-target="#rejectModal"
                                                data-reject-action="{{ route('gov_agency.implan.respond', $row) }}">
                                            Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="no-data">No pending implementations available.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Reject modal --}}
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:15px;overflow:hidden;">
                <div class="modal-header" style="background-color:#35127d;height:8px;padding:.5rem;border:none;"></div>
                <div class="modal-body">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="float:right;"></button>
                    <h3 style="color:#35127d;font-weight:bold;">Reject Implementation Plan</h3>
                    <p class="text-muted">Please provide a reason for rejection</p>
                    <form method="POST" data-reject-form>
                        @csrf
                        <input type="hidden" name="response_status" value="rejected">
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection</label>
                            <textarea name="rejection_reason" rows="4" required class="form-control" style="min-height:100px;"></textarea>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" data-bs-dismiss="modal" class="btn btn-secondary">Cancel</button>
                            <button class="btn btn-danger">Submit Rejection</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-reject-action]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelector('[data-reject-form]').action = btn.dataset.rejectAction;
        });
    });
</script>
@endpush
