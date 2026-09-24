@extends('layouts.skydash-v')
@section('title', 'FRs for Enrollment')
@section('heading', 'PSWDO Enrollment')

@section('content')
<div class="pswdo-dashboard-container">
    {{-- Modern Title Hero Card --}}
    <div class="pswdo-hero mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="position: relative; z-index: 2; gap: 1rem;">
            <div class="d-flex align-items-center" style="gap: 1.15rem;">
                <div class="hero-icon-box" style="width: 52px; height: 52px; border-radius: 14px; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.18); display: flex; align-items: center; justify-content: center; color: #67e8f9; font-size: 1.6rem; flex-shrink: 0;">
                    <i class="mdi mdi-clipboard-text-outline"></i>
                </div>
                <div>
                    <div style="color: #f59e0b; font-size: 0.6875rem; font-weight: 800; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.2rem;">
                        PSWDO Portal
                    </div>
                    <h1 class="h4 font-weight-bold mb-1 text-white" style="letter-spacing: -0.01em;">FRs for Enrollment</h1>
                    <p class="mb-0 text-white-50" style="font-size: 0.825rem;">Surfaced FRs with valid completed CDR and JAPIC prerequisite documents.</p>
                </div>
            </div>
            <div class="d-flex align-items-center" style="gap: 0.65rem;">
                <div class="badge badge-light px-3 py-2" style="border-radius: 10px; font-weight: 750; font-size: 0.8125rem; color: #312e81; background: rgba(255, 255, 255, 0.95);">
                    <i class="mdi mdi-account-multiple-check mr-1 text-primary"></i>
                    {{ $enrollments->total() }} Total Eligible
                </div>
            </div>
        </div>
    </div>

    <section class="pswdo-modern-table-card">
        <div class="table-responsive">
            <table class="table pswdo-table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Reference / FR Name</th>
                        <th>Category</th>
                        <th>Area Location</th>
                        <th>Documents Status</th>
                        <th>Enrollment</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($enrollments as $enrollment)
                    @php($fr = $enrollment->surfacedFormerRebel)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center" style="gap: 0.75rem;">
                                <div style="width: 36px; height: 36px; border-radius: 10px; background: #eef2ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                                    <i class="mdi mdi-account"></i>
                                </div>
                                <div>
                                    <strong style="color: #0f172a; font-size: 0.9rem;">{{ $fr->reference_number }}</strong>
                                    <div class="text-muted" style="font-size: 0.8125rem;">{{ $fr->display_name }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-weight: 700; padding: 0.35rem 0.65rem; border-radius: 6px;">
                                {{ $fr->category->value }}
                            </span>
                        </td>
                        <td style="color: #334155; font-weight: 500;">
                            <i class="mdi mdi-map-marker-outline text-muted mr-1"></i>
                            {{ $fr->barangay?->name ? $fr->barangay->name.', ' : '' }}{{ $fr->municipality->name }}
                        </td>
                        <td>
                            <div class="d-flex align-items-center" style="gap: 0.5rem;">
                                <div style="width: 100px; height: 6px; background: #e2e8f0; border-radius: 9999px; overflow: hidden;">
                                    <div style="width: {{ ($enrollment->completedDocumentCount() / 4) * 100 }}%; height: 100%; background: #4338ca; border-radius: 9999px;"></div>
                                </div>
                                <span class="small font-weight-bold" style="color: #475569;">
                                    {{ $enrollment->completedDocumentCount() }} / 4 completed
                                </span>
                            </div>
                        </td>
                        <td>
                            @if($enrollment->isCompleted())
                                <span class="badge badge-success" style="padding: 0.4rem 0.8rem; border-radius: 9999px; font-weight: 750;">
                                    <i class="mdi mdi-check-circle-outline mr-1"></i> Completed
                                </span>
                            @else
                                <span class="badge badge-info" style="padding: 0.4rem 0.8rem; border-radius: 9999px; font-weight: 750; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">
                                    <i class="mdi mdi-clock-outline mr-1"></i> Pending
                                </span>
                            @endif
                        </td>
                        <td class="text-right">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('pswdo.enrollments.show', $enrollment) }}" style="font-weight: 700; border-radius: 8px; padding: 0.4rem 0.9rem;">
                                View Profile <i class="mdi mdi-arrow-right ml-1"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="pswdo-empty-icon" style="margin-bottom: 0.75rem;">
                                <i class="mdi mdi-folder-outline"></i>
                            </div>
                            <div class="text-muted" style="font-size: 0.9rem;">No eligible surfaced FRs are available for enrollment.</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($enrollments->hasPages())
            <div class="card-body border-top py-3" style="background: #fafbfc;">
                {{ $enrollments->links() }}
            </div>
        @endif
    </section>
</div>
@endsection
