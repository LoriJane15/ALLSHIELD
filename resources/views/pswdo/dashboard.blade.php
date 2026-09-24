@extends('layouts.skydash-v')
@section('title', 'PSWDO Dashboard')
@section('heading', 'PSWDO Enrollment')

@section('content')
<div class="pswdo-dashboard-container">
    {{-- Modern Compact Hero Banner --}}
    <div class="pswdo-hero mb-4" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%); border-radius: 16px; padding: 1.25rem 1.6rem; position: relative; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.12); box-shadow: 0 8px 24px -4px rgba(30, 27, 75, 0.25);">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="position: relative; z-index: 2; gap: 1rem;">
            <div class="d-flex align-items-center" style="gap: 1.15rem;">
                <div class="hero-icon-box" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.22); display: flex; align-items: center; justify-content: center; color: #a5f3fc; font-size: 1.45rem; flex-shrink: 0;">
                    <i class="mdi mdi-account-group-outline"></i>
                </div>
                <div>
                    <div style="color: #fbbf24; font-size: 0.6875rem; font-weight: 750; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 0.15rem;">
                        PSWDO Executive Portal
                    </div>
                    <h1 class="h4 font-weight-bold mb-0 text-white" style="letter-spacing: -0.02em; font-size: 1.4rem;">Enrollment Dashboard</h1>
                    <p class="mb-0 text-white-50" style="font-size: 0.8125rem; margin-top: 0.2rem;">Eligible surfaced FR enrollment overview and document tracking.</p>
                </div>
            </div>
            <div class="d-flex align-items-center flex-wrap" style="gap: 0.75rem;">
                <div class="d-none d-sm-inline-flex align-items-center" style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.18); color: #e0e7ff; font-size: 0.725rem; font-weight: 600; padding: 0.45rem 0.8rem; border-radius: 8px; gap: 0.4rem;">
                    <i class="mdi mdi-clock-outline"></i> Last updated: {{ now()->format('M d, Y · h:i A') }}
                </div>
                <a class="btn" href="{{ route('pswdo.enrollments.index') }}" style="background: #ffffff; color: #312e81; font-weight: 800; font-size: 0.875rem; padding: 0.55rem 1.25rem; border-radius: 10px; border: none; box-shadow: 0 4px 14px rgba(0,0,0,0.18); transition: all 0.2s ease;">
                    <i class="mdi mdi-clipboard-text-outline mr-1"></i> FRs for Enrollment
                </a>
            </div>
        </div>
    </div>

    {{-- Improved High-Contrast KPI Metric Cards --}}
    <div class="row mb-4">
        {{-- Card 1: Eligible FRs (Purple) --}}
        <div class="col-md-4 mb-3">
            <div style="background: #ffffff; border: 1px solid #e0e7ff; border-top: 3px solid #4338ca; border-radius: 14px; padding: 1.15rem 1.35rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05); height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span style="font-size: 0.785rem; font-weight: 750; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Eligible FRs</span>
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: #eef2ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="mdi mdi-account-multiple-check"></i>
                    </div>
                </div>
                <div style="font-size: 2.15rem; font-weight: 800; color: #1e1b4b; line-height: 1; margin: 0.25rem 0 0.4rem 0;">{{ number_format($total) }}</div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="border-color: #f1f5f9 !important;">
                    <span style="font-size: 0.785rem; color: #64748b;">Surfaced FRs ready for intake</span>
                    <span class="pswdo-badge-soft badge-purple" style="font-size: 0.7rem; padding: 0.15rem 0.5rem;">Intake Ready</span>
                </div>
            </div>
        </div>

        {{-- Card 2: Pending (Amber) --}}
        <div class="col-md-4 mb-3">
            <div style="background: #ffffff; border: 1px solid #fde68a; border-top: 3px solid #d97706; border-radius: 14px; padding: 1.15rem 1.35rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05); height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span style="font-size: 0.785rem; font-weight: 750; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Pending Action</span>
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: #fffbeb; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        <i class="mdi mdi-clock-alert-outline"></i>
                    </div>
                </div>
                <div style="font-size: 2.15rem; font-weight: 800; color: #78350f; line-height: 1; margin: 0.25rem 0 0.4rem 0;">{{ number_format($pending) }}</div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="border-color: #fef3c7 !important;">
                    <span style="font-size: 0.785rem; color: #64748b;">Awaiting required documents</span>
                    <span class="pswdo-badge-soft badge-amber" style="font-size: 0.7rem; padding: 0.15rem 0.5rem;">In Progress</span>
                </div>
            </div>
        </div>

        {{-- Card 3: Completed (Green with High-Contrast Icon) --}}
        <div class="col-md-4 mb-3">
            <div style="background: #ffffff; border: 1px solid #a7f3d0; border-top: 3px solid #059669; border-radius: 14px; padding: 1.15rem 1.35rem; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05); height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span style="font-size: 0.785rem; font-weight: 750; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Completed</span>
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; box-shadow: 0 2px 5px rgba(16, 185, 129, 0.15);">
                        <i class="mdi mdi-checkbox-marked-circle-outline"></i>
                    </div>
                </div>
                <div style="font-size: 2.15rem; font-weight: 800; color: #064e3b; line-height: 1; margin: 0.25rem 0 0.4rem 0;">{{ number_format($completed) }}</div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="border-color: #d1fae5 !important;">
                    <span style="font-size: 0.785rem; color: #64748b;">Fully documented and endorsed</span>
                    <span class="pswdo-badge-soft badge-green" style="font-size: 0.7rem; padding: 0.15rem 0.5rem;">{{ $total > 0 ? round(($completed / $total) * 100) : 0 }}% Done</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Visual Centerpiece: Intake & Verification Lifecycle with Connected Stepper & Filter Tabs --}}
    <section class="pswdo-modern-table-card p-4 mb-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap pb-3 mb-3 border-bottom" style="gap: 1rem;">
            <div>
                <h2 class="h6 font-weight-bold mb-1" style="color: #0f172a; font-size: 1.05rem;">
                    <i class="mdi mdi-source-branch text-primary mr-2" style="font-size: 1.3rem;"></i> Intake &amp; Verification Lifecycle
                </h2>
                <p class="text-muted mb-0 small">Follow each surfaced FR from prerequisite completion to PSWDO enrollment.</p>
            </div>
            {{-- Segmented Filter Tabs --}}
            <div class="pswdo-tab-group">
                <a href="{{ route('pswdo.enrollments.index') }}" class="pswdo-tab-btn active">
                    <span>All eligible</span>
                    <span class="pswdo-tab-pill">{{ $total }}</span>
                </a>
                <a href="{{ route('pswdo.enrollments.index', ['status' => 'pending']) }}" class="pswdo-tab-btn">
                    <span>Needs action</span>
                    <span class="pswdo-tab-pill" style="background: #fef3c7; color: #b45309;">{{ $pending }}</span>
                </a>
                <a href="{{ route('pswdo.enrollments.index', ['status' => 'completed']) }}" class="pswdo-tab-btn">
                    <span>Completed</span>
                    <span class="pswdo-tab-pill" style="background: #d1fae5; color: #047857;">{{ $completed }}</span>
                </a>
            </div>
        </div>

        {{-- Connected Stepper Grid --}}
        <div class="pswdo-stepper-grid">
            @php
                $stepStages = [
                    ['title' => 'Surfaced FRs', 'badge' => 'Stage 1', 'status_label' => 'Verified Intake', 'class' => 'is-completed', 'badge_class' => 'badge-done', 'icon_bg' => '#ecfdf5', 'icon_color' => '#059669', 'icon' => 'mdi-account-search-outline'],
                    ['title' => 'CDR Completed', 'badge' => 'Stage 2', 'status_label' => 'Prerequisite Done', 'class' => 'is-completed', 'badge_class' => 'badge-done', 'icon_bg' => '#ecfdf5', 'icon_color' => '#059669', 'icon' => 'mdi-file-check-outline'],
                    ['title' => 'Eligible for PSWDO', 'badge' => 'Stage 3', 'status_label' => 'Active Intake', 'class' => 'is-active', 'badge_class' => 'badge-current', 'icon_bg' => '#eef2ff', 'icon_color' => '#4338ca', 'icon' => 'mdi-account-check-outline'],
                    ['title' => 'Enrollment Complete', 'badge' => 'Stage 4', 'status_label' => 'Final Endorsed', 'class' => 'is-upcoming', 'badge_class' => 'badge-next', 'icon_bg' => '#f1f5f9', 'icon_color' => '#64748b', 'icon' => 'mdi-check-decagram'],
                ];
            @endphp

            @foreach($pipeline as $index => $stage)
                @php($meta = $stepStages[$index] ?? $stepStages[0])
                <div class="pswdo-step-card {{ $meta['class'] }}">
                    <div>
                        <div class="pswdo-step-header">
                            <span class="pswdo-step-badge {{ $meta['badge_class'] }}">{{ $meta['badge'] }}</span>
                            <div class="pswdo-step-icon" style="background: {{ $meta['icon_bg'] }}; color: {{ $meta['icon_color'] }};">
                                <i class="mdi {{ $stage['icon'] ?? $meta['icon'] }}"></i>
                            </div>
                        </div>
                        <div class="pswdo-step-title">{{ $stage['label'] }}</div>
                        <div class="pswdo-step-desc">{{ $meta['status_label'] }}</div>
                    </div>
                    <div class="d-flex align-items-baseline justify-content-between pt-2 border-top" style="border-color: #f1f5f9;">
                        <span class="pswdo-step-count">{{ number_format($stage['count']) }}</span>
                        <span class="small font-weight-bold" style="color: #94a3b8;">Records</span>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 2-Column Responsive Stacked Layout (Eliminates awkward row height gaps) --}}
    <div class="row">
        {{-- Left Column: Action Required Queue & Recent Activity --}}
        <div class="col-lg-7 mb-4">
            <div class="d-flex flex-column" style="gap: 1.5rem;">
                {{-- Action Required Queue --}}
                <section class="pswdo-modern-table-card">
                    <div class="pswdo-card-header-clean">
                        <div>
                            <h2><i class="mdi mdi-alert-circle-outline" style="color: #d97706;"></i> Action Required Queue</h2>
                            <p class="small text-muted mb-0 mt-1">Enrollments missing one or more required final signed documents.</p>
                        </div>
                        <a href="{{ route('pswdo.enrollments.index', ['status' => 'pending']) }}" class="small font-weight-bold text-primary" style="text-decoration: none;">
                            View all <i class="mdi mdi-arrow-right"></i>
                        </a>
                    </div>
                    <div>
                        @forelse($actionQueue as $enrollment)
                            @php($fr = $enrollment->surfacedFormerRebel)
                            @php($documentCount = $enrollment->completedDocumentCount())
                            <div class="pswdo-action-item">
                                <div class="d-flex align-items-center" style="gap: 0.85rem; min-width: 0;">
                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: #eef2ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; box-shadow: 0 2px 6px rgba(67, 56, 202, 0.12);">
                                        <i class="mdi mdi-account-outline"></i>
                                    </div>
                                    <div style="min-width: 0;">
                                        <div class="font-weight-bold text-truncate" style="color: #0f172a; font-size: 0.9rem;">
                                            {{ $fr->reference_number }} &middot; {{ $fr->display_name }}
                                        </div>
                                        <div class="small text-muted text-truncate" style="margin-top: 1px;">
                                            <i class="mdi mdi-map-marker-outline text-muted"></i>
                                            {{ $fr->barangay?->name ? $fr->barangay->name.', ' : '' }}{{ $fr->municipality?->name }}
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center flex-shrink-0" style="gap: 0.9rem;">
                                    <div class="text-right d-none d-sm-block">
                                        <span class="pswdo-badge-soft badge-amber" style="font-size: 0.72rem; padding: 0.2rem 0.5rem;">
                                            {{ $documentCount }} of {{ $requiredDocuments }} docs
                                        </span>
                                        <div class="progress mt-1" style="height: 5px; width: 95px; border-radius: 9999px; background: #f1f5f9;">
                                            <div class="progress-bar" style="width: {{ ($documentCount / $requiredDocuments) * 100 }}%; background: #d97706; border-radius: 9999px;"></div>
                                        </div>
                                    </div>
                                    <a href="{{ route('pswdo.enrollments.workspace', $enrollment) }}" class="btn-pswdo-action">
                                        <i class="mdi mdi-pencil-box-outline"></i> Review Enrollment
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-center text-muted small">
                                <i class="mdi mdi-check-all font-size-24 d-block mb-1 text-success"></i>
                                Every eligible enrollment currently has all its required documents.
                            </div>
                        @endforelse
                    </div>
                </section>

                {{-- Recent Activity --}}
                <section class="pswdo-modern-table-card">
                    <div class="pswdo-card-header-clean">
                        <div>
                            <h2><i class="mdi mdi-history" style="color: #4338ca;"></i> Recent Activity</h2>
                            <p class="small text-muted mb-0 mt-1">Latest document uploads and verifications.</p>
                        </div>
                    </div>
                    <div>
                        @forelse($recentDocuments as $item)
                            <div class="pswdo-activity-item">
                                <div class="d-flex align-items-center" style="gap: 0.85rem; min-width: 0;">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #ecfdf5; color: #059669; flex-shrink: 0; box-shadow: 0 2px 5px rgba(16, 185, 129, 0.15);">
                                        <i class="mdi mdi-file-check-outline" style="font-size: 1.1rem;"></i>
                                    </div>
                                    <div style="min-width: 0;">
                                        <div class="font-weight-bold text-truncate" style="color: #0f172a; font-size: 0.875rem;">
                                            {{ $item['document']->document_type->label() }}
                                        </div>
                                        <div class="small text-muted text-truncate" style="margin-top: 1px;">
                                            <span class="font-weight-bold" style="color: #475569;">{{ $item['enrollment']->surfacedFormerRebel->reference_number }}</span> &middot; {{ $item['document']->uploaded_at?->format('M j, Y h:i A') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center flex-shrink-0" style="gap: 0.5rem;">
                                    <span class="pswdo-badge-soft badge-green d-none d-sm-inline-flex" style="font-size: 0.6875rem; padding: 0.2rem 0.5rem;">
                                        Uploaded
                                    </span>
                                    <a href="{{ route('pswdo.enrollments.show', $item['enrollment']) }}" class="btn btn-sm btn-outline-primary" style="font-weight: 700; border-radius: 8px; padding: 0.35rem 0.85rem; font-size: 0.785rem;">
                                        View
                                    </a>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-center text-muted small">No final documents have been uploaded yet.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>

        {{-- Right Column: Document Completion & Area Summary --}}
        <div class="col-lg-5 mb-4">
            <div class="d-flex flex-column" style="gap: 1.5rem;">
                {{-- Document Completion --}}
                <section class="pswdo-modern-table-card">
                    <div class="pswdo-card-header-clean">
                        <div>
                            <h2><i class="mdi mdi-file-check-outline" style="color: #4338ca;"></i> Document Completion</h2>
                            <p class="small text-muted mb-0 mt-1">Status across all eligible candidate files.</p>
                        </div>
                    </div>
                    <div class="p-3">
                        @foreach($documentBreakdown as $document)
                            @php($pct = $total > 0 ? round(($document['count'] / $total) * 100) : 0)
                            @php($isComplete = $total > 0 && $document['count'] === $total)
                            <div class="pswdo-doc-progress-item">
                                <div class="d-flex justify-content-between align-items-center small mb-2">
                                    <span class="font-weight-bold text-truncate" style="color: #1e293b; font-size: 0.825rem; max-width: 60%;">
                                        {{ $document['label'] }}
                                    </span>
                                    <div class="d-flex align-items-center" style="gap: 0.5rem;">
                                        <span class="pswdo-badge-soft {{ $isComplete ? 'badge-green' : 'badge-amber' }}" style="font-size: 0.6875rem; padding: 0.15rem 0.45rem;">
                                            {{ $isComplete ? 'Complete' : 'Pending' }}
                                        </span>
                                        <strong style="color: {{ $isComplete ? '#059669' : '#4338ca' }}; font-size: 0.825rem;">
                                            {{ $document['count'] }} / {{ $total }} ({{ $pct }}%)
                                        </strong>
                                    </div>
                                </div>
                                <div class="progress" style="height: 7px; border-radius: 9999px; background: #e2e8f0;">
                                    <div class="progress-bar" style="width: {{ $pct }}%; background: {{ $isComplete ? '#10b981' : 'linear-gradient(90deg, #4338ca, #6366f1)' }}; border-radius: 9999px;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Area Summary --}}
                <section class="pswdo-modern-table-card">
                    <div class="pswdo-card-header-clean">
                        <div>
                            <h2><i class="mdi mdi-map-marker-multiple" style="color: #4338ca;"></i> Area Summary</h2>
                            <p class="small text-muted mb-0 mt-1">Eligible enrollments by municipality.</p>
                        </div>
                    </div>
                    <div>
                        @forelse($areaSummary as $area)
                            <div class="pswdo-area-item">
                                <div>
                                    <div class="font-weight-bold" style="color: #0f172a; font-size: 0.885rem;">{{ $area['name'] }}</div>
                                    <div class="small text-muted" style="margin-top: 2px;">{{ $area['completed'] }} completed of {{ $area['total'] }} total</div>
                                </div>
                                <span class="pswdo-badge-soft badge-purple" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 999px;">
                                    <i class="mdi mdi-account-multiple-outline mr-1"></i> {{ $area['total'] }} eligible
                                </span>
                            </div>
                        @empty
                            <div class="p-4 text-center text-muted small">No area data is available yet.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>

    @if($total === 0)
        <div class="pswdo-empty-state mt-3">
            <div class="pswdo-empty-icon">
                <i class="mdi mdi-shield-account-outline"></i>
            </div>
            <h3 class="h6 font-weight-bold text-dark mb-2">No Eligible Surfaced FRs Available</h3>
            <p class="text-muted mb-0 mx-auto" style="max-width: 540px; font-size: 0.875rem; line-height: 1.5;">
                No surfaced FR currently has both a completed final CDR and a completed final JAPIC certification.
            </p>
        </div>
    @endif
</div>
@endsection
