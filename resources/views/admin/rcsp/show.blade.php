@extends('layouts.skydash-h')
@section('title', 'RCSP Monitoring Form')
@section('heading', 'RCSP Implementation Monitoring Form')

@section('content')
    <div class="d-flex justify-content-end mb-2">
        <a href="{{ route('admin.rcsp.index') }}" class="btn btn-sm btn-light bg-white">
            <i class="mdi mdi-arrow-left"></i> Back to list
        </a>
    </div>
    <!-- Form Backdrop -->
    <div class="form-backdrop p-4">
        <!-- Header Section -->
        <div class="text-center mb-4">
            <div class="d-flex justify-content-center align-items-center gap-3">
                <img src="{{ asset('assets/img/agencies/dilg.png') }}" alt="DILG Logo" style="width: 100px;">
                <img src="{{ asset('assets/img/LGRC.GIF') }}" alt="KC Logo" style="width: 100px;">
            </div>
            <h6 class="fw-bold mt-3">Local Government Provincial</h6>
            <h6 class="fw-bold">Local Resource Center Province of Davao del Sur</h6>
            <h6 class="fw-bold">Region XI</h6>
            <h5 class="mt-5" style="color: #f88c01;">RETOOLED COMMUNITY SUPPORT PROGRAM IMPLEMENTATION <br> MONITORING FORM</h5>
            <p class="small mt-4">(This Report is pursuant to Unnumbered Memoranda dated September 10, 2019 and September 30, 2019: Memorandum <br> Circular No. 2019-169 dated October 11, 2019)</p>
            <div class="row text-center mt-3 mb-3">
                <div class="col-md-3">
                    <p><strong>Province:</strong> <span class="fw-bold" style="color: #f88c01;">Davao del Sur</span></p>
                </div>
                <div class="col-md-3">
                    <p><strong>City/Municipality:</strong> <span class="fw-bold" style="color: #f88c01;">{{ $rcspBarangay->municipality?->name }}</span></p>
                </div>
                <div class="col-md-3">
                    <p><strong>Barangay:</strong> <span class="fw-bold" style="color: #f88c01;">{{ $rcspBarangay->barangay?->name }}</span></p>
                </div>
                <div class="col-md-3">
                    <p><strong>File ID:</strong> <span class="fw-bold" style="color: #f88c01;">{{ $rcspBarangay->id }}</span></p>
                </div>
            </div>
            <p class="text-start">
                <strong style="color: #f88c01;">Phase {{ $currentPhase->number }} - {{ $currentPhase->name }}</strong><br>
                <em>{{ $currentPhase->name }}</em>
            </p>
        </div>

        <!-- Content Section -->
        <form action="{{ route('admin.rcsp.review', $rcspBarangay) }}" method="POST">
            @csrf
            <input type="hidden" name="phase_id" value="{{ $currentPhase->id }}">
            <table class="table table-fixed">
                <thead>
                    <tr>
                        <th>Activities</th>
                        <th>Conduct</th>
                        <th>Status</th>
                        <th>Verification</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activities as $activity)
                        @php $form = $forms->get($activity->id); @endphp
                        <tr>
                            <td>{{ $activity->description }}</td>

                            <td>
                                <div class="d-inline-flex align-items-center me-2">
                                    <input type="radio" class="form-check-input" @checked($form && $form->conduct === 'yes') disabled>
                                    <label class="form-check-label ms-1"><i class="ti-check" style="color: green;"></i></label>
                                </div>
                                <div class="d-inline-flex align-items-center me-2">
                                    <input type="radio" class="form-check-input" @checked($form && $form->conduct === 'no') disabled>
                                    <label class="form-check-label ms-1"><i class="ti-close" style="color: red;"></i></label>
                                </div>
                                <div class="d-inline-flex align-items-center">
                                    <input type="radio" class="form-check-input" @checked($form && $form->conduct === 'n/a') disabled>
                                    <label class="form-check-label ms-1">N/A</label>
                                </div>
                            </td>

                            <td>
                                @if ($form)
                                    <select name="statuses[{{ $form->id }}]" class="form-select status-dropdown" required>
                                        <option value="approved" @selected($form->status === 'approved')>Approved</option>
                                        <option value="disapproved" @selected($form->status === 'disapproved')>Disapproved</option>
                                        <option value="to be complied" @selected($form->status === 'to be complied')>To be Complied</option>
                                        <option value="to be conducted" @selected(! in_array($form->status, ['approved', 'disapproved', 'to be complied']))>To be Conducted</option>
                                    </select>
                                @else
                                    <span class="badge bg-secondary">Not submitted</span>
                                @endif
                            </td>

                            <td>
                                @if ($form && $form->file)
                                    <a href="{{ route('admin.rcsp.file', $form->id) }}"
                                       class="badge bg-success text-decoration-none" title="View File">
                                        <i class="ti-file me-1"></i> View File
                                    </a>
                                @else
                                    <span class="badge bg-secondary"><i class="ti-file me-1"></i> No File</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No activities in this phase.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if ($forms->isNotEmpty())
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            @endif
        </form>
    </div>
@endsection

@push('styles')
<style>
    .form-backdrop {
        background-color: #fefefe;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        padding: 20px;
        max-width: 100%;
    }
    .status-dropdown { transition: background-color 0.3s ease; }
    .status-approved { background-color: #d4edda; color: #155724; }
    .status-disapproved { background-color: #f8d7da; color: #721c24; }
    .status-to-be-complied { background-color: #fff3cd; color: #856404; }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dropdowns = document.querySelectorAll('.status-dropdown');
        function updateDropdownColor(d) {
            d.classList.remove('status-approved', 'status-disapproved', 'status-to-be-complied');
            if (d.value === 'approved') d.classList.add('status-approved');
            else if (d.value === 'disapproved') d.classList.add('status-disapproved');
            else if (d.value === 'to be complied') d.classList.add('status-to-be-complied');
        }
        dropdowns.forEach((d) => {
            updateDropdownColor(d);
            d.addEventListener('change', () => updateDropdownColor(d));
        });
    });
</script>
@endpush
