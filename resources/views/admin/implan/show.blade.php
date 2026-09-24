@extends('layouts.skydash-h')
@section('title', 'IMPLAN')
@section('heading', 'Implementation Plan')

@php
    $statusBadge = match ($implan->status) {
        'verified' => 'badge badge-success', 'for verification' => 'badge badge-warning',
        'ongoing' => 'badge badge-info', default => 'badge badge-secondary',
    };
    $hasRejected = $implan->taggings->contains('status', 'Rejected');
    $tagByAgency = $implan->taggings->keyBy('gov_agency_id');
@endphp

@section('content')
    <div class="row mb-4">
        <div class="col-8 col-xl-8 mb-3 mb-xl-0">
            <h3 class="font-weight-bold">Implementation Plan</h3>
            <h6 class="mb-0" style="color: rgba(156, 156, 156, 1); font-weight: 300;">
                <span style="color: #280274; font-weight: bold;">RCSP implementation details</span> — information, agenda files, and documentation.
            </h6>
        </div>
        <div class="col-4 col-xl-4">
            <div class="justify-content-end d-flex align-items-center gap-2">
                <span class="{{ $statusBadge }}">{{ $implan->status }}</span>
                @if ($implan->status === 'for verification')
                    <form method="POST" action="{{ route('admin.implan.verify', $implan) }}"
                          data-confirm="Mark this plan as verified?" data-confirm-title="Verify IMPLAN" data-confirm-action="Verify" data-confirm-danger="false">
                        @csrf
                        <button class="btn btn-sm btn-success"><i class="mdi mdi-check-decagram"></i> Verify</button>
                    </form>
                @endif
                @if ($hasRejected)
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#reassignModal">
                        <i class="mdi mdi-autorenew"></i> Reassign
                    </button>
                @endif
                <a href="{{ route('admin.implan.index') }}" class="btn btn-sm btn-light bg-white">Back</a>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- LEFT: summary cards --}}
        <div class="col-md-4 grid-margin">
            <div class="card">
                <div class="card-body p-3">
                    <div class="mb-3">
                        <p class="text-muted mb-0" style="font-size: 0.85rem;">IMPLAN #{{ $implan->id }}</p>
                    </div>

                    <div class="card mb-3" style="background-color: #ffebe6; border: none; border-radius: 12px;">
                        <div class="card-body p-3">
                            <p class="text-muted mb-1" style="font-size: 0.85rem;">Target Beneficiary</p>
                            <h1 style="color: #d63300; font-weight: 600; font-size: 1.8rem;">{{ $implan->beneficiaries ?: '—' }}</h1>
                        </div>
                    </div>

                    <div class="card mb-3" style="background-color: #e6ffe6; border: none; border-radius: 12px;">
                        <div class="card-body p-3">
                            <p class="text-muted mb-1" style="font-size: 0.85rem;">Resources Needed</p>
                            <h1 style="color: #008000; font-weight: 600; font-size: 1.8rem;">{{ $implan->resources ?: '—' }}</h1>
                        </div>
                    </div>

                    <div class="card mb-4" style="background-color: #e6e6ff; border: none; border-radius: 12px;">
                        <div class="card-body p-3">
                            <p class="text-muted mb-1" style="font-size: 0.85rem;">Target Area</p>
                            @forelse ($areaNames as $name)
                                <h4 style="color: #000080; font-weight: 500; font-size: 1.4rem;">{{ $name }}</h4>
                            @empty
                                <h4 style="color: #000080; font-weight: 500; font-size: 1.4rem;">No Areas Defined</h4>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <p class="text-muted mb-2" style="font-size: 0.85rem;">Other Responsible Agency</p>
                        @forelse ($assignedAgencies as $agency)
                            @php $tag = $tagByAgency[$agency->id] ?? null; @endphp
                            <div class="d-inline-flex flex-column align-items-center me-3 mb-2 text-center">
                                @if ($agency->profile)
                                    <img src="{{ asset('assets/logoAgency/'.$agency->profile) }}" alt="{{ $agency->acronym }}"
                                         style="width: 50px; height: 50px; border-radius: 50%; object-fit: contain; background:#f4f5f7;">
                                @else
                                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light" style="width:50px;height:50px;">
                                        <i class="mdi mdi-office-building text-muted"></i>
                                    </span>
                                @endif
                                <small class="fw-medium mt-1">{{ $agency->acronym }}</small>
                                @if ($tag)
                                    <span class="badge {{ $tag->status === 'Accepted' ? 'badge-success' : ($tag->status === 'Rejected' ? 'badge-danger' : 'badge-secondary') }}">{{ $tag->status }}</span>
                                @endif
                            </div>
                        @empty
                            <div>No Agencies Defined</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: tabbed detail --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs" id="implanTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">Information</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">Agenda File</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#monitoring" type="button" role="tab">Documentation</button>
                        </li>
                    </ul>

                    <div class="tab-content pt-3" id="implanTabContent">
                        {{-- Information --}}
                        <div class="tab-pane fade show active" id="info" role="tabpanel">
                            <div class="header-section" style="background: linear-gradient(to right, #6b5b95, #b8860b); padding: 15px; border-radius: 10px; display: flex; align-items: center; margin-bottom: 20px;">
                                <img src="{{ asset('assets/img/agencies/dilg.png') }}" alt="Cluster Logo" style="width: 80px; height: 80px; margin-right: 15px;">
                                <div style="color: white;">
                                    <h3 style="margin: 0;">IMPLEMENTATION PLAN</h3>
                                    <p style="margin: 0;">Refocus Implementation Plan</p>
                                </div>
                            </div>

                            <div class="content-section" style="padding: 0 10px;">
                                <div class="info-group mb-3">
                                    <label style="color: #666; font-size: 0.9em;">Issues or Concern</label>
                                    <div style="font-size: 1.1em; padding: 8px 0; border-bottom: 1px solid #eee;">{{ $implan->issues ?: '—' }}</div>
                                </div>
                                <div class="info-group mb-3">
                                    <label style="color: #666; font-size: 0.9em;">Program/Project/Activity</label>
                                    <div style="font-size: 1.1em; padding: 8px 0; border-bottom: 1px solid #eee;">{{ $implan->program ?: '—' }}</div>
                                </div>
                                <div class="info-group mb-3">
                                    <label style="color: #666; font-size: 0.9em;">Expected Results/Outcome</label>
                                    <div style="font-size: 1.1em; padding: 8px 0; border-bottom: 1px solid #eee;">{{ $implan->outcome ?: '—' }}</div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-group mb-3">
                                            <label style="color: #666; font-size: 0.9em;">Responsible Agency</label>
                                            <div style="font-size: 1.1em; padding: 8px 0; border-bottom: 1px solid #eee;">{{ $assignedAgencies->pluck('acronym')->implode(', ') ?: '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-group mb-3">
                                            <label style="color: #666; font-size: 0.9em;">Type of Government Agency</label>
                                            <div style="font-size: 1.1em; padding: 8px 0; border-bottom: 1px solid #eee;">{{ $implan->type_gov ?: '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-group mb-3">
                                            <label style="color: #666; font-size: 0.9em;">Source</label>
                                            <div style="font-size: 1.1em; padding: 8px 0; border-bottom: 1px solid #eee;">{{ $implan->sources ?: '—' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-group mb-3">
                                            <label style="color: #666; font-size: 0.9em;">Remarks</label>
                                            <div style="font-size: 1.1em; padding: 8px 0; border-bottom: 1px solid #eee;">{{ $implan->remarks ?: '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Agenda File --}}
                        <div class="tab-pane fade" id="documents" role="tabpanel">
                            <div class="p-2">
                                @forelse ($implan->files as $file)
                                    <div class="border rounded p-3 mb-2">
                                        <a href="{{ $file->pdf ? Storage::url($file->pdf) : '#' }}" target="_blank" class="fw-medium">
                                            {{ $file->file_name }}
                                        </a>
                                        <div class="text-muted small">{{ $file->pdf }}</div>
                                        <p class="text-muted small mb-0">{{ $file->description }}</p>
                                    </div>
                                @empty
                                    <p class="text-muted mb-0">No agenda files uploaded.</p>
                                @endforelse
                            </div>
                        </div>

                        {{-- Documentation --}}
                        <div class="tab-pane fade" id="monitoring" role="tabpanel">
                            <div class="p-2">
                                @if ($implan->photos->isEmpty())
                                    <p class="text-muted mb-0">No documentation photos.</p>
                                @else
                                    <div class="row g-2">
                                        @foreach ($implan->photos as $photo)
                                            <div class="col-4 col-sm-3">
                                                <a href="{{ Storage::url($photo->image) }}" target="_blank">
                                                    <img src="{{ Storage::url($photo->image) }}" class="img-fluid rounded"
                                                         style="height:100px;width:100%;object-fit:cover;" alt="doc">
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($hasRejected)
        <div class="modal fade" id="reassignModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Reassign to Agencies</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('admin.implan.reassign', $implan) }}">
                        @csrf
                        <div class="modal-body">
                            <p class="text-muted">Select the agencies to reassign this plan to.</p>
                            <div class="border rounded p-2" style="max-height:14rem;overflow-y:auto;">
                                @foreach ($allAgencies as $agency)
                                    <div class="form-check">
                                        <input type="checkbox" name="agencies[]" value="{{ $agency->id }}" class="form-check-input" id="agency-{{ $agency->id }}">
                                        <label class="form-check-label" for="agency-{{ $agency->id }}">{{ $agency->acronym }} — {{ $agency->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary">Reassign</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
