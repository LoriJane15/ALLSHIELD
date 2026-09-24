@extends('layouts.skydash-v')
@section('title', 'IMPLAN')
@section('heading', 'Implementation Plans')

@php
    $badge = fn ($s) => match ($s) {
        'not yet started' => 'badge-danger', 'ongoing' => 'badge-primary',
        'verified' => 'badge-success', 'for verification' => 'badge-info', default => 'badge-secondary',
    };
@endphp

@push('styles')
<style>
    .status-card { border: 0; border-radius: 16px; box-shadow: 0 4px 8px rgba(0,0,0,.08); }
    .status-card .icon-wrap { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .status-card h2 { font-size: 24px; font-weight: 600; margin: 0; }
    .status-card p { color: #808191; font-size: 14px; margin: 0; }
    .implementation-list { display: flex; flex-direction: column; gap: 10px; }
    .implementation-item { display: flex; align-items: center; padding: 18px 20px; background: #fff; border: 1px solid #eee; border-radius: 10px; transition: all .2s ease; }
    .implementation-item:hover { box-shadow: 0 4px 8px rgba(0,0,0,.08); }
    .impl-logo { width: 45px; height: 45px; min-width: 45px; margin-right: 20px; border-radius: 50%; background: #f4f5f7; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .impl-logo img { width: 100%; height: 100%; object-fit: contain; }
    .btn-open { background: #FFE0D3; color: #000; border: none; padding: 6px 18px; border-radius: 20px; font-size: 14px; text-decoration: none; }
    .btn-open:hover { background: #ffd0bd; color: #000; }
</style>
@endpush

@section('content')
    <div class="row mb-3">
        <div class="col-8 col-xl-8 mb-3 mb-xl-0">
            <h3 class="font-weight-bold">Implementation Plan</h3>
        </div>
        <div class="col-4 col-xl-4">
            <div class="justify-content-end d-flex">
                <button class="btn btn-sm btn-light bg-white">
                    <i class="mdi mdi-calendar"></i> Today is <span class="text-primary">{{ now()->format('d F Y') }}</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Status cards --}}
    <div class="row mb-4">
        @foreach ([
            ['Ongoing', $counts['ongoing'], '#F8F7FF', '#E8E5FF', '#6C5DD3', 'icon-refresh'],
            ['For Verification', $counts['verification'], '#F1FAFF', '#E2F5FF', '#3E7BFA', 'icon-magnifier'],
            ['Verified', $counts['verified'], '#F0FFF7', '#E2FFE9', '#1AB76C', 'icon-check'],
            ['Not Yet Started', $counts['not_started'], '#FFF1F1', '#FFE2E2', '#FF4B4B', 'icon-control-pause'],
        ] as [$label, $value, $cardBg, $iconBg, $iconColor, $icon])
            <div class="col-md-3 mb-3">
                <div class="card status-card h-100" style="background: {{ $cardBg }};">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-wrap" style="background: {{ $iconBg }};">
                                <i class="{{ $icon }}" style="color: {{ $iconColor }}; font-size: 20px;"></i>
                            </div>
                            <div>
                                <h2>{{ $value }}</h2>
                                <p>{{ $label }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addImplanModal">
            <i class="ti-plus"></i> Add Implementation
        </button>
    </div>

    <div class="col-md-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
                <div class="implementation-list">
                    @forelse ($implans as $im)
                        <div class="implementation-item">
                            <div class="impl-logo">
                                <img src="{{ asset('assets/img/agencies/dilg.png') }}" alt="logo" onerror="this.style.display='none'">
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <h5 class="mb-0" style="font-size:1rem;font-weight:600;">{{ $im->issues ?: 'Untitled IMPLAN' }}</h5>
                                            <span class="badge rounded-pill {{ $badge($im->status) }}">{{ $im->status }}</span>
                                        </div>
                                        <small class="text-muted"><i>{{ $im->uploaded_at?->format('M d, Y') ?? '—' }} · {{ count($im->agencies ?? []) }} agency(ies)</i></small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ route('lgu.implan.show', $im) }}" class="btn-open">Open</a>
                                        <form method="POST" action="{{ route('lgu.implan.destroy', $im) }}"
                                              data-confirm="Delete this IMPLAN?" data-confirm-title="Confirm delete" data-confirm-action="Delete" style="display:inline;">
                                            @csrf @method('DELETE')
                                            <button type="submit" style="background:none;border:none;padding:0;cursor:pointer;" title="Delete">
                                                <i class="icon-trash delete-icon" style="font-size:18px;"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">No implementation plans yet.</div>
                    @endforelse
                </div>

                <div class="mt-3">{{ $implans->links() }}</div>
            </div>
        </div>
    </div>

    {{-- Add modal --}}
    <div class="modal fade" id="addImplanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:15px;overflow:hidden;">
                <div class="modal-header" style="background-color:#35127d;height:8px;padding:.5rem;border:none;"></div>
                <div class="modal-body">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="float:right;"></button>
                    <h3 style="color:#35127d;font-weight:bold;">Identified Priority Need</h3>
                    <p class="text-muted">Add issues or concerns to be addressed and responsible agencies</p>
                    <form method="POST" action="{{ route('lgu.implan.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Issues and Concerns to be Addressed</label>
                            <textarea name="issues" rows="3" required class="form-control">{{ old('issues') }}</textarea>
                            @error('issues') <p class="mt-1 text-danger small">{{ $message }}</p> @enderror
                        </div>
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label class="form-label">Target Areas</label>
                                <div class="border rounded p-2" style="max-height:10rem;overflow-y:auto">
                                    @forelse ($targetAreas as $area)
                                        <div class="form-check">
                                            <input type="checkbox" name="target_areas[]" value="{{ $area['id'] }}" class="form-check-input" id="area_{{ $area['id'] }}">
                                            <label class="form-check-label" for="area_{{ $area['id'] }}">{{ $area['name'] }}</label>
                                        </div>
                                    @empty
                                        <p class="text-muted small mb-0">No RCSP barangays yet.</p>
                                    @endforelse
                                </div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="form-label">Responsible Agency</label>
                                <div class="border rounded p-2" style="max-height:10rem;overflow-y:auto">
                                    @foreach ($agencies as $agency)
                                        <div class="form-check">
                                            <input type="checkbox" name="agencies[]" value="{{ $agency->id }}" class="form-check-input" id="agency_{{ $agency->id }}">
                                            <label class="form-check-label" for="agency_{{ $agency->id }}">{{ $agency->acronym }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        @push('scripts')
            <script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('addImplanModal')).show());</script>
        @endpush
    @endif
@endsection
