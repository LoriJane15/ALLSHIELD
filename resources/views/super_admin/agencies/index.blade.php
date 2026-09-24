@extends('layouts.skydash-v')
@section('title', 'Government Agencies')
@section('heading', 'Government Agencies')

@section('content')
    <div class="row mb-3">
        <div class="col-8 col-xl-8 mb-3 mb-xl-0">
            <h3 class="font-weight-bold">Government Agencies</h3>
            <h6 class="mb-0" style="color: rgba(156,156,156,1); font-weight: 300;">
                <span style="color: #280274; font-weight: bold;">Add and manage government agencies</span> and their acronyms, names, and logos.
            </h6>
        </div>
        <div class="col-4 col-xl-4">
            <div class="justify-content-end d-flex">
                <a href="#" class="btn btn-primary" style="border-radius: 5px;" data-bs-toggle="modal" data-bs-target="#addAgencyModal">
                    Add Government Agency
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
                                    <th>Acronym</th>
                                    <th>Name</th>
                                    <th>Logo</th>
                                    <th>Users</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($agencies as $a)
                                    <tr>
                                        <td class="fw-semibold">{{ $a->acronym }}</td>
                                        <td>{{ $a->name }}</td>
                                        <td>
                                            @if ($a->profile)
                                                <img src="{{ asset('assets/logoAgency/'.$a->profile) }}"
                                                     onerror="this.style.display='none'"
                                                     alt="{{ $a->acronym }}" style="width:40px;height:40px;object-fit:contain;">
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $a->users_count }}</td>
                                        <td>
                                            <div class="d-flex align-items-center justify-content-end" style="gap:15px;">
                                                <a href="#" class="text-decoration-none" title="Edit"
                                                   data-edit-agency
                                                   data-name="{{ $a->name }}" data-acronym="{{ $a->acronym }}"
                                                   data-profile="{{ $a->profile }}"
                                                   data-action="{{ route('super_admin.agencies.update', $a) }}">
                                                    <i class="icon-pencil edit-icon" style="font-size:18px;"></i>
                                                </a>
                                                @if (! $a->users_count)
                                                    <form method="POST" action="{{ route('super_admin.agencies.destroy', $a) }}"
                                                          data-confirm="Delete {{ $a->acronym }}?" data-confirm-title="Confirm delete" data-confirm-action="Delete" style="display:inline;">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" style="background:none;border:none;padding:0;cursor:pointer;" title="Delete">
                                                            <i class="icon-trash delete-icon" style="font-size:18px;"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">No agencies.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $agencies->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addAgencyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Government Agency</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('super_admin.agencies.store') }}">
                        @csrf
                        @include('super_admin.agencies._fields')
                        <div class="d-flex justify-content-end gap-2 pt-2">
                            <button type="button" data-bs-dismiss="modal" class="btn btn-outline-secondary">Cancel</button>
                            <button class="btn btn-success">Add agency</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editAgencyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Government Agency</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" data-edit-agency-form>
                        @csrf @method('PUT')
                        @include('super_admin.agencies._fields')
                        <div class="d-flex justify-content-end gap-2 pt-2">
                            <button type="button" data-bs-dismiss="modal" class="btn btn-outline-secondary">Cancel</button>
                            <button class="btn btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-edit-agency]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const f = document.querySelector('[data-edit-agency-form]');
                f.action = btn.dataset.action;
                f.querySelector('[name=acronym]').value = btn.dataset.acronym;
                f.querySelector('[name=name]').value = btn.dataset.name;
                f.querySelector('[name=profile]').value = btn.dataset.profile || '';
                new bootstrap.Modal(document.getElementById('editAgencyModal')).show();
            });
        });
        @if ($errors->any())
            document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('addAgencyModal')).show());
        @endif
    </script>
@endpush
