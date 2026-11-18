@extends('layouts.skydash-v')
@section('title', 'Municipality IMPLAN Monitoring')
@section('heading', 'Municipality IMPLAN Monitoring')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h3 class="font-weight-bold mb-1">Municipality IMPLAN Monitoring</h3>
            <p class="text-muted mb-0">Read-only monitoring of submitted municipality IMPLANs involving your agency.</p>
        </div>
        <a href="{{ route('gov_agency.implan.index') }}" class="btn btn-light">Back</a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-monitoring-table>
                    <thead>
                        <tr>
                            <th>Municipality</th>
                            <th>Assigned IMPLANs</th>
                            <th>Latest Submission</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($municipalities as $municipality)
                            @php
                                $summary = $monitoringSummaries[$municipality->id] ?? null;
                                $implanCount = (int) ($summary?->implan_count ?? 0);
                            @endphp
                            <tr data-monitoring-municipality="{{ $municipality->id }}">
                                <td>{{ $municipality->name }}</td>
                                <td data-monitoring-count="{{ $implanCount }}">
                                    {{ $implanCount }} IMPLAN{{ $implanCount === 1 ? '' : 's' }}
                                </td>
                                <td>
                                    {{ $summary?->latest_submission_at
                                        ? \Illuminate\Support\Carbon::parse($summary->latest_submission_at)->format('M d, Y')
                                        : '—' }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('gov_agency.implan.municipality', $municipality) }}" class="btn btn-sm btn-outline-primary">
                                        View IMPLANs
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5" data-monitoring-empty>
                                    No municipality IMPLANs are currently assigned to your agency.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($municipalities->hasPages())
        <div class="mt-3">{{ $municipalities->links() }}</div>
    @endif
@endsection
