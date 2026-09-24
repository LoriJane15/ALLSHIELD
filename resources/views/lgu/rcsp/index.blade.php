@extends('layouts.skydash-v')
@section('title', 'RCSP Barangays')
@section('heading', 'RCSP Barangays')

@php
    $municipalName = auth()->user()->municipality?->name;
    $progressPct = fn ($rb) => round(($rb->current_phase / 5) * 100);
    $statusText = function ($rb) {
        $p = round(($rb->current_phase / 5) * 100);
        return $p === 0 ? 'Not Started' : ($p === 100 ? 'Completed' : 'Phase '.$rb->current_phase.' - Ongoing');
    };
@endphp

@section('content')
    <div class="row mb-3">
        <div class="col-8 col-xl-8 mb-3 mb-xl-0">
            <h3 class="font-weight-bold">RCSP Evaluation <span class="fs-4" style="color: #280274; font-weight: bold;">- {{ $municipalName }}</span></h3>
            <h6 class="mb-0" style="color: rgba(156,156,156,1); font-weight: 300;">
                <span style="color: #280274; font-weight: bold;">Add and manage RCSP evaluation data</span> for barangays within the municipality/city, including information, documentation, and monitoring details.
            </h6>
        </div>
        <div class="col-4 col-xl-4">
            <div class="justify-content-end d-flex">
                <a href="#" class="btn btn-primary" style="border-radius: 5px;" data-bs-toggle="modal" data-bs-target="#addRcspModal">
                    <i class="ti-plus"></i> Add RCSP Barangay
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="d-flex justify-content-end mb-3">
                        <input name="search" value="{{ request('search') }}" placeholder="Search..." class="form-control" style="width:15rem;">
                    </form>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Province</th>
                                    <th>Municipality/City</th>
                                    <th>Baranggay</th>
                                    <th style="width:18rem">Status</th>
                                    <th>Uploaded</th>
                                    <th>Action</th>
                                    <th>Manage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rcspBarangays as $rb)
                                    <tr>
                                        <td>Davao del Sur</td>
                                        <td>{{ $rb->municipality?->name ?? $municipalName }}</td>
                                        <td class="font-weight-medium">{{ $rb->barangay?->name ?? 'Barangay #'.$rb->barangay_id }}</td>
                                        <td>
                                            <div class="progress-container">
                                                <div class="progress-text small mb-1">
                                                    {{ $statusText($rb) }} <span class="fw-bold">{{ $progressPct($rb) }}%</span>
                                                </div>
                                                <div class="progress" style="height:8px;">
                                                    <div class="progress-bar" role="progressbar"
                                                         style="width: {{ $progressPct($rb) }}%; background: linear-gradient(to right, rgba(191,0,0,1), rgba(255,229,0,1), rgba(0,176,39,1));"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $rb->created_at?->format('M d, Y') ?? '—' }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('lgu.rcsp.destroy', $rb) }}"
                                                  data-confirm="Delete this RCSP barangay?" data-confirm-title="Confirm delete" data-confirm-action="Delete" style="display:inline;">
                                                @csrf @method('DELETE')
                                                <button type="submit" style="background:none;border:none;padding:0;cursor:pointer;" title="Delete">
                                                    <i class="icon-trash delete-icon" style="font-size:18px;"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <a href="{{ route('lgu.monitoring.show', $rb) }}" class="btn btn-primary btn-sm">View Form</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-4">No data available for this municipality.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">{{ $rcspBarangays->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add RCSP Barangay modal --}}
    <div class="modal fade" id="addRcspModal" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="modal-title text-primary" style="font-weight:900;">Add RCSP Barangay</h5>
                        <p class="text-muted fw-light mb-0">Add identified RCSP Barangay information</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('lgu.rcsp.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Province</label>
                            <input type="text" value="Davao del Sur" readonly class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Municipal</label>
                            <input type="text" value="{{ $municipalName }}" readonly class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Barangay</label>
                            <select name="barangay_id" required class="form-select">
                                <option value="">Select Barangay</option>
                                @foreach ($available as $b)
                                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                            @error('barangay_id') <p class="mt-1 text-danger small">{{ $message }}</p> @enderror
                            @if ($available->isEmpty())
                                <p class="mt-1 text-warning small">All barangays in your municipality are already added.</p>
                            @endif
                        </div>
                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Draft</button>
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        @push('scripts')
            <script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('addRcspModal')).show());</script>
        @endpush
    @endif
@endsection
