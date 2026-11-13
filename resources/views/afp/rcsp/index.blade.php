@extends('layouts.skydash-h')
@section('title', 'RCSP Barangays')
@section('heading', 'RCSP Barangay Monitoring')

@php
    $badge = fn ($s) => match ($s) {
        'Completed' => 'badge badge-success', 'Ongoing' => 'badge badge-warning', default => 'badge badge-secondary',
    };
@endphp

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <h2><span style="color: orange;">RCSP</span> Barangay Monitoring</h2>
            <p class="text-muted mb-0">
                Read-only monitoring of RCSP evaluation data for barangays across the municipalities/cities —
                information, documentation, and monitoring progress.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <select name="municipality_id" class="form-select" style="width:12rem;" onchange="this.form.submit()">
                        <option value="">All municipalities</option>
                        @foreach ($municipalities as $m)
                            <option value="{{ $m->id }}" @selected(request('municipality_id') == $m->id)>{{ $m->name }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="form-select" style="width:10rem;" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach (['Pending', 'Ongoing', 'Completed'] as $st)
                            <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <span class="text-muted small">Read-only monitoring view</span>
            </form>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Province</th>
                            <th>Municipality/City</th>
                            <th>Barangay</th>
                            <th>Status</th>
                            <th style="width:16rem">Progress</th>
                            <th>Uploaded</th>
                            <th>Timeline</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($barangays as $b)
                            <tr>
                                <td>Davao del Sur</td>
                                <td>{{ $b->municipality?->name }}</td>
                                <td class="font-weight-medium">{{ $b->barangay?->name ?? 'Barangay #'.$b->barangay_id }}</td>
                                <td><span class="{{ $badge($b->status) }}">{{ $b->status }}</span></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height:8px">
                                            <div class="progress-bar bg-success" style="width: {{ $b->progress }}%"></div>
                                        </div>
                                        <span class="text-muted small text-nowrap">Phase {{ $b->current_phase }}/5</span>
                                    </div>
                                </td>
                                <td>{{ $b->created_at?->format('Y/m/d') ?? '—' }}</td>
                                <td><a href="{{ route('afp.rcsp.timeline.show', $b) }}" class="btn btn-sm btn-outline-primary">View timeline</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5">No RCSP barangays.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $barangays->links() }}</div>
        </div>
    </div>
@endsection
