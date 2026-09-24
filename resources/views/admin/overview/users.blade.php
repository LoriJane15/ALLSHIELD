@extends('layouts.skydash-h')
@section('title', 'Users')
@section('heading', 'System Users')

@section('content')
    <h4 class="mb-3">System Users</h4>

    <div class="row">
        @foreach ($users as $role => $group)
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase mb-3">
                            {{ config("shield.roles.$role.label", $role) }}
                            <span class="badge badge-secondary">{{ $group->count() }}</span>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <tbody>
                                    @foreach ($group as $u)
                                        <tr>
                                            <td class="fw-medium">{{ $u->name }}</td>
                                            <td class="text-muted">{{ '@'.$u->username }}</td>
                                            <td class="text-muted">{{ $u->municipality?->name ?? $u->govAgency?->acronym ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
