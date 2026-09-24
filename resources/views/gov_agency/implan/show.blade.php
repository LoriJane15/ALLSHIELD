@extends('layouts.skydash-v')
@section('title', 'IMPLAN')
@section('heading', 'Implementation Plan')

@php
    $statusBadge = match ($implan->status) {
        'verified' => 'badge-success', 'ongoing' => 'badge-primary',
        'not yet started' => 'badge-danger', default => 'badge-secondary',
    };
@endphp

@push('styles')
<style>
    .table td { word-wrap: break-word; white-space: normal; max-width: 220px; padding: 15px; line-height: 1.5; }
    .agenda-file-item { background: #fff; border: 1px solid #eee; border-radius: 10px; padding: 14px 18px; margin-bottom: 10px; }
    .agenda-file-item .file-name { font-weight: 500; color: #35127d; text-decoration: none; }
    .agenda-file-item .file-link { font-size: 12px; color: #999; word-break: break-all; }
    .agenda-file-item .file-description { font-size: 13px; color: #666; margin: 4px 0 0; }
</style>
@endpush

@section('content')
    <div class="row mb-4">
        <div class="col-8 col-xl-8 mb-3 mb-xl-0">
            <h3 class="font-weight-bold">Implementation Plan</h3>
            <h6 class="mb-0" style="color: rgba(156,156,156,1); font-weight: 300;">
                <span style="color: #280274; font-weight: bold;">Manage implementation details</span>, agenda files, and documentation.
            </h6>
        </div>
        <div class="col-4 col-xl-4">
            <div class="justify-content-end d-flex">
                <a href="{{ route('gov_agency.implan.index') }}" class="btn btn-sm btn-light bg-white"><i class="mdi mdi-arrow-left"></i> Back</a>
            </div>
        </div>
    </div>

    {{-- Accept / reject if still pending --}}
    @if (! $response || $response->response_status === 'pending')
        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                <p class="font-weight-medium mb-0">Respond to this assignment</p>
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('gov_agency.implan.respond', $implan) }}">
                        @csrf <input type="hidden" name="response_status" value="accepted">
                        <button class="btn btn-success"><i class="mdi mdi-check"></i> Accept</button>
                    </form>
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="mdi mdi-close"></i> Reject</button>
                </div>
            </div>
        </div>
    @elseif ($response->response_status === 'rejected')
        <div class="card mb-4 border-danger">
            <div class="card-body">
                <p class="font-weight-medium text-danger mb-1">Rejected</p>
                <p class="text-muted mb-0">{{ $response->rejection_reason }}</p>
            </div>
        </div>
    @endif

    <div class="row">
        {{-- LEFT: tabbed detail --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#info" type="button">Information</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#documents" type="button">Agenda File</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#monitoring" type="button">Documentation</button></li>
                    </ul>

                    <div class="tab-content pt-3">
                        {{-- Information --}}
                        <div class="tab-pane fade show active" id="info" role="tabpanel">
                            <div class="d-flex justify-content-end mb-3">
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editImplanModal"><i class="mdi mdi-pencil"></i> Edit</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>Outcomes</th><th>Resources Needed</th><th>Support Needed</th><th>Duration</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr class="wrap">
                                            <td>{{ $implan->outcome ?: '—' }}</td>
                                            <td>{{ $implan->resources ?: '—' }}</td>
                                            <td>{{ $implan->support ?: '—' }}</td>
                                            <td>{{ $implan->duration ?: '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Agenda File --}}
                        <div class="tab-pane fade" id="documents" role="tabpanel">
                            <div class="d-flex justify-content-end mb-3">
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFileModal">Add File</button>
                            </div>
                            <div class="agenda-files-list">
                                @forelse ($implan->files as $file)
                                    <div class="agenda-file-item">
                                        <a href="{{ $file->pdf ? Storage::url($file->pdf) : '#' }}" target="_blank" class="file-name">{{ $file->file_name }}</a>
                                        <div class="file-link">{{ $file->pdf }}</div>
                                        <p class="file-description">{{ $file->description }}</p>
                                    </div>
                                @empty
                                    <p class="text-muted">No agenda files uploaded.</p>
                                @endforelse
                            </div>
                        </div>

                        {{-- Documentation --}}
                        <div class="tab-pane fade" id="monitoring" role="tabpanel">
                            <div class="d-flex justify-content-end mb-3">
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPhotoModal">Add Photo</button>
                            </div>
                            @if ($implan->photos->isNotEmpty())
                                <div class="row g-2">
                                    @foreach ($implan->photos as $photo)
                                        <div class="col-4 col-sm-3">
                                            <a href="{{ Storage::url($photo->image) }}" target="_blank">
                                                <img src="{{ Storage::url($photo->image) }}" class="img-fluid rounded" style="height:100px;width:100%;object-fit:cover;" alt="doc">
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-muted">No photos uploaded for this implementation plan.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: summary --}}
        <div class="col-md-4 grid-margin">
            <div class="card">
                <div class="card-body p-3">
                    <div class="mb-3">
                        <span class="badge {{ $statusBadge }} me-2 px-3 py-2" style="font-size:.8rem;">{{ $implan->status }}</span>
                        <h4 class="mt-2" style="font-weight:500;font-size:1.2rem;">{{ $implan->issues }}</h4>
                    </div>

                    <div class="card mb-3" style="background-color:#ffebe6;border:none;border-radius:12px;">
                        <div class="card-body p-3">
                            <p class="text-muted mb-1" style="font-size:.85rem;">Target Beneficiary</p>
                            <h1 style="color:#d63300;font-weight:600;font-size:1.8rem;">{{ $implan->beneficiaries ?: '—' }}</h1>
                        </div>
                    </div>
                    <div class="card mb-3" style="background-color:#e6ffe6;border:none;border-radius:12px;">
                        <div class="card-body p-3">
                            <p class="text-muted mb-1" style="font-size:.85rem;">Resources Needed</p>
                            <h1 style="color:#008000;font-weight:600;font-size:1.8rem;">{{ $implan->resources ?: '—' }}</h1>
                        </div>
                    </div>
                    <div class="card mb-4" style="background-color:#e6e6ff;border:none;border-radius:12px;">
                        <div class="card-body p-3">
                            <p class="text-muted mb-1" style="font-size:.85rem;">Target Area</p>
                            <h4 style="color:#000080;font-weight:500;font-size:1.4rem;">{{ $areaNames->join(', ') ?: 'No Areas Defined' }}</h4>
                        </div>
                    </div>

                    <div>
                        <p class="text-muted mb-2" style="font-size:.85rem;">Other Responsible Agency</p>
                        <div class="d-flex flex-wrap gap-2">
                            @forelse (($implan->agencies ?? []) as $aid)
                                @php $ag = $agenciesById[$aid] ?? null; @endphp
                                @if ($ag && $ag->profile)
                                    <img src="{{ asset('assets/logoAgency/'.$ag->profile) }}" alt="{{ $ag->acronym }}" title="{{ $ag->acronym }}"
                                         style="width:40px;height:40px;object-fit:contain;" onerror="this.style.display='none'">
                                @elseif ($ag)
                                    <span class="badge badge-secondary">{{ $ag->acronym }}</span>
                                @endif
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit modal (Refocus Implementation Plan) --}}
    <div class="modal fade" id="editImplanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:15px;overflow:hidden;">
                <div class="modal-header" style="background-color:#35127d;height:8px;padding:.5rem;border:none;"></div>
                <div class="modal-body">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="float:right;"></button>
                    <h3 style="color:#35127d;font-weight:bold;">Refocus Implementation Plan</h3>
                    <p class="text-muted">Update the implementation plan details</p>
                    <form method="POST" action="{{ route('gov_agency.implan.update', $implan) }}">
                        @csrf @method('PUT')
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Issues or Concerned</label>
                                <input type="text" class="form-control" value="{{ $implan->issues }}" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Program</label>
                                <input type="text" name="program" class="form-control" value="{{ old('program', $implan->program) }}" placeholder="Enter Program">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Target Beneficiary</label>
                                <input type="text" name="beneficiaries" class="form-control" value="{{ old('beneficiaries', $implan->beneficiaries) }}" placeholder="Enter Target Beneficiary">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Type of Government Agency</label>
                                <select class="form-control" name="type_gov">
                                    <option value="">Select Agency Type</option>
                                    <option value="NGA" @selected($implan->type_gov === 'NGA')>National Government Agency</option>
                                    <option value="PGO" @selected($implan->type_gov === 'PGO')>Provincial Government Office</option>
                                    <option value="Development Partner" @selected($implan->type_gov === 'Development Partner')>Development Partner</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Sources</label>
                                <input type="text" name="sources" class="form-control" value="{{ old('sources', $implan->sources) }}" placeholder="If NGA">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Resources Needed</label>
                                <input type="text" name="resources" class="form-control" value="{{ old('resources', $implan->resources) }}" placeholder="Php 0.00">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Support Needed</label>
                                <input type="text" name="support" class="form-control" value="{{ old('support', $implan->support) }}" placeholder="Enter Support Needed">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Duration</label>
                                <input type="text" name="duration" class="form-control" value="{{ old('duration', $implan->duration) }}" placeholder="Enter Duration">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Outcomes</label>
                                <textarea name="outcome" class="form-control" rows="3" placeholder="Enter Outcomes">{{ old('outcome', $implan->outcome) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="3" placeholder="Enter Remarks">{{ old('remarks', $implan->remarks) }}</textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-info">Save changes</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Add File modal --}}
    <div class="modal fade" id="addFileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color:#35127d;height:8px;padding:.5rem;border:none;"></div>
                <div class="modal-body">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="float:right;"></button>
                    <h3 style="color:#35127d;font-weight:bold;">Add Agenda File</h3>
                    <p class="text-muted">Upload new agenda file with description</p>
                    <form method="POST" action="{{ route('gov_agency.implan.agenda', $implan) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">File Name</label>
                            <input type="text" name="file_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Files</label>
                            <input type="file" name="files[]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx" multiple required>
                            <small class="text-muted">Accepted formats: PDF, DOC, DOCX, XLS, XLSX</small>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Upload Files</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Photo modal --}}
    <div class="modal fade" id="addPhotoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color:#35127d;height:8px;padding:.5rem;border:none;"></div>
                <div class="modal-body">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="float:right;"></button>
                    <h3 style="color:#35127d;font-weight:bold;">Add Documentation Photo</h3>
                    <p class="text-muted">Upload documentation photos</p>
                    <form method="POST" action="{{ route('gov_agency.implan.photos', $implan) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Upload Photo</label>
                            <input type="file" name="photos[]" class="form-control" accept=".jpg,.jpeg,.png,.gif" multiple required>
                            <small class="text-muted">Accepted formats: JPG, PNG, GIF</small>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Upload Photo</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </form>
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
                    <form method="POST" action="{{ route('gov_agency.implan.respond', $implan) }}">
                        @csrf
                        <input type="hidden" name="response_status" value="rejected">
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection</label>
                            <textarea name="rejection_reason" rows="4" required class="form-control"></textarea>
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
