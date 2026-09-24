@extends('layouts.skydash-v')
@section('title', 'User Management')
@section('heading', 'User Management')

@section('content')
    <div class="row mb-3">
        <div class="col-8 col-xl-8 mb-3 mb-xl-0">
            <h3 class="font-weight-bold">User Management</h3>
            <h6 class="mb-0" style="color: rgba(156,156,156,1); font-weight: 300;">
                <span style="color: #280274; font-weight: bold;">Add and manage system users</span> across roles, agencies, and municipalities.
            </h6>
        </div>
        <div class="col-4 col-xl-4">
            <div class="justify-content-end d-flex">
                <a href="#" class="btn btn-primary" style="border-radius: 5px;" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    Add User
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3">
                        <input name="search" value="{{ request('search') }}" placeholder="Search..." class="form-control" style="width:15rem;">
                        <select name="role" class="form-select" style="width:11rem;" onchange="this.form.submit()">
                            <option value="">All roles</option>
                            @foreach ($roles as $key => $meta)
                                <option value="{{ $key }}" @selected(request('role') === $key)>{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-outline-secondary">Filter</button>
                    </form>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Scope</th>
                                    <th>Created at</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $u)
                                    <tr>
                                        <td class="fw-medium">{{ $u->name }}</td>
                                        <td>{{ '@'.$u->username }}</td>
                                        <td>{{ $roles[$u->role]['label'] ?? $u->role }}</td>
                                        <td>{{ $u->municipality?->name ?? $u->govAgency?->acronym ?? '—' }}</td>
                                        <td>{{ $u->created_at?->format('M d, Y') ?? '—' }}</td>
                                        <td>
                                            <div class="d-flex align-items-center justify-content-end" style="gap:15px;">
                                                <a href="#" class="text-decoration-none" title="Edit"
                                                   data-edit-user
                                                   data-id="{{ $u->id }}"
                                                   data-username="{{ $u->username }}"
                                                   data-name="{{ $u->name }}"
                                                   data-role="{{ $u->role }}"
                                                   data-municipality="{{ $u->municipality_id }}"
                                                   data-agency="{{ $u->gov_agency_id }}"
                                                   data-action="{{ route('super_admin.users.update', $u) }}">
                                                    <i class="icon-pencil edit-icon" style="font-size:18px;"></i>
                                                </a>
                                                @if ($u->id !== auth()->id())
                                                    <form method="POST" action="{{ route('super_admin.users.destroy', $u) }}"
                                                          data-confirm="Delete {{ $u->username }}?" data-confirm-title="Confirm delete" data-confirm-action="Delete" style="display:inline;">
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
                                    <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $users->links() }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add modal --}}
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('super_admin.users.store') }}" enctype="multipart/form-data" data-user-form>
                        @csrf
                        @include('super_admin.users._fields', ['isEdit' => false])
                        <div class="d-flex justify-content-end gap-2 pt-2">
                            <button type="button" data-bs-dismiss="modal" class="btn btn-outline-secondary">Cancel</button>
                            <button class="btn btn-success">Create user</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit modal (shared, JS-populated) --}}
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data" data-user-form data-edit-form>
                        @csrf @method('PUT')
                        @include('super_admin.users._fields', ['isEdit' => true])
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
        // Role-conditional field visibility (both modals).
        function syncRoleFields(form) {
            const role = form.querySelector('[name=role]').value;
            form.querySelectorAll('[data-role-field]').forEach((el) => {
                el.classList.toggle('d-none', el.dataset.roleField !== role);
            });
        }
        document.querySelectorAll('[data-user-form]').forEach((form) => {
            const roleSel = form.querySelector('[name=role]');
            roleSel.addEventListener('change', () => syncRoleFields(form));
            syncRoleFields(form);
        });

        // Populate + open edit modal.
        document.querySelectorAll('[data-edit-user]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const f = document.querySelector('[data-edit-form]');
                f.action = btn.dataset.action;
                f.querySelector('[name=username]').value = btn.dataset.username;
                f.querySelector('[name=name]').value = btn.dataset.name;
                f.querySelector('[name=role]').value = btn.dataset.role;
                f.querySelector('[name=municipality_id]').value = btn.dataset.municipality || '';
                f.querySelector('[name=gov_agency_id]').value = btn.dataset.agency || '';
                f.querySelector('[name=password]').value = '';
                syncRoleFields(f);
                new bootstrap.Modal(document.getElementById('editUserModal')).show();
            });
        });

        @if ($errors->any())
            document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('addUserModal')).show());
        @endif
    </script>
@endpush
